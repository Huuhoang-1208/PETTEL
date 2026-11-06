<?php

namespace Tests\Feature;

use App\Models\Listing;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Support\BookingFixture;
use Tests\Support\ListingFixture;
use Tests\TestCase;

class SmartMatchTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_pet_filters_search_and_reports_each_checked_match_reason(): void
    {
        $fixture = BookingFixture::make();
        $fixture['pet']->update(['diet_notes' => 'private-care-note-token']);

        $response = $this->actingAs($fixture['owner'])
            ->getJson('/api/listings?'.http_build_query([
                'pet_id' => (string) $fixture['pet']->id,
                'species' => 'DOG',
                'size' => 'SMALL',
                'start_at' => $fixture['start_at']->toIso8601String(),
                'end_at' => $fixture['end_at']->toIso8601String(),
                'max_price_vnd' => '150000',
            ]))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', (string) $fixture['active_listing_id'])
            ->assertJsonPath('data.0.price_total_vnd', 150000)
            ->assertJsonPath('data.0.match_reasons', [
                'SPECIES_ACCEPTED',
                'SIZE_ACCEPTED',
                'AVAILABLE_FOR_REQUESTED_RANGE',
                'TOTAL_PRICE_CALCULATED',
                'WITHIN_REQUESTED_BUDGET',
            ]);

        $this->assertStringNotContainsString('private-care-note-token', $response->getContent());
        $this->assertStringNotContainsString('Mochi', $response->getContent());
    }

    public function test_guest_can_keep_using_manual_filters_without_a_pet(): void
    {
        $fixture = ListingFixture::make();

        $this->getJson('/api/listings?species=DOG&size=SMALL')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', (string) $fixture['active_listing_id'])
            ->assertJsonPath('data.0.match_reasons', ['SPECIES_ACCEPTED', 'SIZE_ACCEPTED']);
    }

    public function test_pet_search_requires_an_authenticated_owner(): void
    {
        $fixture = BookingFixture::make();

        $this->getJson('/api/listings?pet_id='.$fixture['pet']->id)
            ->assertUnauthorized()
            ->assertJsonPath('error.code', 'UNAUTHENTICATED');

        $caregiver = User::factory()->create();
        DB::table('user_roles')->insert([
            'user_id' => $caregiver->id,
            'role' => 'CAREGIVER',
            'assigned_at' => now(),
        ]);

        $this->actingAs($caregiver)
            ->getJson('/api/listings?pet_id='.$fixture['pet']->id)
            ->assertForbidden()
            ->assertJsonPath('error.code', 'FORBIDDEN');
    }

    public function test_pet_search_hides_foreign_and_soft_deleted_pets(): void
    {
        $fixture = BookingFixture::make();

        $this->actingAs($fixture['owner'])
            ->getJson('/api/listings?pet_id='.$fixture['foreign_pet']->id)
            ->assertNotFound()
            ->assertJsonPath('error.code', 'NOT_FOUND');

        $fixture['pet']->delete();

        $this->getJson('/api/listings?pet_id='.$fixture['pet']->id)
            ->assertNotFound()
            ->assertJsonPath('error.code', 'NOT_FOUND');
    }

    public function test_pet_search_rejects_inactive_accounts_archived_pets_and_missing_size(): void
    {
        $fixture = BookingFixture::make();

        $fixture['owner']->forceFill(['status' => 'SUSPENDED'])->save();
        $this->actingAs($fixture['owner'])
            ->getJson('/api/listings?pet_id='.$fixture['pet']->id)
            ->assertForbidden()
            ->assertJsonPath('error.code', 'FORBIDDEN');

        $fixture['owner']->forceFill(['status' => 'ACTIVE'])->save();
        $fixture['pet']->forceFill(['status' => 'ARCHIVED'])->save();
        $this->getJson('/api/listings?pet_id='.$fixture['pet']->id)
            ->assertConflict()
            ->assertJsonPath('error.code', 'INVALID_STATE');

        $fixture['pet']->forceFill(['status' => 'ACTIVE', 'size' => null])->save();
        $this->getJson('/api/listings?pet_id='.$fixture['pet']->id)
            ->assertUnprocessable()
            ->assertJsonPath('error.code', 'VALIDATION_ERROR')
            ->assertJsonPath('error.details.pet_id.0', 'Update the pet size before using Smart Match.');
    }

    public function test_pet_search_rejects_conflicting_manual_species_or_size_filters(): void
    {
        $fixture = BookingFixture::make();

        $this->actingAs($fixture['owner'])
            ->getJson('/api/listings?'.http_build_query([
                'pet_id' => (string) $fixture['pet']->id,
                'species' => 'CAT',
            ]))
            ->assertUnprocessable()
            ->assertJsonPath('error.code', 'VALIDATION_ERROR')
            ->assertJsonPath('error.details.species.0', 'Species must match the selected pet.');

        $this->getJson('/api/listings?'.http_build_query([
            'pet_id' => (string) $fixture['pet']->id,
            'size' => 'LARGE',
        ]))
            ->assertUnprocessable()
            ->assertJsonPath('error.details.size.0', 'Size must match the selected pet.');
    }

    public function test_smart_match_returns_no_match_summary_without_inventing_listings(): void
    {
        $fixture = ListingFixture::make();
        Listing::query()->findOrFail($fixture['active_listing_id'])->update([
            'acceptance_rules_json' => [
                'schema_version' => 1,
                'species' => ['CAT'],
                'sizes' => ['SMALL'],
            ],
        ]);

        $this->actingAs($fixture['owner'])
            ->getJson('/api/listings?pet_id='.$fixture['pet']->id)
            ->assertOk()
            ->assertJsonCount(0, 'data')
            ->assertJsonPath('match_summary.code', 'NO_MATCHING_LISTINGS')
            ->assertJsonPath('match_summary.message', 'No listings match the selected criteria.')
            ->assertJsonStructure(['links', 'meta', 'match_summary' => ['suggestions']]);
    }

    public function test_pet_match_excludes_listings_with_nonmatching_or_invalid_acceptance_rules(): void
    {
        $fixture = ListingFixture::make();
        $listing = Listing::query()->findOrFail($fixture['active_listing_id']);

        foreach ([
            ['schema_version' => 1, 'species' => ['CAT'], 'sizes' => ['SMALL']],
            ['schema_version' => 1, 'species' => ['DOG'], 'sizes' => ['LARGE']],
            ['schema_version' => '1', 'species' => ['DOG'], 'sizes' => ['SMALL']],
            ['schema_version' => 1, 'species' => ['DOG'], 'sizes' => ['SMALL'], 'unsupported' => true],
        ] as $rules) {
            $listing->forceFill(['acceptance_rules_json' => $rules])->save();

            $this->actingAs($fixture['owner'])
                ->getJson('/api/listings?pet_id='.$fixture['pet']->id)
                ->assertOk()
                ->assertJsonCount(0, 'data')
                ->assertJsonPath('match_summary.code', 'NO_MATCHING_LISTINGS');
        }
    }

    public function test_pet_search_excludes_the_owners_own_caregiver_listing(): void
    {
        $fixture = ListingFixture::make();
        $owner = $fixture['owner'];
        DB::table('user_roles')->insert([
            'user_id' => $owner->id,
            'role' => 'CAREGIVER',
            'assigned_at' => now(),
        ]);
        $profile = $owner->caregiverProfile()->create([
            'type' => 'INDIVIDUAL',
            'display_name' => 'Owner caregiver profile',
            'area_code' => 'DN_CENTER',
        ]);
        $template = Listing::query()->findOrFail($fixture['active_listing_id']);
        $ownListing = $template->replicate();
        $ownListing->caregiver_profile_id = $profile->id;
        $ownListing->save();

        $this->actingAs($owner)
            ->getJson('/api/listings?pet_id='.$fixture['pet']->id)
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', (string) $fixture['active_listing_id']);
    }

    public function test_pet_filter_is_not_accepted_until_smart_match_is_authorized(): void
    {
        $this->getJson('/api/listings?pet_id=0')
            ->assertUnprocessable()
            ->assertJsonPath('error.code', 'VALIDATION_ERROR')
            ->assertJsonStructure(['error' => ['details' => ['pet_id']]]);
    }
}
