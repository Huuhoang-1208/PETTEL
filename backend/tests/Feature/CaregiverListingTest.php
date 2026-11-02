<?php

namespace Tests\Feature;

use App\Models\Listing;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class CaregiverListingTest extends TestCase
{
    use RefreshDatabase;

    public function test_caregiver_can_manage_and_soft_delete_their_draft(): void
    {
        $user = $this->caregiver();

        $created = $this->actingAs($user)->postJson('/api/caregiver/listings', $this->payload());
        $created->assertCreated()->assertJsonPath('data.status', 'DRAFT')
            ->assertJsonPath('data.capacity', 1)->assertJsonPath('data.version', 1)
            ->assertJsonPath('data.published_at', null)
            ->assertJsonPath('data.price_vnd', 150000)
            ->assertJsonMissingPath('data.caregiver_profile_id');
        $id = $created->json('data.id');
        $this->assertIsString($id);
        $this->assertDatabaseHas('listings', [
            'id' => $id,
            'caregiver_profile_id' => $user->caregiverProfile->id,
        ]);

        $this->getJson('/api/caregiver/listings')->assertOk()->assertJsonCount(1, 'data')
            ->assertJsonStructure(['data', 'links', 'meta']);
        $this->getJson("/api/caregiver/listings/{$id}")->assertOk();
        $this->patchJson("/api/caregiver/listings/{$id}", ['title' => 'Updated'])
            ->assertOk()->assertJsonPath('data.title', 'Updated')
            ->assertJsonPath('data.price_unit', 'NIGHT');
        $this->deleteJson("/api/caregiver/listings/{$id}")->assertNoContent();
        $this->assertSoftDeleted('listings', ['id' => $id]);
        $this->getJson("/api/caregiver/listings/{$id}")->assertNotFound();
        $this->patchJson("/api/caregiver/listings/{$id}", ['title' => 'Again'])->assertNotFound();
        $this->deleteJson("/api/caregiver/listings/{$id}")->assertNotFound();
        $this->getJson('/api/caregiver/listings')->assertJsonCount(0, 'data');
    }

    public function test_other_caregivers_cannot_read_update_or_delete_a_listing(): void
    {
        $owner = $this->caregiver();
        $other = $this->caregiver();
        $own = $this->draft($owner);
        $foreign = $this->draft($other);

        $this->actingAs($owner)->getJson('/api/caregiver/listings')
            ->assertOk()->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', (string) $own->id);

        foreach (['DRAFT', 'ACTIVE'] as $status) {
            $foreign->status = $status;
            $foreign->save();
            $url = "/api/caregiver/listings/{$foreign->id}";
            $this->getJson($url)->assertNotFound();
            $this->patchJson($url, ['title' => 'Stolen'])->assertNotFound();
            $this->deleteJson($url)->assertNotFound();
            $this->assertDatabaseHas('listings', [
                'id' => $foreign->id,
                'title' => 'Cat boarding',
                'deleted_at' => null,
            ]);
        }
    }

    public function test_non_draft_listings_are_readable_by_owner_but_cannot_be_changed(): void
    {
        $user = $this->caregiver();
        $this->actingAs($user);

        foreach (['PENDING_REVIEW', 'ACTIVE', 'SUSPENDED', 'ARCHIVED'] as $status) {
            $listing = $this->draft($user);
            $listing->status = $status;
            $listing->save();
            $url = "/api/caregiver/listings/{$listing->id}";

            $this->getJson($url)->assertOk()->assertJsonPath('data.status', $status);
            $this->patchJson($url, ['title' => 'Changed'])->assertConflict()
                ->assertJsonPath('error.code', 'INVALID_STATE');
            $this->deleteJson($url)->assertConflict();
            $this->assertDatabaseHas('listings', [
                'id' => $listing->id,
                'title' => 'Cat boarding',
                'status' => $status,
                'deleted_at' => null,
            ]);
        }

        $this->getJson('/api/caregiver/listings')->assertJsonCount(0, 'data');
    }

    public function test_client_cannot_write_system_fields_even_with_null_values(): void
    {
        $user = $this->caregiver();
        $listing = $this->draft($user);
        $this->actingAs($user);
        $url = "/api/caregiver/listings/{$listing->id}";

        foreach (['id', 'user_id', 'caregiver_profile_id', 'status', 'capacity', 'version', 'published_at', 'created_at', 'updated_at', 'deleted_at'] as $field) {
            $this->postJson('/api/caregiver/listings', $this->payload() + [$field => null])
                ->assertUnprocessable()->assertJsonStructure(['error' => ['details' => [$field]]]);
            $this->patchJson($url, [$field => null])->assertUnprocessable();
        }

        $this->patchJson($url, ['status' => 'ACTIVE'])->assertUnprocessable();
        $this->patchJson($url, ['caregiver_profile_id' => '999'])->assertUnprocessable();
        $this->assertDatabaseCount('listings', 1);
        $this->assertDatabaseHas('listings', [
            'id' => $listing->id,
            'status' => 'DRAFT',
            'caregiver_profile_id' => $user->caregiverProfile->id,
        ]);
    }

    public function test_routes_require_role_active_account_and_an_existing_profile(): void
    {
        $this->getJson('/api/caregiver/listings')->assertUnauthorized();
        $user = $this->caregiver();
        DB::table('user_roles')->where('user_id', $user->id)->delete();
        $this->actingAs($user)->postJson('/api/caregiver/listings', $this->payload())
            ->assertForbidden();

        foreach (['DELETION_PENDING', 'DEACTIVATED'] as $status) {
            $inactive = $this->caregiver();
            $inactive->status = $status;
            $inactive->save();
            $this->actingAs($inactive)->getJson('/api/caregiver/listings')->assertForbidden();
        }

        $missing = $this->caregiver(false);
        $this->actingAs($missing)->postJson('/api/caregiver/listings', $this->payload())
            ->assertConflict()->assertJsonPath('error.code', 'INVALID_STATE');
        $this->getJson('/api/caregiver/listings')->assertOk()->assertJsonCount(0, 'data');

        $deleted = $this->caregiver();
        $listing = $this->draft($deleted);
        $deleted->caregiverProfile()->firstOrFail()->delete();
        $this->actingAs($deleted)->getJson('/api/caregiver/listings')->assertJsonCount(0, 'data');
        $this->getJson("/api/caregiver/listings/{$listing->id}")->assertNotFound();
        $this->patchJson("/api/caregiver/listings/{$listing->id}", ['title' => 'No'])->assertNotFound();
        $this->deleteJson("/api/caregiver/listings/{$listing->id}")->assertNotFound();
        $this->postJson('/api/caregiver/listings', $this->payload())->assertConflict();
    }

    public function test_partial_updates_validate_the_resulting_service_pair_and_json(): void
    {
        $user = $this->caregiver();
        $listing = $this->draft($user);
        $this->actingAs($user);
        $url = "/api/caregiver/listings/{$listing->id}";

        $this->patchJson($url, ['service_type' => 'DAYCARE'])->assertUnprocessable();
        $this->patchJson($url, ['price_unit' => 'HOUR'])->assertUnprocessable();
        $this->patchJson($url, ['service_type' => 'DAYCARE', 'price_unit' => 'HOUR'])
            ->assertOk()->assertJsonPath('data.price_unit', 'HOUR');

        foreach ([
            ['price_vnd' => 0],
            ['price_vnd' => 1.5],
            ['price_vnd' => '50000'],
            ['title' => null],
            ['title' => '   '],
            ['acceptance_rules_json' => []],
            ['acceptance_rules_json' => ['schema_version' => '1', 'species' => ['CAT'], 'sizes' => ['SMALL']]],
            ['acceptance_rules_json' => ['schema_version' => 1, 'species' => ['BIRD'], 'sizes' => ['SMALL']]],
            ['acceptance_rules_json' => ['schema_version' => 1, 'species' => ['CAT']]],
        ] as $invalid) {
            $this->patchJson($url, $invalid)->assertUnprocessable()
                ->assertJsonPath('error.code', 'VALIDATION_ERROR');
        }

        $this->assertDatabaseHas('listings', [
            'id' => $listing->id,
            'service_type' => 'DAYCARE',
            'price_unit' => 'HOUR',
            'price_vnd' => 150000,
            'title' => 'Cat boarding',
        ]);
    }

    /** @return array<string, mixed> */
    private function payload(): array
    {
        return [
            'title' => 'Cat boarding',
            'description' => 'Indoor care',
            'service_type' => 'BOARDING',
            'area_code' => 'DN_CENTER',
            'price_vnd' => 150000,
            'price_unit' => 'NIGHT',
            'acceptance_rules_json' => [
                'schema_version' => 1,
                'species' => ['CAT'],
                'sizes' => ['SMALL', 'MEDIUM'],
            ],
        ];
    }

    private function caregiver(bool $withProfile = true): User
    {
        $user = User::factory()->create();
        DB::table('user_roles')->insert([
            'user_id' => $user->id,
            'role' => 'CAREGIVER',
            'assigned_at' => now(),
        ]);

        if ($withProfile) {
            $user->caregiverProfile()->create([
                'type' => 'INDIVIDUAL',
                'display_name' => 'Demo',
                'area_code' => 'DN_CENTER',
            ]);
        }

        return $user;
    }

    private function draft(User $user): Listing
    {
        return $user->caregiverProfile()->firstOrFail()->listings()->create($this->payload());
    }
}
