<?php

namespace App\Domain\Booking\Actions;

use App\Models\Booking;
use App\Models\User;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class RejectBooking
{
    public function handle(User $caregiver, string $id): Booking
    {
        return DB::transaction(function () use ($caregiver, $id): Booking {
            $currentCaregiver = User::query()->whereKey($caregiver->id)
                ->lockForUpdate()->first();
            abort_if($currentCaregiver === null, 401);

            $role = DB::table('user_roles')->where('user_id', $caregiver->id)
                ->where('role', 'CAREGIVER')->lockForUpdate()->first();
            abort_unless($currentCaregiver->status === 'ACTIVE' && $role !== null, 403);

            $booking = Booking::query()->whereKey($id)
                ->where('caregiver_user_id', $caregiver->id)
                ->lockForUpdate()->first();

            if ($booking === null) {
                $this->fail(404, 'NOT_FOUND', 'Booking not found.');
            }

            if ($booking->status === 'CANCELLED') {
                $previousRejection = DB::table('booking_status_events')
                    ->where('booking_id', $booking->id)
                    ->where('from_status', 'REQUESTED')
                    ->where('to_status', 'CANCELLED')
                    ->where('actor_user_id', $caregiver->id)
                    ->where('source', 'USER')
                    ->where('reason_code', 'CAREGIVER_REJECTED')
                    ->lockForUpdate()->first();

                if ($previousRejection !== null) {
                    return $booking;
                }
            }

            if ($booking->status !== 'REQUESTED') {
                $this->fail(409, 'INVALID_STATE', 'Only REQUESTED bookings can be rejected.');
            }

            $booking->status = 'CANCELLED';
            $booking->version = $booking->version + 1;
            $booking->save();

            DB::table('booking_status_events')->insert([
                'booking_id' => $booking->id,
                'from_status' => 'REQUESTED',
                'to_status' => 'CANCELLED',
                'actor_user_id' => $caregiver->id,
                'source' => 'USER',
                'reason_code' => 'CAREGIVER_REJECTED',
                'reason_text' => null,
                'created_at' => now()->utc()->format('Y-m-d H:i:s.u'),
            ]);

            return $booking;
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
