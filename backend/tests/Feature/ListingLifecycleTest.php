<?php

namespace Tests\Feature;

use App\Domain\Listing\Actions\ListingWorkflow;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Mockery;
use RuntimeException;
use Tests\Support\BookingFixture;
use Tests\Support\ListingFixture;
use Tests\TestCase;

class ListingLifecycleTest extends TestCase
{
    use RefreshDatabase;

    public function test_draft_submission_requires_a_separate_admin_and_admin_can_reject_with_reason(): void
    {
        $fixture = BookingFixture::make();
        $caregiver = $fixture['caregiver'];
        $admin = $this->admin();
        $id = $fixture['draft_listing_id'];

        $this->actingAs($caregiver)->postJson("/api/caregiver/listings/{$id}/submit")
            ->assertOk()->assertJsonPath('data.status', 'PENDING_REVIEW')
            ->assertJsonPath('data.version', 2);
        $this->actingAs($caregiver)->postJson("/api/admin/listings/{$id}/approve")
            ->assertForbidden();
        $this->actingAs($admin)->postJson("/api/admin/listings/{$id}/reject", ['reason' => 'Please add clearer care details.'])
            ->assertOk()->assertJsonPath('data.status', 'DRAFT');
        $this->assertDatabaseHas('audit_logs', [
            'resource_type' => 'LISTING', 'resource_id' => $id,
            'action' => 'LISTING_REJECTED', 'reason' => 'Please add clearer care details.',
        ]);
    }

    public function test_admin_approval_and_suspension_restore_control_public_visibility(): void
    {
        $fixture = BookingFixture::make();
        $caregiver = $fixture['caregiver'];
        $admin = $this->admin();
        $id = $fixture['draft_listing_id'];
        $this->actingAs($caregiver)->postJson("/api/caregiver/listings/{$id}/submit")->assertOk();
        $this->actingAs($admin)->postJson("/api/admin/listings/{$id}/approve")->assertOk()
            ->assertJsonPath('data.status', 'ACTIVE');
        $this->getJson("/api/listings/{$id}")->assertOk();

        $this->actingAs($admin)->postJson("/api/admin/listings/{$id}/suspend", ['reason' => 'Moderation review.'])
            ->assertOk()->assertJsonPath('data.status', 'SUSPENDED')
            ->assertJsonPath('data.suspension_source', 'ADMIN');
        $this->getJson("/api/listings/{$id}")->assertNotFound();
        $this->actingAs($caregiver)->postJson("/api/caregiver/listings/{$id}/restore")
            ->assertForbidden()->assertJsonPath('error.code', 'FORBIDDEN');
        $this->actingAs($admin)->postJson("/api/admin/listings/{$id}/restore", ['reason' => 'Review complete.'])
            ->assertOk()->assertJsonPath('data.status', 'ACTIVE')
            ->assertJsonPath('data.suspension_source', null);
        $this->getJson("/api/listings/{$id}")->assertOk();
    }

    public function test_change_request_keeps_old_public_content_until_admin_approves(): void
    {
        $fixture = BookingFixture::make();
        $caregiver = $fixture['caregiver'];
        $admin = $this->admin();
        $id = $fixture['active_listing_id'];
        $this->actingAs($caregiver)->postJson("/api/caregiver/listings/{$id}/change-requests", [
            'base_listing_version' => 1,
            'changes' => ['title' => 'New approved title', 'media' => []],
        ])->assertCreated()->assertJsonPath('data.status', 'PENDING_REVIEW');
        $changeId = DB::table('listing_change_requests')->where('listing_id', $id)->value('id');
        $this->getJson("/api/listings/{$id}")->assertOk()->assertJsonPath('data.title', 'Test daycare');
        $this->actingAs($admin)->postJson("/api/admin/listings/{$id}/change-requests/{$changeId}/approve")
            ->assertOk()->assertJsonPath('data.status', 'APPROVED')->assertJsonPath('data.listing_version', 2);
        $this->getJson("/api/listings/{$id}")->assertOk()->assertJsonPath('data.title', 'New approved title');
    }

