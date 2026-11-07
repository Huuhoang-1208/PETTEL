<?php

namespace Tests\Feature;

use App\Models\Listing;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\ListingFixture;
use Tests\TestCase;

class PublicListingTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_sees_the_same_public_fields_in_list_and_detail(): void
    {
        $fixture = ListingFixture::make();
        $id = (string) $fixture['active_listing_id'];

        $list = $this->getJson('/api/listings')->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $id)
            ->assertJsonStructure(['data', 'links', 'meta']);
        $detail = $this->getJson("/api/listings/{$id}")->assertOk();

        $listItem = $list->json('data.0');
        $this->assertSame([], $listItem['match_reasons']);
        unset($listItem['match_reasons']);
        $this->assertSame($listItem, $detail->json('data'));
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
            'rating_average',
            'review_count',
        ], array_keys($detail->json('data')));
        $detail->assertJsonPath('data.price_vnd', 50000)
            ->assertJsonPath('data.price_total_vnd', null)
            ->assertJsonMissingPath('data.pet')
            ->assertJsonMissingPath('data.bookings')
            ->assertJsonMissingPath('data.caregiver_profile_id')
            ->assertJsonMissingPath('data.match_reasons');
        $this->assertStringNotContainsString('No chicken', $detail->getContent());
    }

    /** @return array<string, array{string}> */
    public static function hiddenListingStates(): array
    {
        return [
            'draft' => ['DRAFT'],
            'pending review' => ['PENDING_REVIEW'],
            'suspended' => ['SUSPENDED'],
            'archived' => ['ARCHIVED'],
            'soft deleted' => ['SOFT_DELETED'],
        ];
    }

    #[DataProvider('hiddenListingStates')]
    public function test_hidden_listing_is_absent_from_list_and_detail(string $state): void
    {
        $fixture = ListingFixture::make();
        $listing = Listing::query()->findOrFail($fixture['active_listing_id']);

        if ($state === 'SOFT_DELETED') {
            $listing->delete();
        } else {
            $listing->forceFill([
                'status' => $state,
                'suspension_source' => $state === 'SUSPENDED' ? 'ADMIN' : null,
            ])->save();
        }

        $this->assertNotPublic((string) $listing->id);
    }

    public function test_soft_deleted_profile_hides_its_active_listing(): void
    {
        $fixture = ListingFixture::make();
        $fixture['caregiver']->caregiverProfile()->firstOrFail()->delete();

        $this->assertNotPublic((string) $fixture['active_listing_id']);
    }

    /** @return array<string, array{string}> */
    public static function inactiveCaregiverStates(): array
    {
        return [
            'suspended' => ['SUSPENDED'],
            'deletion pending' => ['DELETION_PENDING'],
            'deactivated' => ['DEACTIVATED'],
        ];
    }

    #[DataProvider('inactiveCaregiverStates')]
    public function test_inactive_caregiver_hides_the_active_listing(string $status): void
    {
        $fixture = ListingFixture::make();
        $fixture['caregiver']->forceFill(['status' => $status])->save();

        $this->assertNotPublic((string) $fixture['active_listing_id']);
    }

    public function test_removing_caregiver_role_hides_the_active_listing(): void
    {
        $fixture = ListingFixture::make();
        $fixture['caregiver']->roles()->where('role', 'CAREGIVER')->delete();

        $this->assertNotPublic((string) $fixture['active_listing_id']);
    }

    public function test_owner_and_admin_cannot_view_draft_via_public_detail(): void
    {
        $fixture = ListingFixture::make();
        $admin = User::factory()->create();
        DB::table('user_roles')->insert([
            'user_id' => $admin->id,
            'role' => 'ADMIN',
            'assigned_at' => now(),
        ]);

        foreach ([$fixture['owner'], $fixture['caregiver'], $admin] as $actor) {
            $this->actingAs($actor)
                ->getJson("/api/listings/{$fixture['draft_listing_id']}")
                ->assertNotFound()->assertJsonPath('error.code', 'NOT_FOUND');
        }
    }

    public function test_unknown_id_returns_the_same_not_found_error(): void
    {
        $this->getJson('/api/listings/999999')->assertNotFound()
            ->assertJsonPath('error.code', 'NOT_FOUND')
            ->assertJsonPath('error.message', 'Listing not found.');
    }

    private function assertNotPublic(string $id): void
    {
        $this->getJson('/api/listings')->assertOk()->assertJsonCount(0, 'data');
        $this->getJson("/api/listings/{$id}")->assertNotFound()
            ->assertJsonPath('error.code', 'NOT_FOUND')
            ->assertJsonPath('error.message', 'Listing not found.');
    }
}
