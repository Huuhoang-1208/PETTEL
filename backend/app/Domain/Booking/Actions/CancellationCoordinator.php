<?php

namespace App\Domain\Booking\Actions;

use App\Models\Booking;
use App\Models\BookingCancellationOperation;
use App\Models\PaymentAttempt;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CancellationCoordinator
{
    public function start(
        Booking $booking,
        ?User $actor,
        string $source,
        string $reasonCode,
        ?string $reasonNote,
        ?string $idempotencyKey,
        ?string $payloadHash,
    ): BookingCancellationOperation {
        $now = CarbonImmutable::now('UTC');
        $inProgress = BookingCancellationOperation::query()
            ->where('booking_id', $booking->id)
            ->whereIn('status', ['PENDING', 'PROCESSING', 'MANUAL_REVIEW'])
            ->lockForUpdate()
            ->first();

        if ($inProgress !== null) {
            $this->fail(409, 'CANCELLATION_IN_PROGRESS', 'A cancellation is already being processed.');
        }

        $attempt = $this->currentAttempt($booking);
        $operationType = $this->operationType($booking, $attempt, $source, $now);

        $operation = BookingCancellationOperation::query()->create([
            'booking_id' => $booking->id,
            'actor_user_id' => $actor?->id,
            'payment_attempt_id' => $attempt?->id,
            'source' => $source,
            'reason_code' => $reasonCode,
            'reason_note' => $reasonNote,
            'idempotency_key' => $idempotencyKey,
            'payload_hash' => $payloadHash,
            'gateway_operation_key' => (string) Str::uuid(),
            'operation_type' => $operationType,
            'status' => 'PENDING',
            'attempt_count' => 0,
            'next_retry_at' => $operationType === 'NONE' ? null : $now,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        if ($operationType === 'NONE') {
            $this->finishBookingCancellation($booking, $operation, $now);
        }

        return $operation->refresh();
    }

    public function recordManualReview(
        Booking $booking,
        ?User $actor,
        string $source,
        string $reasonCode,
        ?string $reasonNote,
        ?string $idempotencyKey,
        ?string $payloadHash,
        string $errorCode = 'PAYMENT_STATE_MISMATCH',
    ): BookingCancellationOperation {
        $existing = BookingCancellationOperation::query()
            ->where('booking_id', $booking->id)
            ->whereIn('status', ['PENDING', 'PROCESSING', 'MANUAL_REVIEW'])
            ->lockForUpdate()
            ->first();
        if ($existing !== null) {
            if ($existing->source === $source
                && $existing->reason_code === $reasonCode
                && $existing->actor_user_id === $actor?->id
                && $existing->idempotency_key === $idempotencyKey) {
                return $existing;
            }

            $this->fail(409, 'CANCELLATION_IN_PROGRESS', 'A cancellation is already being processed.');
        }

        $attemptId = $booking->current_payment_attempt_id;
        if ($attemptId !== null && ! PaymentAttempt::query()->whereKey($attemptId)
            ->where('booking_id', $booking->id)->exists()) {
            $attemptId = null;
        }

        return BookingCancellationOperation::query()->create([
            'booking_id' => $booking->id,
            'actor_user_id' => $actor?->id,
            'payment_attempt_id' => $attemptId,
            'source' => $source,
            'reason_code' => $reasonCode,
            'reason_note' => $reasonNote,
            'idempotency_key' => $idempotencyKey,
            'payload_hash' => $payloadHash,
            'gateway_operation_key' => (string) Str::uuid(),
            'operation_type' => 'NONE',
            'status' => 'MANUAL_REVIEW',
            'attempt_count' => 0,
            'next_retry_at' => null,
            'last_error_code' => $errorCode,
            'created_at' => CarbonImmutable::now('UTC'),
            'updated_at' => CarbonImmutable::now('UTC'),
        ]);
    }

    /** @return array{booking: Booking, operation: BookingCancellationOperation} */
    public function complete(BookingCancellationOperation $unlockedOperation): array
    {
        return DB::transaction(function () use ($unlockedOperation): array {
            // Global lock order is booking, payment attempt, cancellation operation.
            $booking = Booking::query()->whereKey($unlockedOperation->booking_id)->lockForUpdate()->firstOrFail();
            $attempt = $unlockedOperation->payment_attempt_id === null
                ? null
                : PaymentAttempt::query()->whereKey($unlockedOperation->payment_attempt_id)
                    ->where('booking_id', $booking->id)->lockForUpdate()->first();
            $operation = BookingCancellationOperation::query()->whereKey($unlockedOperation->id)
                ->lockForUpdate()->firstOrFail();
            $now = CarbonImmutable::now('UTC');

            if ($operation->status === 'COMPLETED') {
                return ['booking' => $booking, 'operation' => $operation];
            }

            if ($operation->status === 'MANUAL_REVIEW') {
                return ['booking' => $booking, 'operation' => $operation];
            }

            $isLateAuthorizationRefund = $operation->source === 'SYSTEM'
                && $operation->reason_code === 'PAYMENT_LATE_AUTHORIZED'
                && $operation->operation_type === 'REFUND';
            $expectedBookingStatus = $operation->operation_type === 'VOID' ? 'AWAITING_PAYMENT' : 'CONFIRMED';
            $bookingStatusMatches = $booking->status === $expectedBookingStatus
                || ($isLateAuthorizationRefund && in_array($booking->status, ['AWAITING_PAYMENT', 'CANCELLED'], true));
            if (! $bookingStatusMatches) {
                $operation->status = 'MANUAL_REVIEW';
                $operation->last_error_code = 'PAYMENT_STATE_MISMATCH';
                $operation->lease_expires_at = null;
                $operation->next_retry_at = null;
                $operation->save();

                return ['booking' => $booking, 'operation' => $operation];
            }

            if (! $this->gatewayStateIsSuccessful($operation, $attempt)) {
                $operation->status = 'MANUAL_REVIEW';
                $operation->last_error_code = 'PAYMENT_STATE_MISMATCH';
                $operation->lease_expires_at = null;
                $operation->next_retry_at = null;
                $operation->save();

                return ['booking' => $booking, 'operation' => $operation];
            }

            if ($attempt !== null) {
                $attempt->status = $operation->operation_type === 'VOID' ? 'VOIDED' : 'REFUNDED';
                $attempt->save();
            }

            $this->finishBookingCancellation($booking, $operation, $now);

            return ['booking' => $booking->refresh(), 'operation' => $operation->refresh()];
        }, 3);
    }

    public function recordFailure(BookingCancellationOperation $unlockedOperation, string $errorCode, bool $retryable): void
    {
        DB::transaction(function () use ($unlockedOperation, $errorCode, $retryable): void {
            $bookingId = $unlockedOperation->booking_id;
            $attemptId = $unlockedOperation->payment_attempt_id;
            // Match the booking -> attempt -> operation lock order used by callbacks.
            Booking::query()->whereKey($bookingId)->lockForUpdate()->first();
            if ($attemptId !== null) {
                PaymentAttempt::query()->whereKey($attemptId)->where('booking_id', $bookingId)->lockForUpdate()->first();
            }
            $operation = BookingCancellationOperation::query()->whereKey($unlockedOperation->id)
                ->lockForUpdate()->firstOrFail();
            if ($operation->status !== 'PROCESSING') {
                return;
            }

            $operation->last_error_code = Str::limit($errorCode, 64, '');
            $operation->lease_expires_at = null;

            if (! $retryable) {
                $operation->status = 'MANUAL_REVIEW';
                $operation->next_retry_at = null;
            } elseif ($operation->attempt_count >= 6) {
                $operation->status = 'MANUAL_REVIEW';
                $operation->next_retry_at = null;
                $operation->last_error_code = $errorCode;
            } else {
                $delayMinutes = [1 => 1, 2 => 2, 3 => 4, 4 => 8, 5 => 16][$operation->attempt_count] ?? 16;
                $operation->status = 'PENDING';
                $operation->next_retry_at = CarbonImmutable::now('UTC')->addMinutes($delayMinutes);
            }

            $operation->save();
        }, 3);
    }

    public function markProcessing(BookingCancellationOperation $unlockedOperation): ?BookingCancellationOperation
    {
        return DB::transaction(function () use ($unlockedOperation): ?BookingCancellationOperation {
            $operation = BookingCancellationOperation::query()->whereKey($unlockedOperation->id)
                ->lockForUpdate()->first();
            if ($operation === null || ! in_array($operation->status, ['PENDING', 'PROCESSING'], true)) {
                return null;
            }

            $now = CarbonImmutable::now('UTC');
            if ($operation->next_retry_at !== null && $operation->next_retry_at->isFuture()) {
                return null;
            }
            if ($operation->lease_expires_at !== null && $operation->lease_expires_at->isFuture()) {
                return null;
            }

            $operation->status = 'PROCESSING';
            $operation->attempt_count++;
            $operation->lease_expires_at = $now->addMinutes(2);
            $operation->save();

            return $operation->refresh();
        }, 3);
    }

    private function currentAttempt(Booking $booking): ?PaymentAttempt
    {
        if ($booking->current_payment_attempt_id === null) {
            return null;
        }

        $attempt = PaymentAttempt::query()->whereKey($booking->current_payment_attempt_id)
            ->where('booking_id', $booking->id)->lockForUpdate()->first();
        if ($attempt === null) {
            $this->fail(409, 'PAYMENT_STATE_MISMATCH', 'The current payment attempt is inconsistent.');
        }

        return $attempt;
    }

    private function operationType(Booking $booking, ?PaymentAttempt $attempt, string $source, CarbonImmutable $now): string
    {
        if ($booking->status === 'REQUESTED') {
            if ($attempt !== null) {
                $this->fail(409, 'PAYMENT_STATE_MISMATCH', 'A REQUESTED booking has an unexpected payment attempt.');
            }
            if ($source === 'SYSTEM' && $now->lessThan($booking->created_at->addHours(24)) && $now->lessThan($booking->start_at)) {
                $this->fail(409, 'INVALID_STATE', 'The booking request has not expired.');
            }

            return 'NONE';
        }

        if ($booking->status === 'AWAITING_PAYMENT') {
            if ($source === 'SYSTEM' && ($booking->payment_due_at === null || $now->lessThan($booking->payment_due_at))) {
                $this->fail(409, 'INVALID_STATE', 'The payment deadline has not expired.');
            }
            if ($attempt === null || in_array($attempt->status, ['FAILED', 'VOIDED'], true)) {
                return 'NONE';
            }
            if ($attempt->status === 'PENDING') {
                return 'VOID';
            }

            $this->fail(409, 'PAYMENT_STATE_MISMATCH', 'The payment attempt cannot be voided from its current state.');
        }

        if ($booking->status === 'CONFIRMED') {
            if ($now->greaterThanOrEqualTo($booking->start_at)) {
                $this->fail(409, 'INVALID_STATE', 'A confirmed booking cannot be cancelled at or after its start time.');
            }
            if ($attempt?->status === 'REFUNDED') {
                return 'NONE';
            }
            if ($attempt?->status === 'AUTHORIZED') {
                return 'REFUND';
            }

            $this->fail(409, 'PAYMENT_STATE_MISMATCH', 'The confirmed booking has no refundable authorized payment attempt.');
        }

        $this->fail(409, 'INVALID_STATE', 'This booking cannot be cancelled in its current state.');
    }

    private function gatewayStateIsSuccessful(BookingCancellationOperation $operation, ?PaymentAttempt $attempt): bool
    {
        if ($operation->operation_type === 'VOID') {
            return $attempt !== null && in_array($attempt->status, ['PENDING', 'VOIDED'], true);
        }
        if ($operation->operation_type === 'REFUND') {
            return $attempt !== null && in_array($attempt->status, ['AUTHORIZED', 'REFUNDED'], true);
        }

        return false;
    }

    private function finishBookingCancellation(Booking $booking, BookingCancellationOperation $operation, CarbonImmutable $now): void
    {
        $isLateAuthorizationRefund = $operation->source === 'SYSTEM'
            && $operation->reason_code === 'PAYMENT_LATE_AUTHORIZED'
            && $operation->operation_type === 'REFUND';

        if ($booking->status === 'CANCELLED' && ! $isLateAuthorizationRefund) {
            $matchingEvent = DB::table('booking_status_events')
                ->where('booking_id', $booking->id)
                ->where('to_status', 'CANCELLED')
                ->where('reason_code', $operation->reason_code)
                ->where('source', $operation->source)
                ->when($operation->actor_user_id !== null, fn ($query) => $query->where('actor_user_id', $operation->actor_user_id))
                ->exists();
            if (! $matchingEvent) {
                $operation->status = 'MANUAL_REVIEW';
                $operation->last_error_code = 'PAYMENT_STATE_MISMATCH';
                $operation->lease_expires_at = null;
                $operation->save();

                return;
            }
        } elseif ($booking->status !== 'CANCELLED') {
            $fromStatus = $booking->status;
            $booking->status = 'CANCELLED';
            $booking->version++;
            $booking->save();

            DB::table('booking_status_events')->insert([
                'booking_id' => $booking->id,
                'from_status' => $fromStatus,
                'to_status' => 'CANCELLED',
                'actor_user_id' => $operation->actor_user_id,
                'source' => $operation->source,
                'reason_code' => $operation->reason_code,
                'reason_text' => $operation->reason_note,
                'created_at' => $now->format('Y-m-d H:i:s.u'),
            ]);
        }

        $operation->status = 'COMPLETED';
        $operation->completed_at = $now;
        $operation->last_error_code = null;
        $operation->next_retry_at = null;
        $operation->lease_expires_at = null;
        $operation->save();
    }

    private function fail(int $status, string $code, string $message): never
    {
        throw new BookingCancellationException($status, $code, $message);
    }
}
