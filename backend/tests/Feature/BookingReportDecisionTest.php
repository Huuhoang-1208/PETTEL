<?php

namespace Tests\Feature;

use App\Domain\Booking\Actions\ProcessBookingReleaseOutbox;
use App\Domain\Booking\Actions\ProcessCancellationOutbox;
use App\Domain\Booking\Payment\PaymentCancellationGateway;
use App\Domain\Booking\Payment\PaymentCancellationResult;
use App\Domain\Booking\Payment\PaymentReleaseGateway;
use App\Domain\Booking\Payment\PaymentReleaseResult;
use App\Models\Booking;
use App\Models\BookingCancellationOperation;
use App\Models\PaymentAttempt;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Support\BookingFixture;
use Tests\Support\SequencePaymentReleaseGateway;
use Tests\TestCase;

class BookingReportDecisionTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_complete_decision_retries_release_and_reuses_operation(): void
    {
        $fixture = $this->bookingReportFixture('AWAITING_CONFIRMATION');
        $booking = $fixture['booking'];
        $admin = $fixture['admin'];
        $reportId = $fixture['report_id'];
        $requestId = (string) Str::uuid();

        $gateway = new SequencePaymentReleaseGateway([
            PaymentReleaseResult::retryable('GATEWAY_TIMEOUT'),
            PaymentReleaseResult::succeeded(),
        ]);
        $this->app->instance(PaymentReleaseGateway::class, $gateway);

        $first = $this->actingAs($admin)
            ->withHeader('X-Request-ID', $requestId)
            ->postJson("/api/admin/reports/{$reportId}/booking-decision", [
                'decision' => 'COMPLETE',
                'reason' => 'Evidence supports completion.',
            ])
            ->assertAccepted()
            ->assertJsonPath('data.status', 'RESOLVED')
            ->assertJsonPath('data.operation_type', 'RELEASE');

        $operationId = $first->json('data.operation_id');

        // Creating the operation does not change the booking.
        $this->assertSame('AWAITING_CONFIRMATION', $booking->fresh()->status);
        $this->assertDatabaseHas('booking_release_operations', [
            'id' => $operationId,
            'report_id' => $reportId,
            'source' => 'ADMIN',
            'reason_code' => 'ADMIN_COMPLETION',
            'status' => 'PENDING',
        ]);

        // Repeating the decision reuses the existing operation.
        $this->postJson("/api/admin/reports/{$reportId}/booking-decision", [
            'decision' => 'COMPLETE',
            'reason' => 'Evidence supports completion.',
        ])
            ->assertOk()
            ->assertJsonPath('data.operation_id', $operationId)
            ->assertJsonPath('data.replayed', true);

        $this->assertSame(
            1,
            DB::table('booking_release_operations')->where('booking_id', $booking->id)->count(),
        );

        $firstRun = app(ProcessBookingReleaseOutbox::class)->handle();

        $this->assertSame(1, $firstRun['pending']);
        $this->assertSame('AWAITING_CONFIRMATION', $booking->fresh()->status);
        $this->assertSame('AUTHORIZED', DB::table('payment_attempts')
            ->where('id', $fixture['attempt_id'])->value('status'));

        $this->travel(1)->minutes();

        $secondRun = app(ProcessBookingReleaseOutbox::class)->handle();

        $this->assertSame(1, $secondRun['completed']);
        $this->assertSame('COMPLETED', $booking->fresh()->status);
        $this->assertSame('RELEASED', DB::table('payment_attempts')
            ->where('id', $fixture['attempt_id'])->value('status'));
        $this->assertSame($gateway->operationKeys[0], $gateway->operationKeys[1]);

        $this->assertDatabaseHas('booking_status_events', [
            'booking_id' => $booking->id,
            'from_status' => 'AWAITING_CONFIRMATION',
            'to_status' => 'COMPLETED',
            'actor_user_id' => $admin->id,
            'source' => 'ADMIN',
            'reason_code' => 'ADMIN_COMPLETION',
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'actor_user_id' => $admin->id,
            'action' => 'BOOKING_REPORT_DECISION',
            'resource_type' => 'REPORT',
            'resource_id' => $reportId,
            'request_id' => $requestId,
        ]);

        $this->assertDatabaseCount('payment_events', 1);
    }

    public function test_admin_refund_cancel_retries_and_keeps_booking_until_refund_succeeds(): void
    {
        $fixture = $this->bookingReportFixture('AWAITING_CONFIRMATION');
        $booking = $fixture['booking'];
        $admin = $fixture['admin'];
        $reportId = $fixture['report_id'];

        $gateway = new class([PaymentCancellationResult::retryable('GATEWAY_TIMEOUT'), PaymentCancellationResult::succeeded()]) implements PaymentCancellationGateway
        {
            /** @var list<string> */
            public array $operationKeys = [];

            /** @param list<PaymentCancellationResult> $results */
            public function __construct(private array $results) {}

            public function cancel(
                string $operationType,
                PaymentAttempt $attempt,
                BookingCancellationOperation $operation,
            ): PaymentCancellationResult {
                $this->operationKeys[] = $operation->gateway_operation_key;

                return array_shift($this->results) ?? PaymentCancellationResult::succeeded();
            }
        };

        $this->app->instance(PaymentCancellationGateway::class, $gateway);

        $first = $this->actingAs($admin)
            ->postJson("/api/admin/reports/{$reportId}/booking-decision", [
                'decision' => 'CANCEL_REFUND',
                'reason' => 'Care records support cancelling this booking.',
            ])
            ->assertAccepted()
            ->assertJsonPath('data.status', 'RESOLVED')
            ->assertJsonPath('data.operation_type', 'REFUND');

        $operationId = $first->json('data.operation_id');

        $this->assertSame('AWAITING_CONFIRMATION', $booking->fresh()->status);
        $this->assertDatabaseHas('booking_cancellation_operations', [
            'id' => $operationId,
            'report_id' => $reportId,
            'source' => 'ADMIN',
            'reason_code' => 'ADMIN_REPORT_REFUND_CANCEL',
            'operation_type' => 'REFUND',
            'booking_status_before' => 'AWAITING_CONFIRMATION',
            'status' => 'PENDING',
        ]);

        $this->postJson("/api/admin/reports/{$reportId}/booking-decision", [
            'decision' => 'CANCEL_REFUND',
            'reason' => 'Care records support cancelling this booking.',
        ])
            ->assertOk()
            ->assertJsonPath('data.operation_id', $operationId)
            ->assertJsonPath('data.replayed', true);

        $this->assertSame(
            1,
            DB::table('booking_cancellation_operations')->where('booking_id', $booking->id)->count(),
        );

        $firstRun = app(ProcessCancellationOutbox::class)->handle();

        $this->assertSame(1, $firstRun['pending']);
        $this->assertSame('AWAITING_CONFIRMATION', $booking->fresh()->status);
        $this->assertSame('AUTHORIZED', DB::table('payment_attempts')
            ->where('id', $fixture['attempt_id'])->value('status'));

        $this->travel(1)->minutes();

        $secondRun = app(ProcessCancellationOutbox::class)->handle();

        $this->assertSame(1, $secondRun['completed']);
        $this->assertSame('CANCELLED', $booking->fresh()->status);
        $this->assertSame('REFUNDED', DB::table('payment_attempts')
            ->where('id', $fixture['attempt_id'])->value('status'));
        $this->assertSame($gateway->operationKeys[0], $gateway->operationKeys[1]);

        $operation = BookingCancellationOperation::query()->findOrFail($operationId);
        $this->assertSame(
            1,
            DB::table('payment_events')
                ->where('payment_attempt_id', $fixture['attempt_id'])
                ->where('provider', 'sandbox')
                ->where('external_event_id', 'refund:'.$operation->gateway_operation_key)
                ->where('event_type', 'REFUNDED')
                ->where('processing_status', 'PROCESSED')
                ->count(),
        );

        // Reprocessing a completed operation must not duplicate its payment event.
        app(ProcessCancellationOutbox::class)->handle();
        $this->assertSame(
            1,
            DB::table('payment_events')
                ->where('external_event_id', 'refund:'.$operation->gateway_operation_key)
                ->count(),
        );

        $this->assertDatabaseHas('booking_status_events', [
            'booking_id' => $booking->id,
            'from_status' => 'AWAITING_CONFIRMATION',
            'to_status' => 'CANCELLED',
            'actor_user_id' => $admin->id,
            'source' => 'ADMIN',
            'reason_code' => 'ADMIN_REPORT_REFUND_CANCEL',
        ]);
    }

    public function test_admin_refund_with_stale_booking_state_is_quarantined_before_gateway_call(): void
    {
        $fixture = $this->bookingReportFixture('AWAITING_CONFIRMATION');
        $booking = $fixture['booking'];
        $admin = $fixture['admin'];
        $reportId = $fixture['report_id'];

        $gateway = new class implements PaymentCancellationGateway
        {
            public int $calls = 0;

            public function cancel(
                string $operationType,
                PaymentAttempt $attempt,
                BookingCancellationOperation $operation,
            ): PaymentCancellationResult {
                $this->calls++;

                return PaymentCancellationResult::succeeded();
            }
        };
        $this->app->instance(PaymentCancellationGateway::class, $gateway);

        $decision = $this->actingAs($admin)
            ->postJson("/api/admin/reports/{$reportId}/booking-decision", [
                'decision' => 'CANCEL_REFUND',
                'reason' => 'Care records support cancelling this booking.',
            ])
            ->assertAccepted();

        $operationId = $decision->json('data.operation_id');

        // Simulate another workflow changing the booking after the decision was queued.
        $booking->forceFill(['status' => 'IN_PROGRESS'])->save();

        $result = app(ProcessCancellationOutbox::class)->handle();

        $this->assertSame(1, $result['manual_review']);
        $this->assertSame(0, $gateway->calls);
        $this->assertDatabaseHas('booking_cancellation_operations', [
            'id' => $operationId,
            'status' => 'MANUAL_REVIEW',
            'last_error_code' => 'PAYMENT_STATE_MISMATCH',
        ]);
        $this->assertSame('IN_PROGRESS', $booking->fresh()->status);
        $this->assertSame('AUTHORIZED', DB::table('payment_attempts')
            ->where('id', $fixture['attempt_id'])->value('status'));
        $this->assertDatabaseMissing('payment_events', [
            'payment_attempt_id' => $fixture['attempt_id'],
            'event_type' => 'REFUNDED',
        ]);
    }

    public function test_active_admin_refund_blocks_booking_state_transitions(): void
    {
        $inProgressFixture = $this->bookingReportFixture('IN_PROGRESS');
        $inProgressDecision = $this->actingAs($inProgressFixture['admin'])
            ->postJson('/api/admin/reports/'.$inProgressFixture['report_id'].'/booking-decision', [
                'decision' => 'CANCEL_REFUND',
                'reason' => 'Review supports refund cancellation.',
            ])
            ->assertAccepted();

        $this->actingAs($inProgressFixture['caregiver'])
            ->postJson('/api/bookings/'.$inProgressFixture['booking']->id.'/request-completion')
            ->assertStatus(409)
            ->assertJsonPath('error.code', 'CANCELLATION_IN_PROGRESS');

        $awaitingFixture = $this->bookingReportFixture('AWAITING_CONFIRMATION');
        $this->actingAs($awaitingFixture['admin'])
            ->postJson('/api/admin/reports/'.$awaitingFixture['report_id'].'/booking-decision', [
                'decision' => 'CANCEL_REFUND',
                'reason' => 'Review supports refund cancellation.',
            ])
            ->assertAccepted();

        $this->actingAs($awaitingFixture['owner'])
            ->postJson('/api/bookings/'.$awaitingFixture['booking']->id.'/confirm-completion')
            ->assertStatus(409)
            ->assertJsonPath('error.code', 'CANCELLATION_IN_PROGRESS');
    }

    public function test_generic_resolved_is_blocked_but_dismissed_leaves_booking_unchanged(): void
    {
        $fixture = $this->bookingReportFixture('AWAITING_CONFIRMATION');
        $booking = $fixture['booking'];
        $admin = $fixture['admin'];
        $reportId = $fixture['report_id'];
        $bookingBefore = $booking->fresh()->getAttributes();
        $eventCountBefore = DB::table('booking_status_events')
            ->where('booking_id', $booking->id)
            ->count();

        $this->actingAs($admin)
            ->postJson("/api/admin/reports/{$reportId}/status", [
                'status' => 'RESOLVED',
                'reason' => 'Attempt generic resolution.',
            ])
            ->assertStatus(409)
            ->assertJsonPath('error.code', 'BOOKING_DECISION_REQUIRED');

        $this->assertSame('IN_REVIEW', DB::table('reports')
            ->where('id', $reportId)->value('status'));

        $this->actingAs($admin)
            ->postJson("/api/admin/reports/{$reportId}/status", [
                'status' => 'DISMISSED',
                'reason' => 'The report was not substantiated.',
            ])
            ->assertOk()
            ->assertJsonPath('data.status', 'DISMISSED');

        $this->assertSame($bookingBefore, $booking->fresh()->getAttributes());
        $this->assertSame(
            $eventCountBefore,
            DB::table('booking_status_events')->where('booking_id', $booking->id)->count(),
        );

        // Dismissal removes the hold, so the owner can resume the normal completion flow.
        $this->actingAs($fixture['owner'])
            ->postJson("/api/bookings/{$booking->id}/confirm-completion")
            ->assertAccepted();

        $this->assertSame('AWAITING_CONFIRMATION', $booking->fresh()->status);
        $this->assertDatabaseCount('booking_release_operations', 1);
    }

    /**
     * @return array{
     *     owner: User,
     *     caregiver: User,
     *     booking: Booking,
     *     attempt_id: int,
     *     report_id: string,
     *     admin: User
     * }
     */
    private function bookingReportFixture(string $bookingStatus): array
    {
        $fixture = BookingFixture::requested($this);
        /** @var Booking $booking */
        $booking = $fixture['booking'];

        $attemptId = DB::table('payment_attempts')->insertGetId([
            'booking_id' => $booking->id,
            'attempt_no' => 1,
            'amount_vnd' => $booking->price_snapshot_vnd,
            'status' => 'AUTHORIZED',
            'idempotency_key' => 'booking-report-'.$booking->id,
            'request_payload_hash' => str_repeat('e', 64),
            'authorized_at' => now('UTC'),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $booking->update([
            'status' => $bookingStatus,
            'current_payment_attempt_id' => $attemptId,
            'version' => 4,
        ]);

        $report = $this->actingAs($fixture['owner'])->postJson('/api/reports', [
            'booking_id' => $booking->id,
            'reason_code' => 'SERVICE_DISPUTE',
            'description' => 'Test booking report.',
        ])->assertCreated();

        $admin = $this->admin();

        $this->actingAs($admin)->postJson(
            '/api/admin/reports/'.$report->json('data.id').'/status',
            [
                'status' => 'IN_REVIEW',
                'reason' => 'Begin review.',
            ],
        )->assertOk();

        return [
            'owner' => $fixture['owner'],
            'caregiver' => $fixture['caregiver'],
            'booking' => $booking->fresh(),
            'attempt_id' => $attemptId,
            'report_id' => (string) $report->json('data.id'),
            'admin' => $admin,
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
