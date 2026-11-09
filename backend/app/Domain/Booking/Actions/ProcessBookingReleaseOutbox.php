<?php

namespace App\Domain\Booking\Actions;

use App\Domain\Booking\Payment\PaymentReleaseGateway;
use App\Models\Booking;
use App\Models\BookingReleaseOperation;
use App\Models\PaymentAttempt;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

class ProcessBookingReleaseOutbox
{
    /**
     * @return array{processed: int, completed: int, pending: int, manual_review: int, skipped: int}
     */
    public function handle(int $batchSize = 100): array
    {
        $now = CarbonImmutable::now('UTC');

        $ids = BookingReleaseOperation::query()
            ->whereIn('status', ['PENDING', 'PROCESSING'])
            ->where(function ($query) use ($now): void {
                $query->whereNull('next_retry_at')->orWhere('next_retry_at', '<=', $now);
            })
            ->where(function ($query) use ($now): void {
                $query->whereNull('lease_expires_at')->orWhere('lease_expires_at', '<=', $now);
            })
            ->orderBy('id')
            ->limit(max(1, min($batchSize, 100)))
            ->pluck('id');

        $counts = [
            'processed' => 0,
            'completed' => 0,
            'pending' => 0,
            'manual_review' => 0,
            'skipped' => 0,
        ];

        foreach ($ids as $id) {
            try {
                $unlocked = BookingReleaseOperation::query()->whereKey($id)->first();
                $operation = $unlocked === null ? null : $this->markProcessing($unlocked);

                if ($operation === null) {
                    $counts['skipped']++;

                    continue;
                }

                $counts['processed']++;

                if ($this->holdForReportReview($operation)) {
                    $final = BookingReleaseOperation::query()->whereKey($id)->first();
                    if ($final?->status === 'MANUAL_REVIEW') {
                        $counts['manual_review']++;
                    } else {
                        $counts['skipped']++;
                    }

                    continue;
                }

                $attempt = PaymentAttempt::query()
                    ->whereKey($operation->payment_attempt_id)
                    ->where('booking_id', $operation->booking_id)
                    ->first();

                if ($attempt === null) {
                    $this->recordFailure($operation, 'PAYMENT_STATE_MISMATCH', false);
                } else {
                    try {
                        $result = app(PaymentReleaseGateway::class)->release($attempt, $operation);

                        if ($result->outcome === 'SUCCEEDED') {
                            $this->complete($operation);
                        } else {
                            $this->recordFailure(
                                $operation,
                                $result->errorCode ?? 'PAYMENT_RELEASE_FAILED',
                                $result->outcome === 'RETRYABLE',
                            );
                        }
                    } catch (Throwable) {
                        // Do not log gateway payloads, credentials, or customer data.
                        $this->recordFailure($operation, 'PAYMENT_GATEWAY_ERROR', true);
                    }
                }

                $final = BookingReleaseOperation::query()->whereKey($id)->first();

                if ($final?->status === 'COMPLETED') {
                    $counts['completed']++;
                } elseif ($final?->status === 'MANUAL_REVIEW') {
                    $counts['manual_review']++;
                } else {
                    $counts['pending']++;
                }
            } catch (Throwable $exception) {
                Log::warning('booking_release_operation_failed', [
                    'operation_id' => (string) $id,
                    'exception' => $exception::class,
                ]);
                $counts['pending']++;
            }
        }

        Log::info('booking_release_outbox_processed', $counts);

        return $counts;
    }

    private function markProcessing(BookingReleaseOperation $unlocked): ?BookingReleaseOperation
    {
        return DB::transaction(function () use ($unlocked): ?BookingReleaseOperation {
            // Keep the shared lock order: booking, payment attempt, operation.
            Booking::query()->whereKey($unlocked->booking_id)->lockForUpdate()->firstOrFail();
            PaymentAttempt::query()
                ->whereKey($unlocked->payment_attempt_id)
                ->where('booking_id', $unlocked->booking_id)
                ->lockForUpdate()
                ->firstOrFail();

            $operation = BookingReleaseOperation::query()
                ->whereKey($unlocked->id)
                ->lockForUpdate()
                ->first();

            if ($operation === null || ! in_array($operation->status, ['PENDING', 'PROCESSING'], true)) {
                return null;
            }

            $now = CarbonImmutable::now('UTC');

            if (
                ($operation->next_retry_at !== null && $operation->next_retry_at->isFuture())
                || ($operation->lease_expires_at !== null && $operation->lease_expires_at->isFuture())
            ) {
                return null;
            }

            $operation->status = 'PROCESSING';
            $operation->attempt_count++;
            $operation->next_retry_at = null;
            $operation->lease_expires_at = $now->addMinutes(2);
            $operation->save();

            return $operation;
        }, 3);
    }

