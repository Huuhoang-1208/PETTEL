<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Support\BookingFixture;
use Tests\TestCase;

class BookingCareLifecycleTest extends TestCase
{
    use RefreshDatabase;

    public function test_care_transitions_are_saved_to_booking_status_history(): void
    {
        $fixture = BookingFixture::requested($this);
        $booking = $fixture['booking'];

        // Đưa booking tới trạng thái đầu vào của luồng chăm sóc.
        $booking->update([
            'status' => 'CONFIRMED',
            'version' => 3,
        ]);

        $eventsBefore = DB::table('booking_status_events')
            ->where('booking_id', $booking->id)
            ->count();

        $this->actingAs($fixture['caregiver'])
            ->postJson("/api/bookings/{$booking->id}/start")
            ->assertOk()
            ->assertJsonPath('status', 'IN_PROGRESS');

        $this->postJson("/api/bookings/{$booking->id}/request-completion")
            ->assertOk()
            ->assertJsonPath('status', 'AWAITING_CONFIRMATION');

        $this->assertSame('AWAITING_CONFIRMATION', $booking->fresh()->status);
        $this->assertSame(
            $eventsBefore + 2,
            DB::table('booking_status_events')
                ->where('booking_id', $booking->id)
                ->count(),
        );

        $history = DB::table('booking_status_events')
            ->where('booking_id', $booking->id)
            ->whereIn('to_status', ['IN_PROGRESS', 'AWAITING_CONFIRMATION'])
            ->orderBy('id')
            ->get(['from_status', 'to_status', 'actor_user_id', 'source'])
            ->map(fn ($event) => [
                $event->from_status,
                $event->to_status,
                (int) $event->actor_user_id,
                $event->source,
            ])
            ->all();

        $caregiverId = (int) $fixture['caregiver']->id;

        $this->assertSame([
            ['CONFIRMED', 'IN_PROGRESS', $caregiverId, 'USER'],
            ['IN_PROGRESS', 'AWAITING_CONFIRMATION', $caregiverId, 'USER'],
        ], $history);
    }
}
