<?php

namespace Tests\Feature;

use App\Domain\Listing\Actions\ListingWorkflow;
use App\Models\MediaObject;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use Mockery;
use RuntimeException;
use Tests\Support\BookingFixture;
use Tests\TestCase;

class ListingLifecycleContractTest extends TestCase
{
    use RefreshDatabase;

    public function test_api_created_listing_without_media_or_slots_can_be_reviewed_once(): void
    {
        $fixture = BookingFixture::make();
        $caregiver = $fixture['caregiver'];
        $admin = $this->admin();
        $created = $this->createListing($caregiver);
        $id = $created->json('data.id');

        $this->actingAs($caregiver)->postJson("/api/caregiver/listings/{$id}/submit")
            ->assertOk()->assertJsonPath('data.status', 'PENDING_REVIEW')->assertJsonPath('data.version', 2);
        $this->patchJson("/api/caregiver/listings/{$id}", ['title' => 'Changed during review'])
            ->assertConflict()->assertJsonPath('error.code', 'INVALID_STATE');
        $this->actingAs($admin)->postJson("/api/admin/listings/{$id}/approve")
            ->assertOk()->assertJsonPath('data.status', 'ACTIVE')->assertJsonPath('data.version', 3);
        $publishedAt = DB::table('listings')->where('id', $id)->value('published_at');
        $this->assertNotNull($publishedAt);
        $this->assertDatabaseCount('listing_media', 0);
        $this->assertDatabaseCount('availability_slots', 2); // Only the two unrelated fixture listings have slots.
        $this->getJson("/api/listings/{$id}")->assertOk()->assertJsonPath('data.media', []);

        $this->actingAs($caregiver)->postJson("/api/caregiver/listings/{$id}/submit")->assertConflict();
        $this->actingAs($admin)->postJson("/api/admin/listings/{$id}/approve")->assertConflict();
        $this->postJson("/api/admin/listings/{$id}/reject", ['reason' => 'Replay'])->assertConflict();
        $this->assertDatabaseHas('listings', ['id' => $id, 'status' => 'ACTIVE', 'version' => 3, 'published_at' => $publishedAt]);
        $this->assertDatabaseCount('audit_logs', 2);
    }

    public function test_initial_approval_revalidates_listing_content_and_media_state(): void
    {
        $fixture = BookingFixture::make();
        $caregiver = $fixture['caregiver'];
        $admin = $this->admin();
        $id = $this->createListing($caregiver)->json('data.id');
        $media = $this->media($caregiver);
        DB::table('listing_media')->insert(['listing_id' => $id, 'media_object_id' => $media->id, 'sort_order' => 1]);

        $this->actingAs($caregiver)->postJson("/api/caregiver/listings/{$id}/submit")->assertOk();
        DB::table('media_objects')->where('id', $media->id)->update(['state' => 'PENDING_DELETE']);
        $this->actingAs($admin)->postJson("/api/admin/listings/{$id}/approve")
            ->assertConflict()->assertJsonPath('error.code', 'INVALID_STATE');
        $this->assertDatabaseHas('listings', ['id' => $id, 'status' => 'PENDING_REVIEW', 'version' => 2, 'published_at' => null]);
        $this->assertDatabaseHas('media_objects', ['id' => $media->id, 'visibility' => 'PRIVATE', 'state' => 'PENDING_DELETE']);

        DB::table('media_objects')->where('id', $media->id)->update(['state' => 'READY']);
        DB::table('listings')->where('id', $id)->update(['title' => '   ']);
        $this->postJson("/api/admin/listings/{$id}/approve")->assertConflict()
            ->assertJsonPath('error.code', 'INVALID_STATE');
        $this->assertDatabaseHas('listings', ['id' => $id, 'status' => 'PENDING_REVIEW', 'version' => 2, 'published_at' => null]);
    }

