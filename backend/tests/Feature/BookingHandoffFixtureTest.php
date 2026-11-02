<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\BookingFixture;
use Tests\TestCase;

class BookingHandoffFixtureTest extends TestCase
{
    use RefreshDatabase;

    public function test_requested_fixture_is_ready_for_accept_booking_tests(): void
    {
        $fixture = BookingFixture::requested($this);
        $booking = $fixture['booking'];

        $this->assertSame('REQUESTED', $booking->status);
        $this->assertSame((string) $fixture['owner']->id, (string) $booking->owner_id);
        $this->assertSame((string) $fixture['caregiver']->id, (string) $booking->caregiver_user_id);
        $this->assertTrue($fixture['caregiver']->hasRole('CAREGIVER'));
        $this->assertSame(150000, $booking->price_snapshot_vnd);
        $this->assertNull($booking->payment_due_at);
        $this->assertDatabaseCount('bookings', 1);
        $this->assertDatabaseCount('booking_status_events', 1);
        $this->assertDatabaseHas('booking_status_events', [
            'booking_id' => $booking->id,
            'from_status' => null,
            'to_status' => 'REQUESTED',
            'actor_user_id' => $fixture['owner']->id,
        ]);
    }
}
