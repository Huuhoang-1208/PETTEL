<?php

namespace Tests\Feature;

use App\Models\Booking;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Symfony\Component\Process\InputStream;
use Symfony\Component\Process\Process;
use Tests\Support\BookingFixture;
use Tests\TestCase;

class BookingAcceptanceConcurrencyTest extends TestCase
{
    // Separate connections must be able to read committed fixtures.
    use DatabaseMigrations;

    public function test_two_overlapping_accepts_only_create_one_calendar_hold(): void
    {
        $fixture = BookingFixture::requested($this);
        $first = $fixture['booking'];
        $response = $this->actingAs($fixture['owner'])
            ->withHeader('Idempotency-Key', 'concurrent-accept-second')
            ->postJson('/api/bookings', $fixture['payload'])->assertCreated();
        $second = Booking::query()->findOrFail($response->json('id'));
        $this->assertSame(0, DB::transactionLevel());

        [$winner, $loser] = $this->concurrentAccepts(
            (string) $fixture['caregiver']->id,
            [(string) $first->id, (string) $second->id],
        );
        $this->assertSame(200, $winner['status']);
        $this->assertSame(409, $loser['status']);
        $this->assertSame('AVAILABILITY_CONFLICT', $loser['body']['error']['code']);
        $this->assertSame('AWAITING_PAYMENT', $first->fresh()->status);
        $this->assertNotNull($first->fresh()->payment_due_at);
        $this->assertSame('REQUESTED', $second->fresh()->status);
        $this->assertNull($second->fresh()->payment_due_at);
        $this->assertDatabaseCount('booking_status_events', 3);
        $this->assertSame(1, DB::table('booking_status_events')->where('to_status', 'AWAITING_PAYMENT')->count());
    }

    /**
     * @param  list<string>  $bookingIds
     * @return list<array<string, mixed>>
     */
    private function concurrentAccepts(string $caregiverId, array $bookingIds): array
    {
        $worker = <<<'PHP'
require getcwd().'/vendor/autoload.php';
$app = require getcwd().'/bootstrap/app.php';
$kernel = $app->make(\Illuminate\Contracts\Http\Kernel::class);
$request = \Illuminate\Http\Request::create('/api/bookings/'.$argv[2].'/accept', 'POST', [], [], [], [
    'CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json',
], '{}');
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
if ($argv[3] === 'hold') {
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
            foreach ($bookingIds as $index => $bookingId) {
                $input = new InputStream;
                $process = new Process([
                    PHP_BINARY, '-r', $worker, $caregiverId, $bookingId,
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
            // Process 2 has read its locator before process 1 commits.
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