    public function test_slot_overlap_and_held_booking_coverage_are_protected(): void
    {
        $fixture = BookingFixture::make();
        $caregiver = $fixture['caregiver'];
        $id = $fixture['active_listing_id'];
        $start = $fixture['start_at']->copy()->addHour()->toIso8601String();
        $end = $fixture['start_at']->copy()->addHours(2)->toIso8601String();
        $this->actingAs($caregiver)->postJson("/api/caregiver/listings/{$id}/availability-slots", [
            'start_at' => $start, 'end_at' => $end,
        ])->assertConflict()->assertJsonPath('error.code', 'AVAILABILITY_CONFLICT');

        DB::table('bookings')->insert([
            'owner_id' => $fixture['owner']->id,
            'pet_id' => $fixture['pet']->id,
            'listing_id' => $id,
            'caregiver_user_id' => $caregiver->id,
            'start_at' => $fixture['start_at']->format('Y-m-d H:i:s.u'),
            'end_at' => $fixture['end_at']->format('Y-m-d H:i:s.u'),
            'status' => 'CONFIRMED', 'price_snapshot_vnd' => 100000,
            'price_unit' => 'HOUR', 'price_quantity' => 2,
            'pricing_version' => 1, 'cancellation_policy_version' => 1,
            'pet_snapshot_json' => '{}', 'listing_snapshot_json' => '{}', 'policy_snapshot_json' => '{}',
            'request_idempotency_key' => 'slot-protection', 'request_payload_hash' => str_repeat('a', 64),
            'version' => 1, 'created_at' => now(), 'updated_at' => now(),
        ]);
        $slotId = DB::table('availability_slots')->where('listing_id', $id)->value('id');
        $this->deleteJson("/api/caregiver/listings/{$id}/availability-slots/{$slotId}")
            ->assertConflict()->assertJsonPath('error.code', 'AVAILABILITY_CONFLICT');
        $this->assertDatabaseHas('availability_slots', ['id' => $slotId, 'deleted_at' => null]);
    }

    public function test_uploaded_image_is_private_until_approved_and_hidden_after_suspend(): void
    {
        Storage::fake('local');
        $fixture = BookingFixture::make();
        $caregiver = $fixture['caregiver'];
        $admin = $this->admin();
        $id = $fixture['draft_listing_id'];
        $upload = $this->actingAs($caregiver)->postJson('/api/caregiver/media', [
            'file' => UploadedFile::fake()->image('care.png', 120, 90),
        ])->assertCreated();
        $mediaId = $upload->json('data.id');
        $this->assertDatabaseHas('media_objects', ['id' => $mediaId, 'visibility' => 'PRIVATE', 'state' => 'READY']);

        $this->putJson("/api/caregiver/listings/{$id}/media", ['media' => [[
            'media_object_id' => $mediaId, 'sort_order' => 1,
        ]]])->assertOk();
        $this->actingAs($caregiver)->postJson("/api/caregiver/listings/{$id}/submit")->assertOk();
        $this->actingAs($admin)->postJson("/api/admin/listings/{$id}/approve")->assertOk();
        $this->assertDatabaseHas('media_objects', ['id' => $mediaId, 'visibility' => 'PUBLIC_ELIGIBLE']);
        $this->getJson("/api/listings/{$id}")->assertOk()->assertJsonPath('data.media.0.media_object_id', (string) $mediaId);
        $this->get("/api/listings/{$id}/media/{$mediaId}/content")->assertOk()->assertHeader('Content-Type', 'image/png');

        $this->actingAs($caregiver)->postJson("/api/caregiver/listings/{$id}/suspend", ['reason' => 'Temporarily unavailable.'])->assertOk();
        $this->get("/api/listings/{$id}/media/{$mediaId}/content")->assertNotFound();
    }

