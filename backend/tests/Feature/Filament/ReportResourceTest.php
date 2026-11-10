<?php

namespace Tests\Feature\Filament;

use App\Filament\Resources\Reports\Pages\ListReports;
use App\Models\Report;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\Support\BookingFixture;
use Tests\TestCase;

class ReportResourceTest extends TestCase
{
    use RefreshDatabase;

    public function test_active_admin_can_open_report_queue(): void
    {
        $admin = $this->createUserWithRole('ADMIN');

        $this->actingAs($admin, 'web')
            ->get('/admin/reports')
            ->assertOk();
    }

    public function test_non_admin_cannot_open_report_queue(): void
    {
        $owner = $this->createUserWithRole('OWNER');

        $this->actingAs($owner, 'web')
            ->get('/admin/reports')
            ->assertForbidden();
    }

    public function test_report_queue_does_not_render_private_evidence_or_description(): void
    {
        $fixture = BookingFixture::requested($this);
        $admin = $this->createUserWithRole('ADMIN');

        $privateMessage = 'PRIVATE-MESSAGE-EVIDENCE-SENTINEL';
        $privateDescription = 'PRIVATE-REPORT-DESCRIPTION-SENTINEL';

        $messageId = DB::table('messages')->insertGetId([
            'booking_id' => $fixture['booking']->id,
            'sender_id' => $fixture['owner']->id,
            'body' => $privateMessage,
            'visibility' => 'VISIBLE',
            'created_at' => now('UTC'),
        ]);

        $report = $this->createReport(
            $fixture['owner'],
            ['message_id' => $messageId],
            status: 'IN_REVIEW',
            description: $privateDescription,
        );

        $this->actingAs($admin, 'web')
            ->get('/admin/reports')
            ->assertOk()
            ->assertDontSee($privateMessage)
            ->assertDontSee($privateDescription);

        Livewire::actingAs($admin, 'web')
            ->test(ListReports::class)
            ->assertCanSeeTableRecords([$report])
            ->assertDontSee($privateMessage)
            ->assertDontSee($privateDescription);
    }

    public function test_start_review_action_records_status_event_and_audit_log(): void
    {
        $fixture = BookingFixture::make();
        $admin = $this->createUserWithRole('ADMIN');

        $report = $this->createReport(
            $fixture['owner'],
            ['listing_id' => $fixture['active_listing_id']],
        );

        Livewire::actingAs($admin, 'web')
            ->test(ListReports::class)
            ->assertCanSeeTableRecords([$report])
            ->assertTableActionVisible('markInReview', $report)
            ->callTableAction('markInReview', $report, [
                'reason' => 'Admin started reviewing this report.',
            ])
            ->assertHasNoFormErrors()
            ->assertNotified();

        $this->assertDatabaseHas('reports', [
            'id' => $report->id,
            'status' => 'IN_REVIEW',
        ]);

        $this->assertDatabaseHas('report_status_events', [
            'report_id' => $report->id,
            'actor_user_id' => $admin->id,
            'from_status' => 'OPEN',
            'to_status' => 'IN_REVIEW',
            'reason' => 'Admin started reviewing this report.',
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'actor_user_id' => $admin->id,
            'action' => 'REPORT_STATUS_CHANGED',
            'resource_type' => 'REPORT',
            'resource_id' => $report->id,
            'reason' => 'Admin started reviewing this report.',
        ]);

        $event = DB::table('report_status_events')
            ->where('report_id', $report->id)
            ->where('to_status', 'IN_REVIEW')
            ->first();

        $this->assertNotNull($event);
        $this->assertTrue(Str::isUuid((string) $event->request_id));
    }

