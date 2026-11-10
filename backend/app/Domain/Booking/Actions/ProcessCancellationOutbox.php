<?php

namespace App\Domain\Booking\Actions;

use App\Domain\Booking\Payment\PaymentCancellationGateway;
use App\Models\BookingCancellationOperation;
use App\Models\PaymentAttempt;
use Illuminate\Support\Facades\Log;
use Throwable;

class ProcessCancellationOutbox
{
    /** @return array{processed: int, completed: int, pending: int, manual_review: int, skipped: int} */
    public function handle(int $batchSize = 100): array
    {
        $ids = BookingCancellationOperation::query()
            ->whereIn('status', ['PENDING', 'PROCESSING'])
            ->where(function ($query): void {
                $query->whereNull('next_retry_at')->orWhere('next_retry_at', '<=', now('UTC'));
            })
            ->where(function ($query): void {
                $query->whereNull('lease_expires_at')->orWhere('lease_expires_at', '<=', now('UTC'));
            })
            ->orderBy('id')
            ->limit(max(1, min($batchSize, 100)))
            ->pluck('id');

        $counts = ['processed' => 0, 'completed' => 0, 'pending' => 0, 'manual_review' => 0, 'skipped' => 0];

        foreach ($ids as $id) {
            try {
                $unlocked = BookingCancellationOperation::query()->whereKey($id)->first();
                $operation = $unlocked === null ? null : app(CancellationCoordinator::class)->markProcessing($unlocked);
                if ($operation === null) {
                    $final = BookingCancellationOperation::query()->whereKey($id)->first();
                    if ($final?->status === 'MANUAL_REVIEW') {
                        $counts['manual_review']++;
                    } else {
                        $counts['skipped']++;
                    }

                    continue;
                }

                $counts['processed']++;
                $attempt = $operation->payment_attempt_id === null
                    ? null
                    : PaymentAttempt::query()->whereKey($operation->payment_attempt_id)
                        ->where('booking_id', $operation->booking_id)->first();

                if ($attempt === null || ! in_array($operation->operation_type, ['VOID', 'REFUND'], true)) {
                    app(CancellationCoordinator::class)->recordFailure($operation, 'PAYMENT_STATE_MISMATCH', false);
                } else {
                    try {
                        $result = app(PaymentCancellationGateway::class)->cancel(
                            $operation->operation_type,
                            $attempt,
                            $operation,
                        );

                        if ($result->outcome === 'SUCCEEDED') {
                            app(CancellationCoordinator::class)->complete($operation);
                        } else {
                            app(CancellationCoordinator::class)->recordFailure(
                                $operation,
                                $result->errorCode ?? 'PAYMENT_CANCELLATION_FAILED',
                                $result->outcome === 'RETRYABLE',
                            );
                        }
                    } catch (Throwable) {
                        // Do not log gateway payloads, credentials, or customer notes.
                        app(CancellationCoordinator::class)->recordFailure($operation, 'PAYMENT_GATEWAY_ERROR', true);
                    }
                }

                $final = BookingCancellationOperation::query()->whereKey($id)->first();
                if ($final?->status === 'COMPLETED') {
                    $counts['completed']++;
                } elseif ($final?->status === 'MANUAL_REVIEW') {
                    $counts['manual_review']++;
                } else {
                    $counts['pending']++;
                }
            } catch (Throwable $exception) {
                Log::warning('booking_cancellation_operation_failed', [
                    'operation_id' => (string) $id,
                    'exception' => $exception::class,
                ]);
                $counts['pending']++;
            }
        }

        Log::info('booking_cancellation_outbox_processed', $counts);

        return $counts;
    }
}
