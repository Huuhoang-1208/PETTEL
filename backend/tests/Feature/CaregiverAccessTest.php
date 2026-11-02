<?php

namespace Tests\Feature;

use App\Models\Listing;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\ListingFixture;
use Tests\TestCase;

class CaregiverAccessTest extends TestCase
{
    use RefreshDatabase;

    #[DataProvider('blockedActors')]
    public function test_all_private_routes_block_ineligible_accounts(string $actor, int $expected): void
    {
        $fixture = ListingFixture::make();
        $draft = Listing::query()->findOrFail($fixture['draft_listing_id']);
        $profile = $fixture['caregiver']->caregiverProfile()->firstOrFail();
        $beforeListing = $draft->getRawOriginal();
        $beforeProfile = $profile->getRawOriginal();

        if ($actor === 'owner') {
            $this->actingAs($fixture['owner']);
        } elseif ($actor === 'admin') {
            $admin = User::factory()->create();
            DB::table('user_roles')->insert([
                'user_id' => $admin->id,
                'role' => 'ADMIN',
                'assigned_at' => now(),
            ]);
            $this->actingAs($admin);
        } elseif ($actor !== 'guest') {
            $caregiver = $fixture['caregiver'];
            $caregiver->status = $actor;
            $caregiver->save();
            $this->actingAs($caregiver);
        }

        $profilePayload = [
            'type' => 'INDIVIDUAL',
            'display_name' => 'Not allowed',
            'area_code' => 'DN_CENTER',
        ];
        $listingUrl = "/api/caregiver/listings/{$draft->id}";

        foreach ([
            ['GET', '/api/caregiver/profile', []],
            ['POST', '/api/caregiver/profile', $profilePayload],
            ['PATCH', '/api/caregiver/profile', ['bio' => 'Not allowed']],
            ['GET', '/api/caregiver/listings', []],
            ['POST', '/api/caregiver/listings', $draft->only($draft->getFillable())],
            ['GET', $listingUrl, []],
            ['PATCH', $listingUrl, ['title' => 'Not allowed']],
            ['DELETE', $listingUrl, []],
        ] as [$method, $url, $payload]) {
            $this->json($method, $url, $payload)->assertStatus($expected);
        }

        $this->assertDatabaseCount('caregiver_profiles', 2);
        $this->assertDatabaseCount('listings', 3);
        $this->assertSame($beforeListing, $draft->fresh()->getRawOriginal());
        $this->assertSame($beforeProfile, $profile->fresh()->getRawOriginal());
    }

    public static function blockedActors(): array
    {
        return [
            'guest' => ['guest', 401],
            'owner without caregiver role' => ['owner', 403],
            'admin without caregiver role' => ['admin', 403],
            'suspended caregiver' => ['SUSPENDED', 403],
            'deactivated caregiver' => ['DEACTIVATED', 403],
            'deletion pending caregiver' => ['DELETION_PENDING', 403],
        ];
    }

    public function test_valid_foreign_profile_id_and_active_status_cannot_be_injected(): void
    {
        $fixture = ListingFixture::make();
        $draft = Listing::query()->findOrFail($fixture['draft_listing_id']);
        $foreign = Listing::query()->findOrFail($fixture['other_draft_listing_id']);
        $before = $draft->getRawOriginal();
        $changes = [
            'title' => 'Unauthorized move',
            'caregiver_profile_id' => $foreign->caregiver_profile_id,
            'status' => 'ACTIVE',
        ];
        $this->actingAs($fixture['caregiver']);

        $responses = [
            $this->postJson('/api/caregiver/listings', [
                ...$draft->only($draft->getFillable()),
                ...$changes,
            ]),
            $this->patchJson("/api/caregiver/listings/{$draft->id}", $changes),
        ];

        foreach ($responses as $response) {
            $response->assertUnprocessable()->assertJsonPath('error.code', 'VALIDATION_ERROR');
            $this->assertArrayHasKey('caregiver_profile_id', $response->json('error.details'));
            $this->assertArrayHasKey('status', $response->json('error.details'));
        }

        $this->assertDatabaseCount('listings', 3);
        $this->assertSame($before, $draft->fresh()->getRawOriginal());
    }
}
