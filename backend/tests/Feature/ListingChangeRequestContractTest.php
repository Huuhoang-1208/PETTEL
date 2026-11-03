<?php

namespace Tests\Feature;

use App\Domain\Listing\Actions\ListingWorkflow;
use App\Models\MediaObject;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Mockery;
use RuntimeException;
use Tests\Support\BookingFixture;
use Tests\Support\ListingFixture;
use Tests\TestCase;

class ListingChangeRequestContractTest extends TestCase
{
    use RefreshDatabase;

    public function test_change_request_enforces_allowlist_noop_stale_base_and_single_pending(): void
    {
        $fixture = ListingFixture::make();
        $caregiver = $fixture['caregiver'];
        $id = $fixture['active_listing_id'];
        $version = 1;

        $this->actingAs($caregiver)->postJson("/api/caregiver/listings/{$id}/change-requests", [
            'base_listing_version' => $version,
            'changes' => ['title' => 'Test daycare'],
        ])->assertUnprocessable()->assertJsonPath('error.code', 'VALIDATION_ERROR');
        $this->postJson("/api/caregiver/listings/{$id}/change-requests", [
            'base_listing_version' => $version,
            'changes' => ['internal_field' => 'not allowed'],
        ])->assertUnprocessable()->assertJsonPath('error.code', 'VALIDATION_ERROR');
        $this->postJson("/api/caregiver/listings/{$id}/change-requests", [
            'base_listing_version' => 99,
            'changes' => ['title' => 'Stale proposal'],
        ])->assertConflict()->assertJsonPath('error.code', 'VERSION_CONFLICT');
        $this->postJson("/api/caregiver/listings/{$id}/change-requests", [
            'base_listing_version' => $version,
            'changes' => ['media' => [[
                'media_object_id' => '1', 'sort_order' => 1, 'object_key' => 'forged/key',
            ]]],
        ])->assertUnprocessable()->assertJsonPath('error.code', 'VALIDATION_ERROR');

        $created = $this->postJson("/api/caregiver/listings/{$id}/change-requests", [
            'base_listing_version' => $version,
            'changes' => ['title' => 'Proposed title'],
        ])->assertCreated()->assertJsonPath('data.status', 'PENDING_REVIEW');
        $requestId = $created->json('data.id');
        $this->assertDatabaseHas('listings', ['id' => $id, 'title' => 'Test daycare', 'version' => 1]);
        $this->getJson("/api/listings/{$id}")->assertOk()->assertJsonPath('data.title', 'Test daycare');
        $this->postJson("/api/caregiver/listings/{$id}/change-requests", [
            'base_listing_version' => $version,
            'changes' => ['description' => 'Second pending proposal'],
        ])->assertConflict()->assertJsonPath('error.code', 'INVALID_STATE');

        $this->getJson("/api/caregiver/listings/{$id}/change-requests")->assertOk();
        $this->getJson("/api/caregiver/listings/{$id}/change-requests/{$requestId}")
            ->assertOk()->assertJsonPath('data.status', 'PENDING_REVIEW');
        $this->actingAs($fixture['other_caregiver'])
            ->getJson("/api/caregiver/listings/{$id}/change-requests/{$requestId}")->assertNotFound();
        $this->actingAs($caregiver)
            ->getJson("/api/caregiver/listings/{$fixture['draft_listing_id']}/change-requests/{$requestId}")->assertNotFound();
    }

