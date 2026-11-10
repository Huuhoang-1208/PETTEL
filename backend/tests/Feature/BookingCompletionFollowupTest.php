<?php

namespace Tests\Feature;

use App\Domain\Booking\Actions\ProcessBookingCompletionFollowups;
use App\Domain\Booking\Actions\ProcessBookingReleaseOutbox;
use App\Domain\Booking\Payment\PaymentReleaseGateway;
use App\Domain\Booking\Payment\PaymentReleaseResult;
use App\Models\Booking;
use App\Models\BookingReleaseOperation;
use App\Models\User;
use App\Notifications\BookingCompletionReminder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Tests\Support\BookingFixture;
use Tests\Support\SequencePaymentReleaseGateway;
use Tests\TestCase;

class BookingCompletionFollowupTest extends TestCase
{
    use RefreshDatabase;

    public function test_no_reminder_is_sent_before_24_hours(): void
    {
        Notification::fake();

        $fixture = $this->makeAwaitingBooking(23);

        $counts = app(ProcessBookingCompletionFollowups::class)->handle();

        $this->assertSame(0, $counts['reminded']);
        $this->assertSame(0, $counts['queued']);
        $this->assertDatabaseMissing('booking_completion_followups', [
            'booking_id' => $fixture['booking']->id,
        ]);
        Notification::assertNothingSent();
    }

    public function test_reminder_is_sent_once_after_24_hours(): void
    {
        Notification::fake();

        $fixture = $this->makeAwaitingBooking(24);

        $firstRun = app(ProcessBookingCompletionFollowups::class)->handle();
        $secondRun = app(ProcessBookingCompletionFollowups::class)->handle();

        $this->assertSame(1, $firstRun['reminded']);
        $this->assertSame(0, $firstRun['queued']);
        $this->assertSame(0, $secondRun['reminded']);

        Notification::assertSentToTimes(
            $fixture['owner'],
            BookingCompletionReminder::class,
            1,
        );

        $this->assertDatabaseHas('booking_completion_followups', [
            'booking_id' => $fixture['booking']->id,
        ]);

        $this->assertNotNull(
            DB::table('booking_completion_followups')
                ->where('booking_id', $fixture['booking']->id)
                ->value('reminder_sent_at'),
        );

        $this->assertSame(
            'AWAITING_CONFIRMATION',
            $fixture['booking']->fresh()->status,
        );
    }

    public function test_booking_is_queued_once_after_72_hours_without_changing_booking_or_payment(): void
    {
        Notification::fake();

        $fixture = $this->makeAwaitingBooking(72);

        $firstRun = app(ProcessBookingCompletionFollowups::class)->handle();
        $secondRun = app(ProcessBookingCompletionFollowups::class)->handle();

        $this->assertSame(1, $firstRun['reminded']);
        $this->assertSame(1, $firstRun['queued']);
        $this->assertSame(0, $secondRun['reminded']);
        $this->assertSame(0, $secondRun['queued']);

        $this->assertSame(
            'AWAITING_CONFIRMATION',
            $fixture['booking']->fresh()->status,
        );

        $this->assertDatabaseHas('payment_attempts', [
            'id' => $fixture['attempt_id'],
            'status' => 'AUTHORIZED',
        ]);

        $this->assertDatabaseHas('booking_completion_followups', [
            'booking_id' => $fixture['booking']->id,
        ]);

        $this->assertNotNull(
            DB::table('booking_completion_followups')
                ->where('booking_id', $fixture['booking']->id)
                ->value('admin_queued_at'),
        );

        Notification::assertSentToTimes(
            $fixture['owner'],
            BookingCompletionReminder::class,
            1,
        );
    }

