<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\BookingReleaseOperation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Support\BookingFixture;
use Tests\TestCase;

class BookingCompletionConfirmationTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_can_request_release_and_replay_without_duplicate_side_effects(): void
    {
        $fixture = $this->awaitingConfirmation();
        $booking = $fixture['booking'];
        $url = "/api/bookings/{$booking->id}/confirm-completion";
        $version = $booking->version;
        $eventCount = DB::table('booking_status_events')->where('booking_id', $booking->id)->count();

        $first = $this->actingAs($fixture['owner'])
            ->postJson($url)
            ->assertAccepted()
            ->assertJsonPath('booking.status', 'AWAITING_CONFIRMATION')
            ->assertJsonPath('release.replayed', false);

        $operationId = $first->json('release.id');
        $operationKey = BookingReleaseOperation::query()->findOrFail($operationId)->gateway_operation_key;

        $second = $this->postJson($url)
            ->assertAccepted()
            ->assertJsonPath('release.id', $operationId)
            ->assertJsonPath('release.replayed', true);

        $this->assertSame('AWAITING_CONFIRMATION', $booking->fresh()->status);
        $this->assertSame($version, $booking->fresh()->version);
        $this->assertSame($eventCount, DB::table('booking_status_events')->where('booking_id', $booking->id)->count());
        $this->assertSame(1, BookingReleaseOperation::query()->where('booking_id', $booking->id)->count());
        $this->assertSame($operationKey, BookingReleaseOperation::query()->findOrFail($second->json('release.id'))->gateway_operation_key);
    }

    public function test_non_owner_cannot_request_release(): void
    {
        $fixture = $this->awaitingConfirmation();
        $url = "/api/bookings/{$fixture['booking']->id}/confirm-completion";

        $this->actingAs($fixture['caregiver'])->postJson($url)->assertNotFound();

        $this->assertDatabaseCount('booking_release_operations', 0);
    }

    public function test_confirmation_requires_awaiting_confirmation_and_authorized_payment(): void
    {
        $fixture = $this->awaitingConfirmation(status: 'IN_PROGRESS');
        $this->actingAs($fixture['owner'])
            ->postJson("/api/bookings/{$fixture['booking']->id}/confirm-completion")
            ->assertConflict()
            ->assertJsonPath('error.code', 'INVALID_STATE');

        $fixture = $this->awaitingConfirmation(paymentStatus: 'FAILED');
        $this->actingAs($fixture['owner'])
            ->postJson("/api/bookings/{$fixture['booking']->id}/confirm-completion")
            ->assertConflict()
            ->assertJsonPath('error.code', 'PAYMENT_STATE_MISMATCH');

        $this->assertDatabaseCount('booking_release_operations', 0);
    }

    public function test_report_in_review_blocks_release_request(): void
    {
        $fixture = $this->awaitingConfirmation();
        DB::table('reports')->insert([
            'reporter_user_id' => $fixture['owner']->id,
            'booking_id' => $fixture['booking']->id,
            'reason_code' => 'SERVICE_DISPUTE',
            'status' => 'IN_REVIEW',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($fixture['owner'])
            ->postJson("/api/bookings/{$fixture['booking']->id}/confirm-completion")
            ->assertConflict()
            ->assertJsonPath('error.code', 'REPORT_IN_REVIEW');

        $this->assertDatabaseCount('booking_release_operations', 0);
    }

    public function test_completed_owner_confirmation_replay_has_no_side_effects(): void
    {
        $fixture = $this->awaitingConfirmation();
        $booking = $fixture['booking'];
        $now = now('UTC');

        DB::table('payment_attempts')->where('id', $fixture['attempt_id'])->update([
            'status' => 'RELEASED',
            'released_at' => $now,
            'updated_at' => $now,
        ]);

        BookingReleaseOperation::query()->create([
            'booking_id' => $booking->id,
            'actor_user_id' => $fixture['owner']->id,
            'payment_attempt_id' => $fixture['attempt_id'],
            'gateway_operation_key' => '80dca6c7-33b6-45e3-890c-e9399b74e605',
            'status' => 'COMPLETED',
            'attempt_count' => 1,
            'completed_at' => $now,
        ]);

        $booking->update(['status' => 'COMPLETED', 'version' => 9]);
        DB::table('booking_status_events')->insert([
            'booking_id' => $booking->id,
            'from_status' => 'AWAITING_CONFIRMATION',
            'to_status' => 'COMPLETED',
            'actor_user_id' => $fixture['owner']->id,
            'source' => 'USER',
            'reason_code' => 'OWNER_CONFIRMED',
            'reason_text' => null,
            'created_at' => $now,
        ]);
        $eventCount = DB::table('booking_status_events')->where('booking_id', $booking->id)->count();

        $this->actingAs($fixture['owner'])
            ->postJson("/api/bookings/{$booking->id}/confirm-completion")
            ->assertOk()
            ->assertJsonPath('status', 'COMPLETED');

        $this->assertSame(9, $booking->fresh()->version);
        $this->assertSame($eventCount, DB::table('booking_status_events')->where('booking_id', $booking->id)->count());
        $this->assertDatabaseCount('booking_release_operations', 1);
    }

    /** @return array<string, mixed> */
    private function awaitingConfirmation(
        string $status = 'AWAITING_CONFIRMATION',
        string $paymentStatus = 'AUTHORIZED',
    ): array {
        $fixture = BookingFixture::requested($this);
        /** @var Booking $booking */
        $booking = $fixture['booking'];

        $attemptId = DB::table('payment_attempts')->insertGetId([
            'booking_id' => $booking->id,
            'attempt_no' => 1,
            'amount_vnd' => $booking->price_snapshot_vnd,
            'status' => $paymentStatus,
            'idempotency_key' => 'completion-attempt-'.$booking->id,
            'request_payload_hash' => str_repeat('b', 64),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $booking->update([
            'status' => $status,
            'current_payment_attempt_id' => $attemptId,
            'version' => 4,
        ]);

        return [...$fixture, 'booking' => $booking->fresh(), 'attempt_id' => $attemptId];
    }
}