    public function test_suspension_source_check_rejects_invalid_state_source_pairs(): void
    {
        $fixture = BookingFixture::make();
        $id = $fixture['active_listing_id'];

        try {
            DB::table('listings')->where('id', $id)->update(['status' => 'SUSPENDED', 'suspension_source' => null]);
            $this->fail('The database accepted a suspended listing without a source.');
        } catch (QueryException) {
            $this->assertDatabaseHas('listings', ['id' => $id, 'status' => 'ACTIVE', 'suspension_source' => null]);
        }

        try {
            DB::table('listings')->where('id', $id)->update(['suspension_source' => 'ADMIN']);
            $this->fail('The database accepted a source on an active listing.');
        } catch (QueryException) {
            $this->assertDatabaseHas('listings', ['id' => $id, 'status' => 'ACTIVE', 'suspension_source' => null]);
        }
    }

    public function test_caregiver_cannot_access_foreign_listing_or_mismatched_child_resources(): void
    {
        $fixture = ListingFixture::make();
        $caregiver = $fixture['caregiver'];
        $ownDraft = $fixture['draft_listing_id'];
        $foreignDraft = $fixture['other_draft_listing_id'];
        $active = $fixture['active_listing_id'];
        $slotId = DB::table('availability_slots')->where('listing_id', $active)->value('id');

        $this->actingAs($caregiver)->getJson("/api/caregiver/listings/{$foreignDraft}/change-requests")->assertNotFound();
        $this->postJson("/api/caregiver/listings/{$foreignDraft}/submit")->assertNotFound();
        $this->postJson("/api/caregiver/listings/{$foreignDraft}/availability-slots", [
            'start_at' => 'invalid', 'end_at' => 'invalid',
        ])->assertNotFound();
        $this->deleteJson("/api/caregiver/listings/{$ownDraft}/availability-slots/{$slotId}")->assertNotFound();
    }

    public function test_admin_cannot_review_their_own_listing(): void
    {
        $fixture = BookingFixture::make();
        $caregiver = $fixture['caregiver'];
        DB::table('user_roles')->insert([
            'user_id' => $caregiver->id, 'role' => 'ADMIN', 'assigned_at' => now(),
        ]);
        $id = $fixture['draft_listing_id'];

        $this->actingAs($caregiver)->postJson("/api/caregiver/listings/{$id}/submit")->assertOk();
        $this->postJson("/api/admin/listings/{$id}/approve")->assertForbidden();
        $this->postJson("/api/admin/listings/{$id}/reject")->assertForbidden();
        $this->assertDatabaseHas('listings', ['id' => $id, 'status' => 'PENDING_REVIEW', 'version' => 2]);
    }

    public function test_audit_records_before_after_version_and_request_correlation(): void
    {
        $fixture = BookingFixture::make();
        $requestId = '4ccbf595-bdb0-4ba4-8b35-8640c8ccdb76';
        $id = $fixture['draft_listing_id'];

        $this->actingAs($fixture['caregiver'])
            ->withHeader('X-Request-ID', $requestId)
            ->postJson("/api/caregiver/listings/{$id}/submit")
            ->assertOk();

        $audit = DB::table('audit_logs')->where('action', 'LISTING_SUBMITTED')->first();
        $metadata = json_decode($audit->metadata_json, true, flags: JSON_THROW_ON_ERROR);
        $this->assertSame($requestId, $audit->request_id);
        $this->assertSame(['status' => 'DRAFT', 'version' => 1, 'suspension_source' => null], array_intersect_key($metadata['from'], array_flip(['status', 'version', 'suspension_source'])));
        $this->assertSame(['status' => 'PENDING_REVIEW', 'version' => 2, 'suspension_source' => null], array_intersect_key($metadata['to'], array_flip(['status', 'version', 'suspension_source'])));
    }

