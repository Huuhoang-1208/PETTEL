<?php

namespace Tests\Feature;

use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Tests\Support\BookingFixture;
use Tests\TestCase;

class BookingRejectionTest extends TestCase
{
    use RefreshDatabase;

    public function test_caregiver_can_reject_and_replay_without_duplicate_events(): void
    {
        $fixture = BookingFixture::requested($this);
        $booking = $fixture['booking'];
        $before = $booking->getAttributes();
        $url = "/api/bookings/{$booking->id}/reject";
        $this->actingAs($fixture['caregiver']);

        $response = $this->postJson($url)->assertOk()
            ->assertJsonPath('id', (string) $booking->id)
            ->assertJsonPath('status', 'CANCELLED')
            ->assertJsonPath('payment_due_at', null);

        $after = $booking->fresh()->getAttributes();
        foreach ($before as $field => $value) {
            if (! in_array($field, ['status', 'version', 'updated_at'], true)) {
                $this->assertSame($value, $after[$field], $field);
            }
        }

        $this->assertSame($before['version'] + 1, $after['version']);
        $this->assertDatabaseCount('booking_status_events', 2);
        $this->assertDatabaseHas('booking_status_events', [
            'booking_id' => $booking->id,
            'from_status' => 'REQUESTED',
            'to_status' => 'CANCELLED',
            'actor_user_id' => $fixture['caregiver']->id,
            'source' => 'USER',
            'reason_code' => 'CAREGIVER_REJECTED',
        ]);

        $this->postJson($url)->assertOk()->assertExactJson($response->json());
        $this->assertSame($after, $booking->fresh()->getAttributes());
        $this->assertDatabaseCount('booking_status_events', 2);
    }

    public function test_wrong_caregiver_and_missing_booking_return_not_found(): void
    {
        $fixture = BookingFixture::requested($this);
        $booking = $fixture['booking'];
        $other = BookingFixture::make()['caregiver'];

        $this->actingAs($other)
            ->postJson("/api/bookings/{$booking->id}/reject")
            ->assertNotFound()->assertJsonPath('error.code', 'NOT_FOUND');

        $this->actingAs($fixture['caregiver'])
            ->postJson('/api/bookings/0/reject')
            ->assertNotFound()->assertJsonPath('error.code', 'NOT_FOUND');

        $this->assertSame('REQUESTED', $booking->fresh()->status);
        $this->assertDatabaseCount('booking_status_events', 1);
    }

    public function test_reject_requires_login_role_and_active_account(): void
    {
        $this->postJson('/api/bookings/0/reject')->assertUnauthorized();
        $fixture = BookingFixture::requested($this);
        $url = "/api/bookings/{$fixture['booking']->id}/reject";

        $this->actingAs($fixture['owner'])->postJson($url)->assertForbidden();
        $fixture['caregiver']->forceFill(['status' => 'DEACTIVATED'])->save();
        $this->actingAs($fixture['caregiver'])->postJson($url)->assertForbidden();

        $this->assertSame('REQUESTED', $fixture['booking']->fresh()->status);
        $this->assertDatabaseCount('booking_status_events', 1);
    }

    public function test_processed_bookings_cannot_be_rejected(): void
    {
        $fixture = BookingFixture::requested($this);
        $booking = $fixture['booking'];
        $this->actingAs($fixture['caregiver']);

        foreach (['AWAITING_PAYMENT', 'CONFIRMED', 'IN_PROGRESS', 'AWAITING_CONFIRMATION', 'COMPLETED', 'CANCELLED'] as $status) {
            $booking->update(['status' => $status]);
            $this->postJson("/api/bookings/{$booking->id}/reject")
                ->assertConflict()->assertJsonPath('error.code', 'INVALID_STATE');
            $this->assertSame($status, $booking->fresh()->status);
            $this->assertDatabaseCount('booking_status_events', 1);
        }
    }

    public function test_cancellation_for_another_reason_is_not_a_reject_replay(): void
    {
        $fixture = BookingFixture::requested($this);
        $booking = $fixture['booking'];
        $booking->update(['status' => 'CANCELLED']);
        DB::table('booking_status_events')->insert([
            'booking_id' => $booking->id,
            'from_status' => 'REQUESTED',
            'to_status' => 'CANCELLED',
            'actor_user_id' => null,
            'source' => 'SYSTEM',
            'reason_code' => 'REQUEST_EXPIRED',
            'created_at' => now(),
        ]);

        $this->actingAs($fixture['caregiver'])
            ->postJson("/api/bookings/{$booking->id}/reject")
            ->assertConflict()->assertJsonPath('error.code', 'INVALID_STATE');
        $this->assertDatabaseCount('booking_status_events', 2);
    }

    public function test_suspended_listing_does_not_prevent_rejection(): void
    {
        $fixture = BookingFixture::requested($this);
        DB::table('listings')->where('id', $fixture['active_listing_id'])
            ->update(['status' => 'SUSPENDED', 'suspension_source' => 'ADMIN']);

        $this->actingAs($fixture['caregiver'])
            ->postJson("/api/bookings/{$fixture['booking']->id}/reject")
            ->assertOk()->assertJsonPath('status', 'CANCELLED');
    }

    public function test_event_failure_rolls_back_the_booking_change(): void
    {
        $fixture = BookingFixture::requested($this);
        $booking = $fixture['booking'];
        $before = $booking->getAttributes();
        $this->withoutExceptionHandling();

        DB::listen(function (QueryExecuted $query): void {
            if (str_contains($query->sql, 'insert into `booking_status_events`')) {
                throw new RuntimeException('Simulated event failure');
            }
        });

        try {
            $this->actingAs($fixture['caregiver'])
                ->postJson("/api/bookings/{$booking->id}/reject");
            $this->fail('Expected event failure.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Simulated event failure', $exception->getMessage());
        }

        $this->assertSame($before, $booking->fresh()->getAttributes());
        $this->assertDatabaseCount('booking_status_events', 1);
    }
}
