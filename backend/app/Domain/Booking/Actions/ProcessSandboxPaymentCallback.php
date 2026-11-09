<?php

namespace App\Domain\Booking\Actions;

use App\Domain\Booking\Payment\SandboxCallbackSignature;
use App\Models\Booking;
use App\Models\PaymentAttempt;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ProcessSandboxPaymentCallback
{
    /**
     * @param  array{event_id: string, booking_id: string, attempt_reference: string, amount_vnd: int, result: string}  $payload
     * @return array{
     *   event_id: string, event_record_id?: string, processing_status: string, rejection_code: ?string,
     *   attempt_status?: ?string, booking_status?: ?string, compensation_operation_id?: ?string, replayed: bool
     * }
     */
    public function handle(array $payload, string $signature): array
    {
        $signatures = app(SandboxCallbackSignature::class);
        if (! $signatures->verify($payload, $signature)) {
            $this->fail(401, 'INVALID_CALLBACK_SIGNATURE', 'Callback signature is invalid.');
        }

        $provider = (string) config('services.payment_sandbox.provider');
        $payloadHash = $signatures->hash($payload);

        try {
            return DB::transaction(function () use ($payload, $provider, $payloadHash): array {
                $booking = Booking::query()->whereKey($payload['booking_id'])
                    ->lockForUpdate()->first();
                $attempt = $booking === null
                    ? null
                    : PaymentAttempt::query()
                        ->where('booking_id', $booking->id)
                        ->where('provider_ref', $payload['attempt_reference'])
                        ->lockForUpdate()
                        ->first();

                $existing = DB::table('payment_events')
                    ->where('provider', $provider)
                    ->where('external_event_id', $payload['event_id'])
                    ->lockForUpdate()
                    ->first();

                if ($existing !== null) {
                    if (! hash_equals($existing->payload_hash, $payloadHash)) {
                        $this->fail(409, 'PAYMENT_EVENT_CONFLICT', 'Event ID was reused with a different payload.');
                    }

                    return [
                        'event_id' => $payload['event_id'],
                        'processing_status' => (string) $existing->processing_status,
                        'rejection_code' => $existing->rejection_code === null ? null : (string) $existing->rejection_code,
                        'attempt_status' => $attempt?->status,
                        'booking_status' => $booking?->status,
                        'replayed' => true,
                    ];
                }

                $pendingVoidOperation = null;
                if ($booking !== null && $payload['result'] === 'AUTHORIZED') {
                    $pendingVoidOperation = DB::table('booking_cancellation_operations')
                        ->where('booking_id', $booking->id)
                        ->where('operation_type', 'VOID')
                        ->whereIn('status', ['PENDING', 'PROCESSING', 'MANUAL_REVIEW'])
                        ->lockForUpdate()
                        ->first();
                }

                $now = now('UTC');
                $rejectionCode = $this->rejectionCode($booking, $attempt, $payload['amount_vnd']);
                $processingStatus = 'REJECTED';
                $compensationId = null;

                if ($rejectionCode === null && $booking !== null && $attempt !== null) {
                    $currentAndPending = $booking->status === 'AWAITING_PAYMENT'
                        && (string) $booking->current_payment_attempt_id === (string) $attempt->id
                        && $attempt->status === 'PENDING'
                        && $booking->payment_due_at !== null
                        && $now->lessThan($booking->payment_due_at)
                        && $pendingVoidOperation === null;

                    if ($currentAndPending && $payload['result'] === 'FAILED') {
                        $attempt->status = 'FAILED';
                        $attempt->save();
                        $processingStatus = 'PROCESSED';
                    } elseif ($currentAndPending && $payload['result'] === 'AUTHORIZED') {
                        $attempt->status = 'AUTHORIZED';
                        $attempt->authorized_at = $now;
                        $attempt->save();

                        $fromStatus = $booking->status;
                        $booking->status = 'CONFIRMED';
                        $booking->version++;
                        $booking->save();

                        DB::table('booking_status_events')->insert([
                            'booking_id' => $booking->id,
                            'from_status' => $fromStatus,
                            'to_status' => 'CONFIRMED',
                            'actor_user_id' => null,
                            'source' => 'SANDBOX',
                            'reason_code' => null,
                            'reason_text' => null,
                            'created_at' => $now->format('Y-m-d H:i:s.u'),
                        ]);
                        $processingStatus = 'PROCESSED';
                    } elseif (
                        $payload['result'] === 'AUTHORIZED'
                        && ! in_array($attempt->status, ['AUTHORIZED', 'REFUNDED', 'RELEASED'], true)
                    ) {
                        $attempt->status = 'AUTHORIZED';
                        $attempt->authorized_at = $now;
                        $attempt->save();
                        if (
                            $pendingVoidOperation !== null
                            && in_array($pendingVoidOperation->status, ['PENDING', 'PROCESSING'], true)
                        ) {
                            DB::table('booking_cancellation_operations')
                                ->where('id', $pendingVoidOperation->id)
                                ->update([
                                    'status' => 'MANUAL_REVIEW',
                                    'last_error_code' => 'PAYMENT_CALLBACK_RACE',
                                    'next_retry_at' => null,
                                    'lease_expires_at' => null,
                                    'updated_at' => $now,
                                ]);
                        }
                        $compensationId = $this->queueLateAuthorizationRefund($booking, $attempt, $payload['event_id'], $now);
                        $processingStatus = 'PROCESSED';
                    } else {
                        $rejectionCode = 'PAYMENT_STATE_MISMATCH';
                    }
                }

                $eventId = DB::table('payment_events')->insertGetId([
                    'payment_attempt_id' => $attempt?->id,
                    'provider' => $provider,
                    'external_event_id' => $payload['event_id'],
                    'event_type' => $payload['result'] === 'AUTHORIZED' ? 'PAYMENT_AUTHORIZED' : 'PAYMENT_FAILED',
                    'payload_hash' => $payloadHash,
                    'processing_status' => $processingStatus,
                    'rejection_code' => $rejectionCode,
                    'received_at' => $now,
                    'processed_at' => $now,
                ]);

                return [
                    'event_id' => $payload['event_id'],
                    'event_record_id' => (string) $eventId,
                    'processing_status' => $processingStatus,
                    'rejection_code' => $rejectionCode,
                    'attempt_status' => $attempt?->fresh()->status,
                    'booking_status' => $booking?->fresh()->status,
                    'compensation_operation_id' => $compensationId,
                    'replayed' => false,
                ];
            }, 3);
        } catch (UniqueConstraintViolationException $exception) {
            $existing = DB::table('payment_events')->useWritePdo()
                ->where('provider', $provider)
                ->where('external_event_id', $payload['event_id'])
                ->first();

            if ($existing === null) {
                throw $exception;
            }

            if (! hash_equals($existing->payload_hash, $payloadHash)) {
                $this->fail(409, 'PAYMENT_EVENT_CONFLICT', 'Event ID was reused with a different payload.');
            }

            return [
                'event_id' => $payload['event_id'],
                'processing_status' => (string) $existing->processing_status,
                'rejection_code' => $existing->rejection_code === null ? null : (string) $existing->rejection_code,
                'replayed' => true,
            ];
        }
    }

    private function rejectionCode(?Booking $booking, ?PaymentAttempt $attempt, int $amount): ?string
    {
        if ($booking === null || $attempt === null) {
            return 'PAYMENT_REFERENCE_MISMATCH';
        }

        if ((int) $attempt->amount_vnd !== $amount || (int) $booking->price_snapshot_vnd !== $amount) {
            return 'PAYMENT_AMOUNT_MISMATCH';
        }

        return null;
    }

    private function queueLateAuthorizationRefund(
        Booking $booking,
        PaymentAttempt $attempt,
        string $eventId,
        mixed $now,
    ): string {
        $operationId = DB::table('booking_cancellation_operations')->insertGetId([
            'booking_id' => $booking->id,
            'actor_user_id' => null,
            'payment_attempt_id' => $attempt->id,
            'source' => 'SYSTEM',
            'reason_code' => 'PAYMENT_LATE_AUTHORIZED',
            'reason_note' => null,
            'idempotency_key' => 'late-auth:'.$eventId,
            'payload_hash' => hash('sha256', 'late-auth:'.$eventId),
            'gateway_operation_key' => (string) Str::uuid(),
            'operation_type' => 'REFUND',
            'status' => 'PENDING',
            'attempt_count' => 0,
            'next_retry_at' => $now,
            'lease_expires_at' => null,
            'last_error_code' => null,
            'completed_at' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        DB::table('audit_logs')->insert([
            'actor_user_id' => null,
            'action' => 'payment.late_authorization.compensation_queued',
            'resource_type' => 'payment_attempt',
            'resource_id' => $attempt->id,
            'reason' => 'PAYMENT_LATE_AUTHORIZED',
            'request_id' => $eventId,
            'metadata_json' => json_encode([
                'booking_id' => (string) $booking->id,
                'operation_id' => (string) $operationId,
            ], JSON_THROW_ON_ERROR),
            'created_at' => $now,
        ]);

        return (string) $operationId;
    }

    /** @param array<string, mixed> $details */
    private function fail(int $status, string $code, string $message, array $details = []): never
    {
        throw new HttpResponseException(response()->json([
            'error' => [
                'code' => $code,
                'message' => $message,
                'details' => $details,
                'request_id' => (string) Str::uuid(),
            ],
        ], $status));
    }
}