    public function test_completed_or_cancelled_booking_is_not_queued(): void
    {
        Notification::fake();

        foreach (['COMPLETED', 'CANCELLED'] as $status) {
            $fixture = $this->makeAwaitingBooking(73);
            $booking = $fixture['booking'];

            $booking->update(['status' => $status]);

            DB::table('booking_status_events')->insert([
                'booking_id' => $booking->id,
                'from_status' => 'AWAITING_CONFIRMATION',
                'to_status' => $status,
                'actor_user_id' => $fixture['owner']->id,
                'source' => 'USER',
                'reason_code' => 'TEST_TERMINAL_STATE',
                'created_at' => now('UTC'),
            ]);

            $counts = app(ProcessBookingCompletionFollowups::class)->handle();

            $this->assertSame(0, $counts['reminded']);
            $this->assertSame(0, $counts['queued']);

            $this->assertDatabaseMissing('booking_completion_followups', [
                'booking_id' => $booking->id,
            ]);
        }

        Notification::assertNothingSent();
    }

    public function test_only_active_admin_can_view_completion_review_queue(): void
    {
        Notification::fake();

        $fixture = $this->makeAwaitingBooking(73);
        app(ProcessBookingCompletionFollowups::class)->handle();

        $this->actingAs($fixture['owner'])
            ->getJson('/api/admin/booking-completion-reviews')
            ->assertForbidden();

        $admin = $this->admin();

        $this->actingAs($admin)
            ->getJson('/api/admin/booking-completion-reviews')
            ->assertOk()
            ->assertJsonPath(
                'data.overdue_bookings.0.booking_id',
                (string) $fixture['booking']->id,
            );

        $inactiveAdmin = $this->admin();
        $inactiveAdmin->forceFill(['status' => 'SUSPENDED'])->save();
        $this->assertSame('SUSPENDED', $inactiveAdmin->fresh()->status);

        $this->actingAs($inactiveAdmin)
            ->getJson('/api/admin/booking-completion-reviews')
            ->assertForbidden();
    }

    public function test_admin_cannot_complete_booking_while_report_is_in_review(): void
    {
        Notification::fake();

        $fixture = $this->makeAwaitingBooking(73);
        app(ProcessBookingCompletionFollowups::class)->handle();

        $reportResponse = $this->actingAs($fixture['owner'])
            ->postJson('/api/reports', [
                'booking_id' => $fixture['booking']->id,
                'reason_code' => 'SERVICE_DISPUTE',
                'description' => 'Test report for completion hold.',
            ])
            ->assertCreated();

        $reportId = $reportResponse->json('data.id');
        $admin = $this->admin();

        $this->actingAs($admin)
            ->postJson("/api/admin/reports/{$reportId}/status", [
                'status' => 'IN_REVIEW',
                'reason' => 'Review the booking report.',
            ])
            ->assertOk();

        $this->actingAs($admin)
            ->postJson(
                "/api/admin/booking-completion-reviews/{$fixture['booking']->id}/complete",
                ['reason' => 'Attempt completion while report is open.'],
            )
            ->assertStatus(409)
            ->assertJsonPath('error.code', 'REPORT_IN_REVIEW');

        $this->assertSame(
            'AWAITING_CONFIRMATION',
            $fixture['booking']->fresh()->status,
        );

        $this->assertDatabaseCount('booking_release_operations', 0);

        $this->assertDatabaseHas('payment_attempts', [
            'id' => $fixture['attempt_id'],
            'status' => 'AUTHORIZED',
        ]);
    }

