<?php

namespace Tests\Feature;

use App\Domain\Booking\Actions\ProcessBookingReleaseOutbox;
use App\Domain\Booking\Payment\PaymentReleaseGateway;
use App\Domain\Booking\Payment\PaymentReleaseResult;
use App\Models\Booking;
use App\Models\BookingReleaseOperation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Support\BookingFixture;
use Tests\Support\SequencePaymentReleaseGateway;
use Tests\TestCase;

class BookingReleaseProcessingTest extends TestCase
{
    use RefreshDatabase;

    public function test_successful_release_completes_booking_and_repeated_worker_has_no_second_effect(): void
    {
        $fixture = $this->createPendingOperation();
        $gateway = new SequencePaymentReleaseGateway([PaymentReleaseResult::succeeded()]);
        $this->app->instance(PaymentReleaseGateway::class, $gateway);

        $counts = app(ProcessBookingReleaseOutbox::class)->handle();

        $this->assertSame(1, $counts['completed']);
        $this->assertSame('COMPLETED', $fixture['booking']->fresh()->status);
        $this->assertSame(5, $fixture['booking']->fresh()->version);
        $this->assertDatabaseHas('payment_attempts', [
            'id' => $fixture['attempt_id'],
            'status' => 'RELEASED',
        ]);
        $this->assertDatabaseHas('booking_release_operations', [
            'id' => $fixture['operation']->id,
            'status' => 'COMPLETED',
            'attempt_count' => 1,
        ]);
        $this->assertDatabaseHas('booking_status_events', [
            'booking_id' => $fixture['booking']->id,
            'from_status' => 'AWAITING_CONFIRMATION',
            'to_status' => 'COMPLETED',
            'actor_user_id' => $fixture['owner']->id,
            'source' => 'USER',
            'reason_code' => 'OWNER_CONFIRMED',
        ]);
        $this->assertDatabaseCount('payment_events', 1);

        $eventCount = DB::table('booking_status_events')->where('booking_id', $fixture['booking']->id)->count();
        $repeat = app(ProcessBookingReleaseOutbox::class)->handle();

        $this->assertSame(0, $repeat['processed']);
        $this->assertSame(1, count($gateway->calls));
        $this->assertSame($eventCount, DB::table('booking_status_events')->where('booking_id', $fixture['booking']->id)->count());
        $this->assertDatabaseCount('payment_events', 1);
    }

    public function test_retryable_failure_keeps_booking_waiting_and_reuses_operation_key(): void
    {
        $fixture = $this->createPendingOperation();
        $gateway = new SequencePaymentReleaseGateway([
            PaymentReleaseResult::retryable('GATEWAY_TIMEOUT'),
            PaymentReleaseResult::succeeded(),
        ]);
        $this->app->instance(PaymentReleaseGateway::class, $gateway);

        $first = app(ProcessBookingReleaseOutbox::class)->handle();

        $this->assertSame(1, $first['pending']);
        $this->assertSame('AWAITING_CONFIRMATION', $fixture['booking']->fresh()->status);
        $this->assertSame('PENDING', $fixture['operation']->fresh()->status);
        $this->assertSame(1, $fixture['operation']->fresh()->attempt_count);
        $this->assertSame('GATEWAY_TIMEOUT', $fixture['operation']->fresh()->last_error_code);

        $this->travel(1)->minutes();
        $second = app(ProcessBookingReleaseOutbox::class)->handle();

        $this->assertSame(1, $second['completed']);
        $this->assertSame('COMPLETED', $fixture['booking']->fresh()->status);
        $this->assertCount(2, $gateway->operationKeys);
        $this->assertSame($fixture['operation']->gateway_operation_key, $gateway->operationKeys[0]);
        $this->assertSame($gateway->operationKeys[0], $gateway->operationKeys[1]);
        $this->assertSame(2, $fixture['operation']->fresh()->attempt_count);
        $this->assertDatabaseCount('payment_events', 1);
    }

    public function test_exhausted_retries_send_operation_to_manual_review_without_completing_booking(): void
    {
        $fixture = $this->createPendingOperation();
        $gateway = new SequencePaymentReleaseGateway([
            PaymentReleaseResult::retryable('GATEWAY_TIMEOUT'),
            PaymentReleaseResult::retryable('GATEWAY_TIMEOUT'),
            PaymentReleaseResult::retryable('GATEWAY_TIMEOUT'),
            PaymentReleaseResult::retryable('GATEWAY_TIMEOUT'),
        ]);
        $this->app->instance(PaymentReleaseGateway::class, $gateway);

        app(ProcessBookingReleaseOutbox::class)->handle();
        $this->travel(1)->minutes();
        app(ProcessBookingReleaseOutbox::class)->handle();
        $this->travel(5)->minutes();
        app(ProcessBookingReleaseOutbox::class)->handle();
        $this->travel(15)->minutes();
        $final = app(ProcessBookingReleaseOutbox::class)->handle();

        $this->assertSame(1, $final['manual_review']);
        $this->assertSame('MANUAL_REVIEW', $fixture['operation']->fresh()->status);
        $this->assertSame(4, $fixture['operation']->fresh()->attempt_count);
        $this->assertSame('AWAITING_CONFIRMATION', $fixture['booking']->fresh()->status);
        $this->assertDatabaseHas('payment_attempts', [
            'id' => $fixture['attempt_id'],
            'status' => 'AUTHORIZED',
        ]);
        $this->assertCount(4, $gateway->operationKeys);
        $this->assertSame(
            [$fixture['operation']->gateway_operation_key, $fixture['operation']->gateway_operation_key,
                $fixture['operation']->gateway_operation_key, $fixture['operation']->gateway_operation_key],
            $gateway->operationKeys,
        );
        $this->assertDatabaseCount('payment_events', 0);
    }

