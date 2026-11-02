<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class BookingCreationTest extends TestCase
{
    use RefreshDatabase;

    public function test_booking_fixture_is_ready(): void
    {
        $fixture = $this->bookingFixture();

        $this->assertTrue($fixture['owner']->hasRole('OWNER'));
        $this->assertTrue($fixture['caregiver']->hasRole('CAREGIVER'));
        $this->assertSame('ACTIVE', $fixture['owner']->status);
        $this->assertSame('ACTIVE', $fixture['caregiver']->status);
        $this->assertNotSame($fixture['owner']->id, $fixture['caregiver']->id);
        $this->assertSame('ACTIVE', $fixture['pet']->status);
        $this->assertSame($fixture['owner']->id, $fixture['pet']->user_id);
        $this->assertNotSame($fixture['owner']->id, $fixture['foreign_pet']->user_id);

        $this->assertDatabaseHas('listings', [
            'id' => $fixture['active_listing_id'],
            'status' => 'ACTIVE',
            'price_vnd' => 50000,
        ]);
        $this->assertDatabaseHas('listings', [
            'id' => $fixture['draft_listing_id'],
            'status' => 'DRAFT',
        ]);

        $this->assertTrue($fixture['start_at']->isFuture());

        foreach ([$fixture['active_listing_id'], $fixture['draft_listing_id']] as $listingId) {
            $this->assertTrue(DB::table('availability_slots')
                ->where('listing_id', $listingId)
                ->where('start_at', '<=', $fixture['start_at'])
                ->where('end_at', '>=', $fixture['end_at'])
                ->whereNull('deleted_at')
                ->exists());
        }

        $snapshot = $fixture['pet']->snapshotForBooking();
        $this->assertSame(1, $snapshot['schema_version']);
        $this->assertSame('No chicken', $snapshot['diet_notes']);
        $this->assertArrayNotHasKey('user_id', $snapshot);

        $this->actingAs($fixture['owner'], 'web')
            ->getJson('/api/user')
            ->assertOk();

        $this->assertDatabaseCount('bookings', 0);
    }

    /** @return array<string, mixed> */
    private function bookingFixture(): array
    {
        $owner = User::factory()->create();
        $otherOwner = User::factory()->create();
        $caregiver = User::factory()->create();

        foreach ([
            [$owner, 'OWNER'],
            [$otherOwner, 'OWNER'],
            [$caregiver, 'OWNER'],
            [$caregiver, 'CAREGIVER'],
        ] as [$user, $role]) {
            DB::table('user_roles')->insert([
                'user_id' => $user->id,
                'role' => $role,
                'assigned_at' => now(),
            ]);
        }

        $pet = $owner->pets()->create([
            'name' => 'Mochi',
            'species' => 'DOG',
            'size' => 'SMALL',
            'diet_notes' => 'No chicken',
        ])->refresh();

        $foreignPet = $otherOwner->pets()->create([
            'name' => 'Luna',
            'species' => 'DOG',
            'size' => 'SMALL',
        ])->refresh();

        $profileId = DB::table('caregiver_profiles')->insertGetId([
            'user_id' => $caregiver->id,
            'type' => 'INDIVIDUAL',
            'display_name' => 'Test Pet Care',
            'area_code' => 'DN_CENTER',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // P0 contract version 1, agreed on 02/11/2026.
        $rules = [
            'schema_version' => 1,
            'species' => ['DOG'],
            'sizes' => ['SMALL'],
        ];

        $listingData = [
            'caregiver_profile_id' => $profileId,
            'title' => 'Test daycare',
            'description' => 'Booking test fixture',
            'service_type' => 'DAYCARE',
            'area_code' => 'DN_CENTER',
            'price_vnd' => 50000,
            'price_unit' => 'HOUR',
            'capacity' => 1,
            'acceptance_rules_json' => json_encode($rules, JSON_THROW_ON_ERROR),
            'created_at' => now(),
            'updated_at' => now(),
        ];

        $activeListingId = DB::table('listings')->insertGetId([
            ...$listingData,
            'status' => 'ACTIVE',
            'published_at' => now(),
        ]);

        $draftListingId = DB::table('listings')->insertGetId([
            ...$listingData,
            'status' => 'DRAFT',
            'published_at' => null,
        ]);

        $start = now('Asia/Ho_Chi_Minh')->addDays(2)->setTime(8, 0)->utc();
        $end = $start->copy()->addMinutes(130);

        // Give both listings valid slots so the negative case isolates status.
        foreach ([$activeListingId, $draftListingId] as $listingId) {
            DB::table('availability_slots')->insert([
                'listing_id' => $listingId,
                'start_at' => $start->copy()->subHour(),
                'end_at' => $end->copy()->addHour(),
                'created_by_user_id' => $caregiver->id,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        return [
            'owner' => $owner,
            'caregiver' => $caregiver,
            'pet' => $pet,
            'foreign_pet' => $foreignPet,
            'active_listing_id' => $activeListingId,
            'draft_listing_id' => $draftListingId,
            'start_at' => $start,
            'end_at' => $end,
            'idempotency_key' => 'booking-test-001',
            'payload' => [
                'pet_id' => (string) $pet->id,
                'listing_id' => (string) $activeListingId,
                'start_at' => $start->toIso8601String(),
                'end_at' => $end->toIso8601String(),
                'instructions' => 'Feed at 09:00 Vietnam time',
            ],
        ];
    }
}