    public function test_listing_moderation_actions_hide_restore_and_audit(): void
    {
        $fixture = BookingFixture::make();
        $admin = $this->createUserWithRole('ADMIN');
        $listingId = $fixture['active_listing_id'];

        $report = $this->createReport(
            $fixture['owner'],
            ['listing_id' => $listingId],
            status: 'IN_REVIEW',
        );

        $page = Livewire::actingAs($admin, 'web')
            ->test(ListReports::class)
            ->assertCanSeeTableRecords([$report])
            ->assertTableActionVisible('hideTarget', $report)
            ->callTableAction('hideTarget', $report, [
                'reason' => 'Listing violates the safety rules.',
            ])
            ->assertHasNoFormErrors()
            ->assertNotified();

        $this->assertDatabaseHas('listings', [
            'id' => $listingId,
            'status' => 'SUSPENDED',
            'suspension_source' => 'ADMIN',
        ]);

        $this->assertDatabaseHas('moderation_actions', [
            'report_id' => $report->id,
            'admin_user_id' => $admin->id,
            'action' => 'LISTING_HIDDEN',
            'target_type' => 'LISTING',
            'target_id' => $listingId,
            'reason' => 'Listing violates the safety rules.',
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'actor_user_id' => $admin->id,
            'action' => 'LISTING_HIDDEN',
            'resource_type' => 'LISTING',
            'resource_id' => $listingId,
            'reason' => 'Listing violates the safety rules.',
        ]);

        Livewire::actingAs($admin, 'web')
            ->test(ListReports::class)
            ->assertCanSeeTableRecords([$report->fresh()])
            ->assertTableActionVisible('restoreTarget', $report->fresh())
            ->callTableAction('restoreTarget', $report->fresh(), [
                'reason' => 'The listing was reviewed and can be restored.',
            ])
            ->assertHasNoFormErrors()
            ->assertNotified();

        $this->assertDatabaseHas('listings', [
            'id' => $listingId,
            'status' => 'ACTIVE',
            'suspension_source' => null,
        ]);

        $this->assertDatabaseHas('moderation_actions', [
            'report_id' => $report->id,
            'admin_user_id' => $admin->id,
            'action' => 'LISTING_RESTORED',
            'target_type' => 'LISTING',
            'target_id' => $listingId,
            'reason' => 'The listing was reviewed and can be restored.',
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'actor_user_id' => $admin->id,
            'action' => 'LISTING_RESTORED',
            'resource_type' => 'LISTING',
            'resource_id' => $listingId,
            'reason' => 'The listing was reviewed and can be restored.',
        ]);
    }

    public function test_review_moderation_actions_hide_restore_and_audit(): void
    {
        $fixture = BookingFixture::requested($this);
        $admin = $this->createUserWithRole('ADMIN');

        $reviewId = DB::table('reviews')->insertGetId([
            'booking_id' => $fixture['booking']->id,
            'owner_id' => $fixture['owner']->id,
            'listing_id' => $fixture['active_listing_id'],
            'rating' => 2,
            'body' => 'Review fixture for moderation.',
            'status' => 'VISIBLE',
            'is_demo' => false,
            'created_at' => now('UTC'),
            'updated_at' => now('UTC'),
        ]);

        $report = $this->createReport(
            $fixture['owner'],
            ['review_id' => $reviewId],
            status: 'IN_REVIEW',
        );

        Livewire::actingAs($admin, 'web')
            ->test(ListReports::class)
            ->assertCanSeeTableRecords([$report])
            ->assertTableActionVisible('hideTarget', $report)
            ->callTableAction('hideTarget', $report, [
                'reason' => 'Review violates the content rules.',
            ])
            ->assertHasNoFormErrors()
            ->assertNotified();

        $this->assertDatabaseHas('reviews', [
            'id' => $reviewId,
            'status' => 'HIDDEN',
        ]);

        $this->assertDatabaseHas('moderation_actions', [
            'report_id' => $report->id,
            'admin_user_id' => $admin->id,
            'action' => 'REVIEW_HIDDEN',
            'target_type' => 'REVIEW',
            'target_id' => $reviewId,
            'reason' => 'Review violates the content rules.',
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'actor_user_id' => $admin->id,
            'action' => 'REVIEW_HIDDEN',
            'resource_type' => 'REVIEW',
            'resource_id' => $reviewId,
            'reason' => 'Review violates the content rules.',
        ]);

        Livewire::actingAs($admin, 'web')
            ->test(ListReports::class)
            ->assertCanSeeTableRecords([$report->fresh()])
            ->assertTableActionVisible('restoreTarget', $report->fresh())
            ->callTableAction('restoreTarget', $report->fresh(), [
                'reason' => 'The review was checked and restored.',
            ])
            ->assertHasNoFormErrors()
            ->assertNotified();

        $this->assertDatabaseHas('reviews', [
            'id' => $reviewId,
            'status' => 'VISIBLE',
        ]);

        $this->assertDatabaseHas('moderation_actions', [
            'report_id' => $report->id,
            'admin_user_id' => $admin->id,
            'action' => 'REVIEW_RESTORED',
            'target_type' => 'REVIEW',
            'target_id' => $reviewId,
            'reason' => 'The review was checked and restored.',
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'actor_user_id' => $admin->id,
            'action' => 'REVIEW_RESTORED',
            'resource_type' => 'REVIEW',
            'resource_id' => $reviewId,
            'reason' => 'The review was checked and restored.',
        ]);
    }

