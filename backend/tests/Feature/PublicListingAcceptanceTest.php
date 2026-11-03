<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Listing;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Tests\Support\BookingFixture;
use Tests\Support\ListingFixture;
use Tests\TestCase;

class PublicListingAcceptanceTest extends TestCase
{
    use RefreshDatabase;

    public function test_all_filters_are_applied_before_pagination(): void
    {
        $fixture = BookingFixture::requested($this);
        $base = Listing::query()->findOrFail($fixture['active_listing_id']);
        $base->fill(['title' => 'Acceptance daycare'])->save();

        $secondMatch = $this->copyListing($base);

        $rules = $base->acceptance_rules_json;

        foreach ([
            ['area_code' => 'DN_EAST'],
            [
                'acceptance_rules_json' => [
                    ...$rules,
                    'species' => ['CAT'],
                ],
            ],
            [
                'acceptance_rules_json' => [
                    ...$rules,
                    'sizes' => ['LARGE'],
                ],
            ],
            ['service_type' => 'BOARDING', 'price_unit' => 'NIGHT'],
            ['price_vnd' => 20000],
            ['price_vnd' => 75000],
        ] as $changes) {
            $this->copyListing($base, $changes);
        }

        $withoutSlot = $this->copyListing($base);
        $withoutSlot->availabilitySlots()->delete();

        $busy = $this->copyListing($base);

        $busyResponse = $this->withHeader('Idempotency-Key', 'acceptance-busy')
            ->postJson('/api/bookings', [
                ...$fixture['payload'],
                'listing_id' => (string) $busy->id,
            ])->assertCreated();

        Booking::query()->findOrFail($busyResponse->json('id'))
            ->forceFill(['status' => 'CONFIRMED'])
            ->save();

        Listing::query()->where('status', 'DRAFT')->update([
            'title' => 'Acceptance daycare',
        ]);

        $filters = [
            'q' => 'acceptance',
            'service_type' => 'DAYCARE',
            'area_code' => 'DN_CENTER',
            'species' => 'DOG',
            'size' => 'SMALL',
            'min_price_vnd' => '100000',
            'max_price_vnd' => '200000',
            'per_page' => '1',
        ];

        $first = $this->getJson($this->url($fixture, $filters))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', (string) $base->id)
            ->assertJsonPath('data.0.price_total_vnd', 150000)
            ->assertJsonPath('meta.total', 2)
            ->assertJsonPath('meta.per_page', 1)
            ->assertJsonPath('meta.last_page', 2);

        $next = $first->json('links.next');
        $this->assertIsString($next);

        $second = $this->getJson($next)
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', (string) $secondMatch->id)
            ->assertJsonPath('meta.total', 2)
            ->assertJsonPath('links.next', null);

        $ids = [
            $first->json('data.0.id'),
            $second->json('data.0.id'),
        ];

        $this->assertSame(
            [(string) $base->id, (string) $secondMatch->id],
            $ids,
        );
        $this->assertCount(2, array_unique($ids));

        $this->getJson($this->url($fixture, [
            ...$filters,
            'page' => '3',
        ]))->assertOk()
            ->assertJsonCount(0, 'data')
            ->assertJsonPath('meta.total', 2);

        $this->getJson($this->url($fixture, [
            ...$filters,
            'min_price_vnd' => '160000',
        ]))->assertOk()
            ->assertJsonCount(0, 'data')
            ->assertJsonPath('meta.total', 0);

        $this->assertDatabaseHas('bookings', [
            'id' => $fixture['booking']->id,
            'status' => 'REQUESTED',
        ]);
    }