    public function test_initial_approval_rechecks_caregiver_and_rolls_back_if_audit_fails(): void
    {
        $fixture = BookingFixture::make();
        $caregiver = $fixture['caregiver'];
        $admin = $this->admin();
        $id = $this->createListing($caregiver)->json('data.id');
        $this->actingAs($caregiver)->postJson("/api/caregiver/listings/{$id}/submit")->assertOk();
        $caregiver->caregiverProfile()->firstOrFail()->delete();
        $this->actingAs($admin)->postJson("/api/admin/listings/{$id}/approve")
            ->assertConflict()->assertJsonPath('error.code', 'INVALID_STATE');
        $this->assertDatabaseHas('listings', ['id' => $id, 'status' => 'PENDING_REVIEW', 'version' => 2]);

        $other = BookingFixture::make();
        $secondId = $this->createListing($other['caregiver'])->json('data.id');
        $this->actingAs($other['caregiver'])->postJson("/api/caregiver/listings/{$secondId}/submit")->assertOk();
        $workflow = Mockery::mock(ListingWorkflow::class)->makePartial();
        $workflow->shouldReceive('audit')->once()->andThrow(new RuntimeException('audit unavailable'));
        $this->app->instance(ListingWorkflow::class, $workflow);

        $this->actingAs($admin)->postJson("/api/admin/listings/{$secondId}/approve")->assertInternalServerError();
        $this->assertDatabaseHas('listings', ['id' => $secondId, 'status' => 'PENDING_REVIEW', 'version' => 2, 'published_at' => null]);
        $this->assertDatabaseMissing('audit_logs', ['action' => 'LISTING_APPROVED', 'resource_id' => $secondId]);
    }

