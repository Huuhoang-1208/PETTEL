<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Symfony\Component\Process\InputStream;
use Symfony\Component\Process\Process;
use Tests\Support\BookingFixture;
use Tests\Support\ListingFixture;
use Tests\TestCase;

class ListingWorkflowConcurrencyTest extends TestCase
{
    use DatabaseMigrations;

    public function test_only_one_concurrent_change_request_can_become_pending(): void
    {
        $fixture = ListingFixture::make();
        $listingId = $fixture['active_listing_id'];
        $payload = ['base_listing_version' => 1, 'changes' => ['title' => 'Concurrent proposal']];
        $responses = $this->race(
            (string) $fixture['caregiver']->id,
            "/api/caregiver/listings/{$listingId}/change-requests",
            'POST', $payload,
            "/api/caregiver/listings/{$listingId}/change-requests", 'POST', $payload,
        );

        $this->assertSame([201, 409], $this->sortedStatuses($responses));
        $this->assertSame('INVALID_STATE', $this->errorCode($responses, 409));
        $this->assertSame(1, DB::table('listing_change_requests')->where('listing_id', $listingId)->where('status', 'PENDING_REVIEW')->count());
        $this->assertSame(1, DB::table('audit_logs')->where('action', 'LISTING_CHANGE_REQUESTED')->where('resource_id', $listingId)->count());
    }

    public function test_only_one_concurrent_review_can_apply_a_change_request(): void
    {
        $fixture = ListingFixture::make();
        $listingId = $fixture['active_listing_id'];
        $pending = $this->actingAs($fixture['caregiver'])->postJson("/api/caregiver/listings/{$listingId}/change-requests", [
            'base_listing_version' => 1, 'changes' => ['title' => 'Review once'],
        ])->assertCreated();
        $admin = $this->admin();
        $path = "/api/admin/listings/{$listingId}/change-requests/{$pending->json('data.id')}/approve";
        $responses = $this->race((string) $admin->id, $path, 'POST', [], $path, 'POST', []);

        $this->assertSame([200, 409], $this->sortedStatuses($responses));
        $this->assertSame('INVALID_STATE', $this->errorCode($responses, 409));
        $this->assertDatabaseHas('listings', ['id' => $listingId, 'title' => 'Review once', 'version' => 2]);
        $this->assertDatabaseHas('listing_change_requests', ['id' => $pending->json('data.id'), 'status' => 'APPROVED']);
        $this->assertSame(1, DB::table('audit_logs')->where('action', 'LISTING_CHANGE_APPROVED')->where('resource_id', $listingId)->count());
    }

    public function test_only_one_concurrent_overlapping_slot_write_succeeds(): void
    {
        $fixture = ListingFixture::make();
        $listingId = $fixture['active_listing_id'];
        $start = now('UTC')->addDays(20);
        $payload = [
            'start_at' => $start->format('Y-m-d\TH:i:s.u\Z'),
            'end_at' => $start->copy()->addHour()->format('Y-m-d\TH:i:s.u\Z'),
        ];
        $path = "/api/caregiver/listings/{$listingId}/availability-slots";
        $responses = $this->race((string) $fixture['caregiver']->id, $path, 'POST', $payload, $path, 'POST', $payload);

        $this->assertSame([201, 409], $this->sortedStatuses($responses));
        $this->assertSame('AVAILABILITY_CONFLICT', $this->errorCode($responses, 409));
        $this->assertSame(1, DB::table('availability_slots')->where('listing_id', $listingId)->where('start_at', $start->format('Y-m-d H:i:s.u'))->count());
        $this->assertDatabaseHas('listings', ['id' => $listingId, 'version' => 1]);
    }

