<?php

namespace Tests\Feature;

use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\BookingFixture;
use Tests\Support\ListingFixture;
use Tests\TestCase;

class PublicListingAvailabilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_open_slot_covers_the_search_range_in_both_timezones(): void
    {
        $fixture = ListingFixture::make();
        $start = CarbonImmutable::instance($fixture['start_at']);
        $end = CarbonImmutable::instance($fixture['end_at']);

        $utc = $this->search($fixture)->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', (string) $fixture['active_listing_id']);

        $local = $this->search($fixture, [
            'start_at' => $start->setTimezone('Asia/Ho_Chi_Minh')->format('Y-m-d\TH:i:s.uP'),
            'end_at' => $end->setTimezone('Asia/Ho_Chi_Minh')->format('Y-m-d\TH:i:s.uP'),
        ])->assertOk();

        $this->assertSame($utc->json('data'), $local->json('data'));
    }

    /** @return array<string, array{string}> */
    public static function unavailableSlots(): array
    {
        return [
            'no slot' => ['missing'],
            'deleted slot' => ['deleted'],
            'starts too late' => ['late'],
            'ends too early' => ['early'],
            'two adjacent slots' => ['split'],
        ];
    }

    #[DataProvider('unavailableSlots')]
    public function test_a_single_live_slot_must_cover_the_whole_range(string $mode): void
    {
        $fixture = ListingFixture::make();
        $start = CarbonImmutable::instance($fixture['start_at']);
        $end = CarbonImmutable::instance($fixture['end_at']);
        $slots = DB::table('availability_slots')
            ->where('listing_id', $fixture['active_listing_id']);

        if ($mode === 'missing' || $mode === 'split') {
            $slots->delete();
        } elseif ($mode === 'deleted') {
            $slots->update(['deleted_at' => now()]);
        } elseif ($mode === 'late') {
            $slots->update([
                'start_at' => $start->addMicrosecond()->format('Y-m-d H:i:s.u'),
            ]);
        } else {
            $slots->update([
                'end_at' => $end->subMicrosecond()->format('Y-m-d H:i:s.u'),
            ]);
        }

        if ($mode === 'split') {
            $middle = $start->addHour();

            foreach ([[$start, $middle], [$middle, $end]] as [$from, $to]) {
                DB::table('availability_slots')->insert([
                    'listing_id' => $fixture['active_listing_id'],
                    'start_at' => $from->format('Y-m-d H:i:s.u'),
                    'end_at' => $to->format('Y-m-d H:i:s.u'),
                    'created_by_user_id' => $fixture['caregiver']->id,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }

        $this->search($fixture)->assertOk()->assertJsonCount(0, 'data');
    }

    /** @return array<string, array{string, bool}> */
    public static function bookingStates(): array
    {
        return [
            'requested' => ['REQUESTED', false],
            'awaiting payment' => ['AWAITING_PAYMENT', true],
            'confirmed' => ['CONFIRMED', true],
            'in progress' => ['IN_PROGRESS', true],
            'awaiting confirmation' => ['AWAITING_CONFIRMATION', true],
            'completed' => ['COMPLETED', false],
            'cancelled' => ['CANCELLED', false],
        ];
    }

    #[DataProvider('bookingStates')]
    public function test_search_and_quote_use_the_same_blocking_states(string $status, bool $blocked): void
    {
        $fixture = BookingFixture::requested($this);

        $fixture['booking']->forceFill([
            'status' => $status,
            'payment_due_at' => $status === 'AWAITING_PAYMENT'
                ? now()->addMinutes(15)
                : null,
        ])->save();

        $this->search($fixture)->assertOk()
            ->assertJsonCount($blocked ? 0 : 1, 'data');

        $this->postJson('/api/bookings/quote', $fixture['payload'])
            ->assertStatus($blocked ? 409 : 200);
    }

    public function test_overdue_hold_still_blocks_until_booking_is_cancelled(): void
    {
        $fixture = BookingFixture::requested($this);

        $fixture['booking']->forceFill([
            'status' => 'AWAITING_PAYMENT',
            'payment_due_at' => now()->subMinute(),
        ])->save();

        $this->search($fixture)->assertOk()->assertJsonCount(0, 'data');

        $this->postJson('/api/bookings/quote', $fixture['payload'])
            ->assertConflict()
            ->assertJsonPath('error.code', 'AVAILABILITY_CONFLICT');

        $this->assertDatabaseHas('bookings', [
            'id' => $fixture['booking']->id,
            'status' => 'AWAITING_PAYMENT',
        ]);

        // Simulate the final state written by the expiry/cancellation flow.
        $fixture['booking']->forceFill(['status' => 'CANCELLED'])->save();

        $this->search($fixture)->assertOk()->assertJsonCount(1, 'data');
        $this->postJson('/api/bookings/quote', $fixture['payload'])->assertOk();
    }

    /** @return array<string, array{string, bool}> */
    public static function overlapCases(): array
    {
        return [
            'ends at search start' => ['before', false],
            'starts at search end' => ['after', false],
            'one microsecond overlap' => ['overlap', true],
        ];
    }

    #[DataProvider('overlapCases')]
    public function test_intervals_use_half_open_boundaries(string $position, bool $blocked): void
    {
        $fixture = BookingFixture::requested($this);
        $start = CarbonImmutable::instance($fixture['start_at']);
        $end = CarbonImmutable::instance($fixture['end_at']);

        [$from, $to] = match ($position) {
            'before' => [$start->subHour(), $start],
            'after' => [$end, $end->addHour()],
            default => [$end->subMicrosecond(), $end->addHour()],
        };

        $fixture['booking']->forceFill([
            'status' => 'CONFIRMED',
            'start_at' => $from,
            'end_at' => $to,
        ])->save();

        $this->search($fixture)->assertOk()
            ->assertJsonCount($blocked ? 0 : 1, 'data');
    }

    public function test_invalid_time_ranges_return_field_errors(): void
    {
        $fixture = ListingFixture::make();
        $base = [
            'start_at' => $fixture['payload']['start_at'],
            'end_at' => $fixture['payload']['end_at'],
        ];

        foreach ([
            [['start_at' => $base['start_at']], 'end_at'],
            [['end_at' => $base['end_at']], 'start_at'],
            [[...$base, 'start_at' => '2030-01-01T08:00:00'], 'start_at'],
            [[...$base, 'end_at' => $base['start_at']], 'end_at'],
            [[...$base, 'start_at' => now()->subDay()->toIso8601String()], 'start_at'],
            [[...$base, 'end_at' => 'invalid'], 'end_at'],
        ] as [$query, $field]) {
            $response = $this->getJson('/api/listings?'.http_build_query($query))
                ->assertUnprocessable()
                ->assertJsonPath('error.code', 'VALIDATION_ERROR');

            $this->assertArrayHasKey($field, $response->json('error.details'));
        }
    }

    public function test_busy_listing_stays_public_when_no_dates_are_selected(): void
    {
        $fixture = BookingFixture::requested($this);
        $fixture['booking']->forceFill(['status' => 'CONFIRMED'])->save();

        $this->search($fixture)->assertOk()->assertJsonCount(0, 'data');
        $this->getJson('/api/listings')->assertOk()->assertJsonCount(1, 'data');
        $this->getJson("/api/listings/{$fixture['active_listing_id']}")->assertOk();
    }

    public function test_daycare_cannot_accept_a_two_day_search(): void
    {
        $fixture = ListingFixture::make();
        $start = CarbonImmutable::instance($fixture['start_at']);

        $this->search($fixture, [
            'end_at' => $start->addDays(2)->toIso8601String(),
        ])->assertUnprocessable()
            ->assertJsonStructure(['error' => ['details' => ['end_at']]]);
    }

    private function search(array $fixture, array $overrides = []): TestResponse
    {
        $query = array_replace([
            'start_at' => $fixture['payload']['start_at'],
            'end_at' => $fixture['payload']['end_at'],
            'service_type' => 'DAYCARE',
        ], $overrides);

        return $this->getJson('/api/listings?'.http_build_query($query));
    }
}
