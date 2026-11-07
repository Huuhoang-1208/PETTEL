<?php

namespace Tests\Feature;

use Illuminate\Database\QueryException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Support\BookingFixture;
use Tests\TestCase;

class PaymentSchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_booking_stores_payment_deadline_and_current_attempt(): void
    {
        $fixture = BookingFixture::requested($this);
        $booking = $fixture['booking'];
        $deadline = now('UTC')->addMinutes(15);

        $booking->update([
            'status' => 'AWAITING_PAYMENT',
            'payment_due_at' => $deadline,
        ]);

        $attemptId = $this->insertAttempt($booking->id, 1, 'payment-create-1');
        $booking->update(['current_payment_attempt_id' => $attemptId]);

        $this->assertDatabaseHas('payment_attempts', [
            'id' => $attemptId,
            'booking_id' => $booking->id,
            'attempt_no' => 1,
            'amount_vnd' => $booking->price_snapshot_vnd,
            'status' => 'PENDING',
            'idempotency_key' => 'payment-create-1',
        ]);

        $this->assertSame(
            $attemptId,
            (int) $booking->fresh()->current_payment_attempt_id,
        );
        $this->assertTrue(
            $booking->fresh()->payment_due_at->equalTo($deadline),
        );
    }

    public function test_attempt_number_is_unique_per_booking(): void
    {
        $booking = BookingFixture::requested($this)['booking'];

        $this->insertAttempt($booking->id, 1, 'payment-create-1');

        $this->expectException(UniqueConstraintViolationException::class);

        $this->insertAttempt($booking->id, 1, 'payment-create-2');
    }

    public function test_attempt_idempotency_key_is_unique_per_booking(): void
    {
        $booking = BookingFixture::requested($this)['booking'];

        $this->insertAttempt($booking->id, 1, 'payment-create-same-key');

        $this->expectException(UniqueConstraintViolationException::class);

        $this->insertAttempt($booking->id, 2, 'payment-create-same-key');
    }

    public function test_event_id_is_unique_per_provider(): void
    {
        $booking = BookingFixture::requested($this)['booking'];
        $attemptId = $this->insertAttempt($booking->id, 1, 'payment-create-1');

        $this->insertEvent($attemptId, 'sandbox', 'event-1');

        $this->expectException(UniqueConstraintViolationException::class);

        $this->insertEvent($attemptId, 'sandbox', 'event-1');
    }

    public function test_same_event_id_can_belong_to_a_different_provider(): void
    {
        $booking = BookingFixture::requested($this)['booking'];
        $attemptId = $this->insertAttempt($booking->id, 1, 'payment-create-1');

        $this->insertEvent($attemptId, 'sandbox-a', 'event-1');
        $this->insertEvent($attemptId, 'sandbox-b', 'event-1');

        $this->assertDatabaseCount('payment_events', 2);
    }

    public function test_database_rejects_non_positive_attempt_amount(): void
    {
        $booking = BookingFixture::requested($this)['booking'];

        $this->expectException(QueryException::class);
        $this->expectExceptionMessage('chk_payment_attempt_amount');

        $this->insertAttempt($booking->id, 1, 'payment-create-1', amount: 0);
    }

    public function test_database_rejects_unknown_attempt_status(): void
    {
        $booking = BookingFixture::requested($this)['booking'];

        $this->expectException(QueryException::class);
        $this->expectExceptionMessage('chk_payment_attempt_status');

        $this->insertAttempt(
            $booking->id,
            1,
            'payment-create-1',
            status: 'TIMEOUT',
        );
    }

    private function insertAttempt(
        int $bookingId,
        int $attemptNo,
        string $idempotencyKey,
        ?int $amount = null,
        string $status = 'PENDING',
    ): int {
        $booking = DB::table('bookings')->where('id', $bookingId)->first();

        return DB::table('payment_attempts')->insertGetId([
            'booking_id' => $bookingId,
            'attempt_no' => $attemptNo,
            'amount_vnd' => $amount ?? (int) $booking->price_snapshot_vnd,
            'status' => $status,
            'idempotency_key' => $idempotencyKey,
            'request_payload_hash' => hash('sha256', $idempotencyKey),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function insertEvent(
        int $attemptId,
        string $provider,
        string $eventId,
    ): int {
        return DB::table('payment_events')->insertGetId([
            'payment_attempt_id' => $attemptId,
            'provider' => $provider,
            'external_event_id' => $eventId,
            'event_type' => 'PAYMENT_AUTHORIZED',
            'payload_hash' => hash('sha256', $eventId),
            'processing_status' => 'RECEIVED',
            'received_at' => now(),
        ]);
    }
}
