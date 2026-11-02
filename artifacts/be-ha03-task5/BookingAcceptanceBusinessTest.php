<?php

namespace Tests\Feature;

use App\Models\Booking;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\BookingFixture;
use Tests\TestCase;

class BookingAcceptanceBusinessTest extends TestCase
{
    use RefreshDatabase;

    public static function caregiverActions(): array
    {
        return ['accept' => ['accept'], 'reject' => ['reject']];
    }

    #[DataProvider('caregiverActions')]
    public function test_wrong_caregiver_cannot_change_booking_or_history(string $action): void
    {
        $fixture = BookingFixture::requested($this);
        $booking = $fixture['booking'];
        $other = BookingFixture::make()['caregiver'];
        $before = $booking->getAttributes();
        $events = $this->eventsFor($booking);

        $this->actingAs($other)->postJson("/api/bookings/{$booking->id}/{$action}")
            ->assertNotFound()->assertJsonPath('error.code', 'NOT_FOUND');

        $this->assertBookingUnchanged($booking, $before, $events);
    }

    public static function stoppedListingStates(): array
    {
        return array_map(fn ($status) => [$status], ['DRAFT', 'PENDING_REVIEW', 'SUSPENDED', 'ARCHIVED']);
    }

    #[DataProvider('stoppedListingStates')]
    public function test_stopped_listing_cannot_accept_booking(string $status): void
    {
        $fixture = BookingFixture::requested($this);
        $booking = $fixture['booking'];
        DB::table('listings')->where('id', $booking->listing_id)->update(['status' => $status]);
        $before = $booking->getAttributes();
        $events = $this->eventsFor($booking);

        $this->actingAs($fixture['caregiver'])->postJson("/api/bookings/{$booking->id}/accept")
            ->assertConflict()->assertJsonPath('error.code', 'INVALID_STATE');

        $this->assertBookingUnchanged($booking, $before, $events);
    }

    public static function processedBookingStates(): array
    {
        return array_map(fn ($status) => [$status], [
            'AWAITING_PAYMENT', 'CONFIRMED', 'IN_PROGRESS',
            'AWAITING_CONFIRMATION', 'COMPLETED', 'CANCELLED',
        ]);
    }

    #[DataProvider('processedBookingStates')]
    public function test_accept_from_processed_state_changes_nothing(string $status): void
    {
        $fixture = BookingFixture::requested($this);
        $booking = $fixture['booking'];
        $booking->update(['status' => $status]);
        $before = $booking->getAttributes();
        $events = $this->eventsFor($booking);

        $this->actingAs($fixture['caregiver'])->postJson("/api/bookings/{$booking->id}/accept")
            ->assertConflict()->assertJsonPath('error.code', 'INVALID_STATE');

        $this->assertBookingUnchanged($booking, $before, $events);
    }

    public function test_two_overlapping_requests_exist_until_one_is_accepted(): void
    {
        $fixture = BookingFixture::requested($this);
        $first = $fixture['booking'];
        $second = $this->secondBooking($fixture);
        $before = $second->getAttributes();
        $events = $this->eventsFor($second);
        $this->assertNotSame($first->id, $second->id);
        $this->assertSame('REQUESTED', $first->fresh()->status);
        $this->assertSame('REQUESTED', $second->fresh()->status);
        $this->assertDatabaseCount('bookings', 2);

        $this->actingAs($fixture['caregiver'])->postJson("/api/bookings/{$first->id}/accept")
            ->assertOk()->assertJsonPath('status', 'AWAITING_PAYMENT');
        $this->postJson("/api/bookings/{$second->id}/accept")
            ->assertConflict()->assertJsonPath('error.code', 'AVAILABILITY_CONFLICT');

        $this->assertBookingUnchanged($second, $before, $events);
        $this->assertDatabaseCount('booking_status_events', 3);
    }

    public static function separateTimeRanges(): array
    {
        return [
            'ends at first start' => [true, 0],
            'starts at first end' => [false, 0],
            'one second gap' => [false, 1],
        ];
    }

    #[DataProvider('separateTimeRanges')]
    public function test_separate_or_adjacent_bookings_can_both_be_accepted(bool $beforeFirst, int $gap): void
    {
        $fixture = BookingFixture::requested($this);
        $first = $fixture['booking'];
        $start = $beforeFirst ? $first->start_at->subHour() : $first->end_at->addSeconds($gap);
        $end = $start->addHour();
        $lastEnd = $end->greaterThan($first->end_at) ? $end : $first->end_at;
        DB::table('availability_slots')->where('listing_id', $first->listing_id)->update([
            'start_at' => $first->start_at->subHours(2),
            'end_at' => $lastEnd->addHour(),
        ]);
        $second = $this->secondBooking($fixture, [
            'start_at' => $start->format('Y-m-d\TH:i:s.u\Z'),
            'end_at' => $end->format('Y-m-d\TH:i:s.u\Z'),
        ]);

        $this->actingAs($fixture['caregiver']);
        foreach ([$first, $second] as $booking) {
            $this->postJson("/api/bookings/{$booking->id}/accept")
                ->assertOk()->assertJsonPath('status', 'AWAITING_PAYMENT');
            $this->assertNotNull($booking->fresh()->payment_due_at);
        }
        $this->assertDatabaseCount('booking_status_events', 4);
    }

    public function test_different_listings_of_one_caregiver_have_separate_capacity(): void
    {
        $fixture = BookingFixture::requested($this);
        $first = $fixture['booking'];
        DB::table('listings')->where('id', $fixture['draft_listing_id'])
            ->update(['status' => 'ACTIVE', 'published_at' => now()]);
        $second = $this->secondBooking($fixture, ['listing_id' => (string) $fixture['draft_listing_id']]);

        $this->actingAs($fixture['caregiver']);
        foreach ([$first, $second] as $booking) {
            $this->postJson("/api/bookings/{$booking->id}/accept")
                ->assertOk()->assertJsonPath('status', 'AWAITING_PAYMENT');
        }
        $this->assertDatabaseCount('booking_status_events', 4);
    }

    private function secondBooking(array $fixture, array $overrides = []): Booking
    {
        $response = $this->actingAs($fixture['owner'])
            ->withHeader('Idempotency-Key', 'business-second-booking')
            ->postJson('/api/bookings', array_replace($fixture['payload'], $overrides))
            ->assertCreated();

        return Booking::query()->findOrFail($response->json('id'));
    }

    private function eventsFor(Booking $booking): string
    {
        return DB::table('booking_status_events')->where('booking_id', $booking->id)
            ->orderBy('id')->get()->toJson();
    }

    private function assertBookingUnchanged(Booking $booking, array $before, string $events): void
    {
        $this->assertSame($before, $booking->fresh()->getAttributes());
        $this->assertSame($events, $this->eventsFor($booking));
    }
}
