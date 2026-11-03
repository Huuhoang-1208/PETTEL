<?php

namespace Tests\Integration;

use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Tests\Support\BookingFixture;
use Tests\TestCase;

class ListingSuspensionSourceMigrationTest extends TestCase
{
    public function test_upgrade_backfills_legacy_rows_and_rollback_reapply_preserves_business_rows(): void
    {
        if (! app()->environment('testing') || DB::connection()->getDatabaseName() !== 'pettel_test') {
            throw new RuntimeException('This migration test requires the isolated pettel_test database.');
        }

        Artisan::call('migrate:fresh', ['--seed' => true, '--force' => true]);
        $fixture = BookingFixture::make();
        $listingId = $fixture['active_listing_id'];
        $activeListingId = $fixture['draft_listing_id'];

        DB::table('bookings')->insert([
            'owner_id' => $fixture['owner']->id,
            'pet_id' => $fixture['pet']->id,
            'listing_id' => $listingId,
            'caregiver_user_id' => $fixture['caregiver']->id,
            'start_at' => $fixture['start_at']->format('Y-m-d H:i:s.u'),
            'end_at' => $fixture['end_at']->format('Y-m-d H:i:s.u'),
            'status' => 'CONFIRMED', 'price_snapshot_vnd' => 100000,
            'price_unit' => 'HOUR', 'price_quantity' => 2,
            'pricing_version' => 1, 'cancellation_policy_version' => 1,
            'pet_snapshot_json' => '{}', 'listing_snapshot_json' => '{}', 'policy_snapshot_json' => '{}',
            'request_idempotency_key' => 'migration-preserves-booking', 'request_payload_hash' => str_repeat('b', 64),
            'version' => 1, 'created_at' => now(), 'updated_at' => now(),
        ]);
        $bookingId = DB::table('bookings')->where('request_idempotency_key', 'migration-preserves-booking')->value('id');

        $this->assertSame(0, Artisan::call('migrate:rollback', ['--step' => 1, '--force' => true]));
        $this->assertFalse(Schema::hasColumn('listings', 'suspension_source'));
        DB::table('listings')->where('id', $listingId)->update(['status' => 'SUSPENDED']);
        DB::table('listings')->where('id', $activeListingId)->update(['status' => 'ACTIVE']);

        $this->assertSame(0, Artisan::call('migrate', ['--force' => true]));
        $this->assertDatabaseHas('listings', ['id' => $listingId, 'status' => 'SUSPENDED', 'suspension_source' => 'ADMIN']);
        $this->assertDatabaseHas('listings', ['id' => $activeListingId, 'status' => 'ACTIVE', 'suspension_source' => null]);
        $this->assertDatabaseHas('bookings', ['id' => $bookingId, 'status' => 'CONFIRMED']);

        try {
            DB::table('listings')->where('id', $listingId)->update(['suspension_source' => null]);
            $this->fail('The database accepted SUSPENDED without a source.');
        } catch (QueryException) {
            $this->assertDatabaseHas('listings', ['id' => $listingId, 'status' => 'SUSPENDED', 'suspension_source' => 'ADMIN']);
        }

        try {
            DB::table('listings')->where('id', $listingId)->update(['status' => 'ACTIVE']);
            $this->fail('The database accepted a non-suspended listing with a source.');
        } catch (QueryException) {
            $this->assertDatabaseHas('listings', ['id' => $listingId, 'status' => 'SUSPENDED', 'suspension_source' => 'ADMIN']);
        }

        $this->assertSame(0, Artisan::call('migrate:rollback', ['--step' => 1, '--force' => true]));
        $this->assertDatabaseHas('listings', ['id' => $listingId, 'status' => 'SUSPENDED']);
        $this->assertDatabaseHas('listings', ['id' => $activeListingId, 'status' => 'ACTIVE']);
        $this->assertDatabaseHas('bookings', ['id' => $bookingId, 'status' => 'CONFIRMED']);
        $this->assertSame(0, Artisan::call('migrate', ['--force' => true]));
        $this->assertDatabaseHas('listings', ['id' => $listingId, 'status' => 'SUSPENDED', 'suspension_source' => 'ADMIN']);
        $this->assertDatabaseHas('listings', ['id' => $activeListingId, 'status' => 'ACTIVE', 'suspension_source' => null]);
    }
}
