<?php

namespace App\Domain\Booking\Actions;

use App\Models\Booking;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

class ExpireBooking
{
    public function handle(string $id): bool
    {
        try {
            return DB::transaction(function () use ($id): bool {
                $booking = Booking::query()->whereKey($id)->lockForUpdate()->first();
                if ($booking === null) {
                    return false;
                }

                $now = CarbonImmutable::now('UTC');
                if ($booking->status === 'REQUESTED') {
                    $deadline = $booking->created_at->addHours(24)->min($booking->start_at);
                    if ($now->lessThan($deadline)) {
                        return false;
                    }
                    $reasonCode = 'REQUEST_EXPIRED';
                } elseif ($booking->status === 'AWAITING_PAYMENT') {
                    if ($booking->payment_due_at === null || $now->lessThan($booking->payment_due_at)) {
                        return false;
                    }
                    $reasonCode = 'PAYMENT_EXPIRED';
                } else {
                    return false;
                }

                app(CancellationCoordinator::class)->start(
                    $booking,
                    null,
                    'SYSTEM',
                    $reasonCode,
                    null,
                    null,
                    null,
                );

                return true;
            }, 3);
        } catch (BookingCancellationException $exception) {
            if ($exception->errorCode !== 'PAYMENT_STATE_MISMATCH') {
                return false;
            }

            return DB::transaction(function () use ($id): bool {
                $booking = Booking::query()->whereKey($id)->lockForUpdate()->first();
                if ($booking === null || ! in_array($booking->status, ['AWAITING_PAYMENT', 'REQUESTED'], true)) {
                    return false;
                }

                $reasonCode = $booking->status === 'REQUESTED' ? 'REQUEST_EXPIRED' : 'PAYMENT_EXPIRED';
                app(CancellationCoordinator::class)->recordManualReview(
                    $booking,
                    null,
                    'SYSTEM',
                    $reasonCode,
                    null,
                    null,
                    null,
                );

                return true;
            }, 3);
        }
    }
}
