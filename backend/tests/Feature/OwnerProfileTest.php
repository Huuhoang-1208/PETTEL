<?php

namespace Tests\Feature;

use App\Models\MediaObject;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OwnerProfileTest extends TestCase
{
    use RefreshDatabase;

    public function test_profile_is_private_and_changes_only_the_current_user(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $originalEmail = $owner->email;
        $this->getJson('/api/v1/owner-profile')->assertUnauthorized();
        $this->actingAs($owner)->patchJson('/api/v1/owner-profile', [
            'name' => 'Chủ nuôi mới', 'bio' => 'Mình yêu thú cưng', 'phone' => '0901234567', 'area_code' => 'DN_CENTER',
            'email' => 'forged@example.com', 'status' => 'SUSPENDED', 'id' => $other->id,
        ])->assertOk()->assertJsonPath('data.name', 'Chủ nuôi mới')->assertJsonPath('data.email', $originalEmail)
            ->assertJsonPath('data.bio', 'Mình yêu thú cưng');
        $this->assertSame($originalEmail, $owner->fresh()->email);
        $this->assertSame('ACTIVE', $owner->fresh()->status);
        $this->assertNull($other->fresh()->owner_profile_json);
        $this->getJson('/api/v1/owner-profile')->assertOk()->assertJsonPath('data.phone', '0901234567');
        $this->actingAs($other, 'web')->getJson('/api/v1/owner-profile')->assertOk()->assertJsonPath('data.bio', null);
    }

    public function test_avatar_must_belong_to_the_current_user(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $media = new MediaObject(['object_key' => 'avatar-test.jpg', 'mime_type' => 'image/jpeg', 'byte_size' => 10, 'sha256' => str_repeat('a', 64)]);
        $media->owner()->associate($other);
        $media->save();
        $this->actingAs($owner)->patchJson('/api/v1/owner-profile', ['avatar_media_id' => $media->id])->assertUnprocessable();
        $this->actingAs($other)->patchJson('/api/v1/owner-profile', ['avatar_media_id' => $media->id])->assertOk()->assertJsonPath('data.avatar_media_id', (string) $media->id);
        $this->patchJson('/api/v1/owner-profile', ['area_code' => 'UNKNOWN'])->assertUnprocessable();
    }

    public function test_emergency_contacts_are_saved_privately_without_overwriting_profile(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $this->actingAs($owner)->patchJson('/api/v1/owner-profile', ['bio' => 'Giới thiệu của chủ nuôi'])->assertOk();
        $contacts = [
            'primary' => ['name' => 'Chủ nuôi', 'phone' => '0905123456'],
            'secondary' => ['name' => 'Người thân', 'phone' => '+84935123456', 'relationship' => 'Vợ/chồng', 'available_24h' => true],
            'vet' => ['name' => 'Phòng khám quen thuộc', 'phone' => '02363888999', 'doctor' => 'Bác sĩ thú y', 'address' => 'Đà Nẵng'],
        ];
        $this->patchJson('/api/v1/owner-profile', ['emergency_contacts' => $contacts])->assertOk()
            ->assertJsonPath('data.emergency_contacts', fn (array $actual): bool => $actual == $contacts)
            ->assertJsonPath('data.bio', 'Giới thiệu của chủ nuôi');
        $this->getJson('/api/v1/owner-profile')->assertOk()->assertJsonPath('data.emergency_contacts.secondary.available_24h', true);
        $this->actingAs($other, 'web')->getJson('/api/v1/owner-profile')->assertOk()->assertJsonPath('data.emergency_contacts', null);
        $this->assertNull($other->fresh()->owner_profile_json);
    }

    public function test_emergency_contacts_reject_invalid_phone_and_unexpected_fields(): void
    {
        $owner = User::factory()->create();
        $this->actingAs($owner)->patchJson('/api/v1/owner-profile', ['emergency_contacts' => ['primary' => ['name' => 'Chủ nuôi', 'phone' => 'invalid-phone']]])->assertUnprocessable();
        $this->patchJson('/api/v1/owner-profile', ['emergency_contacts' => ['primary' => ['name' => 'Chủ nuôi', 'owner_id' => 999]]])->assertUnprocessable();
        $this->assertNull($owner->fresh()->owner_profile_json);
    }
}
