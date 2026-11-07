<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Symfony\Component\Process\InputStream;
use Symfony\Component\Process\Process;
use Tests\Support\BookingFixture;
use Tests\TestCase;

class ReviewCreationConcurrencyTest extends TestCase
{
    // Separate connections must see committed booking and account fixtures.
    use DatabaseMigrations;

    public function test_concurrent_review_requests_create_exactly_one_review(): void
    {
        $fixture = BookingFixture::requested($this);
        $booking = $fixture['booking'];
        $booking->update(['status' => 'COMPLETED']);
        $this->assertSame(0, DB::transactionLevel());

        $results = $this->concurrentReviewRequests(
            (string) $fixture['owner']->id,
            (string) $booking->id,
        );

        $statuses = array_column($results, 'status');
        sort($statuses);

        $this->assertSame([201, 409], $statuses);
        $conflict = collect($results)->firstWhere('status', 409);
        $this->assertSame('REVIEW_ALREADY_EXISTS', $conflict['body']['error']['code']);
        $this->assertDatabaseCount('reviews', 1);
        $this->assertDatabaseHas('reviews', [
            'booking_id' => $booking->id,
            'owner_id' => $fixture['owner']->id,
            'listing_id' => $booking->listing_id,
            'status' => 'VISIBLE',
            'is_demo' => false,
        ]);
    }

    /** @return list<array{status: int, body: array<string, mixed>}> */
    private function concurrentReviewRequests(string $ownerId, string $bookingId): array
    {
        $worker = <<<'PHP'
require getcwd().'/vendor/autoload.php';
$app = require getcwd().'/bootstrap/app.php';
$kernel = $app->make(\Illuminate\Contracts\Http\Kernel::class);
$request = \Illuminate\Http\Request::create('/api/bookings/'.$argv[2].'/reviews', 'POST', [], [], [], [
    'CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json',
], '{"rating":5,"body":"Concurrent review"}');
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
if ($argv[3] === 'hold') {
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
            foreach (['hold', 'normal'] as $mode) {
                $input = new InputStream;
                $process = new Process([
                    PHP_BINARY,
                    '-r',
                    $worker,
                    $ownerId,
                    $bookingId,
                    $mode,
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
            // The second request is waiting for the booking row held by request one.
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
            throw new \RuntimeException(implode("\n", array_map(
                fn ($process) => $process->getOutput().$process->getErrorOutput(),
                $processes,
            )), 0, $exception);
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