    public function test_rejection_requires_reason_returns_to_draft_and_cannot_be_replayed(): void
    {
        $fixture = BookingFixture::make();
        $caregiver = $fixture['caregiver'];
        $admin = $this->admin();
        $id = $this->createListing($caregiver)->json('data.id');
        $this->actingAs($caregiver)->postJson("/api/caregiver/listings/{$id}/submit")->assertOk();

        $this->actingAs($admin)->postJson("/api/admin/listings/{$id}/reject")->assertUnprocessable();
        $this->postJson("/api/admin/listings/{$id}/reject", ['reason' => 'Please improve the care details.'])
            ->assertOk()->assertJsonPath('data.status', 'DRAFT')->assertJsonPath('data.version', 3);
        $this->postJson("/api/admin/listings/{$id}/reject", ['reason' => 'Replay'])->assertConflict();
        $this->postJson("/api/admin/listings/{$id}/approve")->assertConflict();
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'LISTING_REJECTED', 'resource_id' => $id,
            'reason' => 'Please improve the care details.',
        ]);
        $this->assertSame(1, DB::table('audit_logs')->where('action', 'LISTING_REJECTED')->where('resource_id', $id)->count());
    }

    public function test_restore_keeps_first_published_at_and_rechecks_caregiver_eligibility(): void
    {
        $fixture = BookingFixture::make();
        $caregiver = $fixture['caregiver'];
        $admin = $this->admin();
        $id = $this->createListing($caregiver)->json('data.id');
        $this->actingAs($caregiver)->postJson("/api/caregiver/listings/{$id}/submit")->assertOk();
        $this->actingAs($admin)->postJson("/api/admin/listings/{$id}/approve")->assertOk();
        $firstPublishedAt = DB::table('listings')->where('id', $id)->value('published_at');

        $this->actingAs($caregiver)->postJson("/api/caregiver/listings/{$id}/suspend", ['reason' => 'Caregiver pause.'])
            ->assertOk()->assertJsonPath('data.suspension_source', 'CAREGIVER');
        $this->postJson("/api/caregiver/listings/{$id}/restore")->assertOk()->assertJsonPath('data.status', 'ACTIVE');
        $this->assertDatabaseHas('listings', ['id' => $id, 'published_at' => $firstPublishedAt, 'suspension_source' => null]);

        $this->postJson("/api/caregiver/listings/{$id}/suspend", ['reason' => 'Pause again.'])->assertOk();
        $caregiver->status = 'DEACTIVATED';
        $caregiver->save();
        $this->postJson("/api/caregiver/listings/{$id}/restore")->assertForbidden();
        $this->actingAs($admin)->postJson("/api/admin/listings/{$id}/restore", ['reason' => 'Review complete.'])
            ->assertConflict()->assertJsonPath('error.code', 'INVALID_STATE');
        $this->assertDatabaseHas('listings', ['id' => $id, 'status' => 'SUSPENDED', 'published_at' => $firstPublishedAt]);
    }

    public function test_admin_can_escalate_caregiver_suspension_and_caregiver_cannot_restore_admin_source(): void
    {
        $fixture = BookingFixture::make();
        $caregiver = $fixture['caregiver'];
        $admin = $this->admin();
        $id = $fixture['active_listing_id'];
        $this->actingAs($caregiver)->postJson("/api/caregiver/listings/{$id}/suspend", ['reason' => 'Caregiver pause.'])
            ->assertOk()->assertJsonPath('data.suspension_source', 'CAREGIVER');
        $this->actingAs($admin)->postJson("/api/admin/listings/{$id}/suspend", ['reason' => 'Escalated moderation.'])
            ->assertOk()->assertJsonPath('data.suspension_source', 'ADMIN');
        $version = DB::table('listings')->where('id', $id)->value('version');
        $this->actingAs($caregiver)->postJson("/api/caregiver/listings/{$id}/restore")->assertForbidden();
        $this->assertDatabaseHas('listings', ['id' => $id, 'status' => 'SUSPENDED', 'suspension_source' => 'ADMIN', 'version' => $version]);
        $this->actingAs($admin)->postJson("/api/admin/listings/{$id}/restore", ['reason' => 'Moderation cleared.'])
            ->assertOk()->assertJsonPath('data.status', 'ACTIVE')->assertJsonPath('data.suspension_source', null);
    }

    public function test_suspend_withdraws_pending_request_atomically_without_touching_booking_history(): void
    {
        $fixture = BookingFixture::requested($this);
        $caregiver = $fixture['caregiver'];
        $listingId = $fixture['active_listing_id'];
        $request = $this->actingAs($caregiver)->postJson("/api/caregiver/listings/{$listingId}/change-requests", [
            'base_listing_version' => 1,
            'changes' => ['title' => 'Changed after review'],
        ])->assertCreated();
        $bookingBefore = DB::table('bookings')->where('id', $fixture['booking']->id)->first();
        $eventsBefore = DB::table('booking_status_events')->where('booking_id', $fixture['booking']->id)->get()->toArray();

        $this->postJson("/api/caregiver/listings/{$listingId}/suspend", ['reason' => 'Pause services.'])
            ->assertOk()->assertJsonPath('data.status', 'SUSPENDED');
        $this->assertDatabaseHas('listing_change_requests', ['id' => $request->json('data.id'), 'status' => 'WITHDRAWN']);
        $this->assertEquals($bookingBefore, DB::table('bookings')->where('id', $fixture['booking']->id)->first());
        $this->assertEquals($eventsBefore, DB::table('booking_status_events')->where('booking_id', $fixture['booking']->id)->get()->toArray());
    }

    public function test_suspend_rollback_restores_pending_request_and_audit_when_final_audit_fails(): void
    {
        $fixture = BookingFixture::make();
        $caregiver = $fixture['caregiver'];
        $listingId = $fixture['active_listing_id'];
        $request = $this->actingAs($caregiver)->postJson("/api/caregiver/listings/{$listingId}/change-requests", [
            'base_listing_version' => 1,
            'changes' => ['title' => 'Pending title'],
        ])->assertCreated();
        $workflow = Mockery::mock(ListingWorkflow::class)->makePartial();
        $workflow->shouldReceive('audit')->once()->andThrow(new RuntimeException('audit unavailable'));
        $this->app->instance(ListingWorkflow::class, $workflow);

        $this->postJson("/api/caregiver/listings/{$listingId}/suspend", ['reason' => 'Pause services.'])->assertInternalServerError();
        $this->assertDatabaseHas('listings', ['id' => $listingId, 'status' => 'ACTIVE', 'version' => 1, 'suspension_source' => null]);
        $this->assertDatabaseHas('listing_change_requests', ['id' => $request->json('data.id'), 'status' => 'PENDING_REVIEW']);
        $this->assertDatabaseMissing('audit_logs', ['action' => 'LISTING_CHANGE_WITHDRAWN', 'resource_id' => $request->json('data.id')]);
    }

    public function test_expired_awaiting_payment_still_blocks_archive_and_archived_is_terminal(): void
    {
        $fixture = BookingFixture::requested($this);
        $caregiver = $fixture['caregiver'];
        $admin = $this->admin();
        $id = $fixture['active_listing_id'];
        DB::table('bookings')->where('id', $fixture['booking']->id)->update([
            'status' => 'AWAITING_PAYMENT', 'payment_due_at' => now()->subMinute(),
        ]);

        $this->actingAs($caregiver)->postJson("/api/caregiver/listings/{$id}/archive", ['reason' => 'Close listing.'])
            ->assertConflict()->assertJsonPath('error.code', 'INVALID_STATE');
        $this->assertDatabaseHas('listings', ['id' => $id, 'status' => 'ACTIVE', 'version' => 1]);
        $this->assertDatabaseHas('bookings', ['id' => $fixture['booking']->id, 'status' => 'AWAITING_PAYMENT']);

        foreach (['CONFIRMED', 'IN_PROGRESS', 'AWAITING_CONFIRMATION'] as $heldStatus) {
            DB::table('bookings')->where('id', $fixture['booking']->id)->update(['status' => $heldStatus]);
            $this->postJson("/api/caregiver/listings/{$id}/archive", ['reason' => 'Close listing.'])
                ->assertConflict()->assertJsonPath('error.code', 'INVALID_STATE');
            $this->assertDatabaseHas('listings', ['id' => $id, 'status' => 'ACTIVE', 'version' => 1]);
            $this->assertDatabaseHas('bookings', ['id' => $fixture['booking']->id, 'status' => $heldStatus]);
        }

        DB::table('bookings')->where('id', $fixture['booking']->id)->update(['status' => 'CANCELLED']);
        $this->postJson("/api/caregiver/listings/{$id}/archive", ['reason' => 'Close listing.'])
            ->assertOk()->assertJsonPath('data.status', 'ARCHIVED')->assertJsonPath('data.version', 2);
        $this->actingAs($admin)->postJson("/api/admin/listings/{$id}/restore", ['reason' => 'Try reopen.'])->assertConflict();
        $this->actingAs($caregiver)->postJson("/api/caregiver/listings/{$id}/suspend", ['reason' => 'Try suspend.'])->assertConflict();
        $this->postJson("/api/caregiver/listings/{$id}/archive", ['reason' => 'Try archive again.'])->assertConflict();
        $this->assertDatabaseHas('listings', ['id' => $id, 'status' => 'ARCHIVED', 'version' => 2]);
    }

    public function test_archive_with_requested_booking_withdraws_pending_request_and_preserves_booking(): void
    {
        $fixture = BookingFixture::requested($this);
        $caregiver = $fixture['caregiver'];
        $listingId = $fixture['active_listing_id'];
        $request = $this->actingAs($caregiver)->postJson("/api/caregiver/listings/{$listingId}/change-requests", [
            'base_listing_version' => 1,
            'changes' => ['title' => 'Pending title'],
        ])->assertCreated();
        $bookingBefore = DB::table('bookings')->where('id', $fixture['booking']->id)->first();
        $eventsBefore = DB::table('booking_status_events')->where('booking_id', $fixture['booking']->id)->get()->toArray();

        $this->postJson("/api/caregiver/listings/{$listingId}/archive", ['reason' => 'Close listing.'])
            ->assertOk()->assertJsonPath('data.status', 'ARCHIVED');
        $this->assertDatabaseHas('listing_change_requests', ['id' => $request->json('data.id'), 'status' => 'WITHDRAWN']);
        $this->assertEquals($bookingBefore, DB::table('bookings')->where('id', $fixture['booking']->id)->first());
        $this->assertEquals($eventsBefore, DB::table('booking_status_events')->where('booking_id', $fixture['booking']->id)->get()->toArray());
    }

    private function createListing(User $caregiver): TestResponse
    {
        return $this->actingAs($caregiver)->postJson('/api/caregiver/listings', [
            'title' => 'API created daycare',
            'description' => 'A valid listing created through the caregiver API.',
            'service_type' => 'DAYCARE',
            'area_code' => 'DN_CENTER',
            'price_vnd' => 75000,
            'price_unit' => 'HOUR',
            'acceptance_rules_json' => ['schema_version' => 1, 'species' => ['DOG'], 'sizes' => ['SMALL']],
        ])->assertCreated();
    }

    private function media(User $owner): MediaObject
    {
        $media = new MediaObject([
            'object_key' => 'listings/'.$owner->id.'/review-'.fake()->uuid().'.png',
            'mime_type' => 'image/png', 'byte_size' => 100, 'sha256' => hash('sha256', fake()->uuid()),
        ]);
        $media->owner()->associate($owner);
        $media->save();

        return $media;
    }

    private function admin(): User
    {
        $user = User::factory()->create();
        DB::table('user_roles')->insert(['user_id' => $user->id, 'role' => 'ADMIN', 'assigned_at' => now()]);

        return $user;
    }
}
