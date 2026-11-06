<?php

namespace App\Domain\Booking\Actions;

use App\Models\Booking;
use App\Models\BookingCancellationOperation;
use App\Models\User;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CancelBooking
{
    /** @return array{booking: Booking, operation: BookingCancellationOperation|null, http_status: int} */
    public function handle(User $actor, string $id, string $key, ?string $note): array
    {
        $booking = DB::transaction(function () use ($actor, $id, $key, $note): Booking|BookingCancellationOperation {
            $booking = Booking::query()->whereKey($id)->lockForUpdate()->first();
            if ($booking === null) {
                $this->fail(404, 'NOT_FOUND', 'Booking not found.');
            }

            $reasonCode = $this->authorizeAndReason($actor, $booking);
            $payloadHash = hash('sha256', json_encode([
                'booking_id' => (string) $booking->id,
                'action' => 'CANCEL',
                'reason_code' => $reasonCode,
                'reason_note' => $note,
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));

            $existingOperation = BookingCancellationOperation::query()
                ->where('booking_id', $booking->id)
                ->where('actor_user_id', $actor->id)
                ->where('idempotency_key', $key)
                ->first();
            if ($existingOperation !== null) {
                if (! hash_equals((string) $existingOperation->payload_hash, $payloadHash)) {
                    $this->fail(409, 'IDEMPOTENCY_CONFLICT', 'The idempotency key was already used with different cancellation data.');
                }

                return $existingOperation;
            }

            if ($booking->status === 'CANCELLED') {
                $matchingCancellation = DB::table('booking_status_events')
                    ->where('booking_id', $booking->id)
                    ->where('to_status', 'CANCELLED')
                    ->where('actor_user_id', $actor->id)
                    ->where('source', 'USER')
                    ->where('reason_code', $reasonCode)
                    ->exists();
                if ($matchingCancellation) {
                    return $booking;
                }
            }

            $coordinator = app(CancellationCoordinator::class);

            try {
                return $coordinator->start(
                    $booking,
                    $actor,
                    'USER',
                    $reasonCode,
                    $note,
                    $key,
                    $payloadHash,
                );
            } catch (BookingCancellationException $exception) {
                if ($exception->errorCode !== 'PAYMENT_STATE_MISMATCH') {
                    throw $exception;
                }

                return $coordinator->recordManualReview(
                    $booking,
                    $actor,
                    'USER',
                    $reasonCode,
                    $note,
                    $key,
                    $payloadHash,
                );
            }
        }, 3);

        if ($booking instanceof BookingCancellationOperation) {
            $operation = $booking;
            $freshBooking = Booking::query()->findOrFail($operation->booking_id);

            return [
                'booking' => $freshBooking,
                'operation' => $operation,
                'http_status' => match ($operation->status) {
                    'COMPLETED' => 200,
                    'MANUAL_REVIEW' => $operation->last_error_code === 'PAYMENT_STATE_MISMATCH' ? 409 : 502,
                    default => 202,
                },
            ];
        }

        return ['booking' => $booking, 'operation' => null, 'http_status' => 200];
    }

    private function authorizeAndReason(User $actor, Booking $booking): string
    {
        if ((int) $booking->owner_id === (int) $actor->id) {
            if (! $actor->hasRole('OWNER')) {
                $this->fail(403, 'FORBIDDEN', 'An owner role is required to cancel this booking.');
            }

            return 'OWNER_CANCELLED';
        }
        if ((int) $booking->caregiver_user_id === (int) $actor->id) {
            if (! $actor->hasRole('CAREGIVER')) {
                $this->fail(403, 'FORBIDDEN', 'A caregiver role is required to cancel this booking.');
            }

            return 'CAREGIVER_CANCELLED';
        }

        $this->fail(404, 'NOT_FOUND', 'Booking not found.');
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