    public function test_owner_can_withdraw_pending_request_without_changing_listing_or_deleting_proposed_media(): void
    {
        Storage::fake('local');
        $fixture = ListingFixture::make();
        $caregiver = $fixture['caregiver'];
        $id = $fixture['active_listing_id'];
        $media = $this->media($caregiver);
        Storage::disk('local')->put($media->object_key, 'image-bytes');
        Storage::disk('local')->put($this->thumbnailKey($media->object_key), 'thumbnail-bytes');
        $created = $this->actingAs($caregiver)->postJson("/api/caregiver/listings/{$id}/change-requests", [
            'base_listing_version' => 1,
            'changes' => [
                'title' => 'Proposed title',
                'media' => [['media_object_id' => (string) $media->id, 'sort_order' => 1]],
            ],
        ])->assertCreated();
        $requestId = $created->json('data.id');

        $this->actingAs($fixture['other_caregiver'])
            ->postJson("/api/caregiver/listings/{$id}/change-requests/{$requestId}/withdraw")->assertNotFound();
        $this->actingAs($caregiver)
            ->postJson("/api/caregiver/listings/{$id}/change-requests/{$requestId}/withdraw")
            ->assertOk()->assertJsonPath('data.status', 'WITHDRAWN');
        $this->assertDatabaseHas('listing_change_requests', [
            'id' => $requestId, 'status' => 'WITHDRAWN', 'reviewed_by_user_id' => null,
            'reviewed_at' => null, 'decision_reason' => null,
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'LISTING_CHANGE_WITHDRAWN', 'resource_type' => 'LISTING_CHANGE_REQUEST',
            'resource_id' => $requestId, 'actor_user_id' => $caregiver->id,
        ]);
        $this->assertDatabaseHas('listings', ['id' => $id, 'status' => 'ACTIVE', 'title' => 'Test daycare', 'version' => 1]);
        $this->assertDatabaseHas('media_objects', ['id' => $media->id, 'state' => 'READY', 'visibility' => 'PRIVATE']);
        Storage::disk('local')->assertExists($media->object_key);
        Storage::disk('local')->assertExists($this->thumbnailKey($media->object_key));
        $this->getJson("/api/listings/{$id}")->assertOk()->assertJsonPath('data.title', 'Test daycare')->assertJsonPath('data.media', []);
        $this->postJson("/api/caregiver/listings/{$id}/change-requests/{$requestId}/withdraw")->assertConflict();
    }

    public function test_approved_request_applies_content_and_media_once_without_changing_booking_snapshots(): void
    {
        Storage::fake('local');
        $fixture = BookingFixture::requested($this);
        $caregiver = $fixture['caregiver'];
        $admin = $this->admin();
        $id = $fixture['active_listing_id'];
        $media = $this->media($caregiver);
        $bookingBefore = DB::table('bookings')->where('id', $fixture['booking']->id)->first();
        $eventsBefore = DB::table('booking_status_events')->where('booking_id', $fixture['booking']->id)->get()->toArray();
        $created = $this->actingAs($caregiver)->postJson("/api/caregiver/listings/{$id}/change-requests", [
            'base_listing_version' => 1,
            'changes' => [
                'title' => 'Approved title',
                'price_vnd' => 60000,
                'media' => [['media_object_id' => (string) $media->id, 'sort_order' => 1]],
            ],
        ])->assertCreated();
        $requestId = $created->json('data.id');
        $this->getJson("/api/listings/{$id}")->assertOk()->assertJsonPath('data.title', 'Test daycare');

        $this->actingAs($admin)->postJson("/api/admin/listings/{$id}/change-requests/{$requestId}/approve")
            ->assertOk()->assertJsonPath('data.status', 'APPROVED')->assertJsonPath('data.listing_version', 2);
        $this->assertDatabaseHas('listings', ['id' => $id, 'title' => 'Approved title', 'price_vnd' => 60000, 'version' => 2]);
        $this->assertDatabaseHas('listing_media', ['listing_id' => $id, 'media_object_id' => $media->id, 'sort_order' => 1]);
        $this->assertDatabaseHas('media_objects', ['id' => $media->id, 'visibility' => 'PUBLIC_ELIGIBLE', 'state' => 'READY']);
        $this->assertEquals($bookingBefore, DB::table('bookings')->where('id', $fixture['booking']->id)->first());
        $this->assertEquals($eventsBefore, DB::table('booking_status_events')->where('booking_id', $fixture['booking']->id)->get()->toArray());
        $this->assertSame(1, DB::table('audit_logs')->where('action', 'LISTING_CHANGE_APPROVED')->where('resource_id', $id)->count());

        $this->postJson("/api/admin/listings/{$id}/change-requests/{$requestId}/approve")->assertConflict();
        $this->assertDatabaseHas('listings', ['id' => $id, 'title' => 'Approved title', 'version' => 2]);
        $this->assertSame(1, DB::table('audit_logs')->where('action', 'LISTING_CHANGE_APPROVED')->where('resource_id', $id)->count());
    }

