<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\Support\BookingFixture;
use Tests\TestCase;

class ListingManagementEndToEndTest extends TestCase
{
    use RefreshDatabase;

    public function test_caregiver_publishes_a_listing_with_media_and_availability_then_admin_change_and_lifecycle_updates_public_search(): void
    {
        Storage::fake('local');
        $fixture = BookingFixture::make();
        $caregiver = $fixture['caregiver'];
        $admin = $this->admin();
        $payload = [
            'title' => 'E2E Listing Management Initial',
            'description' => 'A caregiver listing published through the reviewed workflow.',
            'service_type' => 'DAYCARE',
            'area_code' => 'DN_CENTER',
            'price_vnd' => 45000,
            'price_unit' => 'HOUR',
            'acceptance_rules_json' => [
                'schema_version' => 1,
                'species' => ['DOG'],
                'sizes' => ['SMALL'],
            ],
        ];

        $this->actingAs($fixture['owner'])->postJson('/api/caregiver/listings', $payload)->assertForbidden();
        $this->actingAs($caregiver)->postJson('/api/caregiver/listings', [
            ...$payload,
            'price_unit' => 'NIGHT',
        ])->assertUnprocessable()->assertJsonPath('error.code', 'VALIDATION_ERROR');

        $created = $this->actingAs($caregiver)->postJson('/api/caregiver/listings', $payload)
            ->assertCreated()->assertJsonPath('data.status', 'DRAFT');
        $listingId = (string) $created->json('data.id');
        $this->assertDatabaseHas('listings', [
            'id' => $listingId,
            'status' => 'DRAFT',
            'caregiver_profile_id' => $caregiver->caregiverProfile->id,
        ]);

        $invalidUpload = UploadedFile::fake()->createWithContent('invalid.png', 'not an image');
        $this->postJson('/api/caregiver/media', ['file' => $invalidUpload])->assertUnprocessable()
            ->assertJsonPath('error.code', 'VALIDATION_ERROR');
        $this->assertDatabaseCount('media_objects', 0);

        $upload = $this->postJson('/api/caregiver/media', [
            'file' => UploadedFile::fake()->image('e2e-listing.png', 120, 90),
        ])->assertCreated();
        $mediaId = (string) $upload->json('data.id');
        $this->putJson('/api/caregiver/listings/'.$listingId.'/media', [
            'media' => [['media_object_id' => $mediaId, 'sort_order' => 1]],
        ])->assertOk();

        $start = now('UTC')->addDays(12)->setTime(10, 0, 0);
        $end = $start->copy()->addHours(2);
        $startText = $start->format('Y-m-d\TH:i:s.u\Z');
        $endText = $end->format('Y-m-d\TH:i:s.u\Z');
        $this->postJson('/api/caregiver/listings/'.$listingId.'/availability-slots', [
            'start_at' => $startText,
            'end_at' => $endText,
        ])->assertCreated();
        $this->postJson('/api/caregiver/listings/'.$listingId.'/availability-slots', [
            'start_at' => $endText,
            'end_at' => $startText,
        ])->assertUnprocessable()->assertJsonPath('error.code', 'VALIDATION_ERROR');

        $initialSearch = '/api/listings?'.http_build_query(['q' => 'E2E Listing Management Initial']);
        $this->getJson($initialSearch)->assertOk()->assertJsonCount(0, 'data');
        $this->get('/api/listings/'.$listingId.'/media/'.$mediaId.'/content')->assertNotFound();

        $this->postJson('/api/caregiver/listings/'.$listingId.'/submit')->assertOk()
            ->assertJsonPath('data.status', 'PENDING_REVIEW');
        $this->postJson('/api/admin/listings/'.$listingId.'/approve')->assertForbidden();
        $approved = $this->actingAs($admin)->postJson('/api/admin/listings/'.$listingId.'/approve')
            ->assertOk()->assertJsonPath('data.status', 'ACTIVE');
        $version = $approved->json('data.version');
        $this->assertDatabaseHas('media_objects', [
            'id' => $mediaId,
            'state' => 'READY',
            'visibility' => 'PUBLIC_ELIGIBLE',
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'resource_type' => 'LISTING',
            'resource_id' => $listingId,
            'action' => 'LISTING_APPROVED',
        ]);

        $approvedSearch = '/api/listings?'.http_build_query([
            'q' => 'E2E Listing Management Initial',
            'start_at' => $startText,
            'end_at' => $endText,
        ]);
        $this->getJson($approvedSearch)->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $listingId)
            ->assertJsonPath('data.0.price_total_vnd', 90000)
            ->assertJsonPath('data.0.media.0.media_object_id', $mediaId);
        $this->getJson('/api/listings/'.$listingId)->assertOk()
            ->assertJsonPath('data.id', $listingId)
            ->assertJsonPath('data.media.0.media_object_id', $mediaId);
        $this->get('/api/listings/'.$listingId.'/media/'.$mediaId.'/content')->assertOk()
            ->assertHeader('Content-Type', 'image/png');

