<?php

namespace Tests\Feature;

use App\Models\Listing;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\ListingFixture;
use Tests\TestCase;

class ListingFixtureTest extends TestCase
{
    use RefreshDatabase;

    public function test_fixture_has_two_eligible_caregivers_and_a_bookable_active_listing(): void
    {
        $fixture = ListingFixture::make();
        $this->assertNotSame($fixture['caregiver']->id, $fixture['other_caregiver']->id);

        foreach ([$fixture['caregiver'], $fixture['other_caregiver']] as $caregiver) {
            $this->assertSame('ACTIVE', $caregiver->status);
            $this->assertTrue($caregiver->hasRole('CAREGIVER'));
            $profile = $caregiver->caregiverProfile()->firstOrFail();
            $this->assertSame($caregiver->id, $profile->user_id);
            $this->assertNull($profile->deleted_at);
        }

        $active = Listing::query()->findOrFail($fixture['active_listing_id']);
        $draft = Listing::query()->findOrFail($fixture['draft_listing_id']);
        $otherDraft = Listing::query()->findOrFail($fixture['other_draft_listing_id']);

        $this->assertSame('ACTIVE', $active->status);
        $this->assertNotNull($active->published_at);
        $this->assertFalse($active->trashed());
        $this->assertSame('DRAFT', $draft->status);
        $this->assertNull($draft->published_at);
        $this->assertSame('DRAFT', $otherDraft->status);
        $this->assertSame($fixture['caregiver']->id, $active->caregiverProfile->user_id);
        $this->assertSame($fixture['other_caregiver']->id, $otherDraft->caregiverProfile->user_id);
        $this->assertSame(1, $active->capacity);
        $this->assertSame(1, $active->acceptance_rules_json['schema_version']);
        $this->assertContains($fixture['pet']->species, $active->acceptance_rules_json['species']);
        $this->assertContains($fixture['pet']->size, $active->acceptance_rules_json['sizes']);
        $this->assertTrue($fixture['start_at']->isFuture());
        $this->assertTrue($active->availabilitySlots()
            ->where('start_at', '<=', $fixture['start_at']->format('Y-m-d H:i:s.u'))
            ->where('end_at', '>=', $fixture['end_at']->format('Y-m-d H:i:s.u'))
            ->exists());

        $this->actingAs($fixture['owner'])->postJson('/api/bookings/quote', $fixture['payload'])
            ->assertOk()->assertJsonPath('price_total_vnd', 150000);

        $draftPayload = array_replace($fixture['payload'], [
            'listing_id' => (string) $fixture['draft_listing_id'],
        ]);
        $this->postJson('/api/bookings/quote', $draftPayload)->assertNotFound();

        $this->assertDatabaseCount('caregiver_profiles', 2);
        $this->assertDatabaseCount('listings', 3);
        $this->assertDatabaseCount('bookings', 0);
    }

    public function test_both_caregivers_only_manage_their_own_drafts(): void
    {
        $fixture = ListingFixture::make();

        foreach ([
            [$fixture['caregiver'], $fixture['draft_listing_id'], $fixture['other_draft_listing_id']],
            [$fixture['other_caregiver'], $fixture['other_draft_listing_id'], $fixture['draft_listing_id']],
        ] as [$caregiver, $ownId, $foreignId]) {
            $this->actingAs($caregiver)->getJson('/api/caregiver/listings')
                ->assertOk()->assertJsonCount(1, 'data')
                ->assertJsonPath('data.0.id', (string) $ownId);
            $this->getJson("/api/caregiver/listings/{$ownId}")->assertOk();

            $url = "/api/caregiver/listings/{$foreignId}";
            $this->getJson($url)->assertNotFound();
            $this->patchJson($url, ['title' => 'Not allowed'])->assertNotFound();
            $this->deleteJson($url)->assertNotFound();
            $this->assertDatabaseHas('listings', [
                'id' => $foreignId,
                'deleted_at' => null,
            ]);
        }
    }

    public function test_each_fixture_call_creates_independent_data(): void
    {
        $first = ListingFixture::make();
        $second = ListingFixture::make();

        foreach (['owner', 'caregiver', 'other_caregiver'] as $key) {
            $this->assertNotSame($first[$key]->id, $second[$key]->id);
        }

        foreach (['active_listing_id', 'draft_listing_id', 'other_draft_listing_id'] as $key) {
            $this->assertNotSame($first[$key], $second[$key]);
        }

        $this->assertDatabaseCount('caregiver_profiles', 4);
        $this->assertDatabaseCount('listings', 6);
        $this->assertDatabaseCount('bookings', 0);
    }

    public function test_active_fixture_does_not_allow_client_to_bypass_review(): void
    {
        $fixture = ListingFixture::make();
        $draft = Listing::query()->findOrFail($fixture['draft_listing_id']);
        $payload = [
            ...$draft->only($draft->getFillable()),
            'status' => 'ACTIVE',
        ];

        $response = $this->actingAs($fixture['caregiver'])
            ->postJson('/api/caregiver/listings', $payload);

        $response->assertUnprocessable()->assertJsonPath('error.code', 'VALIDATION_ERROR');
        $this->assertArrayHasKey('status', $response->json('error.details'));
        $this->assertDatabaseCount('listings', 3);
        $this->assertSame('DRAFT', $draft->refresh()->status);
    }
}
