<?php

namespace App\Domain\Booking\Actions;

use App\Models\Booking;
use App\Models\BookingReleaseOperation;
use App\Models\PaymentAttempt;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class AdminConfirmBookingCompletion
{
    /**
     * @return array{
     *     booking: Booking,
     *     operation: BookingReleaseOperation,
     *     replayed: bool
     * }
     */
    public function handle(
        User $admin,
        string $bookingId,
        string $reason,
        string $requestId,
    ): array {
        if ($admin->status !== 'ACTIVE' || ! $admin->hasRole('ADMIN')) {
            $this->fail(403, 'FORBIDDEN', 'An active admin account is required.', $requestId);
        }

        return DB::transaction(function () use ($admin, $bookingId, $reason, $requestId): array {
            // Lock order must match the existing booking/payment workflows.
            $booking = Booking::query()
                ->whereKey($bookingId)
                ->lockForUpdate()
                ->first();

            if ($booking === null) {
                $this->fail(404, 'NOT_FOUND', 'Booking not found.', $requestId);
            }

            $attempt = $booking->current_payment_attempt_id === null
                ? null
                : PaymentAttempt::query()
                    ->whereKey($booking->current_payment_attempt_id)
                    ->where('booking_id', $booking->id)
                    ->lockForUpdate()
                    ->first();

            $operation = $attempt === null
                ? null
                : BookingReleaseOperation::query()
                    ->where('booking_id', $booking->id)
                    ->where('payment_attempt_id', $attempt->id)
                    ->lockForUpdate()
                    ->first();

            // Safe replay after the outbox worker has completed the release.
            if (
                $booking->status === 'COMPLETED'
                && $operation !== null
                && $operation->status === 'COMPLETED'
                && $operation->source === 'ADMIN'
                && $operation->reason_code === 'ADMIN_COMPLETION'
                && (string) $operation->actor_user_id === (string) $admin->id
            ) {
                return [
                    'booking' => $booking,
                    'operation' => $operation,
                    'replayed' => true,
                ];
            }

            if ($booking->status !== 'AWAITING_CONFIRMATION') {
                $this->fail(
                    409,
                    'INVALID_STATE',
                    'Only bookings awaiting confirmation can be completed.',
                    $requestId,
                );
            }

            $followup = DB::table('booking_completion_followups')
                ->where('booking_id', $booking->id)
                ->lockForUpdate()
                ->first();

            if ($followup === null || $followup->admin_queued_at === null) {
                $this->fail(
                    409,
                    'BOOKING_NOT_ESCALATED',
                    'This booking is not in the admin completion queue.',
                    $requestId,
                );
            }

            $reportInReview = DB::table('reports')
                ->where('booking_id', $booking->id)
                ->where('status', 'IN_REVIEW')
                ->exists();

            if ($reportInReview) {
                $this->fail(
                    409,
                    'REPORT_IN_REVIEW',
                    'Resolve the booking report before completing this booking.',
                    $requestId,
                );
            }

            if ($attempt === null || $attempt->status !== 'AUTHORIZED') {
                $this->fail(
                    409,
                    'PAYMENT_STATE_MISMATCH',
                    'The current payment attempt is not authorized.',
                    $requestId,
                );
            }

            if ($operation !== null) {
                if (
                    $operation->source !== 'ADMIN'
                    || $operation->reason_code !== 'ADMIN_COMPLETION'
                    || (string) $operation->actor_user_id !== (string) $admin->id
                ) {
                    $this->fail(
                        409,
                        'RELEASE_OPERATION_CONFLICT',
                        'A different release operation already exists for this booking.',
                        $requestId,
                    );
                }

                if ($operation->status === 'MANUAL_REVIEW') {
                    $this->fail(
                        409,
                        'RELEASE_REQUIRES_REVIEW',
                        'Use the audited release retry action for this operation.',
                        $requestId,
                    );
                }

                return [
                    'booking' => $booking,
                    'operation' => $operation,
                    'replayed' => true,
                ];
            }

            $now = CarbonImmutable::now('UTC');

            $operation = BookingReleaseOperation::query()->create([
                'booking_id' => $booking->id,
                'actor_user_id' => $admin->id,
                'payment_attempt_id' => $attempt->id,
                'gateway_operation_key' => (string) Str::uuid(),
                'source' => 'ADMIN',
                'reason_code' => 'ADMIN_COMPLETION',
                'reason_note' => $reason,
                'status' => 'PENDING',
                'attempt_count' => 0,
                'next_retry_at' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            DB::table('audit_logs')->insert([
                'actor_user_id' => $admin->id,
                'action' => 'booking.admin_completion_requested',
                'resource_type' => 'booking_release_operation',
                'resource_id' => $operation->id,
                'reason' => $reason,
                'request_id' => $requestId,
                'metadata_json' => json_encode([
                    'booking_id' => (string) $booking->id,
                    'operation_id' => (string) $operation->id,
                ], JSON_THROW_ON_ERROR),
                'created_at' => $now->format('Y-m-d H:i:s.u'),
            ]);

            return [
                'booking' => $booking,
                'operation' => $operation,
                'replayed' => false,
            ];
        }, 3);
    }

    private function fail(
        int $status,
        string $code,
        string $message,
        string $requestId,
    ): never {
        throw new HttpResponseException(response()->json([
            'error' => [
                'code' => $code,
                'message' => $message,
                'details' => [],
                'request_id' => $requestId,
            ],
        ], $status));
    }
}