    private function complete(BookingReleaseOperation $unlocked): void
    {
        DB::transaction(function () use ($unlocked): void {
            $booking = Booking::query()
                ->whereKey($unlocked->booking_id)
                ->lockForUpdate()
                ->firstOrFail();

            $attempt = PaymentAttempt::query()
                ->whereKey($unlocked->payment_attempt_id)
                ->where('booking_id', $booking->id)
                ->lockForUpdate()
                ->firstOrFail();

            $operation = BookingReleaseOperation::query()
                ->whereKey($unlocked->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($operation->status === 'COMPLETED') {
                return;
            }

            if ($operation->status !== 'PROCESSING') {
                return;
            }

            if (
                $booking->status !== 'AWAITING_CONFIRMATION'
                || (string) $booking->current_payment_attempt_id !== (string) $attempt->id
                || ! in_array($attempt->status, ['AUTHORIZED', 'RELEASED'], true)
            ) {
                $this->moveToManualReview($operation, 'PAYMENT_STATE_MISMATCH');

                return;
            }

            $reportInReview = DB::table('reports')
                ->where('booking_id', $booking->id)
                ->where('status', 'IN_REVIEW')
                ->exists();

            if ($reportInReview) {
                $this->moveToManualReview($operation, 'REPORT_IN_REVIEW');

                return;
            }

            $now = CarbonImmutable::now('UTC');

            $attempt->status = 'RELEASED';
            $attempt->released_at ??= $now;
            $attempt->save();

            $booking->status = 'COMPLETED';
            $booking->version++;
            $booking->save();

            DB::table('booking_status_events')->insert([
                'booking_id' => $booking->id,
                'from_status' => 'AWAITING_CONFIRMATION',
                'to_status' => 'COMPLETED',
                'actor_user_id' => $operation->actor_user_id,
                'source' => 'USER',
                'reason_code' => 'OWNER_CONFIRMED',
                'reason_text' => null,
                'created_at' => $now->format('Y-m-d H:i:s.u'),
            ]);

            $externalEventId = 'release:'.$operation->gateway_operation_key;
            $payloadHash = hash('sha256', implode('|', [
                (string) $booking->id,
                (string) $attempt->id,
                $operation->gateway_operation_key,
                'RELEASED',
            ]));

            DB::table('payment_events')->insertOrIgnore([
                'payment_attempt_id' => $attempt->id,
                'provider' => 'sandbox',
                'external_event_id' => $externalEventId,
                'event_type' => 'RELEASED',
                'payload_hash' => $payloadHash,
                'processing_status' => 'PROCESSED',
                'rejection_code' => null,
                'received_at' => $now->format('Y-m-d H:i:s.u'),
                'processed_at' => $now->format('Y-m-d H:i:s.u'),
            ]);

            $operation->status = 'COMPLETED';
            $operation->completed_at = $now;
            $operation->lease_expires_at = null;
            $operation->next_retry_at = null;
            $operation->last_error_code = null;
            $operation->save();
        }, 3);
    }

    private function holdForReportReview(BookingReleaseOperation $unlocked): bool
    {
        return DB::transaction(function () use ($unlocked): bool {
            Booking::query()->whereKey($unlocked->booking_id)->lockForUpdate()->firstOrFail();
            PaymentAttempt::query()
                ->whereKey($unlocked->payment_attempt_id)
                ->where('booking_id', $unlocked->booking_id)
                ->lockForUpdate()
                ->firstOrFail();

            $operation = BookingReleaseOperation::query()
                ->whereKey($unlocked->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($operation->status !== 'PROCESSING') {
                return true;
            }

            $reportInReview = DB::table('reports')
                ->where('booking_id', $operation->booking_id)
                ->where('status', 'IN_REVIEW')
                ->exists();

            if (! $reportInReview) {
                return false;
            }

            $this->moveToManualReview($operation, 'REPORT_IN_REVIEW');

            return true;
        }, 3);
    }

    private function recordFailure(
        BookingReleaseOperation $unlocked,
        string $errorCode,
        bool $retryable,
    ): void {
        DB::transaction(function () use ($unlocked, $errorCode, $retryable): void {
            Booking::query()->whereKey($unlocked->booking_id)->lockForUpdate()->first();
            PaymentAttempt::query()
                ->whereKey($unlocked->payment_attempt_id)
                ->where('booking_id', $unlocked->booking_id)
                ->lockForUpdate()
                ->first();

            $operation = BookingReleaseOperation::query()
                ->whereKey($unlocked->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($operation->status !== 'PROCESSING') {
                return;
            }

            $operation->last_error_code = Str::limit($errorCode, 64, '');
            $operation->lease_expires_at = null;

            $retryDelays = [1 => 1, 2 => 5, 3 => 15];
            $delayMinutes = $retryDelays[$operation->attempt_count] ?? null;

            if (! $retryable || $delayMinutes === null) {
                $this->moveToManualReview($operation, $errorCode);

                return;
            }

            $operation->status = 'PENDING';
            $operation->next_retry_at = CarbonImmutable::now('UTC')->addMinutes($delayMinutes);
            $operation->save();
        }, 3);
    }

    private function moveToManualReview(BookingReleaseOperation $operation, string $errorCode): void
    {
        $operation->status = 'MANUAL_REVIEW';
        $operation->last_error_code = Str::limit($errorCode, 64, '');
        $operation->lease_expires_at = null;
        $operation->next_retry_at = null;
        $operation->save();
    }
}