        $change = $this->actingAs($caregiver)->postJson('/api/caregiver/listings/'.$listingId.'/change-requests', [
            'base_listing_version' => $version,
            'changes' => ['title' => 'E2E Listing Management Updated'],
        ])->assertCreated()->assertJsonPath('data.status', 'PENDING_REVIEW');
        $this->getJson('/api/listings/'.$listingId)->assertOk()
            ->assertJsonPath('data.title', 'E2E Listing Management Initial');
        $this->actingAs($admin)->postJson(
            '/api/admin/listings/'.$listingId.'/change-requests/'.$change->json('data.id').'/approve',
        )->assertOk()->assertJsonPath('data.status', 'APPROVED');

        $updatedSearch = '/api/listings?'.http_build_query(['q' => 'E2E Listing Management Updated']);
        $this->getJson($updatedSearch)->assertOk()->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.title', 'E2E Listing Management Updated');
        $this->getJson('/api/listings?'.http_build_query(['q' => 'E2E Listing Management Initial']))
            ->assertOk()->assertJsonCount(0, 'data');

        $this->postJson('/api/admin/listings/'.$listingId.'/approve')->assertConflict()
            ->assertJsonPath('error.code', 'INVALID_STATE');
        $this->assertDatabaseHas('listings', ['id' => $listingId, 'status' => 'ACTIVE']);

        $this->postJson('/api/admin/listings/'.$listingId.'/suspend', [
            'reason' => 'E2E suspension visibility check.',
        ])->assertOk()->assertJsonPath('data.status', 'SUSPENDED');
        $this->getJson($updatedSearch)->assertOk()->assertJsonCount(0, 'data');
        $this->get('/api/listings/'.$listingId.'/media/'.$mediaId.'/content')->assertNotFound();
        $this->actingAs($caregiver)->postJson('/api/caregiver/listings/'.$listingId.'/restore')
            ->assertForbidden()->assertJsonPath('error.code', 'FORBIDDEN');
        $this->actingAs($admin)->postJson('/api/admin/listings/'.$listingId.'/restore', [
            'reason' => 'E2E restore visibility check.',
        ])->assertOk()->assertJsonPath('data.status', 'ACTIVE');
        $this->getJson($updatedSearch)->assertOk()->assertJsonCount(1, 'data');

        $this->actingAs($caregiver)->postJson('/api/caregiver/listings/'.$listingId.'/archive', [
            'reason' => 'E2E caregiver archive.',
        ])->assertOk()->assertJsonPath('data.status', 'ARCHIVED');
        $this->getJson($updatedSearch)->assertOk()->assertJsonCount(0, 'data');
        $this->getJson('/api/listings/'.$listingId)->assertNotFound();
        $this->assertDatabaseHas('audit_logs', [
            'resource_type' => 'LISTING',
            'resource_id' => $listingId,
            'action' => 'LISTING_ARCHIVED',
        ]);
    }

    private function admin(): User
    {
        $user = User::factory()->create();
        DB::table('user_roles')->insert([
            'user_id' => $user->id,
            'role' => 'ADMIN',
            'assigned_at' => now(),
        ]);

        return $user;
    }
}
