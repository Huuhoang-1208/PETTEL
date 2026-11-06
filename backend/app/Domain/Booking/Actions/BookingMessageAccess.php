<?php

namespace App\Domain\Booking\Actions;

use App\Models\Booking;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class BookingMessageAccess
{
    private const OPEN_STATUSES = [
        'REQUESTED',
        'AWAITING_PAYMENT',
        'CONFIRMED',
        'IN_PROGRESS',
        'AWAITING_CONFIRMATION',
    ];

    public function bookingForRead(User $actor, string $id): Booking
    {
        return $this->participantBooking($actor, $id, lock: false);
    }

    public function bookingForSend(User $actor, string $id, bool $lock = true): Booking
    {
        if ($lock) {
            $actor = User::query()->whereKey($actor->getKey())->lockForUpdate()->firstOrFail();
        }
        $booking = $this->participantBooking($actor, $id, $lock);

        if ($actor->status !== 'ACTIVE') {
            $this->fail(403, 'FORBIDDEN', 'This account cannot send booking messages.');
        }

        if (in_array($booking->status, self::OPEN_STATUSES, true)) {
            return $booking;
        }

        if ($booking->status === 'COMPLETED') {
            $completedAt = DB::table('booking_status_events')
                ->where('booking_id', $booking->getKey())
                ->where('to_status', 'COMPLETED')
                ->orderByDesc('created_at')
                ->orderByDesc('id')
                ->value('created_at');

            if (is_string($completedAt)
                && CarbonImmutable::now('UTC')->lessThan(CarbonImmutable::parse($completedAt, 'UTC')->addDays(90))) {
                return $booking;
            }
        }

        $this->fail(409, 'INVALID_STATE', 'This booking chat is read-only.');
    }

    private function participantBooking(User $actor, string $id, bool $lock): Booking
    {
        $query = Booking::query()->whereKey($id)
            ->where(fn (Builder $query) => $query
                ->where('owner_id', $actor->getKey())
                ->orWhere('caregiver_user_id', $actor->getKey()));

        if ($lock) {
            $query->lockForUpdate();
        }

        $booking = $query->first();

        if ($booking === null) {
            $this->fail(404, 'NOT_FOUND', 'Booking not found.');
        }

        if (! in_array($actor->status, ['ACTIVE', 'DELETION_PENDING'], true)) {
            $this->fail(403, 'FORBIDDEN', 'This account cannot access booking messages.');
        }

        return $booking;
    }

    private function fail(int $status, string $code, string $message): never
    {
        throw new HttpResponseException(response()->json(['error' => [
            'code' => $code,
            'message' => $message,
            'details' => [],
            'request_id' => (string) Str::uuid(),
        ]], $status));
    }
}