    public function test_booking_decision_action_creates_operation_without_changing_booking(): void
    {
        $fixture = BookingFixture::requested($this);
        $booking = $fixture['booking'];
        $admin = $this->createUserWithRole('ADMIN');

        $attemptId = DB::table('payment_attempts')->insertGetId([
            'booking_id' => $booking->id,
            'attempt_no' => 1,
            'amount_vnd' => $booking->price_snapshot_vnd,
            'status' => 'AUTHORIZED',
            'idempotency_key' => 'filament-report-' . $booking->id,
            'request_payload_hash' => str_repeat('f', 64),
            'authorized_at' => now('UTC'),
            'created_at' => now('UTC'),
            'updated_at' => now('UTC'),
        ]);

        $booking->update([
            'status' => 'AWAITING_CONFIRMATION',
            'current_payment_attempt_id' => $attemptId,
            'version' => 4,
        ]);

        $report = $this->createReport(
            $fixture['owner'],
            ['booking_id' => $booking->id],
            status: 'IN_REVIEW',
        );

        Livewire::actingAs($admin, 'web')
            ->test(ListReports::class)
            ->assertCanSeeTableRecords([$report])
            ->assertTableActionVisible('bookingDecision', $report)
            ->callTableAction('bookingDecision', $report, [
                'decision' => 'COMPLETE',
                'reason' => 'Evidence supports completing the booking.',
            ])
            ->assertHasNoFormErrors()
            ->assertNotified();

        $this->assertDatabaseHas('reports', [
            'id' => $report->id,
            'status' => 'RESOLVED',
        ]);

        $this->assertDatabaseHas('booking_release_operations', [
            'booking_id' => $booking->id,
            'actor_user_id' => $admin->id,
            'payment_attempt_id' => $attemptId,
            'report_id' => $report->id,
            'source' => 'ADMIN',
            'reason_code' => 'ADMIN_COMPLETION',
            'status' => 'PENDING',
        ]);

        // Filament chỉ tạo operation; worker mới hoàn tất việc chuyển booking.
        $this->assertSame('AWAITING_CONFIRMATION', $booking->fresh()->status);
        $this->assertDatabaseCount('booking_release_operations', 1);
    }

    public function test_evidence_action_uses_audited_policy_checked_api_route(): void
    {
        $fixture = BookingFixture::requested($this);
        $admin = $this->createUserWithRole('ADMIN');

        $messageBody = 'PRIVATE-EVIDENCE-ONLY-FROM-AUTHORIZED-ENDPOINT';

        $messageId = DB::table('messages')->insertGetId([
            'booking_id' => $fixture['booking']->id,
            'sender_id' => $fixture['owner']->id,
            'body' => $messageBody,
            'visibility' => 'VISIBLE',
            'created_at' => now('UTC'),
        ]);

        $report = $this->createReport(
            $fixture['owner'],
            ['message_id' => $messageId],
            status: 'IN_REVIEW',
        );

        $evidenceUrl = route('admin.reports.evidence', ['id' => $report->id]);

        // Exercise the protected endpoint before Livewire changes the active test guard.
        $this->actingAs($admin, 'sanctum')
            ->getJson($evidenceUrl)
            ->assertOk()
            ->assertJsonPath('data.type', 'MESSAGE')
            ->assertJsonPath('data.body', $messageBody);

        $audit = DB::table('audit_logs')
            ->where('actor_user_id', $admin->id)
            ->where('resource_type', 'REPORT')
            ->where('resource_id', $report->id)
            ->where('action', 'REPORT_EVIDENCE_VIEWED')
            ->first();

        $this->assertNotNull($audit);
        $this->assertStringNotContainsString($messageBody, (string) $audit->metadata_json);

        Livewire::actingAs($admin, 'web')
            ->test(ListReports::class)
            ->assertCanSeeTableRecords([$report])
            ->assertTableActionVisible('evidence', $report)
            ->assertSee($evidenceUrl);
    }

    private function createUserWithRole(string $role): User
    {
        $user = User::factory()->create();

        DB::table('user_roles')->insert([
            'user_id' => $user->id,
            'role' => $role,
            'assigned_at' => now(),
            'assigned_by_user_id' => null,
        ]);

        return $user;
    }

    /**
     * @param array<string, int> $target
     */
    private function createReport(
        User $reporter,
        array $target,
        string $status = 'OPEN',
        ?string $description = null,
    ): Report {
        $reportId = DB::table('reports')->insertGetId([
            'reporter_user_id' => $reporter->id,
            ...$target,
            'reason_code' => 'SAFETY_CONCERN',
            'description' => $description,
            'status' => $status,
            'resolved_at' => null,
            'created_at' => now('UTC'),
            'updated_at' => now('UTC'),
        ]);

        return Report::query()->findOrFail($reportId);
    }
}
