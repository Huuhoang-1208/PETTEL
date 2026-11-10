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

class ConfirmBookingCompletion
{
    /**
     * @return array{
     *     booking: Booking,
     *     operation: BookingReleaseOperation|null,
     *     replayed: bool
     * }
     */
    public function handle(User $owner, string $id): array
    {
        return DB::transaction(function () use ($owner, $id): array {
            // Scope by owner before returning any booking data.
            $booking = Booking::query()
                ->whereKey($id)
                ->where('owner_id', $owner->id)
                ->lockForUpdate()
                ->first();

            if ($booking === null) {
                $this->fail(404, 'NOT_FOUND', 'Booking not found.');
            }

            $adminRefundInProgress = DB::table('booking_cancellation_operations')
                ->where('booking_id', $booking->id)
                ->where('source', 'ADMIN')
                ->where('reason_code', 'ADMIN_REPORT_REFUND_CANCEL')
                ->where('operation_type', 'REFUND')
                ->whereIn('status', ['PENDING', 'PROCESSING', 'MANUAL_REVIEW'])
                ->exists();

            if ($adminRefundInProgress) {
                $this->fail(
                    409,
                    'CANCELLATION_IN_PROGRESS',
                    'Booking is locked while an admin refund decision is being processed.',
                );
            }

            if ($booking->status === 'COMPLETED') {
                $ownerConfirmation = DB::table('booking_status_events')
                    ->where('booking_id', $booking->id)
                    ->where('from_status', 'AWAITING_CONFIRMATION')
                    ->where('to_status', 'COMPLETED')
                    ->where('actor_user_id', $owner->id)
                    ->where('source', 'USER')
                    ->where('reason_code', 'OWNER_CONFIRMED')
                    ->first();

                if ($ownerConfirmation !== null) {
                    return [
                        'booking' => $booking,
                        'operation' => BookingReleaseOperation::query()
                            ->where('booking_id', $booking->id)
                            ->where('status', 'COMPLETED')
                            ->first(),
                        'replayed' => true,
                    ];
                }

                $this->fail(409, 'INVALID_STATE', 'Booking was completed by another workflow.');
            }

            if ($booking->status !== 'AWAITING_CONFIRMATION') {
                $this->fail(409, 'INVALID_STATE', 'Only bookings awaiting confirmation can be confirmed.');
            }

            $reportInReview = DB::table('reports')
                ->where('booking_id', $booking->id)
                ->where('status', 'IN_REVIEW')
                ->exists();

            if ($reportInReview) {
                $this->fail(409, 'REPORT_IN_REVIEW', 'Completion is on hold while a report is under review.');
            }

            $attempt = $booking->current_payment_attempt_id === null
                ? null
                : PaymentAttempt::query()
                    ->whereKey($booking->current_payment_attempt_id)
                    ->where('booking_id', $booking->id)
                    ->lockForUpdate()
                    ->first();

            if ($attempt === null || $attempt->status !== 'AUTHORIZED') {
                $this->fail(409, 'PAYMENT_STATE_MISMATCH', 'The current payment attempt is not authorized.');
            }

            $operation = BookingReleaseOperation::query()
                ->where('booking_id', $booking->id)
                ->where('payment_attempt_id', $attempt->id)
                ->lockForUpdate()
                ->first();
            $replayed = $operation !== null;

            if ($operation === null) {
                $now = CarbonImmutable::now('UTC');

                $operation = BookingReleaseOperation::query()->create([
                    'booking_id' => $booking->id,
                    'actor_user_id' => $owner->id,
                    'payment_attempt_id' => $attempt->id,
                    'gateway_operation_key' => (string) Str::uuid(),
                    'status' => 'PENDING',
                    'attempt_count' => 0,
                    'next_retry_at' => $now,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            } elseif ((string) $operation->actor_user_id !== (string) $owner->id) {
                $this->fail(409, 'RELEASE_OPERATION_CONFLICT', 'A release operation already exists for this booking.');
            }

            if ($operation->status === 'COMPLETED') {
                $this->fail(409, 'PAYMENT_STATE_MISMATCH', 'Release is recorded but the booking is not completed.');
            }

            return [
                'booking' => $booking,
                'operation' => $operation,
                'replayed' => $replayed,
            ];
        }, 3);
    }

    private function fail(int $status, string $code, string $message): never
    {
        throw new HttpResponseException(response()->json([
            'error' => [
                'code' => $code,
                'message' => $message,
                'details' => [],
                'request_id' => (string) Str::uuid(),
            ],
        ], $status));
    }
}
