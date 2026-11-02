<?php

namespace App\Domain\Booking\Actions;

use App\Models\Booking;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

class AcceptBooking
{
    public function handle(User $caregiver, string $id): Booking
    {
        return DB::transaction(function () use ($caregiver, $id): Booking {
            $validator = app(ValidateBookingAcceptance::class);
            $booking = $validator->handle($caregiver, $id, lockForAcceptance: true);
            $acceptedAt = CarbonImmutable::now('UTC');
            // Time can advance while waiting for a DB lock.
            $validator->ensureNotExpired($booking, $acceptedAt);

            $booking->status = 'AWAITING_PAYMENT';
            $booking->payment_due_at = $acceptedAt->addMinutes(15);
            $booking->version = $booking->version + 1;
            $booking->save();

            DB::table('booking_status_events')->insert([
                'booking_id' => $booking->id,
                'from_status' => 'REQUESTED',
                'to_status' => 'AWAITING_PAYMENT',
                'actor_user_id' => $caregiver->id,
                'source' => 'USER',
                'reason_code' => null,
                'reason_text' => null,
                'created_at' => $acceptedAt->format('Y-m-d H:i:s.u'),
            ]);

            return $booking;
        }, 3);
    }
}
