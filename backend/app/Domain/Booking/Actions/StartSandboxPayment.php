<?php

namespace App\Domain\Booking\Actions;

use App\Models\Booking;
use App\Models\PaymentAttempt;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class StartSandboxPayment
{
    /**
     * @return array{attempt: PaymentAttempt, booking: Booking, replayed: bool}
     */
    public function handle(User $owner, string $bookingId, string $idempotencyKey): array
    {
        return DB::transaction(function () use ($owner, $bookingId, $idempotencyKey): array {
            $booking = Booking::query()
                ->whereKey($bookingId)
                ->where('owner_id', $owner->id)
                ->lockForUpdate()
                ->first();

            if ($booking === null) {
                $this->fail(404, 'NOT_FOUND', 'Booking not found.');
            }

            $payloadHash = hash('sha256', json_encode([
                'booking_id' => (string) $booking->id,
                'amount_vnd' => (int) $booking->price_snapshot_vnd,
                'sandbox_version' => 1,
            ], JSON_THROW_ON_ERROR));

            $existing = PaymentAttempt::query()
                ->where('booking_id', $booking->id)
                ->where('idempotency_key', $idempotencyKey)
                ->lockForUpdate()
                ->first();

            if ($existing !== null) {
                if (! hash_equals($existing->request_payload_hash, $payloadHash)) {
                    $this->fail(409, 'IDEMPOTENCY_CONFLICT', 'Idempotency key was used with a different request.');
                }

                return [
                    'attempt' => $existing,
                    'booking' => $booking,
                    'replayed' => true,
                ];
            }

            if ($booking->status !== 'AWAITING_PAYMENT') {
                $this->fail(409, 'INVALID_STATE', 'Booking is not awaiting payment.');
            }

            $now = CarbonImmutable::now('UTC');

            if ($booking->payment_due_at === null) {
                $this->fail(409, 'PAYMENT_STATE_MISMATCH', 'Booking has no payment deadline.');
            }

            if ($now->greaterThanOrEqualTo($booking->payment_due_at)) {
                $this->fail(409, 'PAYMENT_EXPIRED', 'Payment deadline has passed.');
            }

            if ($booking->current_payment_attempt_id !== null) {
                $current = PaymentAttempt::query()
                    ->whereKey($booking->current_payment_attempt_id)
                    ->where('booking_id', $booking->id)
                    ->lockForUpdate()
                    ->first();

                if ($current === null) {
                    $this->fail(409, 'PAYMENT_STATE_MISMATCH', 'Current payment attempt is invalid.');
                }

                if ($current->status === 'PENDING') {
                    $this->fail(409, 'PAYMENT_ATTEMPT_IN_PROGRESS', 'A payment attempt is already pending.');
                }

                if ($current->status !== 'FAILED') {
                    $this->fail(409, 'INVALID_STATE', 'The current payment attempt cannot be retried.');
                }
            }

            $attemptNo = ((int) PaymentAttempt::query()
                ->where('booking_id', $booking->id)
                ->max('attempt_no')) + 1;

            $attempt = PaymentAttempt::query()->create([
                'booking_id' => $booking->id,
                'attempt_no' => $attemptNo,
                'amount_vnd' => $booking->price_snapshot_vnd,
                'status' => 'PENDING',
                'provider_ref' => 'sandbox_'.Str::uuid(),
                'idempotency_key' => $idempotencyKey,
                'request_payload_hash' => $payloadHash,
            ]);

            $booking->current_payment_attempt_id = $attempt->id;
            $booking->save();

            return [
                'attempt' => $attempt,
                'booking' => $booking->refresh(),
                'replayed' => false,
            ];
        }, 3);
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
