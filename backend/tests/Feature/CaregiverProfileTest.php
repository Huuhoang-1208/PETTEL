<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class CaregiverProfileTest extends TestCase
{
    use RefreshDatabase;

    public function test_caregiver_can_create_read_and_update_their_profile(): void
    {
        $user = $this->userWithRole();
        $payload = $this->payload();
        $payload['display_name'] = "  Cafe\u{0301}  ";

        $this->actingAs($user)->postJson('/api/caregiver/profile', $payload)
            ->assertCreated()->assertJsonPath('data.display_name', 'Café');
        $this->getJson('/api/caregiver/profile')->assertOk()
            ->assertJsonMissingPath('data.user_id');
        $this->patchJson('/api/caregiver/profile', ['bio' => null])
            ->assertOk()->assertJsonPath('data.bio', null)
            ->assertJsonPath('data.type', 'INDIVIDUAL');

        $profile = $user->caregiverProfile()->firstOrFail();
        $listingId = DB::table('listings')->insertGetId([
            'caregiver_profile_id' => $profile->id,
            'title' => 'Daycare',
            'description' => 'Demo',
            'service_type' => 'DAYCARE',
            'area_code' => 'DN_CENTER',
            'price_vnd' => 50000,
            'price_unit' => 'HOUR',
            'acceptance_rules_json' => json_encode([
                'schema_version' => 1,
                'species' => ['DOG'],
                'sizes' => ['SMALL'],
            ], JSON_THROW_ON_ERROR),
        ]);
        $listing = $profile->listings()->findOrFail($listingId);
        $this->assertSame($user->id, $listing->caregiverProfile->user->id);
    }

    public function test_caregiver_cannot_access_or_update_another_profile(): void
    {
        $first = $this->userWithRole();
        $second = $this->userWithRole();
        $this->actingAs($first)->postJson('/api/caregiver/profile', $this->payload())
            ->assertCreated();

        $this->actingAs($second)->getJson('/api/caregiver/profile')->assertNotFound();
        $this->patchJson('/api/caregiver/profile', ['display_name' => 'Changed'])
            ->assertNotFound();
        $this->assertDatabaseHas('caregiver_profiles', [
            'user_id' => $first->id,
            'display_name' => 'Demo Caregiver',
        ]);
    }

    public function test_routes_require_an_active_caregiver(): void
    {
        $this->getJson('/api/caregiver/profile')->assertUnauthorized();
        $this->actingAs($this->userWithRole('OWNER'))
            ->postJson('/api/caregiver/profile', $this->payload())->assertForbidden();

        foreach (['DELETION_PENDING', 'DEACTIVATED'] as $status) {
            $this->actingAs($this->userWithRole('CAREGIVER', $status))
                ->getJson('/api/caregiver/profile')->assertForbidden();
        }
    }

    public function test_duplicate_profile_is_rejected_even_after_soft_delete(): void
    {
        $user = $this->userWithRole();
        $this->actingAs($user)->postJson('/api/caregiver/profile', $this->payload())
            ->assertCreated();
        $this->postJson('/api/caregiver/profile', $this->payload())
            ->assertConflict()->assertJsonPath('error.code', 'INVALID_STATE');

        $user->caregiverProfile()->firstOrFail()->delete();

        $this->postJson('/api/caregiver/profile', $this->payload())->assertConflict();
        $this->getJson('/api/caregiver/profile')->assertNotFound();
        $this->patchJson('/api/caregiver/profile', ['bio' => 'New'])->assertNotFound();
        $this->assertDatabaseCount('caregiver_profiles', 1);
    }

    public function test_invalid_data_and_system_fields_are_rejected(): void
    {
        $this->actingAs($this->userWithRole());

        foreach ([
            ['type' => 'OTHER'],
            ['display_name' => '   '],
            ['display_name' => str_repeat('x', 101)],
            ['bio' => str_repeat('x', 2001)],
            ['area_code' => 'UNKNOWN'],
        ] as $invalid) {
            $this->postJson('/api/caregiver/profile', array_replace($this->payload(), $invalid))
                ->assertUnprocessable()->assertJsonPath('error.code', 'VALIDATION_ERROR');
        }

        foreach (['id', 'user_id', 'caregiver_profile_id', 'status', 'capacity', 'version', 'published_at', 'created_at', 'updated_at', 'deleted_at'] as $field) {
            $this->postJson('/api/caregiver/profile', $this->payload() + [$field => null])
                ->assertUnprocessable()->assertJsonStructure(['error' => ['details' => [$field]]]);
        }

        $this->assertDatabaseCount('caregiver_profiles', 0);
        $this->postJson('/api/caregiver/profile', $this->payload())->assertCreated();
        $this->patchJson('/api/caregiver/profile', ['user_id' => null])
            ->assertUnprocessable();
        $this->patchJson('/api/caregiver/profile', ['display_name' => null])
            ->assertUnprocessable();
        $this->assertDatabaseCount('caregiver_profiles', 1);
    }

    /** @return array<string, string> */
    private function payload(): array
    {
        return [
            'type' => 'INDIVIDUAL',
            'display_name' => 'Demo Caregiver',
            'bio' => 'Pet care',
            'area_code' => 'DN_CENTER',
        ];
    }

    private function userWithRole(string $role = 'CAREGIVER', string $status = 'ACTIVE'): User
    {
        $user = User::factory()->create();
        $user->status = $status;
        $user->save();
        DB::table('user_roles')->insert([
            'user_id' => $user->id,
            'role' => $role,
            'assigned_at' => now(),
        ]);

        return $user;
    }
}