    public function test_rejected_request_requires_reason_and_leaves_public_listing_and_media_unchanged(): void
    {
        $fixture = ListingFixture::make();
        $caregiver = $fixture['caregiver'];
        $admin = $this->admin();
        $id = $fixture['active_listing_id'];
        $media = $this->media($caregiver);
        $created = $this->actingAs($caregiver)->postJson("/api/caregiver/listings/{$id}/change-requests", [
            'base_listing_version' => 1,
            'changes' => [
                'title' => 'Rejected title',
                'media' => [['media_object_id' => (string) $media->id, 'sort_order' => 1]],
            ],
        ])->assertCreated();
        $requestId = $created->json('data.id');

        $this->actingAs($admin)->postJson("/api/admin/listings/{$id}/change-requests/{$requestId}/reject")
            ->assertUnprocessable();
        $this->postJson("/api/admin/listings/{$id}/change-requests/{$requestId}/reject", ['reason' => 'Please retain the current approved content.'])
            ->assertOk()->assertJsonPath('data.status', 'REJECTED');
        $this->getJson("/api/listings/{$id}")->assertOk()->assertJsonPath('data.title', 'Test daycare')->assertJsonPath('data.media', []);
        $this->assertDatabaseHas('listings', ['id' => $id, 'title' => 'Test daycare', 'version' => 1]);
        $this->assertDatabaseHas('media_objects', ['id' => $media->id, 'visibility' => 'PRIVATE', 'state' => 'READY']);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'LISTING_CHANGE_REJECTED', 'resource_id' => $id,
            'reason' => 'Please retain the current approved content.',
        ]);
        $this->postJson("/api/admin/listings/{$id}/change-requests/{$requestId}/reject", ['reason' => 'Replay'])->assertConflict();
    }

    public function test_suspended_listing_change_approval_does_not_restore_it_and_ineligible_caregiver_cannot_be_approved(): void
    {
        $fixture = ListingFixture::make();
        $caregiver = $fixture['caregiver'];
        $admin = $this->admin();
        $id = $fixture['active_listing_id'];
        DB::table('listings')->where('id', $id)->update(['status' => 'SUSPENDED', 'suspension_source' => 'CAREGIVER']);
        $created = $this->actingAs($caregiver)->postJson("/api/caregiver/listings/{$id}/change-requests", [
            'base_listing_version' => 1,
            'changes' => ['title' => 'Approved while suspended'],
        ])->assertCreated();
        $requestId = $created->json('data.id');

        $this->actingAs($admin)->postJson("/api/admin/listings/{$id}/change-requests/{$requestId}/approve")
            ->assertOk()->assertJsonPath('data.status', 'APPROVED');
        $this->assertDatabaseHas('listings', [
            'id' => $id, 'status' => 'SUSPENDED', 'suspension_source' => 'CAREGIVER',
            'title' => 'Approved while suspended', 'version' => 2,
        ]);

        $other = ListingFixture::make();
        $otherId = $other['active_listing_id'];
        $pending = $this->actingAs($other['caregiver'])->postJson("/api/caregiver/listings/{$otherId}/change-requests", [
            'base_listing_version' => 1, 'changes' => ['title' => 'Must not be approved'],
        ])->assertCreated();
        $other['caregiver']->status = 'DEACTIVATED';
        $other['caregiver']->save();
        $this->actingAs($admin)->postJson("/api/admin/listings/{$otherId}/change-requests/{$pending->json('data.id')}/approve")
            ->assertConflict()->assertJsonPath('error.code', 'INVALID_STATE');
        $this->assertDatabaseHas('listing_change_requests', ['id' => $pending->json('data.id'), 'status' => 'PENDING_REVIEW']);
        $this->assertDatabaseHas('listings', ['id' => $otherId, 'title' => 'Test daycare', 'version' => 1]);
    }

    public function test_stale_change_request_cannot_be_applied(): void
    {
        $fixture = ListingFixture::make();
        $caregiver = $fixture['caregiver'];
        $admin = $this->admin();
        $id = $fixture['active_listing_id'];
        $created = $this->actingAs($caregiver)->postJson("/api/caregiver/listings/{$id}/change-requests", [
            'base_listing_version' => 1, 'changes' => ['title' => 'Stale title'],
        ])->assertCreated();
        DB::table('listings')->where('id', $id)->update(['version' => 2]);

        $this->actingAs($admin)->postJson("/api/admin/listings/{$id}/change-requests/{$created->json('data.id')}/approve")
            ->assertConflict()->assertJsonPath('error.code', 'VERSION_CONFLICT');
        $this->assertDatabaseHas('listing_change_requests', ['id' => $created->json('data.id'), 'status' => 'PENDING_REVIEW']);
        $this->assertDatabaseHas('listings', ['id' => $id, 'title' => 'Test daycare', 'version' => 2]);
    }

    public function test_admin_cannot_review_their_own_change_request(): void
    {
        $fixture = BookingFixture::make();
        $caregiver = $fixture['caregiver'];
        DB::table('user_roles')->insert(['user_id' => $caregiver->id, 'role' => 'ADMIN', 'assigned_at' => now()]);
        $id = $fixture['active_listing_id'];
        $created = $this->actingAs($caregiver)->postJson("/api/caregiver/listings/{$id}/change-requests", [
            'base_listing_version' => 1, 'changes' => ['title' => 'Self approved'],
        ])->assertCreated();
        $requestId = $created->json('data.id');

        $this->postJson("/api/admin/listings/{$id}/change-requests/{$requestId}/approve")->assertForbidden();
        $this->postJson("/api/admin/listings/{$id}/change-requests/{$requestId}/reject", ['reason' => 'Self reject'])->assertForbidden();
        $this->assertDatabaseHas('listing_change_requests', ['id' => $requestId, 'status' => 'PENDING_REVIEW']);
        $this->assertDatabaseHas('listings', ['id' => $id, 'title' => 'Test daycare', 'version' => 1]);
    }

    public function test_change_approval_rolls_back_all_writes_on_audit_or_pivot_failure(): void
    {
        foreach (['audit', 'pivot'] as $failurePoint) {
            $this->app->instance(ListingWorkflow::class, new ListingWorkflow);
            $fixture = ListingFixture::make();
            $caregiver = $fixture['caregiver'];
            $admin = $this->admin();
            $id = $fixture['active_listing_id'];
            $oldMedia = $this->media($caregiver, 'PUBLIC_ELIGIBLE');
            $newMedia = $this->media($caregiver);
            DB::table('listing_media')->insert(['listing_id' => $id, 'media_object_id' => $oldMedia->id, 'sort_order' => 1]);
            $created = $this->actingAs($caregiver)->postJson("/api/caregiver/listings/{$id}/change-requests", [
                'base_listing_version' => 1,
                'changes' => [
                    'title' => 'Must roll back',
                    'media' => [['media_object_id' => (string) $newMedia->id, 'sort_order' => 1]],
                ],
            ])->assertCreated();
            $requestId = $created->json('data.id');

            $workflow = Mockery::mock(ListingWorkflow::class)->makePartial();
            if ($failurePoint === 'audit') {
                $workflow->shouldReceive('audit')->once()->andThrow(new RuntimeException('audit write failed'));
            } else {
                $workflow->shouldReceive('setMedia')->once()->andReturnUsing(function ($listing): never {
                    $listing->media()->detach();
                    throw new RuntimeException('pivot write failed');
                });
            }
            $this->app->instance(ListingWorkflow::class, $workflow);

            $this->actingAs($admin)->postJson("/api/admin/listings/{$id}/change-requests/{$requestId}/approve")
                ->assertInternalServerError();
            $this->assertDatabaseHas('listings', ['id' => $id, 'title' => 'Test daycare', 'version' => 1]);
            $this->assertDatabaseHas('listing_media', ['listing_id' => $id, 'media_object_id' => $oldMedia->id, 'sort_order' => 1]);
            $this->assertDatabaseMissing('listing_media', ['listing_id' => $id, 'media_object_id' => $newMedia->id]);
            $this->assertDatabaseHas('media_objects', ['id' => $newMedia->id, 'visibility' => 'PRIVATE', 'state' => 'READY']);
            $this->assertDatabaseHas('listing_change_requests', ['id' => $requestId, 'status' => 'PENDING_REVIEW']);
            $this->assertDatabaseMissing('audit_logs', ['action' => 'LISTING_CHANGE_APPROVED', 'resource_id' => $id]);
        }
    }

    public function test_management_resources_and_change_history_follow_summary_pagination_and_id_contract(): void
    {
        $fixture = ListingFixture::make();
        $caregiver = $fixture['caregiver'];
        $admin = $this->admin();
        $id = $fixture['active_listing_id'];

        $this->actingAs($caregiver)->getJson('/api/caregiver/listings')
            ->assertOk()->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.id', (string) $fixture['draft_listing_id'])
            ->assertJsonPath('data.0.status', 'DRAFT');
        $this->getJson('/api/caregiver/listings?status=ALL&per_page=1')
            ->assertOk()->assertJsonStructure(['data', 'links', 'meta'])
            ->assertJsonPath('meta.total', 2)->assertJsonPath('meta.per_page', 1);
        $this->getJson('/api/caregiver/listings?status=ALL&per_page=1&page=2')
            ->assertOk()->assertJsonPath('data.0.id', (string) $id)
            ->assertJsonPath('data.0.status', 'ACTIVE');
        $this->getJson('/api/caregiver/listings?status=INVALID')
            ->assertUnprocessable()->assertJsonPath('error.code', 'VALIDATION_ERROR');

        $this->actingAs($admin)->getJson('/api/admin/listings')
            ->assertOk()->assertJsonPath('meta.total', 0);
        $this->getJson('/api/admin/listings?status=ALL&per_page=1')
            ->assertOk()->assertJsonStructure(['data', 'links', 'meta'])
            ->assertJsonPath('meta.total', 3)->assertJsonPath('meta.per_page', 1);
        $this->getJson('/api/admin/listings?status=INVALID')
            ->assertUnprocessable()->assertJsonPath('error.code', 'VALIDATION_ERROR');

        $created = $this->actingAs($caregiver)->postJson("/api/caregiver/listings/{$id}/change-requests", [
            'base_listing_version' => 1,
            'changes' => ['title' => 'Contract title'],
        ])->assertCreated();
        $requestId = $created->json('data.id');
        $this->assertSame($this->latestChangeId($id), $requestId);

        $this->getJson("/api/caregiver/listings/{$id}")->assertOk()
            ->assertJsonPath('data.pending_change_request_id', (string) $requestId)
            ->assertJsonPath('data.latest_decision', null);
        $history = $this->getJson("/api/caregiver/listings/{$id}/change-requests?per_page=1")
            ->assertOk()->assertJsonStructure(['data', 'links', 'meta'])
            ->assertJsonPath('meta.per_page', 1)
            ->assertJsonPath('data.0.id', (string) $requestId)
            ->assertJsonPath('data.0.listing_id', (string) $id)
            ->assertJsonPath('data.0.reviewed_by_user_id', null)
            ->assertJsonPath('data.0.reviewed_at', null);
        $this->assertMatchesRegularExpression('/Z$/', $history->json('data.0.requested_at'));
        $this->getJson("/api/caregiver/listings/{$id}/change-requests?unexpected=1")
            ->assertUnprocessable()->assertJsonPath('error.code', 'VALIDATION_ERROR');
        $this->getJson("/api/caregiver/listings/{$id}?unexpected=1")
            ->assertUnprocessable()->assertJsonPath('error.code', 'VALIDATION_ERROR');

        $this->actingAs($admin)->getJson("/api/admin/listings/{$id}")->assertOk()
            ->assertJsonPath('data.pending_change_request_id', (string) $requestId)
            ->assertJsonPath('data.latest_decision', null);
        $this->postJson("/api/admin/listings/{$id}/change-requests/{$requestId}/approve")
            ->assertOk()->assertJsonPath('data.id', (string) $requestId)
            ->assertJsonPath('data.reviewed_by_user_id', (string) $admin->id);

        $detail = $this->actingAs($caregiver)->getJson("/api/caregiver/listings/{$id}")->assertOk()
            ->assertJsonPath('data.pending_change_request_id', null)
            ->assertJsonPath('data.latest_decision.action', 'LISTING_CHANGE_APPROVED');
        $this->assertMatchesRegularExpression('/Z$/', $detail->json('data.latest_decision.decided_at'));
        $this->actingAs($admin)->getJson('/api/admin/listing-change-requests?status=ALL&per_page=1')
            ->assertOk()->assertJsonStructure(['data', 'links', 'meta'])
            ->assertJsonPath('meta.per_page', 1)
            ->assertJsonPath('data.0.id', (string) $requestId);
        $this->getJson('/api/admin/listing-change-requests?sort=latest')
            ->assertUnprocessable()->assertJsonPath('error.code', 'VALIDATION_ERROR');
    }

    private function latestChangeId(int|string $listingId): string
    {
        return (string) DB::table('listing_change_requests')->where('listing_id', $listingId)->max('id');
    }

    private function media(User $owner, string $visibility = 'PRIVATE'): MediaObject
    {
        $media = new MediaObject([
            'object_key' => 'listings/'.$owner->id.'/request-'.fake()->uuid().'.png',
            'mime_type' => 'image/png', 'byte_size' => 100, 'sha256' => hash('sha256', fake()->uuid()),
        ]);
        $media->owner()->associate($owner);
        $media->visibility = $visibility;
        $media->save();

        return $media;
    }

    private function thumbnailKey(string $key): string
    {
        return pathinfo($key, PATHINFO_DIRNAME).'/'.pathinfo($key, PATHINFO_FILENAME).'-thumb.webp';
    }

    private function admin(): User
    {
        $user = User::factory()->create();
        DB::table('user_roles')->insert(['user_id' => $user->id, 'role' => 'ADMIN', 'assigned_at' => now()]);

        return $user;
    }
}
