<?php

namespace Tests\Support;

use App\Models\Booking;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class BookingFixture
{
    /** @return array<string, mixed> */
    public static function make(): array
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

    /** @return array<string, mixed> */
    public static function requested(TestCase $test): array
    {
        $fixture = self::make();
        $response = $test->actingAs($fixture['owner'])
            ->withHeader('Idempotency-Key', $fixture['idempotency_key'])
            ->postJson('/api/bookings', $fixture['payload'])->assertCreated();
        $fixture['booking'] = Booking::query()->findOrFail($response->json('id'));

        return $fixture;
    }
}
