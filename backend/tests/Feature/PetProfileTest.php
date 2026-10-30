<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PetProfileTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_can_manage_archive_and_soft_delete_pets(): void
    {
        $owner = $this->userWithRole('OWNER');

        $created = $this->actingAs($owner)->postJson('/api/pets', [
            'name' => 'Milo',
            'species' => 'DOG',
        ]);

        $created->assertCreated()
            ->assertJsonPath('data.name', 'Milo')
            ->assertJsonPath('data.species', 'DOG')
            ->assertJsonPath('data.status', 'ACTIVE');

        $petId = $created->json('data.id');

        $this->getJson('/api/pets')->assertOk()->assertJsonCount(1, 'data');
        $this->patchJson("/api/pets/{$petId}", ['name' => 'Milo Jr.'])
            ->assertOk()
            ->assertJsonPath('data.name', 'Milo Jr.');
        $this->patchJson("/api/pets/{$petId}/archive", [])
            ->assertOk()
            ->assertJsonPath('data.status', 'ARCHIVED');
        $this->patchJson("/api/pets/{$petId}/activate", [])
            ->assertOk()
            ->assertJsonPath('data.status', 'ACTIVE');
        $this->deleteJson("/api/pets/{$petId}")->assertNoContent();

        $this->assertSoftDeleted('pets', ['id' => $petId]);
        $this->assertDatabaseHas('pets', ['id' => $petId, 'name' => 'Milo Jr.']);
        $this->getJson('/api/pets')->assertOk()->assertJsonCount(0, 'data');
    }

    public function test_pet_routes_require_an_active_owner_and_hide_other_owners_pets(): void
    {
        $this->getJson('/api/pets')->assertUnauthorized();

        $caregiver = $this->userWithRole('CAREGIVER');
        $this->actingAs($caregiver)->postJson('/api/pets', [
            'name' => 'Milo',
            'species' => 'DOG',
        ])->assertForbidden();

        $owner = $this->userWithRole('OWNER');
        $otherOwner = $this->userWithRole('OWNER');
        $pet = $otherOwner->pets()->create(['name' => 'Luna', 'species' => 'CAT']);

        $this->actingAs($owner)->getJson("/api/pets/{$pet->id}")->assertNotFound();
        $this->patchJson("/api/pets/{$pet->id}", ['name' => 'Stolen'])->assertNotFound();
        $this->deleteJson("/api/pets/{$pet->id}")->assertNotFound();
        $this->assertDatabaseHas('pets', ['id' => $pet->id, 'name' => 'Luna', 'deleted_at' => null]);

        $inactiveOwner = $this->userWithRole('OWNER', ['status' => 'DELETION_PENDING']);
        $this->actingAs($inactiveOwner)->getJson('/api/pets')->assertForbidden();
    }

    public function test_pet_input_is_normalized_and_validated(): void
    {
        $owner = $this->userWithRole('OWNER');

        $this->actingAs($owner)->postJson('/api/pets', [
            'name' => "  Cafe\u{0301}  ",
            'species' => 'DOG',
            'birth_date' => now()->subYear()->toDateString(),
        ])->assertCreated()->assertJsonPath('data.name', 'Café');

        $this->postJson('/api/pets', [
            'name' => 'Future pet',
            'species' => 'BIRD',
            'birth_date' => now()->addDay()->toDateString(),
            'diet_notes' => str_repeat('x', 2001),
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['species', 'birth_date', 'diet_notes']);

        $this->assertDatabaseCount('pets', 1);
    }

    public function test_photo_must_be_ready_and_owned_by_the_pet_owner(): void
    {
        $owner = $this->userWithRole('OWNER');
        $otherOwner = $this->userWithRole('OWNER');
        $ownedMedia = $this->mediaFor($owner);
        $foreignMedia = $this->mediaFor($otherOwner);

        $this->actingAs($owner)->postJson('/api/pets', [
            'name' => 'Milo',
            'species' => 'DOG',
            'photo_media_id' => $ownedMedia,
        ])->assertCreated()->assertJsonPath('data.photo_media_id', (string) $ownedMedia);

        $this->postJson('/api/pets', [
            'name' => 'Luna',
            'species' => 'CAT',
            'photo_media_id' => $foreignMedia,
        ])->assertUnprocessable()->assertJsonValidationErrors('photo_media_id');

        $pendingMedia = $this->mediaFor($owner, 'PENDING_DELETE');
        $this->postJson('/api/pets', [
            'name' => 'Pending photo',
            'species' => 'DOG',
            'photo_media_id' => $pendingMedia,
        ])->assertUnprocessable()->assertJsonValidationErrors('photo_media_id');
    }

    public function test_booking_snapshot_is_a_versioned_allowlist_of_pet_care_data(): void
    {
        $owner = $this->userWithRole('OWNER');
        $pet = $owner->pets()->create([
            'name' => 'Milo',
            'species' => 'DOG',
            'size' => 'SMALL',
            'diet_notes' => 'No chicken',
            'special_needs' => 'Medication at night',
        ]);

        $snapshot = $pet->snapshotForBooking();

        $this->assertSame(1, $snapshot['schema_version']);
        $this->assertSame((string) $pet->id, $snapshot['pet_id']);
        $this->assertSame('No chicken', $snapshot['diet_notes']);
        $this->assertArrayNotHasKey('user_id', $snapshot);
        $this->assertArrayNotHasKey('photo_media_id', $snapshot);

        $this->actingAs($owner)->patchJson("/api/pets/{$pet->id}", [
            'diet_notes' => 'No fish',
        ])->assertOk();

        $this->assertSame('No chicken', $snapshot['diet_notes']);
        $this->assertSame('No fish', $pet->fresh()->snapshotForBooking()['diet_notes']);
    }

    /** @param array<string, mixed> $attributes */
    private function userWithRole(string $role, array $attributes = []): User
    {
        $status = $attributes['status'] ?? null;
        unset($attributes['status']);

        $user = User::factory()->create($attributes);

        if ($status !== null) {
            $user->status = $status;
            $user->save();
        }

        DB::table('user_roles')->insert([
            'user_id' => $user->id,
            'role' => $role,
            'assigned_at' => now(),
        ]);

        return $user;
    }

    private function mediaFor(User $owner, string $state = 'READY'): int
    {
        return DB::table('media_objects')->insertGetId([
            'owner_user_id' => $owner->id,
            'object_key' => "test/{$owner->id}/".uniqid(),
            'mime_type' => 'image/png',
            'byte_size' => 512,
            'sha256' => str_repeat('a', 64),
            'visibility' => 'PRIVATE',
            'state' => $state,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