    public function test_accept_winning_slot_race_keeps_booking_covered_and_rejects_slot_change(): void
    {
        $fixture = BookingFixture::requested($this);
        $slot = DB::table('availability_slots')->where('listing_id', $fixture['active_listing_id'])->first();
        $start = now('UTC')->addDays(30);
        $responses = $this->race(
            (string) $fixture['caregiver']->id,
            "/api/bookings/{$fixture['booking']->id}/accept", 'POST', [],
            "/api/caregiver/listings/{$fixture['active_listing_id']}/availability-slots/{$slot->id}", 'PATCH', [
                'start_at' => $start->format('Y-m-d\TH:i:s.u\Z'),
                'end_at' => $start->copy()->addHour()->format('Y-m-d\TH:i:s.u\Z'),
            ],
        );

        $this->assertSame([200, 409], $this->sortedStatuses($responses));
        $this->assertSame('AVAILABILITY_CONFLICT', $this->errorCode($responses, 409));
        $this->assertDatabaseHas('bookings', ['id' => $fixture['booking']->id, 'status' => 'AWAITING_PAYMENT']);
        $this->assertDatabaseHas('availability_slots', ['id' => $slot->id, 'start_at' => $slot->start_at, 'end_at' => $slot->end_at, 'deleted_at' => null]);
        $this->assertDatabaseCount('booking_status_events', 2);
    }

    public function test_slot_winning_accept_race_makes_accept_read_new_calendar(): void
    {
        $fixture = BookingFixture::requested($this);
        $slot = DB::table('availability_slots')->where('listing_id', $fixture['active_listing_id'])->first();
        $start = now('UTC')->addDays(30);
        $responses = $this->race(
            (string) $fixture['caregiver']->id,
            "/api/caregiver/listings/{$fixture['active_listing_id']}/availability-slots/{$slot->id}", 'PATCH', [
                'start_at' => $start->format('Y-m-d\TH:i:s.u\Z'),
                'end_at' => $start->copy()->addHour()->format('Y-m-d\TH:i:s.u\Z'),
            ],
            "/api/bookings/{$fixture['booking']->id}/accept", 'POST', [],
        );

        $this->assertSame([200, 409], $this->sortedStatuses($responses));
        $this->assertSame('AVAILABILITY_CONFLICT', $this->errorCode($responses, 409));
        $this->assertDatabaseHas('bookings', ['id' => $fixture['booking']->id, 'status' => 'REQUESTED']);
        $this->assertDatabaseHas('availability_slots', ['id' => $slot->id, 'start_at' => $start->format('Y-m-d H:i:s.u')]);
        $this->assertDatabaseCount('booking_status_events', 1);
    }

    public function test_accept_and_suspend_follow_listing_lock_order_in_both_winner_orders(): void
    {
        $first = BookingFixture::requested($this);
        $suspendPath = "/api/caregiver/listings/{$first['active_listing_id']}/suspend";
        $responses = $this->race(
            (string) $first['caregiver']->id,
            "/api/bookings/{$first['booking']->id}/accept", 'POST', [],
            $suspendPath, 'POST', ['reason' => 'Pause services.'],
        );
        $this->assertSame([200, 200], $this->sortedStatuses($responses));
        $this->assertDatabaseHas('listings', ['id' => $first['active_listing_id'], 'status' => 'SUSPENDED']);
        $this->assertDatabaseHas('bookings', ['id' => $first['booking']->id, 'status' => 'AWAITING_PAYMENT']);

        $second = BookingFixture::requested($this);
        $suspendPath = "/api/caregiver/listings/{$second['active_listing_id']}/suspend";
        $responses = $this->race(
            (string) $second['caregiver']->id,
            $suspendPath, 'POST', ['reason' => 'Pause services.'],
            "/api/bookings/{$second['booking']->id}/accept", 'POST', [],
        );
        $this->assertSame([200, 409], $this->sortedStatuses($responses));
        $this->assertSame('INVALID_STATE', $this->errorCode($responses, 409));
        $this->assertDatabaseHas('listings', ['id' => $second['active_listing_id'], 'status' => 'SUSPENDED']);
        $this->assertDatabaseHas('bookings', ['id' => $second['booking']->id, 'status' => 'REQUESTED']);
        $this->assertDatabaseCount('booking_status_events', 3);
    }