    public function test_permanent_gateway_rejection_does_not_retry_or_complete_booking(): void
    {
        $fixture = $this->createPendingOperation();
        $gateway = new SequencePaymentReleaseGateway([
            PaymentReleaseResult::rejected('PAYMENT_STATE_MISMATCH'),
        ]);
        $this->app->instance(PaymentReleaseGateway::class, $gateway);

        $counts = app(ProcessBookingReleaseOutbox::class)->handle();

        $this->assertSame(1, $counts['manual_review']);
        $this->assertSame('MANUAL_REVIEW', $fixture['operation']->fresh()->status);
        $this->assertSame('PAYMENT_STATE_MISMATCH', $fixture['operation']->fresh()->last_error_code);
        $this->assertSame('AWAITING_CONFIRMATION', $fixture['booking']->fresh()->status);
        $this->assertCount(1, $gateway->calls);
        $this->assertDatabaseCount('payment_events', 0);
    }

    public function test_live_processing_lease_prevents_a_second_worker_from_calling_gateway(): void
    {
        $fixture = $this->createPendingOperation();
        $fixture['operation']->update([
            'status' => 'PROCESSING',
            'attempt_count' => 1,
            'lease_expires_at' => now('UTC')->addMinute(),
        ]);
        $gateway = new SequencePaymentReleaseGateway([PaymentReleaseResult::succeeded()]);
        $this->app->instance(PaymentReleaseGateway::class, $gateway);

        $whileLeased = app(ProcessBookingReleaseOutbox::class)->handle();

        $this->assertSame(0, $whileLeased['processed']);
        $this->assertCount(0, $gateway->calls);
        $this->assertSame('AWAITING_CONFIRMATION', $fixture['booking']->fresh()->status);

        $this->travel(3)->minutes();
        $afterLease = app(ProcessBookingReleaseOutbox::class)->handle();

        $this->assertSame(1, $afterLease['completed']);
        $this->assertCount(1, $gateway->calls);
        $this->assertSame('COMPLETED', $fixture['booking']->fresh()->status);
    }

    public function test_report_moved_to_review_before_processing_blocks_gateway_release(): void
    {
        $fixture = $this->createPendingOperation();
        DB::table('reports')->insert([
            'reporter_user_id' => $fixture['owner']->id,
            'booking_id' => $fixture['booking']->id,
            'reason_code' => 'SERVICE_DISPUTE',
            'status' => 'IN_REVIEW',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $gateway = new SequencePaymentReleaseGateway([PaymentReleaseResult::succeeded()]);
        $this->app->instance(PaymentReleaseGateway::class, $gateway);

        $counts = app(ProcessBookingReleaseOutbox::class)->handle();

        $this->assertSame(1, $counts['manual_review']);
        $this->assertSame('MANUAL_REVIEW', $fixture['operation']->fresh()->status);
        $this->assertSame('REPORT_IN_REVIEW', $fixture['operation']->fresh()->last_error_code);
        $this->assertSame('AWAITING_CONFIRMATION', $fixture['booking']->fresh()->status);
        $this->assertCount(0, $gateway->calls);
        $this->assertDatabaseHas('payment_attempts', [
            'id' => $fixture['attempt_id'],
            'status' => 'AUTHORIZED',
        ]);
        $this->assertDatabaseCount('payment_events', 0);
    }

    /** @return array{owner: User, booking: Booking, attempt_id: int, operation: BookingReleaseOperation} */
    private function createPendingOperation(): array
    {
        $fixture = BookingFixture::requested($this);
        /** @var Booking $booking */
        $booking = $fixture['booking'];

        $attemptId = DB::table('payment_attempts')->insertGetId([
            'booking_id' => $booking->id,
            'attempt_no' => 1,
            'amount_vnd' => $booking->price_snapshot_vnd,
            'status' => 'AUTHORIZED',
            'idempotency_key' => 'release-attempt-'.$booking->id,
            'request_payload_hash' => str_repeat('c', 64),
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

        return [
            'owner' => $fixture['owner'],
            'booking' => $booking->fresh(),
            'attempt_id' => $attemptId,
            'operation' => BookingReleaseOperation::query()->where('booking_id', $booking->id)->firstOrFail(),
        ];
    }
}
