<?php

namespace Tests\Feature;

use App\Domain\Booking\Payment\SandboxCallbackSignature;
use App\Models\Booking;
use App\Models\PaymentAttempt;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\Process\InputStream;
use Symfony\Component\Process\Process;
use Tests\Support\BookingFixture;
use Tests\TestCase;

class PaymentCallbackCancellationConcurrencyTest extends TestCase
{
    use DatabaseMigrations;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('services.payment_sandbox.simulation_enabled', true);
        config()->set('services.payment_sandbox.secret', 'test-sandbox-secret');
    }

    public function test_callback_and_cancellation_serialize_without_duplicate_or_resurrected_state(): void
    {
        $fixture = BookingFixture::requested($this);
        $booking = $fixture['booking'];
        $booking->status = 'AWAITING_PAYMENT';
        $booking->payment_due_at = now('UTC')->addMinutes(15);
        $booking->save();

        $attemptResponse = $this->actingAs($fixture['owner'])
            ->withHeader('Idempotency-Key', 'payment-concurrent-callback-cancel')
            ->postJson('/api/bookings/'.$booking->id.'/sandbox-payments')
            ->assertCreated();
        $attempt = PaymentAttempt::query()->findOrFail($attemptResponse->json('data.id'));
        $initialStatusEventCount = DB::table('booking_status_events')->count();

        $payload = [
            'event_id' => (string) Str::uuid(),
            'booking_id' => (string) $booking->id,
            'attempt_reference' => (string) $attempt->provider_ref,
            'amount_vnd' => (int) $attempt->amount_vnd,
            'result' => 'AUTHORIZED',
        ];
        $signature = app(SandboxCallbackSignature::class)->sign($payload);

        [$callback, $cancellation] = $this->runConcurrentCallbackAndCancellation(
            (string) $fixture['owner']->id,
            (string) $booking->id,
            $payload,
            $signature,
        );

        $this->assertSame(200, $callback['status']);
        $this->assertSame('PROCESSED', $callback['body']['data']['processing_status']);
        $this->assertSame(202, $cancellation['status']);
        $this->assertSame('CONFIRMED', Booking::query()->findOrFail($booking->id)->status);
        $this->assertDatabaseHas('payment_attempts', [
            'id' => $attempt->id,
            'status' => 'AUTHORIZED',
        ]);
        $this->assertDatabaseCount('payment_events', 1);
        $this->assertDatabaseCount('booking_status_events', $initialStatusEventCount + 1);
        $this->assertDatabaseHas('booking_status_events', [
            'booking_id' => $booking->id,
            'to_status' => 'CONFIRMED',
            'source' => 'SANDBOX',
        ]);
        $this->assertDatabaseHas('booking_cancellation_operations', [
            'booking_id' => $booking->id,
            'payment_attempt_id' => $attempt->id,
            'operation_type' => 'REFUND',
            'status' => 'PENDING',
        ]);
        $this->assertDatabaseMissing('booking_status_events', [
            'booking_id' => $booking->id,
            'to_status' => 'CANCELLED',
        ]);
    }

    /** @param array<string, mixed> $payload
     * @return array{0: array<string, mixed>, 1: array<string, mixed>}
     */
    private function runConcurrentCallbackAndCancellation(
        string $ownerId,
        string $bookingId,
        array $payload,
        string $signature,
    ): array {
        $worker = <<<'PHP'
require getcwd().'/vendor/autoload.php';
$app = require getcwd().'/bootstrap/app.php';
$kernel = $app->make(\Illuminate\Contracts\Http\Kernel::class);
$mode = $argv[1];
$bookingId = $argv[2];
$ownerId = $argv[3];
$payload = json_decode(base64_decode($argv[4]), true, 512, JSON_THROW_ON_ERROR);
$signature = $argv[5];
$path = $mode === 'callback' ? '/api/sandbox/payment-callback' : '/api/bookings/'.$bookingId.'/cancel';
$headers = ['CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json'];
$body = '{}';
if ($mode === 'callback') {
    $headers['HTTP_X_PAYMENT_SIGNATURE'] = $signature;
    $body = json_encode($payload, JSON_THROW_ON_ERROR);
} else {
    $headers['HTTP_IDEMPOTENCY_KEY'] = 'concurrent-callback-cancel';
}
$request = \Illuminate\Http\Request::create($path, 'POST', [], [], [], $headers, $body);
$app->instance('request', $request);
$kernel->bootstrap();
config()->set('services.payment_sandbox.secret', $argv[6]);
$connection = \Illuminate\Support\Facades\DB::connection();
if (! $app->environment('testing') || $connection->getDriverName() !== 'mysql'
    || $connection->getDatabaseName() !== 'pettel_test' || $connection->getConfig('username') !== 'pettel_test') {
    throw new \RuntimeException('Use the dedicated test database.');
}
if ($mode === 'cancel') {
    $app['auth']->guard('web')->setUser(\App\Models\User::findOrFail($ownerId));
}
$isBookingLock = fn ($sql) => str_contains($sql, 'from `bookings`') && str_contains($sql, 'for update');
$connection->beforeExecuting(function ($sql) use ($isBookingLock) {
    if ($isBookingLock($sql)) { echo "ATTEMPTING\n"; flush(); }
});
if ($mode === 'callback') {
    $held = false;
    \Illuminate\Support\Facades\DB::listen(function ($query) use ($isBookingLock, &$held) {
        if (! $held && $isBookingLock($query->sql)) {
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
            foreach (['callback', 'cancel'] as $mode) {
                $input = new InputStream;
                $process = new Process([
                    PHP_BINARY,
                    '-r',
                    $worker,
                    $mode,
                    $bookingId,
                    $ownerId,
                    base64_encode(json_encode($payload, JSON_THROW_ON_ERROR)),
                    $signature,
                    'test-sandbox-secret',
                ], base_path(), null, $input, 20);
                $processes[] = $process;
                $inputs[] = $input;
                $process->start();
                $this->assertTrue($this->waitForSignal($process, 'READY'), $process->getOutput().$process->getErrorOutput());
            }

            $inputs[0]->write("go\n");
            $this->assertTrue($this->waitForSignal($processes[0], 'LOCKED'), $processes[0]->getErrorOutput());
            $inputs[1]->write("go\n");
            $this->assertTrue($this->waitForSignal($processes[1], 'ATTEMPTING'), $processes[1]->getErrorOutput());
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
        $deadline = microtime(true) + 10;
        while (microtime(true) < $deadline && $process->isRunning()) {
            if (str_contains($process->getOutput(), $signal)) {
                return true;
            }
            usleep(10_000);
        }

        return str_contains($process->getOutput(), $signal);
    }
}
