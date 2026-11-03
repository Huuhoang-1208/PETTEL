<?php

namespace Tests\Feature;

use App\Models\MediaObject;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\Support\BookingFixture;
use Tests\TestCase;

class ListingPublicBookingIntegrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_slot_api_controls_public_search_quote_create_and_accept(): void
    {
        $fixture = BookingFixture::make();
        $caregiver = $fixture['caregiver'];
        $owner = $fixture['owner'];
        $listingId = $fixture['active_listing_id'];
        $oldSlotId = DB::table('availability_slots')->where('listing_id', $listingId)->value('id');
        $this->actingAs($caregiver)->deleteJson("/api/caregiver/listings/{$listingId}/availability-slots/{$oldSlotId}")
            ->assertNoContent();
        $start = now('UTC')->addDays(10)->setTime(9, 0, 0, 123456);
        $end = $start->copy()->addHours(2);
        $startText = $start->format('Y-m-d\TH:i:s.u\Z');
        $endText = $end->format('Y-m-d\TH:i:s.u\Z');
        $url = '/api/listings?'.http_build_query(['start_at' => $startText, 'end_at' => $endText]);

        $this->getJson($url)->assertOk()->assertJsonCount(0, 'data');
        $this->actingAs($caregiver)->postJson("/api/caregiver/listings/{$listingId}/availability-slots", [
            'start_at' => $startText, 'end_at' => $endText,
        ])->assertCreated();
        $this->getJson($url)->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', (string) $listingId);

        $payload = [
            'pet_id' => (string) $fixture['pet']->id,
            'listing_id' => (string) $listingId,
            'start_at' => $startText,
            'end_at' => $endText,
        ];
        $this->actingAs($owner)->postJson('/api/bookings/quote', $payload)->assertOk()
            ->assertJsonPath('price_total_vnd', 100000);
        $created = $this->withHeader('Idempotency-Key', 'listing-slot-api-booking')
            ->postJson('/api/bookings', $payload)->assertCreated();
        $bookingId = $created->json('id');
        $this->actingAs($caregiver)->postJson("/api/bookings/{$bookingId}/accept")
            ->assertOk()->assertJsonPath('status', 'AWAITING_PAYMENT');
        $this->assertDatabaseHas('bookings', ['id' => $bookingId, 'status' => 'AWAITING_PAYMENT']);
    }

    public function test_suspension_hides_public_data_blocks_new_and_accept_but_allows_rejecting_requested(): void
    {
        $fixture = BookingFixture::make();
        $owner = $fixture['owner'];
        $caregiver = $fixture['caregiver'];
        $listingId = $fixture['active_listing_id'];
        $acceptRequest = $this->withHeader('Idempotency-Key', 'suspend-existing-accept')
            ->actingAs($owner)->postJson('/api/bookings', $fixture['payload'])->assertCreated();
        $rejectRequest = $this->withHeader('Idempotency-Key', 'suspend-existing-reject')
            ->postJson('/api/bookings', $fixture['payload'])->assertCreated();
        $acceptId = $acceptRequest->json('id');
        $rejectId = $rejectRequest->json('id');
        $snapshot = DB::table('bookings')->where('id', $acceptId)->value('listing_snapshot_json');
        $events = DB::table('booking_status_events')->whereIn('booking_id', [$acceptId, $rejectId])->count();

        $this->actingAs($caregiver)->postJson("/api/caregiver/listings/{$listingId}/suspend", ['reason' => 'Pause new requests.'])
            ->assertOk()->assertJsonPath('data.status', 'SUSPENDED');
        $this->getJson("/api/listings/{$listingId}")->assertNotFound();
        $this->getJson('/api/listings')->assertOk()->assertJsonCount(0, 'data');
        $this->actingAs($owner)->postJson('/api/bookings/quote', $fixture['payload'])
            ->assertNotFound()->assertJsonPath('error.code', 'NOT_FOUND');
        $this->withHeader('Idempotency-Key', 'suspend-new-request')->postJson('/api/bookings', $fixture['payload'])
            ->assertNotFound();
        $this->actingAs($caregiver)->postJson("/api/bookings/{$acceptId}/accept")
            ->assertConflict()->assertJsonPath('error.code', 'INVALID_STATE');
        $this->postJson("/api/bookings/{$rejectId}/reject")->assertOk()->assertJsonPath('status', 'CANCELLED');

        $this->assertSame($snapshot, DB::table('bookings')->where('id', $acceptId)->value('listing_snapshot_json'));
        $this->assertDatabaseHas('bookings', ['id' => $acceptId, 'status' => 'REQUESTED']);
        $this->assertDatabaseHas('bookings', ['id' => $rejectId, 'status' => 'CANCELLED']);
        $this->assertSame($events + 1, DB::table('booking_status_events')->whereIn('booking_id', [$acceptId, $rejectId])->count());
    }

    public function test_approved_price_changes_keep_request_snapshot_acceptable_but_service_and_rules_changes_invalidate_it(): void
    {
        $admin = $this->admin();
        $fixture = BookingFixture::requested($this);
        $listingId = $fixture['active_listing_id'];
        $booking = $fixture['booking'];
        $snapshot = $booking->listing_snapshot_json;
        $priceSnapshot = $booking->price_snapshot_vnd;
        $request = $this->actingAs($fixture['caregiver'])->postJson("/api/caregiver/listings/{$listingId}/change-requests", [
            'base_listing_version' => 1,
            'changes' => ['title' => 'New public title', 'price_vnd' => 60000],
        ])->assertCreated();
        $this->actingAs($admin)->postJson("/api/admin/listings/{$listingId}/change-requests/{$request->json('data.id')}/approve")
            ->assertOk();
        $this->actingAs($fixture['caregiver'])->postJson("/api/bookings/{$booking->id}/accept")
            ->assertOk()->assertJsonPath('status', 'AWAITING_PAYMENT');
        $this->assertSame($snapshot, $booking->fresh()->listing_snapshot_json);
        $this->assertSame($priceSnapshot, $booking->fresh()->price_snapshot_vnd);

        foreach ([
            ['service_type' => 'BOARDING', 'price_unit' => 'NIGHT'],
            ['acceptance_rules_json' => ['schema_version' => 1, 'species' => ['CAT'], 'sizes' => ['SMALL']]],
        ] as $changes) {
            $next = BookingFixture::requested($this);
            $id = $next['active_listing_id'];
            $pending = $this->actingAs($next['caregiver'])->postJson("/api/caregiver/listings/{$id}/change-requests", [
                'base_listing_version' => 1, 'changes' => $changes,
            ])->assertCreated();
            $this->actingAs($admin)->postJson("/api/admin/listings/{$id}/change-requests/{$pending->json('data.id')}/approve")
                ->assertOk();
            $this->actingAs($next['caregiver'])->postJson("/api/bookings/{$next['booking']->id}/accept")
                ->assertConflict()->assertJsonPath('error.code', 'INVALID_STATE');
            $this->assertDatabaseHas('bookings', ['id' => $next['booking']->id, 'status' => 'REQUESTED']);
        }
    }

    public function test_approved_detach_hides_public_media_without_deleting_retained_files(): void
    {
        Storage::fake('local');
        $fixture = BookingFixture::make();
        $caregiver = $fixture['caregiver'];
        $admin = $this->admin();
        $listingId = $fixture['active_listing_id'];
        $key = 'listings/'.$caregiver->id.'/retained.png';
        Storage::disk('local')->put($key, 'image bytes');
        Storage::disk('local')->put('listings/'.$caregiver->id.'/retained-thumb.webp', 'thumbnail bytes');
        $media = new MediaObject([
            'object_key' => $key, 'mime_type' => 'image/png', 'byte_size' => 11,
            'sha256' => hash('sha256', 'image bytes'),
        ]);
        $media->owner()->associate($caregiver);
        $media->visibility = 'PUBLIC_ELIGIBLE';
        $media->state = 'READY';
        $media->save();
        DB::table('listing_media')->insert(['listing_id' => $listingId, 'media_object_id' => $media->id, 'sort_order' => 1]);

        $this->get("/api/listings/{$listingId}/media/{$media->id}/content")->assertOk();
        $proposal = $this->actingAs($caregiver)->postJson("/api/caregiver/listings/{$listingId}/change-requests", [
            'base_listing_version' => 1, 'changes' => ['media' => []],
        ])->assertCreated();
        $this->actingAs($admin)->postJson("/api/admin/listings/{$listingId}/change-requests/{$proposal->json('data.id')}/approve")
            ->assertOk();

        $this->getJson("/api/listings/{$listingId}")->assertOk()->assertJsonPath('data.media', []);
        $this->get("/api/listings/{$listingId}/media/{$media->id}/content")->assertNotFound();
        $this->assertDatabaseHas('media_objects', ['id' => $media->id, 'state' => 'READY']);
        Storage::disk('local')->assertExists($key);
        Storage::disk('local')->assertExists('listings/'.$caregiver->id.'/retained-thumb.webp');
    }

    private function admin(): User
    {
        $user = User::factory()->create();
        DB::table('user_roles')->insert(['user_id' => $user->id, 'role' => 'ADMIN', 'assigned_at' => now()]);

        return $user;
    }
}
