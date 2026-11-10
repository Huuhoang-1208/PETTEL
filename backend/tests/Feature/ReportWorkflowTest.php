<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Support\BookingFixture;
use Tests\TestCase;

class ReportWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_report_creation_records_open_history_and_correlates_request_id(): void
    {
        $fixture = BookingFixture::make();
        $requestId = (string) Str::uuid();

        $response = $this->actingAs($fixture['owner'])
            ->withHeader('X-Request-ID', $requestId)
            ->postJson('/api/reports', [
                'listing_id' => $fixture['active_listing_id'],
                'reason_code' => 'SAFETY_CONCERN',
                'description' => 'Please review this listing.',
            ])
            ->assertCreated()
            ->assertHeader('X-Request-ID', $requestId)
            ->assertJsonPath('data.status', 'OPEN');

        $reportId = $response->json('data.id');

        $this->assertDatabaseHas('reports', [
            'id' => $reportId,
            'reporter_user_id' => $fixture['owner']->id,
            'listing_id' => $fixture['active_listing_id'],
            'status' => 'OPEN',
        ]);
        $this->assertDatabaseHas('report_status_events', [
            'report_id' => $reportId,
            'actor_user_id' => $fixture['owner']->id,
            'from_status' => null,
            'to_status' => 'OPEN',
            'request_id' => $requestId,
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'actor_user_id' => $fixture['owner']->id,
            'action' => 'REPORT_CREATED',
            'resource_type' => 'REPORT',
            'resource_id' => $reportId,
            'request_id' => $requestId,
        ]);
    }

    public function test_reporter_can_only_list_their_own_reports(): void
    {
        $fixture = BookingFixture::make();
        $this->actingAs($fixture['owner'])->postJson('/api/reports', [
            'listing_id' => $fixture['active_listing_id'],
            'reason_code' => 'OTHER',
        ])->assertCreated();

        $secondReporter = User::factory()->create();
        $this->actingAs($secondReporter)->postJson('/api/reports', [
            'listing_id' => $fixture['draft_listing_id'],
            'reason_code' => 'OTHER',
        ])->assertCreated();

        $this->actingAs($fixture['owner'])->getJson('/api/reports/mine')
            ->assertOk()->assertJsonCount(1, 'data');
        $this->actingAs($secondReporter)->getJson('/api/reports/mine')
            ->assertOk()->assertJsonCount(1, 'data');

        $outsider = User::factory()->create();
        $this->actingAs($outsider)->getJson('/api/reports/mine')
            ->assertOk()->assertJsonCount(0, 'data');
    }

    public function test_non_participant_cannot_report_private_booking_or_message(): void
    {
        $fixture = BookingFixture::requested($this);
        $outsider = User::factory()->create();
        $messageId = DB::table('messages')->insertGetId([
            'booking_id' => $fixture['booking']->id,
            'sender_id' => $fixture['owner']->id,
            'body' => 'Private booking message.',
            'visibility' => 'VISIBLE',
            'created_at' => now('UTC'),
        ]);

        $this->actingAs($outsider)->postJson('/api/reports', [
            'booking_id' => $fixture['booking']->id,
            'reason_code' => 'OTHER',
        ])->assertNotFound();

        $this->actingAs($outsider)->postJson('/api/reports', [
            'message_id' => $messageId,
            'reason_code' => 'OTHER',
        ])->assertNotFound();

        $this->assertDatabaseCount('reports', 0);
    }

    public function test_admin_status_workflow_records_history_and_blocks_non_admin(): void
    {
        $fixture = BookingFixture::make();
        $created = $this->actingAs($fixture['owner'])->postJson('/api/reports', [
            'listing_id' => $fixture['active_listing_id'],
            'reason_code' => 'OTHER',
        ])->assertCreated();
        $reportId = $created->json('data.id');
        $admin = $this->admin();

        $this->actingAs($fixture['owner'])->postJson("/api/admin/reports/{$reportId}/status", [
            'status' => 'IN_REVIEW',
            'reason' => 'Start review',
        ])->assertForbidden();

        $requestId = (string) Str::uuid();
        $this->actingAs($admin)->withHeader('X-Request-ID', $requestId)
            ->getJson('/api/admin/reports?status=OPEN')
            ->assertOk()->assertJsonPath('data.0.id', (string) $reportId);

        $this->actingAs($admin)->postJson("/api/admin/reports/{$reportId}/status", [
            'status' => 'IN_REVIEW',
            'reason' => 'Review started.',
        ])->assertOk()->assertJsonPath('data.status', 'IN_REVIEW');

        $this->actingAs($admin)->postJson("/api/admin/reports/{$reportId}/status", [
            'status' => 'RESOLVED',
            'reason' => 'Reviewed and resolved.',
        ])->assertOk()->assertJsonPath('data.status', 'RESOLVED');

        $this->actingAs($admin)->getJson("/api/admin/reports/{$reportId}/history")
            ->assertOk()
            ->assertJsonCount(3, 'data')
            ->assertJsonPath('data.0.to_status', 'OPEN')
            ->assertJsonPath('data.1.to_status', 'IN_REVIEW')
            ->assertJsonPath('data.2.to_status', 'RESOLVED');

        $this->assertDatabaseHas('report_status_events', [
            'report_id' => $reportId,
            'actor_user_id' => $admin->id,
            'from_status' => 'OPEN',
            'to_status' => 'IN_REVIEW',
            'reason' => 'Review started.',
        ]);
    }

    public function test_private_evidence_requires_admin_and_is_audited_without_copying_content(): void
    {
        $fixture = BookingFixture::requested($this);
        $messageBody = 'Evidence text that must not enter audit metadata.';
        $messageId = DB::table('messages')->insertGetId([
            'booking_id' => $fixture['booking']->id,
            'sender_id' => $fixture['owner']->id,
            'body' => $messageBody,
            'visibility' => 'VISIBLE',
            'created_at' => now('UTC'),
        ]);

        $created = $this->actingAs($fixture['owner'])->postJson('/api/reports', [
            'message_id' => $messageId,
            'reason_code' => 'HARASSMENT',
            'description' => 'Please review this private message.',
        ])->assertCreated();
        $reportId = $created->json('data.id');
        $admin = $this->admin();

        $this->actingAs($fixture['owner'])->getJson("/api/admin/reports/{$reportId}/evidence")
            ->assertForbidden();

        $this->actingAs($admin)->getJson("/api/admin/reports/{$reportId}/evidence")
            ->assertForbidden();

        $this->actingAs($admin)->postJson("/api/admin/reports/{$reportId}/status", [
            'status' => 'IN_REVIEW',
            'reason' => 'Review private evidence.',
        ])->assertOk();

        $requestId = (string) Str::uuid();
        $this->actingAs($admin)->withHeader('X-Request-ID', $requestId)
            ->getJson("/api/admin/reports/{$reportId}/evidence")
            ->assertOk()
            ->assertHeader('X-Request-ID', $requestId)
            ->assertJsonPath('data.type', 'MESSAGE')
            ->assertJsonPath('data.body', $messageBody)
            ->assertJsonMissingPath('data.attachment_url');

        $audit = DB::table('audit_logs')
            ->where('resource_type', 'REPORT')
            ->where('resource_id', $reportId)
            ->where('action', 'REPORT_EVIDENCE_VIEWED')
            ->first();

        $this->assertNotNull($audit);
        $this->assertSame($requestId, $audit->request_id);
        $this->assertStringNotContainsString($messageBody, (string) $audit->metadata_json);
    }

    public function test_shared_moderation_hides_listing_without_mutating_booking(): void
    {
        $fixture = BookingFixture::requested($this);
        $created = $this->actingAs($fixture['owner'])->postJson('/api/reports', [
            'listing_id' => $fixture['active_listing_id'],
            'reason_code' => 'SAFETY_CONCERN',
        ])->assertCreated();
        $reportId = $created->json('data.id');
        $admin = $this->admin();

        $this->actingAs($admin)->postJson("/api/admin/reports/{$reportId}/status", [
            'status' => 'IN_REVIEW',
            'reason' => 'Review listing report.',
        ])->assertOk();

        $booking = $fixture['booking']->fresh();
        $bookingEventsBefore = DB::table('booking_status_events')
            ->where('booking_id', $booking->id)->count();

        $this->actingAs($admin)->postJson("/api/admin/reports/{$reportId}/moderation", [
            'action' => 'HIDE',
            'reason' => 'Hide listing while reviewing report.',
        ])->assertOk();

        $this->assertDatabaseHas('listings', [
            'id' => $fixture['active_listing_id'],
            'status' => 'SUSPENDED',
            'suspension_source' => 'ADMIN',
        ]);
        $this->assertDatabaseHas('moderation_actions', [
            'report_id' => $reportId,
            'admin_user_id' => $admin->id,
            'action' => 'LISTING_HIDDEN',
            'target_type' => 'LISTING',
            'target_id' => $fixture['active_listing_id'],
            'reason' => 'Hide listing while reviewing report.',
        ]);
        $this->assertDatabaseHas('bookings', [
            'id' => $booking->id,
            'status' => $booking->status,
            'version' => $booking->version,
            'instructions_snapshot' => $booking->instructions_snapshot,
        ]);
        $this->assertSame($bookingEventsBefore, DB::table('booking_status_events')
            ->where('booking_id', $booking->id)->count());
    }

    public function test_shared_moderation_hides_review_through_admin_endpoint(): void
    {
        $fixture = BookingFixture::requested($this);
        $fixture['booking']->update(['status' => 'COMPLETED']);
        $review = $this->actingAs($fixture['owner'])->postJson(
            "/api/bookings/{$fixture['booking']->id}/reviews",
            ['rating' => 4, 'body' => 'Review subject to moderation.'],
        )->assertCreated();
        $reviewId = $review->json('data.id');

        $report = $this->actingAs($fixture['caregiver'])->postJson('/api/reports', [
            'review_id' => $reviewId,
            'reason_code' => 'OTHER',
        ])->assertCreated();
        $reportId = $report->json('data.id');
        $admin = $this->admin();

        $this->actingAs($admin)->postJson("/api/admin/reports/{$reportId}/status", [
            'status' => 'IN_REVIEW',
            'reason' => 'Review reported review.',
        ])->assertOk();

        $this->actingAs($admin)->postJson("/api/admin/reports/{$reportId}/moderation", [
            'action' => 'HIDE',
            'reason' => 'Hide reported review.',
        ])->assertOk();

        $this->assertDatabaseHas('reviews', ['id' => $reviewId, 'status' => 'HIDDEN']);
        $this->assertDatabaseHas('moderation_actions', [
            'report_id' => $reportId,
            'admin_user_id' => $admin->id,
            'action' => 'REVIEW_HIDDEN',
            'target_type' => 'REVIEW',
            'target_id' => $reviewId,
        ]);
        $this->assertDatabaseHas('bookings', [
            'id' => $fixture['booking']->id,
            'status' => 'COMPLETED',
        ]);
    }

    public function test_booking_report_evidence_is_limited_audited_and_does_not_change_booking(): void
    {
        $fixture = BookingFixture::requested($this);
        $booking = $fixture['booking'];
        $bookingBefore = $booking->fresh()->getAttributes();
        $privateText = $fixture['payload']['instructions'];

        $created = $this->actingAs($fixture['owner'])->postJson('/api/reports', [
            'booking_id' => $booking->id,
            'reason_code' => 'SERVICE_DISPUTE',
            'description' => 'Please review this booking.',
        ])->assertCreated();

        $reportId = $created->json('data.id');
        $admin = $this->admin();

        $this->actingAs($admin)
            ->getJson('/api/admin/reports?status=OPEN')
            ->assertOk()
            ->assertJsonPath('data.0.id', (string) $reportId)
            ->assertJsonPath('data.0.target.type', 'BOOKING');

        $this->actingAs($admin)
            ->getJson("/api/admin/reports/{$reportId}")
            ->assertOk()
            ->assertJsonPath('data.status', 'OPEN')
            ->assertJsonMissingPath('data.booking_snapshot')
            ->assertJsonMissingPath('data.instructions_snapshot');

        // Reporter không được xem evidence qua route admin.
        $this->actingAs($fixture['owner'])
            ->getJson("/api/admin/reports/{$reportId}/evidence")
            ->assertForbidden();

        // Admin chỉ xem evidence sau khi đưa report vào xử lý.
        $this->actingAs($admin)
            ->getJson("/api/admin/reports/{$reportId}/evidence")
            ->assertForbidden();

        $this->actingAs($admin)->postJson("/api/admin/reports/{$reportId}/status", [
            'status' => 'IN_REVIEW',
            'reason' => 'Review booking evidence.',
        ])->assertOk();

        $requestId = (string) Str::uuid();

        $this->actingAs($admin)
            ->withHeader('X-Request-ID', $requestId)
            ->getJson("/api/admin/reports/{$reportId}/evidence")
            ->assertOk()
            ->assertHeader('X-Request-ID', $requestId)
            ->assertJsonPath('data.type', 'BOOKING')
            ->assertJsonPath('data.id', (string) $booking->id)
            ->assertJsonPath('data.listing_id', (string) $fixture['active_listing_id'])
            ->assertJsonPath('data.status', 'REQUESTED')
            ->assertJsonMissingPath('data.instructions_snapshot')
            ->assertJsonMissingPath('data.pet_snapshot');

        $audit = DB::table('audit_logs')
            ->where('resource_type', 'REPORT')
            ->where('resource_id', $reportId)
            ->where('action', 'REPORT_EVIDENCE_VIEWED')
            ->first();

        $this->assertNotNull($audit);
        $this->assertSame($requestId, $audit->request_id);
        $this->assertStringNotContainsString($privateText, (string) $audit->metadata_json);

        $this->actingAs($admin)->postJson("/api/admin/reports/{$reportId}/status", [
            'status' => 'DISMISSED',
            'reason' => 'No booking action was required for this evidence-access test.',
        ])->assertOk();

        $this->actingAs($admin)
            ->getJson("/api/admin/reports/{$reportId}/history")
            ->assertOk()
            ->assertJsonCount(3, 'data')
            ->assertJsonPath('data.0.to_status', 'OPEN')
            ->assertJsonPath('data.1.to_status', 'IN_REVIEW')
            ->assertJsonPath('data.2.to_status', 'DISMISSED');

        $this->assertSame($bookingBefore, $booking->fresh()->getAttributes());
    }

    public function test_listing_report_can_be_dismissed_after_hide_and_restore_without_changing_booking(): void
    {
        $fixture = BookingFixture::requested($this);
        $booking = $fixture['booking'];
        $bookingBefore = $booking->fresh()->getAttributes();
        $bookingEventsBefore = DB::table('booking_status_events')
            ->where('booking_id', $booking->id)
            ->count();

        $listingId = $fixture['active_listing_id'];

        $this->getJson("/api/listings/{$listingId}")->assertOk();

        $created = $this->actingAs($fixture['owner'])->postJson('/api/reports', [
            'listing_id' => $listingId,
            'reason_code' => 'SAFETY_CONCERN',
            'description' => 'Please check this listing.',
        ])->assertCreated();

        $reportId = $created->json('data.id');
        $admin = $this->admin();

        $this->actingAs($admin)->postJson("/api/admin/reports/{$reportId}/status", [
            'status' => 'IN_REVIEW',
            'reason' => 'Review listing report.',
        ])->assertOk();

        $this->actingAs($admin)->postJson("/api/admin/reports/{$reportId}/moderation", [
            'action' => 'HIDE',
            'reason' => 'Temporarily hide while reviewing.',
        ])->assertOk();

        $this->assertDatabaseHas('listings', [
            'id' => $listingId,
            'status' => 'SUSPENDED',
            'suspension_source' => 'ADMIN',
        ]);
        $this->getJson("/api/listings/{$listingId}")->assertNotFound();

        $this->actingAs($admin)->postJson("/api/admin/reports/{$reportId}/moderation", [
            'action' => 'RESTORE',
            'reason' => 'Listing passed review.',
        ])->assertOk();

        $this->assertDatabaseHas('listings', [
            'id' => $listingId,
            'status' => 'ACTIVE',
            'suspension_source' => null,
        ]);
        $this->getJson("/api/listings/{$listingId}")->assertOk();

        $this->actingAs($admin)->postJson("/api/admin/reports/{$reportId}/status", [
            'status' => 'DISMISSED',
            'reason' => 'Report was not substantiated.',
        ])->assertOk()->assertJsonPath('data.status', 'DISMISSED');

        $this->actingAs($admin)
            ->getJson("/api/admin/reports/{$reportId}/history")
            ->assertOk()
            ->assertJsonCount(3, 'data')
            ->assertJsonPath('data.0.to_status', 'OPEN')
            ->assertJsonPath('data.1.to_status', 'IN_REVIEW')
            ->assertJsonPath('data.2.to_status', 'DISMISSED')
            ->assertJsonCount(2, 'moderation_actions')
            ->assertJsonPath('moderation_actions.0.action', 'LISTING_HIDDEN')
            ->assertJsonPath('moderation_actions.1.action', 'LISTING_RESTORED');

        $this->assertSame($bookingBefore, $booking->fresh()->getAttributes());
        $this->assertSame(
            $bookingEventsBefore,
            DB::table('booking_status_events')->where('booking_id', $booking->id)->count(),
        );
    }

    public function test_booking_and_listing_reports_complete_admin_workflow_without_changing_booking(): void
    {
        $fixture = BookingFixture::requested($this);
        $booking = $fixture['booking'];
        $bookingBefore = $booking->fresh()->getAttributes();
        $bookingEventsBefore = DB::table('booking_status_events')
            ->where('booking_id', $booking->id)
            ->count();
        $privateText = $fixture['payload']['instructions'];

        // Tạo report riêng cho booking để admin xem evidence giới hạn.
        $bookingReport = $this->actingAs($fixture['owner'])->postJson('/api/reports', [
            'booking_id' => $booking->id,
            'reason_code' => 'SERVICE_DISPUTE',
            'description' => 'Please review this booking.',
        ])->assertCreated();

        $bookingReportId = $bookingReport->json('data.id');

        // Tạo report listing để kiểm tra moderation và public visibility.
        $listingReport = $this->actingAs($fixture['owner'])->postJson('/api/reports', [
            'listing_id' => $fixture['active_listing_id'],
            'reason_code' => 'SAFETY_CONCERN',
            'description' => 'Please review this listing.',
        ])->assertCreated();

        $listingReportId = $listingReport->json('data.id');
        $admin = $this->admin();

        // Admin thấy cả hai report trong queue.
        $this->actingAs($admin)
            ->getJson('/api/admin/reports?status=OPEN')
            ->assertOk()
            ->assertJsonFragment(['id' => (string) $bookingReportId])
            ->assertJsonFragment(['id' => (string) $listingReportId]);

        // Chi tiết report không trả snapshot riêng tư của booking.
        $this->actingAs($admin)
            ->getJson("/api/admin/reports/{$bookingReportId}")
            ->assertOk()
            ->assertJsonPath('data.target.type', 'BOOKING')
            ->assertJsonMissingPath('data.booking_snapshot')
            ->assertJsonMissingPath('data.instructions_snapshot');

        // Đưa cả hai report vào IN_REVIEW.
        foreach ([$bookingReportId, $listingReportId] as $reportId) {
            $this->actingAs($admin)
                ->postJson("/api/admin/reports/{$reportId}/status", [
                    'status' => 'IN_REVIEW',
                    'reason' => 'Admin started reviewing.',
                ])
                ->assertOk()
                ->assertJsonPath('data.status', 'IN_REVIEW');
        }

        // Evidence bị chặn trước khi xét duyệt và được phép sau IN_REVIEW.
        $this->actingAs($admin)
            ->getJson("/api/admin/reports/{$bookingReportId}/evidence")
            ->assertOk()
            ->assertJsonPath('data.type', 'BOOKING')
            ->assertJsonPath('data.id', (string) $booking->id)
            ->assertJsonPath('data.status', 'REQUESTED')
            ->assertJsonMissingPath('data.instructions_snapshot')
            ->assertJsonMissingPath('data.pet_snapshot');

        // Ẩn listing: listing biến mất khỏi API công khai.
        $this->getJson("/api/listings/{$fixture['active_listing_id']}")->assertOk();

        $this->actingAs($admin)
            ->postJson("/api/admin/reports/{$listingReportId}/moderation", [
                'action' => 'HIDE',
                'reason' => 'Hide while reviewing.',
            ])
            ->assertOk();

        $this->assertDatabaseHas('listings', [
            'id' => $fixture['active_listing_id'],
            'status' => 'SUSPENDED',
            'suspension_source' => 'ADMIN',
        ]);
        $this->getJson("/api/listings/{$fixture['active_listing_id']}")->assertNotFound();

        // Khôi phục listing: listing xuất hiện lại trên API công khai.
        $this->actingAs($admin)
            ->postJson("/api/admin/reports/{$listingReportId}/moderation", [
                'action' => 'RESTORE',
                'reason' => 'Listing passed review.',
            ])
            ->assertOk();

        $this->assertDatabaseHas('listings', [
            'id' => $fixture['active_listing_id'],
            'status' => 'ACTIVE',
            'suspension_source' => null,
        ]);
        $this->getJson("/api/listings/{$fixture['active_listing_id']}")->assertOk();

        // Đóng report theo hai kết quả khác nhau.
        $this->actingAs($admin)
            ->postJson("/api/admin/reports/{$bookingReportId}/status", [
                'status' => 'DISMISSED',
                'reason' => 'No booking action was required in this workflow test.',
            ])
            ->assertOk()
            ->assertJsonPath('data.status', 'DISMISSED');

        $this->actingAs($admin)
            ->postJson("/api/admin/reports/{$listingReportId}/status", [
                'status' => 'DISMISSED',
                'reason' => 'Listing report was not substantiated.',
            ])
            ->assertOk()
            ->assertJsonPath('data.status', 'DISMISSED');

        // Lịch sử report booking.
        $this->actingAs($admin)
            ->getJson("/api/admin/reports/{$bookingReportId}/history")
            ->assertOk()
            ->assertJsonCount(3, 'data')
            ->assertJsonPath('data.0.to_status', 'OPEN')
            ->assertJsonPath('data.1.to_status', 'IN_REVIEW')
            ->assertJsonPath('data.2.to_status', 'DISMISSED');

        // Lịch sử report listing và hai moderation action.
        $this->actingAs($admin)
            ->getJson("/api/admin/reports/{$listingReportId}/history")
            ->assertOk()
            ->assertJsonCount(3, 'data')
            ->assertJsonPath('data.0.to_status', 'OPEN')
            ->assertJsonPath('data.1.to_status', 'IN_REVIEW')
            ->assertJsonPath('data.2.to_status', 'DISMISSED')
            ->assertJsonCount(2, 'moderation_actions')
            ->assertJsonPath('moderation_actions.0.action', 'LISTING_HIDDEN')
            ->assertJsonPath('moderation_actions.1.action', 'LISTING_RESTORED');

        // Evidence được audit bằng metadata, không chép nội dung riêng tư vào audit.
        $audit = DB::table('audit_logs')
            ->where('resource_type', 'REPORT')
            ->where('resource_id', $bookingReportId)
            ->where('action', 'REPORT_EVIDENCE_VIEWED')
            ->first();

        $this->assertNotNull($audit);
        $this->assertStringNotContainsString($privateText, (string) $audit->metadata_json);

        // Report/moderation không sửa booking hoặc lịch sử trạng thái booking.
        $this->assertSame($bookingBefore, $booking->fresh()->getAttributes());
        $this->assertSame(
            $bookingEventsBefore,
            DB::table('booking_status_events')
                ->where('booking_id', $booking->id)
                ->count(),
        );
    }

    private function admin(): User
    {
        $admin = User::factory()->create();
        DB::table('user_roles')->insert([
            'user_id' => $admin->id,
            'role' => 'ADMIN',
            'assigned_at' => now(),
        ]);

        return $admin;
    }
}
