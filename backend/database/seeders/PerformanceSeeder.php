<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use RuntimeException;

class PerformanceSeeder extends Seeder
{
    public function run(): void
    {
        if (app()->environment() !== 'performance' || DB::connection()->getDatabaseName() !== 'pettel_perf') {
            throw new RuntimeException('PerformanceSeeder only runs against the isolated pettel_perf database.');
        }

        $now = now()->utc()->format('Y-m-d H:i:s.u');
        $ownerId = DB::table('users')->insertGetId([
            'name' => 'Benchmark Owner',
            'email' => 'owner@performance.invalid',
            'password' => Hash::make('performance-only'),
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $caregiverId = DB::table('users')->insertGetId([
            'name' => 'Benchmark Caregiver',
            'email' => 'caregiver@performance.invalid',
            'password' => Hash::make('performance-only'),
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        DB::table('user_roles')->insert([
            ['user_id' => $ownerId, 'role' => 'OWNER', 'assigned_at' => $now],
            ['user_id' => $caregiverId, 'role' => 'CAREGIVER', 'assigned_at' => $now],
        ]);
        $profileId = DB::table('caregiver_profiles')->insertGetId([
            'user_id' => $caregiverId,
            'type' => 'INDIVIDUAL',
            'display_name' => 'Benchmark Caregiver',
            'bio' => 'Synthetic performance fixture.',
            'area_code' => 'DN_CENTER',
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $petId = DB::table('pets')->insertGetId([
            'user_id' => $ownerId,
            'name' => 'Benchmark Pet',
            'species' => 'DOG',
            'size' => 'SMALL',
            'sex' => 'UNKNOWN',
            'status' => 'ACTIVE',
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $mediaId = DB::table('media_objects')->insertGetId([
            'owner_user_id' => $caregiverId,
            'object_key' => 'performance/shared.webp',
            'mime_type' => 'image/webp',
            'byte_size' => 128,
            'sha256' => hash('sha256', 'performance-fixture'),
            'visibility' => 'PUBLIC_ELIGIBLE',
            'state' => 'READY',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $rules = json_encode(['schema_version' => 1, 'species' => ['DOG'], 'sizes' => ['SMALL']], JSON_THROW_ON_ERROR);
        $listingIds = [];
        for ($first = 1; $first <= 5000; $first += 250) {
            $rows = [];
            for ($offset = $first; $offset < min($first + 250, 5001); $offset++) {
                $rows[] = [
                    'caregiver_profile_id' => $profileId,
                    'title' => "Benchmark daycare {$offset}",
                    'description' => 'Synthetic public listing for isolated performance measurement.',
                    'service_type' => 'DAYCARE',
                    'area_code' => ['DN_CENTER', 'DN_NORTH', 'DN_SOUTH', 'DN_WEST', 'DN_EAST'][$offset % 5],
                    'price_vnd' => 50000 + ($offset % 100) * 1000,
                    'price_unit' => 'HOUR',
                    'capacity' => 1,
                    'acceptance_rules_json' => $rules,
                    'status' => 'ACTIVE',
                    'published_at' => $now,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
            DB::table('listings')->insert($rows);
        }

        $listingIds = DB::table('listings')->orderBy('id')->pluck('id')->all();
        foreach (array_chunk($listingIds, 500) as $chunk) {
            $media = [];
            $slots = [];
            foreach ($chunk as $listingId) {
                $media[] = ['listing_id' => $listingId, 'media_object_id' => $mediaId, 'sort_order' => 0];
                $slots[] = [
                    'listing_id' => $listingId,
                    'start_at' => now()->addDays(2)->startOfDay()->utc()->format('Y-m-d H:i:s.u'),
                    'end_at' => now()->addDays(2)->endOfDay()->utc()->format('Y-m-d H:i:s.u'),
                    'created_by_user_id' => $caregiverId,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
            DB::table('listing_media')->insert($media);
            DB::table('availability_slots')->insert($slots);
        }

        $listing = DB::table('listings')->where('id', $listingIds[0])->first();
        $bookingIds = [];
        for ($first = 1; $first <= 10000; $first += 250) {
            $rows = [];
            for ($number = $first; $number < min($first + 250, 10001); $number++) {
                $rows[] = [
                    'owner_id' => $ownerId,
                    'pet_id' => $petId,
                    'listing_id' => $listing->id,
                    'caregiver_user_id' => $caregiverId,
                    'start_at' => now()->subDays(20)->addMinutes($number)->utc()->format('Y-m-d H:i:s.u'),
                    'end_at' => now()->subDays(20)->addMinutes($number + 60)->utc()->format('Y-m-d H:i:s.u'),
                    'status' => 'COMPLETED',
                    'price_snapshot_vnd' => 50000,
                    'price_unit' => 'HOUR',
                    'price_quantity' => 1,
                    'pet_snapshot_json' => json_encode(['name' => 'Benchmark Pet', 'species' => 'DOG', 'size' => 'SMALL'], JSON_THROW_ON_ERROR),
                    'listing_snapshot_json' => json_encode(['title' => $listing->title, 'service_type' => 'DAYCARE'], JSON_THROW_ON_ERROR),
                    'policy_snapshot_json' => json_encode(['version' => 1], JSON_THROW_ON_ERROR),
                    'request_idempotency_key' => "performance-{$number}",
                    'request_payload_hash' => hash('sha256', "performance-{$number}"),
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
            DB::table('bookings')->insert($rows);
        }

        $bookingIds = DB::table('bookings')->orderBy('id')->pluck('id')->all();
        foreach (array_chunk($bookingIds, 500) as $chunk) {
            $reviews = [];
            foreach ($chunk as $id) {
                $reviews[] = [
                    'booking_id' => $id,
                    'owner_id' => $ownerId,
                    'listing_id' => $listing->id,
                    'rating' => 5,
                    'body' => 'Synthetic review.',
                    'status' => 'VISIBLE',
                    'is_demo' => false,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
            DB::table('reviews')->insert($reviews);
        }
    }
}