    public function test_lifecycle_withdrawal_audit_shares_request_id_and_records_listing_versions(): void
    {
        $fixture = BookingFixture::make();
        $caregiver = $fixture['caregiver'];
        $listingId = $fixture['active_listing_id'];
        $this->actingAs($caregiver)->postJson("/api/caregiver/listings/{$listingId}/change-requests", [
            'base_listing_version' => 1,
            'changes' => ['title' => 'Proposed title', 'media' => []],
        ])->assertCreated();

        $requestId = 'fb8ea28a-9da3-40ed-97f5-5bfb2a65cd80';
        $this->withHeader('X-Request-ID', $requestId)
            ->postJson("/api/caregiver/listings/{$listingId}/suspend", ['reason' => 'Temporarily unavailable.'])
            ->assertOk();
        $changeId = DB::table('listing_change_requests')->where('listing_id', $listingId)->value('id');
        $this->assertDatabaseHas('listing_change_requests', [
            'id' => $changeId, 'status' => 'WITHDRAWN', 'reviewed_by_user_id' => null,
            'reviewed_at' => null, 'decision_reason' => null,
        ]);

        $withdrawal = DB::table('audit_logs')->where('action', 'LISTING_CHANGE_WITHDRAWN')->first();
        $metadata = json_decode($withdrawal->metadata_json, true, flags: JSON_THROW_ON_ERROR);
        $this->assertSame($requestId, $withdrawal->request_id);
        $this->assertSame('ACTIVE', $metadata['from']['listing_status']);
        $this->assertSame(1, $metadata['from']['listing_version']);
        $this->assertSame('SUSPENDED', $metadata['to']['listing_status']);
        $this->assertSame(2, $metadata['to']['listing_version']);
        $this->assertSame('WITHDRAWN', $metadata['to']['change_request_status']);
    }

    public function test_audit_failure_rolls_back_listing_mutation(): void
    {
        $fixture = BookingFixture::make();
        $listingId = $fixture['draft_listing_id'];
        $workflow = Mockery::mock(ListingWorkflow::class)->makePartial();
        $workflow->shouldReceive('snapshot')->andReturnUsing(fn ($listing) => [
            'status' => $listing->status, 'version' => (int) $listing->version,
        ]);
        $workflow->shouldReceive('audit')->once()->andThrow(new RuntimeException('audit storage unavailable'));
        $this->app->instance(ListingWorkflow::class, $workflow);

        $this->actingAs($fixture['caregiver'])
            ->postJson("/api/caregiver/listings/{$listingId}/submit")
            ->assertInternalServerError();

        $this->assertDatabaseHas('listings', ['id' => $listingId, 'status' => 'DRAFT', 'version' => 1]);
        $this->assertDatabaseMissing('audit_logs', ['resource_type' => 'LISTING', 'resource_id' => $listingId]);
    }

    public function test_noop_draft_update_preserves_version_and_slot_writes_do_not_change_listing_version(): void
    {
        $fixture = BookingFixture::make();
        $caregiver = $fixture['caregiver'];
        $draftId = $fixture['draft_listing_id'];
        $draft = DB::table('listings')->where('id', $draftId)->first();

        $this->actingAs($caregiver)->patchJson("/api/caregiver/listings/{$draftId}", ['title' => $draft->title])->assertOk();
        $this->assertDatabaseHas('listings', ['id' => $draftId, 'version' => 1]);

        $activeId = $fixture['active_listing_id'];
        $version = DB::table('listings')->where('id', $activeId)->value('version');
        $this->postJson("/api/caregiver/listings/{$activeId}/availability-slots", [
            'start_at' => now()->addDays(30)->toIso8601String(),
            'end_at' => now()->addDays(30)->addHour()->toIso8601String(),
        ])->assertCreated();
        $this->assertDatabaseHas('listings', ['id' => $activeId, 'version' => $version]);
    }

    private function admin(): User
    {
        $user = User::factory()->create();
        DB::table('user_roles')->insert(['user_id' => $user->id, 'role' => 'ADMIN', 'assigned_at' => now()]);

        return $user;
    }
}
