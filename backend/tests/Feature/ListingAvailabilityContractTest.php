<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Support\BookingFixture;
use Tests\TestCase;

class ListingAvailabilityContractTest extends TestCase
{
    use RefreshDatabase;

    public function test_slot_api_preserves_offset_microseconds_and_half_open_boundaries(): void
    {
        $fixture = BookingFixture::make();
        $caregiver = $fixture['caregiver'];
        $listingId = $fixture['active_listing_id'];
        $version = DB::table('listings')->where('id', $listingId)->value('version');
        $start = now('Asia/Ho_Chi_Minh')->addDays(20)->setTime(9, 0, 0, 123456);
        $end = $start->copy()->addHour()->setMicrosecond(654321);
        $format = fn ($time) => $time->format('Y-m-d\TH:i:s.uP');

        $this->actingAs($caregiver)->postJson("/api/caregiver/listings/{$listingId}/availability-slots", [
            'start_at' => $start->format('Y-m-d\TH:i:s'), 'end_at' => $format($end),
        ])->assertUnprocessable()->assertJsonPath('error.code', 'VALIDATION_ERROR');
        $first = $this->postJson("/api/caregiver/listings/{$listingId}/availability-slots", [
            'start_at' => $format($start), 'end_at' => $format($end),
        ])->assertCreated()->assertJsonPath('data.start_at', $start->copy()->utc()->format('Y-m-d\TH:i:s.u\Z'))
            ->assertJsonPath('data.end_at', $end->copy()->utc()->format('Y-m-d\TH:i:s.u\Z'));
        $firstId = $first->json('data.id');

        $adjacentEnd = $end->copy()->addHour();
        $second = $this->postJson("/api/caregiver/listings/{$listingId}/availability-slots", [
            'start_at' => $format($end), 'end_at' => $format($adjacentEnd),
        ])->assertCreated();

        $this->postJson("/api/caregiver/listings/{$listingId}/availability-slots", [
            'start_at' => $format($start->copy()->addMinutes(5)),
            'end_at' => $format($start->copy()->addMinutes(10)),
        ])->assertConflict()->assertJsonPath('error.code', 'AVAILABILITY_CONFLICT');
        $this->postJson("/api/caregiver/listings/{$listingId}/availability-slots?extra=1", [
            'start_at' => $format($adjacentEnd->copy()->addHour()),
            'end_at' => $format($adjacentEnd->copy()->addHours(2)),
        ])->assertUnprocessable()->assertJsonPath('error.code', 'VALIDATION_ERROR');

        $list = $this->getJson("/api/caregiver/listings/{$listingId}/availability-slots?per_page=1")
            ->assertOk()->assertJsonPath('meta.per_page', 1)->assertJsonPath('meta.total', 3);
        $this->assertSame((string) DB::table('availability_slots')->where('listing_id', $listingId)->orderBy('start_at')->value('id'), $list->json('data.0.id'));
        $this->getJson("/api/caregiver/listings/{$listingId}/availability-slots?unknown=1")
            ->assertUnprocessable()->assertJsonPath('error.code', 'VALIDATION_ERROR');
        $this->assertDatabaseHas('listings', ['id' => $listingId, 'version' => $version]);
        $this->assertDatabaseHas('availability_slots', ['id' => $firstId, 'created_by_user_id' => $caregiver->id]);
        $this->assertNotSame($firstId, $second->json('data.id'));
    }

    public function test_slot_update_and_delete_preserve_all_four_held_booking_states_but_not_requested(): void
    {
        $fixture = BookingFixture::requested($this);
        $listingId = $fixture['active_listing_id'];
        $caregiver = $fixture['caregiver'];
        $booking = $fixture['booking'];
        $slot = DB::table('availability_slots')->where('listing_id', $listingId)->first();
        $startAt = $slot->start_at;
        $endAt = $slot->end_at;
        $version = DB::table('listings')->where('id', $listingId)->value('version');
        $futureStart = now()->addDays(30)->utc();
        $futureEnd = $futureStart->copy()->addHour();
        $payload = [
            'start_at' => $futureStart->format('Y-m-d\TH:i:s.u\Z'),
            'end_at' => $futureEnd->format('Y-m-d\TH:i:s.u\Z'),
        ];

        foreach (['AWAITING_PAYMENT', 'CONFIRMED', 'IN_PROGRESS', 'AWAITING_CONFIRMATION'] as $status) {
            DB::table('bookings')->where('id', $booking->id)->update([
                'status' => $status,
                'payment_due_at' => $status === 'AWAITING_PAYMENT' ? now()->subMinute() : null,
            ]);
            $this->actingAs($caregiver)->patchJson("/api/caregiver/listings/{$listingId}/availability-slots/{$slot->id}", $payload)
                ->assertConflict()->assertJsonPath('error.code', 'AVAILABILITY_CONFLICT');
            $this->deleteJson("/api/caregiver/listings/{$listingId}/availability-slots/{$slot->id}")
                ->assertConflict()->assertJsonPath('error.code', 'AVAILABILITY_CONFLICT');
            $this->assertDatabaseHas('availability_slots', ['id' => $slot->id, 'start_at' => $startAt, 'end_at' => $endAt, 'deleted_at' => null]);
        }

        DB::table('bookings')->where('id', $booking->id)->update(['status' => 'REQUESTED', 'payment_due_at' => null]);
        $this->patchJson("/api/caregiver/listings/{$listingId}/availability-slots/{$slot->id}", $payload)->assertOk();
        $this->assertDatabaseHas('bookings', ['id' => $booking->id, 'status' => 'REQUESTED']);
        $this->postJson("/api/bookings/{$booking->id}/accept")->assertConflict()->assertJsonPath('error.code', 'AVAILABILITY_CONFLICT');
        $this->assertDatabaseHas('listings', ['id' => $listingId, 'version' => $version]);
        $this->assertDatabaseHas('availability_slots', ['id' => $slot->id, 'deleted_at' => null]);
    }

    public function test_archived_listing_rejects_slot_create_update_and_delete(): void
    {
        $fixture = BookingFixture::make();
        $caregiver = $fixture['caregiver'];
        $listingId = $fixture['active_listing_id'];
        $slotId = DB::table('availability_slots')->where('listing_id', $listingId)->value('id');
        $this->actingAs($caregiver)->postJson("/api/caregiver/listings/{$listingId}/archive", ['reason' => 'No longer offered.'])
            ->assertOk()->assertJsonPath('data.status', 'ARCHIVED');
        $start = now()->addDays(30);
        $payload = ['start_at' => $start->format('Y-m-d\TH:i:s.u\Z'), 'end_at' => $start->copy()->addHour()->format('Y-m-d\TH:i:s.u\Z')];

        $this->postJson("/api/caregiver/listings/{$listingId}/availability-slots", $payload)
            ->assertConflict()->assertJsonPath('error.code', 'INVALID_STATE');
        $this->patchJson("/api/caregiver/listings/{$listingId}/availability-slots/{$slotId}", $payload)
            ->assertConflict()->assertJsonPath('error.code', 'INVALID_STATE');
        $this->deleteJson("/api/caregiver/listings/{$listingId}/availability-slots/{$slotId}")
            ->assertConflict()->assertJsonPath('error.code', 'INVALID_STATE');
        $this->assertDatabaseHas('availability_slots', ['id' => $slotId, 'deleted_at' => null]);
    }
}
