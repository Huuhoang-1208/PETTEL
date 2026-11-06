<?php

namespace Tests\Feature;

use App\Domain\Booking\Actions\ProcessCancellationOutbox;
use App\Domain\Booking\Payment\PaymentCancellationGateway;
use App\Models\BookingCancellationOperation;
use Carbon\CarbonImmutable;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Support\BookingFixture;
use Tests\Support\SuccessfulPaymentCancellationGateway;
use Tests\TestCase;

class BookingExpiryTest extends TestCase
{
    use RefreshDatabase;

    public function test_requested_expiry_is_scheduled_once_and_audit_event_is_system_owned(): void
    {
        $fixture = BookingFixture::requested($this);
        $booking = $fixture['booking'];
        DB::table('bookings')->where('id', $booking->id)->update([
            'created_at' => CarbonImmutable::now('UTC')->subHours(25),
        ]);

        $this->artisan('bookings:process-expiries --batch=100')->assertSuccessful();
        $this->artisan('bookings:process-expiries --batch=100')->assertSuccessful();

        $this->assertSame('CANCELLED', $booking->fresh()->status);
        $this->assertDatabaseHas('booking_status_events', [
            'booking_id' => $booking->id,
            'from_status' => 'REQUESTED',
            'to_status' => 'CANCELLED',
            'actor_user_id' => null,
            'source' => 'SYSTEM',
            'reason_code' => 'REQUEST_EXPIRED',
        ]);
        $this->assertSame(2, (int) $booking->fresh()->version);
        $this->assertDatabaseCount('booking_cancellation_operations', 1);
        $this->assertDatabaseCount('booking_status_events', 2);

        $this->actingAs($fixture['owner'])->withHeader('Idempotency-Key', 'cancel-after-expiry')
            ->postJson("/api/bookings/{$booking->id}/cancel")
            ->assertConflict()->assertJsonPath('error.code', 'INVALID_STATE');
    }

    public function test_expired_payment_is_queued_for_void_and_keeps_booking_held_until_gateway_success(): void
    {
        $fixture = BookingFixture::requested($this);
        $booking = $fixture['booking'];
        $booking->update(['status' => 'AWAITING_PAYMENT', 'payment_due_at' => now()->subSecond()]);
        $attemptId = DB::table('payment_attempts')->insertGetId([
            'booking_id' => $booking->id,
            'attempt_no' => 1,
            'amount_vnd' => $booking->price_snapshot_vnd,
            'status' => 'PENDING',
            'idempotency_key' => 'expire-attempt-'.$booking->id,
            'request_payload_hash' => str_repeat('b', 64),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $booking->update(['current_payment_attempt_id' => $attemptId]);

        $this->artisan('bookings:process-expiries --batch=100')->assertSuccessful();
        $operation = BookingCancellationOperation::query()->firstOrFail();
        $this->assertSame('PAYMENT_EXPIRED', $operation->reason_code);
        $this->assertSame('PENDING', $operation->status);
        $this->assertSame('AWAITING_PAYMENT', $booking->fresh()->status);
        $this->assertNotNull($booking->fresh()->payment_due_at);

        $gateway = new SuccessfulPaymentCancellationGateway;
        $this->app->instance(PaymentCancellationGateway::class, $gateway);
        app(ProcessCancellationOutbox::class)->handle();
        $this->assertSame('CANCELLED', $booking->fresh()->status);
        $this->assertSame('VOIDED', DB::table('payment_attempts')->where('id', $attemptId)->value('status'));
        $this->assertDatabaseCount('booking_status_events', 2);
    }

    public function test_scheduler_registers_expiry_and_outbox_every_minute(): void
    {
        $events = app(Schedule::class)->events();
        $commands = array_map(fn ($event) => $event->command, $events);

        $this->assertTrue(collect($commands)->contains(fn (?string $command): bool => str_contains((string) $command, 'bookings:process-expiries')));
        $this->assertTrue(collect($commands)->contains(fn (?string $command): bool => str_contains((string) $command, 'bookings:process-cancellations')));
    }
}