    /** @return list<array{status: int, body: array<string, mixed>}> */
    private function race(string $userId, string $firstPath, string $firstMethod, array $firstBody, string $secondPath, string $secondMethod, array $secondBody): array
    {
        $worker = <<<'PHP'
require getcwd().'/vendor/autoload.php';
$app = require getcwd().'/bootstrap/app.php';
$kernel = $app->make(\Illuminate\Contracts\Http\Kernel::class);
$request = \Illuminate\Http\Request::create($argv[2], $argv[3], [], [], [], [
    'CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json',
], $argv[4]);
$app->instance('request', $request);
$kernel->bootstrap();
$connection = \Illuminate\Support\Facades\DB::connection();
if (! $app->environment('testing') || $connection->getDriverName() !== 'mysql'
    || $connection->getDatabaseName() !== 'pettel_test' || $connection->getConfig('username') !== 'pettel_test') {
    throw new \RuntimeException('Use the dedicated test database.');
}
$app['auth']->guard('web')->setUser(\App\Models\User::findOrFail($argv[1]));
$isListingLock = fn ($sql) => str_contains($sql, 'from `listings`') && str_contains($sql, 'for update');
$connection->beforeExecuting(function ($sql) use ($isListingLock) {
    if ($isListingLock($sql)) { echo "ATTEMPTING\n"; flush(); }
});
if ($argv[5] === 'hold') {
    $held = false;
    \Illuminate\Support\Facades\DB::listen(function ($query) use ($isListingLock, &$held) {
        if (! $held && $isListingLock($query->sql)) {
            $held = true;
            echo "LOCKED\n"; flush();
            fgets(STDIN);
        }
    });
}
echo "READY\n"; flush();
fgets(STDIN);
$response = $kernel->handle($request);
echo 'RESULT '.json_encode(['status' => $response->getStatusCode(),
    'body' => json_decode($response->getContent(), true)], JSON_THROW_ON_ERROR)."\n";
$kernel->terminate($request, $response);
PHP;
        $processes = [];
        $inputs = [];
        try {
            foreach ([[$firstPath, $firstMethod, $firstBody], [$secondPath, $secondMethod, $secondBody]] as $index => [$path, $method, $body]) {
                $input = new InputStream;
                $process = new Process([
                    PHP_BINARY, '-r', $worker, $userId, $path, $method,
                    json_encode($body, JSON_THROW_ON_ERROR), $index === 0 ? 'hold' : 'normal',
                ], base_path(), null, $input, 30);
                $processes[] = $process;
                $inputs[] = $input;
                $process->start();
                $this->assertTrue($this->waitForSignal($process, 'READY'), $process->getErrorOutput());
            }
            $inputs[0]->write("go\n");
            $this->assertTrue($this->waitForSignal($processes[0], 'LOCKED'));
            $inputs[1]->write("go\n");
            $this->assertTrue($this->waitForSignal($processes[1], 'ATTEMPTING'));
            $inputs[0]->write("release\n");
            foreach ($inputs as $input) {
                $input->close();
            }
            $results = [];
            foreach ($processes as $process) {
                $process->wait();
                $this->assertSame(0, $process->getExitCode(), $process->getErrorOutput());
                $this->assertSame(1, preg_match('/^RESULT (.+)$/m', $process->getOutput(), $matches), $process->getOutput());
                $results[] = json_decode($matches[1], true, 512, JSON_THROW_ON_ERROR);
            }

            return $results;
        } catch (\Throwable $exception) {
            throw new \RuntimeException(implode("\n", array_map(fn ($process) => $process->getOutput().$process->getErrorOutput(), $processes)), 0, $exception);
        } finally {
            foreach ($processes as $process) {
                if ($process->isRunning()) {
                    $process->stop(0);
                }
            }
        }
    }

    /** @param list<array{status: int, body: array<string, mixed>}> $responses @return list<int> */
    private function sortedStatuses(array $responses): array
    {
        $statuses = array_column($responses, 'status');
        sort($statuses);

        return $statuses;
    }

    /** @param list<array{status: int, body: array<string, mixed>}> $responses */
    private function errorCode(array $responses, int $status): ?string
    {
        foreach ($responses as $response) {
            if ($response['status'] === $status) {
                return $response['body']['error']['code'] ?? null;
            }
        }

        return null;
    }

    private function waitForSignal(Process $process, string $signal): bool
    {
        $buffer = $process->getOutput();
        if (str_contains($buffer, $signal)) {
            return true;
        }

        return $process->waitUntil(function (string $type, string $output) use (&$buffer, $signal): bool {
            $buffer .= $output;

            return str_contains($buffer, $signal);
        });
    }

    private function admin(): User
    {
        $user = User::factory()->create();
        DB::table('user_roles')->insert(['user_id' => $user->id, 'role' => 'ADMIN', 'assigned_at' => now()]);

        return $user;
    }
}
