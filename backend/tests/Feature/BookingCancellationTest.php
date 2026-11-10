<?php

namespace Tests\Feature;

use App\Domain\Booking\Actions\ProcessCancellationOutbox;
use App\Domain\Booking\Payment\PaymentCancellationGateway;
use App\Domain\Booking\Payment\PaymentCancellationResult;
use App\Models\Booking;
use App\Models\BookingCancellationOperation;
use App\Models\PaymentAttempt;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Tests\Support\BookingFixture;
use Tests\Support\SuccessfulPaymentCancellationGateway;
use Tests\TestCase;

class BookingCancellationTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_can_cancel_requested_booking_and_replay_without_duplicate_event(): void
    {
        $fixture = BookingFixture::requested($this);
        $booking = $fixture['booking'];
        $key = 'cancel-owner-001';
        $url = "/api/bookings/{$booking->id}/cancel";
        $this->actingAs($fixture['owner']);

        $first = $this->withHeader('Idempotency-Key', $key)
            ->postJson($url, ['reason_note' => 'Kế hoạch thay đổi'])
            ->assertOk()
            ->assertJsonPath('status', 'CANCELLED');

        $this->assertDatabaseHas('booking_status_events', [
            'booking_id' => $booking->id,
            'from_status' => 'REQUESTED',
            'to_status' => 'CANCELLED',
            'actor_user_id' => $fixture['owner']->id,
            'source' => 'USER',
            'reason_code' => 'OWNER_CANCELLED',
            'reason_text' => 'Kế hoạch thay đổi',
        ]);
        $this->assertSame(2, (int) $booking->fresh()->version);
        $this->assertDatabaseCount('booking_cancellation_operations', 1);

        $this->withHeader('Idempotency-Key', $key)
            ->postJson($url, ['reason_note' => 'Kế hoạch thay đổi'])
            ->assertOk()->assertExactJson($first->json());
        $this->withHeader('Idempotency-Key', 'cancel-owner-new-key')
            ->postJson($url, ['reason_note' => 'Kế hoạch thay đổi'])
            ->assertOk();

        $this->assertDatabaseCount('booking_status_events', 2);
        $this->assertDatabaseCount('booking_cancellation_operations', 1);
    }

    public function test_cancel_requires_valid_key_and_same_key_cannot_change_note(): void
    {
        $fixture = BookingFixture::requested($this);
        $url = "/api/bookings/{$fixture['booking']->id}/cancel";

        $this->actingAs($fixture['owner'])->flushHeaders();
        $this->postJson($url)->assertUnprocessable()
            ->assertJsonPath('error.code', 'VALIDATION_ERROR');
        $this->withHeader('Idempotency-Key', 'Bad-Key')->postJson($url)->assertUnprocessable();

        $this->withHeader('Idempotency-Key', 'cancel-owner-conflict')
            ->postJson($url, ['reason_note' => 'Đổi lịch'])
            ->assertOk();
        $this->withHeader('Idempotency-Key', 'cancel-owner-conflict')
            ->postJson($url, ['reason_note' => 'Lý do đã đổi'])
            ->assertConflict()->assertJsonPath('error.code', 'IDEMPOTENCY_CONFLICT');
    }

    public function test_only_booking_participants_can_cancel_and_roles_are_required(): void
    {
        $fixture = BookingFixture::requested($this);
        $otherOwner = BookingFixture::make()['owner'];
        $url = "/api/bookings/{$fixture['booking']->id}/cancel";

        Auth::guard('web')->logout();
        Auth::forgetGuards();
        $this->flushHeaders()->postJson($url)->assertUnauthorized();
        $this->actingAs($otherOwner)->withHeader('Idempotency-Key', 'not-my-booking')
            ->postJson($url)->assertNotFound();
        $this->actingAs($fixture['caregiver'])->withHeader('Idempotency-Key', 'caregiver-role-ok')
            ->postJson($url)->assertOk()->assertJsonPath('status', 'CANCELLED');

        $second = BookingFixture::requested($this);
        DB::table('user_roles')->where('user_id', $second['caregiver']->id)->where('role', 'CAREGIVER')->delete();
        $this->actingAs($second['caregiver'])->withHeader('Idempotency-Key', 'caregiver-role-revoked')
            ->postJson("/api/bookings/{$second['booking']->id}/cancel")
            ->assertForbidden()->assertJsonPath('error.code', 'FORBIDDEN');
        $this->assertSame('REQUESTED', $second['booking']->fresh()->status);
    }

    public function test_cancel_pending_payment_is_durable_and_voids_before_releasing_booking(): void
    {
        $fixture = BookingFixture::requested($this);
        $booking = $fixture['booking'];
        $booking->update(['status' => 'AWAITING_PAYMENT', 'payment_due_at' => now()->addMinutes(15)]);
        $attemptId = $this->createAttempt($booking, 'PENDING');
        $booking->update(['current_payment_attempt_id' => $attemptId]);
        $url = "/api/bookings/{$booking->id}/cancel";

        $this->actingAs($fixture['owner'])->withHeader('Idempotency-Key', 'cancel-pending-void')
            ->postJson($url)->assertStatus(202)
            ->assertJsonPath('booking.status', 'AWAITING_PAYMENT')
            ->assertJsonPath('cancellation.status', 'CANCELLATION_PENDING');

        $operation = BookingCancellationOperation::query()->firstOrFail();
        $gateway = new SuccessfulPaymentCancellationGateway;
        $this->app->instance(PaymentCancellationGateway::class, $gateway);
        $stats = app(ProcessCancellationOutbox::class)->handle();

        $this->assertSame(1, $stats['completed']);
        $this->assertSame('VOID', $gateway->calls[0]['type']);
        $this->assertSame('COMPLETED', $operation->fresh()->status);
        $this->assertSame('CANCELLED', $booking->fresh()->status);
        $this->assertSame($attemptId, (int) $booking->fresh()->current_payment_attempt_id);
        $this->assertNotNull($booking->fresh()->payment_due_at);
        $this->assertDatabaseHas('payment_attempts', ['id' => $attemptId, 'status' => 'VOIDED']);
        $this->assertDatabaseCount('booking_status_events', 2);

        $this->withHeader('Idempotency-Key', 'cancel-pending-void')->postJson($url)->assertOk();
        $this->assertDatabaseCount('booking_status_events', 2);
        $this->assertSame($operation->gateway_operation_key, $gateway->calls[0]['key']);
    }

    public function test_confirmed_booking_refunds_before_cancel_and_rejects_after_start(): void
    {
        $fixture = BookingFixture::requested($this);
        $booking = $fixture['booking'];
        $booking->update(['status' => 'CONFIRMED', 'start_at' => now()->addHour()]);
        $attemptId = $this->createAttempt($booking, 'AUTHORIZED');
        $booking->update(['current_payment_attempt_id' => $attemptId]);
        $url = "/api/bookings/{$booking->id}/cancel";

        $this->actingAs($fixture['owner'])->withHeader('Idempotency-Key', 'cancel-confirmed')
            ->postJson($url)->assertStatus(202);
        $operation = BookingCancellationOperation::query()->firstOrFail();
        $this->assertSame('REFUND', $operation->operation_type);

        $gateway = new SuccessfulPaymentCancellationGateway;
        $this->app->instance(PaymentCancellationGateway::class, $gateway);
        app(ProcessCancellationOutbox::class)->handle();
        $this->assertSame('REFUNDED', DB::table('payment_attempts')->where('id', $attemptId)->value('status'));
        $this->assertSame('CANCELLED', $booking->fresh()->status);

        $second = BookingFixture::requested($this);
        $second['booking']->update(['status' => 'CONFIRMED', 'start_at' => now()->subSecond()]);
        $secondAttempt = $this->createAttempt($second['booking'], 'AUTHORIZED');
        $second['booking']->update(['current_payment_attempt_id' => $secondAttempt]);
        $this->actingAs($second['owner'])->withHeader('Idempotency-Key', 'too-late-confirmed')
            ->postJson("/api/bookings/{$second['booking']->id}/cancel")
            ->assertConflict()->assertJsonPath('error.code', 'INVALID_STATE');
    }

    public function test_default_sandbox_gateway_processes_void_and_refund_by_policy(): void
    {
        $voidFixture = BookingFixture::requested($this);
        $voidBooking = $voidFixture['booking'];
        $voidBooking->update([
            'status' => 'AWAITING_PAYMENT',
            'payment_due_at' => now()->addMinutes(10),
        ]);
        $voidAttemptId = $this->createAttempt($voidBooking, 'PENDING');
        $voidBooking->update(['current_payment_attempt_id' => $voidAttemptId]);

        $this->actingAs($voidFixture['owner'])->withHeader('Idempotency-Key', 'sandbox-default-void')
            ->postJson("/api/bookings/{$voidBooking->id}/cancel")
            ->assertStatus(202);

        $voidResult = app(ProcessCancellationOutbox::class)->handle();

        $this->assertSame(1, $voidResult['completed']);
        $this->assertSame('VOIDED', DB::table('payment_attempts')->where('id', $voidAttemptId)->value('status'));
        $this->assertSame('CANCELLED', $voidBooking->fresh()->status);

        $refundFixture = BookingFixture::requested($this);
        $refundBooking = $refundFixture['booking'];
        $refundBooking->update([
            'status' => 'CONFIRMED',
            'start_at' => now()->addHour(),
        ]);
        $refundAttemptId = $this->createAttempt($refundBooking, 'AUTHORIZED');
        $refundBooking->update(['current_payment_attempt_id' => $refundAttemptId]);

        $this->actingAs($refundFixture['owner'])->withHeader('Idempotency-Key', 'sandbox-default-refund')
            ->postJson("/api/bookings/{$refundBooking->id}/cancel")
            ->assertStatus(202);

        $refundResult = app(ProcessCancellationOutbox::class)->handle();

        $this->assertSame(1, $refundResult['completed']);
        $this->assertSame('REFUNDED', DB::table('payment_attempts')->where('id', $refundAttemptId)->value('status'));
        $this->assertSame('CANCELLED', $refundBooking->fresh()->status);
    }

    public function test_attempt_mismatch_does_not_change_booking_or_release_schedule(): void
    {
        $fixture = BookingFixture::requested($this);
        $booking = $fixture['booking'];
        $booking->update(['status' => 'CONFIRMED', 'start_at' => now()->addHour()]);
        $attemptId = $this->createAttempt($booking, 'PENDING');
        $booking->update(['current_payment_attempt_id' => $attemptId]);

        $this->actingAs($fixture['owner'])->withHeader('Idempotency-Key', 'mismatched-payment')
            ->postJson("/api/bookings/{$booking->id}/cancel")
            ->assertConflict()->assertJsonPath('error.code', 'PAYMENT_STATE_MISMATCH');

        $this->assertSame('CONFIRMED', $booking->fresh()->status);
        $this->assertDatabaseCount('booking_status_events', 1);
        $this->assertDatabaseHas('booking_cancellation_operations', [
            'booking_id' => $booking->id,
            'status' => 'MANUAL_REVIEW',
            'last_error_code' => 'PAYMENT_STATE_MISMATCH',
        ]);
        $this->assertDatabaseCount('booking_cancellation_operations', 1);
    }

    public function test_retryable_gateway_error_is_persisted_with_backoff_and_reuses_operation_key(): void
    {
        $fixture = BookingFixture::requested($this);
        $booking = $fixture['booking'];
        $booking->update(['status' => 'AWAITING_PAYMENT', 'payment_due_at' => now()->addMinutes(10)]);
        $attemptId = $this->createAttempt($booking, 'PENDING');
        $booking->update(['current_payment_attempt_id' => $attemptId]);
        $this->actingAs($fixture['owner'])->withHeader('Idempotency-Key', 'retry-void-key')
            ->postJson("/api/bookings/{$booking->id}/cancel")->assertStatus(202);
        $operation = BookingCancellationOperation::query()->firstOrFail();

        $gateway = new class implements PaymentCancellationGateway
        {
            public function cancel(string $operationType, PaymentAttempt $attempt, BookingCancellationOperation $operation): PaymentCancellationResult
            {
                return PaymentCancellationResult::retryable('GATEWAY_TIMEOUT');
            }
        };
        $this->app->instance(PaymentCancellationGateway::class, $gateway);
        app(ProcessCancellationOutbox::class)->handle();

        $this->assertSame('PENDING', $operation->fresh()->status);
        $this->assertSame(1, $operation->fresh()->attempt_count);
        $this->assertSame('GATEWAY_TIMEOUT', $operation->fresh()->last_error_code);
        $this->assertTrue($operation->fresh()->next_retry_at->greaterThan(CarbonImmutable::now('UTC')));
        $this->assertSame('AWAITING_PAYMENT', $booking->fresh()->status);
    }

    public function test_admin_can_review_queue_and_retry_with_audit_and_stable_gateway_key(): void
    {
        $fixture = BookingFixture::requested($this);
        $booking = $fixture['booking'];
        $booking->update(['status' => 'AWAITING_PAYMENT', 'payment_due_at' => now()->addMinutes(5)]);
        $attemptId = $this->createAttempt($booking, 'PENDING');
        $booking->update(['current_payment_attempt_id' => $attemptId]);

        $this->actingAs($fixture['owner'])->withHeader('Idempotency-Key', 'admin-queue-cancel')
            ->postJson("/api/bookings/{$booking->id}/cancel")->assertStatus(202);
        $operation = BookingCancellationOperation::query()->firstOrFail();
        $operation->update(['status' => 'MANUAL_REVIEW', 'last_error_code' => 'PAYMENT_GATEWAY_ERROR']);

        $admin = User::factory()->create();
        DB::table('user_roles')->insert([
            'user_id' => $admin->id,
            'role' => 'ADMIN',
            'assigned_at' => now(),
        ]);
        $this->actingAs($admin)->getJson('/api/admin/booking-cancellations')
            ->assertOk()->assertJsonPath('data.0.id', (string) $operation->id);
        $key = $operation->gateway_operation_key;

        $this->postJson("/api/admin/booking-cancellations/{$operation->id}/retry", [
            'reason' => 'Reviewed transient payment gateway failure.',
        ])->assertStatus(202)->assertJsonPath('status', 'CANCELLATION_PENDING');

        $this->assertSame('PENDING', $operation->fresh()->status);
        $this->assertSame(0, $operation->fresh()->attempt_count);
        $this->assertSame($key, $operation->fresh()->gateway_operation_key);
        $this->assertDatabaseHas('audit_logs', [
            'actor_user_id' => $admin->id,
            'action' => 'booking.cancellation.retry',
            'resource_type' => 'booking_cancellation_operation',
            'resource_id' => $operation->id,
        ]);
    }

    private function createAttempt(Booking $booking, string $status): int
    {
        return DB::table('payment_attempts')->insertGetId([
            'booking_id' => $booking->id,
            'attempt_no' => 1,
            'amount_vnd' => $booking->price_snapshot_vnd,
            'status' => $status,
            'idempotency_key' => 'attempt-'.$booking->id,
            'request_payload_hash' => str_repeat('a', 64),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
