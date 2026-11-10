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