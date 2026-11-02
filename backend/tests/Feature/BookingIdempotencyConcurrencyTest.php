<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Symfony\Component\Process\InputStream;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class BookingIdempotencyConcurrencyTest extends TestCase
{
    // Child processes must see committed fixtures, so do not use RefreshDatabase here.
    use DatabaseMigrations;

    public function test_simultaneous_replay_and_conflict_create_only_one_booking_each(): void
    {
        $owner = User::factory()->create();
        $caregiver = User::factory()->create();
        foreach ([[$owner->id, 'OWNER'], [$caregiver->id, 'CAREGIVER']] as [$userId, $role]) {
            DB::table('user_roles')->insert(['user_id' => $userId, 'role' => $role, 'assigned_at' => now()]);
        }
        $pet = $owner->pets()->create(['name' => 'Mochi', 'species' => 'DOG', 'size' => 'SMALL']);
        $profileId = DB::table('caregiver_profiles')->insertGetId([
            'user_id' => $caregiver->id,
            'type' => 'INDIVIDUAL',
            'display_name' => 'Concurrency test caregiver',
            'area_code' => 'DN_CENTER',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $start = now('Asia/Ho_Chi_Minh')->addDays(2)->setTime(8, 0)->utc();
        $end = $start->addHours(2);
        $listingIds = [];
        for ($i = 0; $i < 2; $i++) {
            $listingId = DB::table('listings')->insertGetId([
                'caregiver_profile_id' => $profileId,
                'title' => 'Concurrency test daycare',
                'description' => 'Synthetic fixture',
                'service_type' => 'DAYCARE',
                'area_code' => 'DN_CENTER',
                'status' => 'ACTIVE',
                'price_vnd' => 50000,
                'price_unit' => 'HOUR',
                'capacity' => 1,
                'acceptance_rules_json' => json_encode([
                    'schema_version' => 1,
                    'species' => ['DOG'],
                    'sizes' => ['SMALL'],
                ], JSON_THROW_ON_ERROR),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $listingIds[] = $listingId;
            DB::table('availability_slots')->insert([
                'listing_id' => $listingId,
                'start_at' => $start->format('Y-m-d H:i:s.u'),
                'end_at' => $end->format('Y-m-d H:i:s.u'),
                'created_by_user_id' => $caregiver->id,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
        $payload = [
            'pet_id' => (string) $pet->id,
            'listing_id' => (string) $listingIds[0],
            'start_at' => $start->toIso8601String(),
            'end_at' => $end->toIso8601String(),
            'instructions' => 'Concurrent request',
        ];
        $this->assertSame(0, DB::transactionLevel());

        foreach ([false, true] as $differentPayload) {
            $otherPayload = $differentPayload
                ? array_replace($payload, ['listing_id' => (string) $listingIds[1]])
                : $payload;
            $key = $differentPayload ? 'concurrent-conflict' : 'concurrent-replay';
            [$first, $second] = $this->concurrentRequests((string) $owner->id, $key, $payload, $otherPayload);
            $this->assertSame(201, $first['status']);
            $this->assertSame($differentPayload ? 409 : 200, $second['status']);
            if ($differentPayload) {
                $this->assertSame('IDEMPOTENCY_CONFLICT', $second['body']['error']['code']);
            } else {
                $this->assertSame($first['body'], $second['body']);
            }
            $count = $differentPayload ? 2 : 1;
            $this->assertDatabaseCount('bookings', $count);
            $this->assertDatabaseCount('booking_status_events', $count);
        }
    }

    /** @return array<int, array<string, mixed>> */
    private function concurrentRequests(string $ownerId, string $key, array $firstPayload, array $secondPayload): array
    {
        $worker = <<<'PHP'
require getcwd().'/vendor/autoload.php';
$app = require getcwd().'/bootstrap/app.php';
$kernel = $app->make(\Illuminate\Contracts\Http\Kernel::class);
$request = \Illuminate\Http\Request::create('/api/bookings', 'POST', [], [], [], [
    'CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json', 'HTTP_IDEMPOTENCY_KEY' => $argv[3],
], base64_decode($argv[2], true));
$app->instance('request', $request);
$kernel->bootstrap();
$connection = \Illuminate\Support\Facades\DB::connection();
if (! $app->environment('testing') || $connection->getDriverName() !== 'mysql'
    || $connection->getDatabaseName() !== 'pettel_test' || $connection->getConfig('username') !== 'pettel_test') {
    throw new \RuntimeException('Use the dedicated test database.');
}
$app['auth']->guard('web')->setUser(\App\Models\User::findOrFail($argv[1]));
$isOwnerLock = fn ($sql) => str_contains($sql, 'from `users`') && str_contains($sql, 'for update');
$connection->beforeExecuting(function ($sql) use ($isOwnerLock) {
    if ($isOwnerLock($sql)) { echo "ATTEMPTING\n"; flush(); }
});
if ($argv[4] === 'hold') {
    $held = false;
    \Illuminate\Support\Facades\DB::listen(function ($query) use ($isOwnerLock, &$held) {
        if (! $held && $isOwnerLock($query->sql)) {
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
            foreach ([$firstPayload, $secondPayload] as $index => $payload) {
                $input = new InputStream;
                $process = new Process([
                    PHP_BINARY,
                    '-r',
                    $worker,
                    $ownerId,
                    base64_encode(json_encode($payload, JSON_THROW_ON_ERROR)),
                    $key,
                    $index === 0 ? 'hold' : 'normal',
                ], base_path(), null, $input, 20);
                $processes[] = $process;
                $inputs[] = $input;
                $process->start();
                $this->assertTrue($this->waitForSignal($process, 'READY'), $process->getErrorOutput());
            }
            $inputs[0]->write("go\n");
            $this->assertTrue($this->waitForSignal($processes[0], 'LOCKED'));
            $inputs[1]->write("go\n");
            $this->assertTrue($this->waitForSignal($processes[1], 'ATTEMPTING'));
            // Request 2 reached the same owner lock while request 1 still holds it.
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
            throw new \RuntimeException(implode("\n", array_map(fn ($p) => $p->getOutput().$p->getErrorOutput(), $processes)), 0, $exception);
        } finally {
            foreach ($processes as $process) {
                if ($process->isRunning()) {
                    $process->stop(0);
                }
            }
        }
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
}
