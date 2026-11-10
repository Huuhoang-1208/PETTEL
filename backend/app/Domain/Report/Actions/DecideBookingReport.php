<?php

namespace App\Domain\Report\Actions;

use App\Models\Booking;
use App\Models\BookingCancellationOperation;
use App\Models\BookingReleaseOperation;
use App\Models\PaymentAttempt;
use App\Models\Report;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class DecideBookingReport
{
    /**
     * @return array{
     *     report_id: string,
     *     status: string,
     *     decision: string,
     *     operation_type: string,
     *     operation_id: string,
     *     operation_status: string,
     *     replayed: bool
     * }
     */
    public function handle(
        User $admin,
        string $reportId,
        string $decision,
        string $reason,
        string $requestId,
    ): array {
        $decision = strtoupper($decision);
        $reason = trim($reason);

        abort_unless(in_array($decision, ['COMPLETE', 'CANCEL_REFUND'], true), 422);
        abort_unless($reason !== '' && mb_strlen($reason) <= 1000, 422);

        $normalizedReason = \Normalizer::normalize($reason, \Normalizer::FORM_C);
        if ($normalizedReason !== false) {
            $reason = $normalizedReason;
        }

        if (! Str::isUuid($requestId)) {
            $requestId = (string) Str::uuid();
        }

        return DB::transaction(function () use ($admin, $reportId, $decision, $reason, $requestId): array {
            $freshAdmin = User::query()->whereKey($admin->id)->lockForUpdate()->first();
            $adminRole = DB::table('user_roles')
                ->where('user_id', $admin->id)
                ->where('role', 'ADMIN')
                ->lockForUpdate()
                ->first();

            abort_unless($freshAdmin !== null
                && $freshAdmin->status === 'ACTIVE'
                && $adminRole !== null, 403);

            $report = Report::query()->whereKey($reportId)->lockForUpdate()->first();
            abort_if($report === null || $report->booking_id === null, 404);

            $reasonCode = $decision === 'COMPLETE'
                ? 'ADMIN_COMPLETION'
                : 'ADMIN_REPORT_REFUND_CANCEL';
            $actionName = $decision === 'COMPLETE'
                ? 'BOOKING_COMPLETION_DECIDED'
                : 'BOOKING_REFUND_CANCELLATION_DECIDED';
            $operationType = $decision === 'COMPLETE' ? 'RELEASE' : 'REFUND';

            if ($report->status === 'RESOLVED') {
                $existingAction = DB::table('moderation_actions')
                    ->where('report_id', $report->id)
                    ->where('action', $actionName)
                    ->where('target_type', 'BOOKING')
                    ->where('target_id', $report->booking_id)
                    ->first();

                if ($existingAction !== null) {
                    $operation = $decision === 'COMPLETE'
                        ? BookingReleaseOperation::query()->where('report_id', $report->id)->first()
                        : BookingCancellationOperation::query()->where('report_id', $report->id)->first();

                    if ($operation !== null) {
                        return $this->result($report, $decision, $operation, true);
                    }
                }
            }

            abort_unless($report->status === 'IN_REVIEW', 409, 'Booking report must be IN_REVIEW.');

            $booking = Booking::query()->whereKey($report->booking_id)->lockForUpdate()->first();
            abort_if($booking === null, 404);

            $attempt = $booking->current_payment_attempt_id === null
                ? null
                : PaymentAttempt::query()
                    ->whereKey($booking->current_payment_attempt_id)
                    ->where('booking_id', $booking->id)
                    ->lockForUpdate()
                    ->first();

            abort_if(
                $attempt === null || $attempt->status !== 'AUTHORIZED',
                409,
                'The booking does not have an authorized payment attempt.'
            );

            if ($decision === 'COMPLETE') {
                abort_unless(
                    $booking->status === 'AWAITING_CONFIRMATION',
                    409,
                    'Only a booking awaiting confirmation can be completed by an admin.'
                );

                $cancellationInProgress = BookingCancellationOperation::query()
                    ->where('booking_id', $booking->id)
                    ->whereIn('status', ['PENDING', 'PROCESSING', 'MANUAL_REVIEW'])
                    ->lockForUpdate()
                    ->exists();
                abort_if($cancellationInProgress, 409, 'A cancellation operation is already active.');

                $operation = BookingReleaseOperation::query()
                    ->where('booking_id', $booking->id)
                    ->where('payment_attempt_id', $attempt->id)
                    ->lockForUpdate()
                    ->first();

                if ($operation === null) {
                    $operation = BookingReleaseOperation::query()->create([
                        'booking_id' => $booking->id,
                        'actor_user_id' => $freshAdmin->id,
                        'payment_attempt_id' => $attempt->id,
                        'report_id' => $report->id,
                        'gateway_operation_key' => (string) Str::uuid(),
                        'source' => 'ADMIN',
                        'reason_code' => $reasonCode,
                        'reason_note' => $reason,
                        'status' => 'PENDING',
                        'attempt_count' => 0,
                        'next_retry_at' => CarbonImmutable::now('UTC'),
                    ]);
                } else {
                    abort_if(
                        $operation->status === 'COMPLETED',
                        409,
                        'The payment release has already completed.'
                    );
                    abort_if(
                        $operation->status === 'PROCESSING',
                        409,
                        'The payment release is already processing; retry the decision after it settles.'
                    );

                    // Reuse the operation key if a user release was held by this report.
                    $operation->actor_user_id = $freshAdmin->id;
                    $operation->report_id = $report->id;
                    $operation->source = 'ADMIN';
                    $operation->reason_code = $reasonCode;
                    $operation->reason_note = $reason;
                    $operation->status = 'PENDING';
                    $operation->next_retry_at = CarbonImmutable::now('UTC');
                    $operation->lease_expires_at = null;
                    $operation->last_error_code = null;
                    $operation->save();
                }
            } else {
                abort_unless(
                    in_array($booking->status, ['IN_PROGRESS', 'AWAITING_CONFIRMATION'], true),
                    409,
                    'Admin refund cancellation is only valid during active care or awaiting confirmation.'
                );

                $releaseExists = BookingReleaseOperation::query()
                    ->where('booking_id', $booking->id)
                    ->where('payment_attempt_id', $attempt->id)
                    ->lockForUpdate()
                    ->exists();
                abort_if(
                    $releaseExists,
                    409,
                    'A release operation already exists; payment outcome requires manual review.'
                );

                $cancellationExists = BookingCancellationOperation::query()
                    ->where('booking_id', $booking->id)
                    ->whereIn('status', ['PENDING', 'PROCESSING', 'MANUAL_REVIEW', 'COMPLETED'])
                    ->lockForUpdate()
                    ->first();
                abort_if(
                    $cancellationExists !== null,
                    409,
                    'A cancellation operation already exists for this booking.'
                );

                $now = CarbonImmutable::now('UTC');
                $key = 'report-'.$report->id.'-refund-cancel';
                $payloadHash = hash('sha256', json_encode([
                    'report_id' => (string) $report->id,
                    'booking_id' => (string) $booking->id,
                    'decision' => $decision,
                    'reason' => $reason,
                ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));

                $operation = BookingCancellationOperation::query()->create([
                    'booking_id' => $booking->id,
                    'booking_status_before' => $booking->status,
                    'actor_user_id' => $freshAdmin->id,
                    'payment_attempt_id' => $attempt->id,
                    'report_id' => $report->id,
                    'source' => 'ADMIN',
                    'reason_code' => $reasonCode,
                    'reason_note' => $reason,
                    'idempotency_key' => $key,
                    'payload_hash' => $payloadHash,
                    'gateway_operation_key' => (string) Str::uuid(),
                    'operation_type' => 'REFUND',
                    'status' => 'PENDING',
                    'attempt_count' => 0,
                    'next_retry_at' => $now,
                ]);
            }

            $now = CarbonImmutable::now('UTC');
            DB::table('reports')->where('id', $report->id)->update([
                'status' => 'RESOLVED',
                'resolved_at' => $now,
                'updated_at' => $now,
            ]);

            DB::table('report_status_events')->insert([
                'report_id' => $report->id,
                'actor_user_id' => $freshAdmin->id,
                'from_status' => 'IN_REVIEW',
                'to_status' => 'RESOLVED',
                'reason' => $reason,
                'request_id' => $requestId,
                'created_at' => $now->format('Y-m-d H:i:s.u'),
            ]);

            DB::table('moderation_actions')->insert([
                'report_id' => $report->id,
                'admin_user_id' => $freshAdmin->id,
                'action' => $actionName,
                'target_type' => 'BOOKING',
                'target_id' => $booking->id,
                'reason' => $reason,
                'created_at' => $now->format('Y-m-d H:i:s.u'),
            ]);

            DB::table('audit_logs')->insert([
                'actor_user_id' => $freshAdmin->id,
                'action' => 'BOOKING_REPORT_DECISION',
                'resource_type' => 'REPORT',
                'resource_id' => $report->id,
                'reason' => $reason,
                'request_id' => $requestId,
                'metadata_json' => json_encode([
                    'decision' => $decision,
                    'booking_id' => (string) $booking->id,
                    'operation_type' => $operationType,
                    'operation_id' => (string) $operation->id,
                ], JSON_THROW_ON_ERROR),
                'created_at' => $now->format('Y-m-d H:i:s.u'),
            ]);

            $report->status = 'RESOLVED';

            return $this->result($report, $decision, $operation, false);
        }, 3);
    }

    /**
     * @return array{
     *     report_id: string,
     *     status: string,
     *     decision: string,
     *     operation_type: string,
     *     operation_id: string,
     *     operation_status: string,
     *     replayed: bool
     * }
     */
    private function result(
        Report $report,
        string $decision,
        BookingReleaseOperation|BookingCancellationOperation $operation,
        bool $replayed,
    ): array {
        return [
            'report_id' => (string) $report->id,
            'status' => $report->status,
            'decision' => $decision,
            'operation_type' => $decision === 'COMPLETE' ? 'RELEASE' : 'REFUND',
            'operation_id' => (string) $operation->id,
            'operation_status' => $operation->status,
            'replayed' => $replayed,
        ];
    }
}