    public function test_admin_completion_uses_one_release_operation_and_waits_for_release_success(): void
    {
        Notification::fake();

        $fixture = $this->makeAwaitingBooking(73);
        app(ProcessBookingCompletionFollowups::class)->handle();

        $admin = $this->admin();
        $requestId = (string) Str::uuid();

        $gateway = new SequencePaymentReleaseGateway([
            PaymentReleaseResult::retryable('GATEWAY_TIMEOUT'),
            PaymentReleaseResult::succeeded(),
        ]);
        $this->app->instance(PaymentReleaseGateway::class, $gateway);

        $firstResponse = $this->actingAs($admin)
            ->withHeader('X-Request-ID', $requestId)
            ->postJson(
                "/api/admin/booking-completion-reviews/{$fixture['booking']->id}/complete",
                ['reason' => 'Owner did not respond after the review period.'],
            )
            ->assertAccepted()
            ->assertJsonPath('data.operation_status', 'PENDING')
            ->assertJsonPath('data.replayed', false);

        $operationId = $firstResponse->json('data.operation_id');

        $this->assertSame(
            'AWAITING_CONFIRMATION',
            $fixture['booking']->fresh()->status,
        );

        $this->assertDatabaseHas('booking_release_operations', [
            'id' => $operationId,
            'booking_id' => $fixture['booking']->id,
            'actor_user_id' => $admin->id,
            'source' => 'ADMIN',
            'reason_code' => 'ADMIN_COMPLETION',
            'status' => 'PENDING',
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'actor_user_id' => $admin->id,
            'action' => 'booking.admin_completion_requested',
            'resource_type' => 'booking_release_operation',
            'resource_id' => $operationId,
            'request_id' => $requestId,
        ]);

        $replay = $this->actingAs($admin)
            ->postJson(
                "/api/admin/booking-completion-reviews/{$fixture['booking']->id}/complete",
                ['reason' => 'Owner did not respond after the review period.'],
            )
            ->assertOk()
            ->assertJsonPath('data.operation_id', $operationId)
            ->assertJsonPath('data.replayed', true);

        $this->assertSame(1, BookingReleaseOperation::query()
            ->where('booking_id', $fixture['booking']->id)
            ->count());

        $firstReleaseRun = app(ProcessBookingReleaseOutbox::class)->handle();

        $this->assertSame(1, $firstReleaseRun['pending']);
        $this->assertSame(
            'AWAITING_CONFIRMATION',
            $fixture['booking']->fresh()->status,
        );

        $this->assertNull(
            DB::table('booking_completion_followups')
                ->where('booking_id', $fixture['booking']->id)
                ->value('resolved_at'),
        );

        $this->travel(1)->minutes();

        $secondReleaseRun = app(ProcessBookingReleaseOutbox::class)->handle();

        $this->assertSame(1, $secondReleaseRun['completed']);
        $this->assertSame('COMPLETED', $fixture['booking']->fresh()->status);
        $this->assertSame(2, count($gateway->operationKeys));
        $this->assertSame($gateway->operationKeys[0], $gateway->operationKeys[1]);

        $this->assertDatabaseHas('payment_attempts', [
            'id' => $fixture['attempt_id'],
            'status' => 'RELEASED',
        ]);

        $this->assertNotNull(
            DB::table('booking_completion_followups')
                ->where('booking_id', $fixture['booking']->id)
                ->value('resolved_at'),
        );
    }

    /**
     * Build a booking that has entered AWAITING_CONFIRMATION and has an authorized payment.
     *
     * @return array{
     *     owner: User,
     *     caregiver: User,
     *     booking: Booking,
     *     attempt_id: int
     * }
     */
    private function makeAwaitingBooking(int $hoursAgo): array
    {
        $fixture = BookingFixture::requested($this);
        /** @var Booking $booking */
        $booking = $fixture['booking'];

        $attemptId = DB::table('payment_attempts')->insertGetId([
            'booking_id' => $booking->id,
            'attempt_no' => 1,
            'amount_vnd' => $booking->price_snapshot_vnd,
            'status' => 'AUTHORIZED',
            'idempotency_key' => 'followup-attempt-'.$booking->id,
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

        $awaitingSince = now('UTC')->subHours($hoursAgo);
        $eventTime = $awaitingSince->copy()->subMinutes(3);

        foreach ([
            ['REQUESTED', 'AWAITING_PAYMENT'],
            ['AWAITING_PAYMENT', 'CONFIRMED'],
            ['CONFIRMED', 'IN_PROGRESS'],
            ['IN_PROGRESS', 'AWAITING_CONFIRMATION'],
        ] as [$from, $to]) {
            DB::table('booking_status_events')->insert([
                'booking_id' => $booking->id,
                'from_status' => $from,
                'to_status' => $to,
                'actor_user_id' => $fixture['owner']->id,
                'source' => 'USER',
                'reason_code' => 'TEST_'.$to,
                'created_at' => $eventTime,
            ]);

            $eventTime = $eventTime->copy()->addMinutes(1);
        }

        return [
            'owner' => $fixture['owner'],
            'caregiver' => $fixture['caregiver'],
            'booking' => $booking->fresh(),
            'attempt_id' => $attemptId,
        ];
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
