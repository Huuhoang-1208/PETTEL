<?php

namespace Tests\Feature;

use App\Domain\Booking\Actions\ProcessBookingReleaseOutbox;
use App\Domain\Booking\Payment\PaymentReleaseGateway;
use App\Domain\Booking\Payment\PaymentReleaseResult;
use App\Models\Booking;
use App\Models\BookingReleaseOperation;
use App\Models\PaymentAttempt;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Support\BookingFixture;
use Tests\TestCase;

class BookingReleaseRetrySafetyTest extends TestCase
{
    use RefreshDatabase;

    public function test_successful_gateway_release_retries_with_same_key_after_database_write_failure(): void
    {
        $fixture = BookingFixture::requested($this);
        /** @var Booking $booking */
        $booking = $fixture['booking'];

        $attemptId = DB::table('payment_attempts')->insertGetId([
            'booking_id' => $booking->id,
            'attempt_no' => 1,
            'amount_vnd' => $booking->price_snapshot_vnd,
            'status' => 'AUTHORIZED',
            'idempotency_key' => 'retry-safe-release-'.$booking->id,
            'request_payload_hash' => str_repeat('d', 64),
            'authorized_at' => now('UTC'),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $booking->update([
            'status' => 'AWAITING_CONFIRMATION',
            'current_payment_attempt_id' => $attemptId,
            'version' => 4,
        ]);

        $this->actingAs($fixture['owner'])
            ->postJson("/api/bookings/{$booking->id}/confirm-completion")
            ->assertAccepted();

        $operation = BookingReleaseOperation::query()
            ->where('booking_id', $booking->id)
            ->firstOrFail();

        $gateway = new class implements PaymentReleaseGateway
        {
            /** @var list<string> */
            public array $calls = [];

            /** @var array<string, true> */
            public array $releaseEffects = [];

            public function release(
                PaymentAttempt $attempt,
                BookingReleaseOperation $operation,
            ): PaymentReleaseResult {
                $key = $operation->gateway_operation_key;
                $this->calls[] = $key;

                // Model an idempotent sandbox/provider: a repeated key is one effect.
                $this->releaseEffects[$key] = true;

                return PaymentReleaseResult::succeeded();
            }
        };

        $this->app->instance(PaymentReleaseGateway::class, $gateway);

        // Fail once after the completion transaction inserts its booking event.
        $failStatusEventWriteOnce = true;
        DB::listen(function (QueryExecuted $query) use (&$failStatusEventWriteOnce): void {
            $sql = strtolower(ltrim($query->sql));

            if (
                $failStatusEventWriteOnce
                && str_starts_with($sql, 'insert')
                && str_contains($sql, 'booking_status_events')
            ) {
                $failStatusEventWriteOnce = false;
                throw new \RuntimeException('Simulated status event write failure.');
            }
        });

        $firstRun = app(ProcessBookingReleaseOutbox::class)->handle();

        $this->assertSame(1, $firstRun['pending']);
        $this->assertSame('AWAITING_CONFIRMATION', $booking->fresh()->status);
        $this->assertSame('AUTHORIZED', DB::table('payment_attempts')->where('id', $attemptId)->value('status'));
        $this->assertSame('PENDING', $operation->fresh()->status);
        $this->assertDatabaseMissing('booking_status_events', [
            'booking_id' => $booking->id,
            'to_status' => 'COMPLETED',
        ]);
        $this->assertDatabaseCount('payment_events', 0);

        $this->travel(1)->minutes();

        $secondRun = app(ProcessBookingReleaseOutbox::class)->handle();

        $this->assertSame(1, $secondRun['completed']);
        $this->assertSame('COMPLETED', $booking->fresh()->status);
        $this->assertSame('RELEASED', DB::table('payment_attempts')->where('id', $attemptId)->value('status'));
        $this->assertSame('COMPLETED', $operation->fresh()->status);

        // The provider was called twice, but both calls used one idempotency key.
        $this->assertSame([
            $operation->gateway_operation_key,
            $operation->gateway_operation_key,
        ], $gateway->calls);
        $this->assertCount(1, $gateway->releaseEffects);
        $this->assertDatabaseCount('payment_events', 1);
        $this->assertSame(
            1,
            DB::table('booking_status_events')
                ->where('booking_id', $booking->id)
                ->where('to_status', 'COMPLETED')
                ->count(),
        );
    }
}