    public function test_public_responses_do_not_expose_existing_pet_or_booking_secrets(): void
    {
        $fixture = ListingFixture::make();
        $petSecret = 'PRIVATE_PET_NOTE_BE_H03';
        $instructionSecret = 'PRIVATE_BOOKING_INSTRUCTION_BE_H03';

        $fixture['pet']->fill(['diet_notes' => $petSecret])->save();

        $created = $this->actingAs($fixture['owner'])
            ->withHeader('Idempotency-Key', 'acceptance-privacy')
            ->postJson('/api/bookings', [
                ...$fixture['payload'],
                'instructions' => $instructionSecret,
            ])->assertCreated();

        $booking = Booking::query()->findOrFail($created->json('id'));

        $this->assertSame(
            $petSecret,
            $booking->pet_snapshot_json['diet_notes'],
        );
        $this->assertSame(
            $instructionSecret,
            $booking->instructions_snapshot,
        );

        Auth::forgetGuards();
        $this->assertGuest();

        $id = (string) $fixture['active_listing_id'];

        $list = $this->getJson('/api/listings')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $id);

        $datedList = $this->getJson($this->url($fixture))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $id)
            ->assertJsonPath('data.0.price_total_vnd', 150000);

        $detail = $this->getJson("/api/listings/{$id}")
            ->assertOk()
            ->assertJsonPath('data.id', $id);

        foreach ([
            $list->json('data.0'),
            $datedList->json('data.0'),
            $detail->json('data'),
        ] as $item) {
            $this->assertEqualsCanonicalizing([
                'id',
                'title',
                'description',
                'service_type',
                'area_code',
                'price_vnd',
                'price_unit',
                'acceptance_rules_json',
                'price_total_vnd',
                'media',
            ], array_keys($item));
            $this->assertSame([], $item['media']);

            $this->assertEquals([
                'schema_version' => 1,
                'species' => ['DOG'],
                'sizes' => ['SMALL'],
            ], $item['acceptance_rules_json']);
        }

        foreach ([$list, $datedList, $detail] as $response) {
            $this->assertStringNotContainsString(
                $petSecret,
                $response->getContent(),
            );
            $this->assertStringNotContainsString(
                $instructionSecret,
                $response->getContent(),
            );
        }

        foreach ([$petSecret, $instructionSecret] as $secret) {
            $this->getJson('/api/listings?'.http_build_query(['q' => $secret]))
                ->assertOk()
                ->assertJsonCount(0, 'data')
                ->assertJsonPath('meta.total', 0);
        }
    }

    public function test_slot_can_match_both_search_boundaries_exactly(): void
    {
        $fixture = ListingFixture::make();
        $start = CarbonImmutable::instance($fixture['start_at'])->addMicrosecond();
        $end = CarbonImmutable::instance($fixture['end_at'])->addMicrosecond();

        DB::table('availability_slots')
            ->where('listing_id', $fixture['active_listing_id'])
            ->update([
                'start_at' => $start->format('Y-m-d H:i:s.u'),
                'end_at' => $end->format('Y-m-d H:i:s.u'),
            ]);

        $this->getJson($this->url($fixture, [
            'start_at' => $start->format('Y-m-d\TH:i:s.u\Z'),
            'end_at' => $end->format('Y-m-d\TH:i:s.u\Z'),
        ]))->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', (string) $fixture['active_listing_id']);
    }

    public function test_booking_of_another_listing_does_not_block_this_listing(): void
    {
        $fixture = BookingFixture::requested($this);
        $booked = Listing::query()->findOrFail($fixture['active_listing_id']);
        $available = $this->copyListing($booked);

        $fixture['booking']->forceFill(['status' => 'CONFIRMED'])->save();

        $this->getJson($this->url($fixture))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', (string) $available->id);
    }

    private function copyListing(Listing $template, array $changes = []): Listing
    {
        $listing = $template->replicate();
        $listing->fill($changes)->save();

        $slot = $template->availabilitySlots()->firstOrFail();

        DB::table('availability_slots')->insert([
            'listing_id' => $listing->id,
            'start_at' => $slot->start_at->format('Y-m-d H:i:s.u'),
            'end_at' => $slot->end_at->format('Y-m-d H:i:s.u'),
            'created_by_user_id' => $slot->created_by_user_id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $listing;
    }

    private function url(array $fixture, array $filters = []): string
    {
        return '/api/listings?'.http_build_query(array_replace([
            'start_at' => $fixture['payload']['start_at'],
            'end_at' => $fixture['payload']['end_at'],
        ], $filters));
    }
}
