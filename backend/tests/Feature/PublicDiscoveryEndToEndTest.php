<?php

namespace Tests\Feature;

use App\Models\Listing;
use App\Models\MediaObject;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\Support\BookingFixture;
use Tests\TestCase;

class PublicDiscoveryEndToEndTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_discovers_a_public_listing_reads_its_image_and_sees_a_new_review_in_search_and_detail(): void
    {
        Storage::fake('local');
        $fixture = BookingFixture::requested($this);
        $booking = $fixture['booking'];
        $booking->update(['status' => 'COMPLETED']); // Existing completed booking is a prerequisite for the discovery/review workflow.

        $listing = Listing::query()->findOrFail($booking->listing_id);
        $listing->update(['title' => 'E2E Discovery Sunny Daycare']);
        $imageBytes = 'approved listing image bytes';
        $objectKey = 'listings/'.$fixture['caregiver']->id.'/e2e-discovery.png';
        Storage::disk('local')->put($objectKey, $imageBytes);

        $media = new MediaObject([
            'object_key' => $objectKey,
            'mime_type' => 'image/png',
            'byte_size' => strlen($imageBytes),
            'sha256' => hash('sha256', $imageBytes),
        ]);
        $media->owner()->associate($fixture['caregiver']);
        $media->visibility = 'PUBLIC_ELIGIBLE';
        $media->state = 'READY';
        $media->save();
        DB::table('listing_media')->insert([
            'listing_id' => $listing->id,
            'media_object_id' => $media->id,
            'sort_order' => 1,
        ]);

        $searchQuery = http_build_query([
            'q' => 'E2E Discovery Sunny Daycare',
            'service_type' => 'DAYCARE',
            'area_code' => 'DN_CENTER',
            'species' => 'DOG',
            'size' => 'SMALL',
        ]);
        $this->getJson('/api/listings?'.$searchQuery)->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', (string) $listing->id)
            ->assertJsonPath('data.0.rating_average', null)
            ->assertJsonPath('data.0.review_count', 0);

        $this->getJson('/api/listings/'.$listing->id)->assertOk()
            ->assertJsonPath('data.id', (string) $listing->id)
            ->assertJsonPath('data.media.0.media_object_id', (string) $media->id);
        $this->get('/api/listings/'.$listing->id.'/media/'.$media->id.'/content')
            ->assertOk()->assertContent($imageBytes);
        $this->getJson('/api/listings/'.$listing->id.'/reviews')->assertOk()
            ->assertJsonCount(0, 'data')
            ->assertJsonPath('rating_average', null)
            ->assertJsonPath('review_count', 0);

        $this->actingAs($fixture['owner'])->postJson('/api/bookings/'.$booking->id.'/reviews', [
            'rating' => 5,
            'body' => 'Excellent care from the verified booking.',
        ])->assertCreated()->assertJsonPath('data.status', 'VISIBLE');

        $this->getJson('/api/listings/'.$listing->id.'/reviews')->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.rating', 5)
            ->assertJsonPath('rating_average', 5)
            ->assertJsonPath('review_count', 1);
        $this->getJson('/api/listings/'.$listing->id)->assertOk()
            ->assertJsonPath('data.rating_average', 5)
            ->assertJsonPath('data.review_count', 1);
        $this->getJson('/api/listings?'.$searchQuery)->assertOk()
            ->assertJsonPath('data.0.rating_average', 5)
            ->assertJsonPath('data.0.review_count', 1);

        $this->getJson('/api/listings?q=E2E-No-Such-Service')->assertOk()
            ->assertJsonCount(0, 'data')
            ->assertJsonPath('match_summary.code', 'NO_MATCHING_LISTINGS');
        $this->getJson('/api/listings?service_type=INVALID')->assertUnprocessable()
            ->assertJsonPath('error.code', 'VALIDATION_ERROR');

        $admin = $this->admin();
        $this->actingAs($admin)->postJson('/api/admin/listings/'.$listing->id.'/suspend', [
            'reason' => 'E2E public visibility check.',
        ])->assertOk()->assertJsonPath('data.status', 'SUSPENDED');

        $this->getJson('/api/listings?'.$searchQuery)->assertOk()->assertJsonCount(0, 'data');
        $this->getJson('/api/listings/'.$listing->id)->assertNotFound();
        $this->get('/api/listings/'.$listing->id.'/media/'.$media->id.'/content')->assertNotFound();
        $this->getJson('/api/listings/'.$listing->id.'/reviews')->assertNotFound();
        $this->assertDatabaseHas('reviews', [
            'booking_id' => $booking->id,
            'rating' => 5,
            'status' => 'VISIBLE',
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
