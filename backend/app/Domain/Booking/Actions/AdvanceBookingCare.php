<?php

namespace App\Domain\Booking\Actions;

use App\Models\Booking;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class AdvanceBookingCare
{
    /** @var array<string, array{from: string, to: string}> */
    private const TRANSITIONS = [
        'START' => ['from' => 'CONFIRMED', 'to' => 'IN_PROGRESS'],
        'REQUEST_COMPLETION' => ['from' => 'IN_PROGRESS', 'to' => 'AWAITING_CONFIRMATION'],
    ];

    public function handle(User $caregiver, string $id, string $transition): Booking
    {
        $states = self::TRANSITIONS[$transition] ?? null;
        if ($states === null) {
            throw new \InvalidArgumentException('Unsupported booking care transition.');
        }

        return DB::transaction(function () use ($caregiver, $id, $states): Booking {
            $currentCaregiver = User::query()->whereKey($caregiver->id)->lockForUpdate()->first();
            abort_if($currentCaregiver === null, 401);

            $role = DB::table('user_roles')->where('user_id', $currentCaregiver->id)
                ->where('role', 'CAREGIVER')->lockForUpdate()->first();
            abort_unless($currentCaregiver->status === 'ACTIVE' && $role !== null, 403);

            $booking = Booking::query()->whereKey($id)
                ->where('caregiver_user_id', $currentCaregiver->id)
                ->lockForUpdate()->first();

            if ($booking === null) {
                $this->fail(404, 'NOT_FOUND', 'Booking not found.');
            }

            $adminRefundInProgress = DB::table('booking_cancellation_operations')
                ->where('booking_id', $booking->id)
                ->where('source', 'ADMIN')
                ->where('reason_code', 'ADMIN_REPORT_REFUND_CANCEL')
                ->where('operation_type', 'REFUND')
                ->whereIn('status', ['PENDING', 'PROCESSING', 'MANUAL_REVIEW'])
                ->exists();

            if ($adminRefundInProgress) {
                $this->fail(
                    409,
                    'CANCELLATION_IN_PROGRESS',
                    'Booking is locked while an admin refund decision is being processed.',
                );
            }

            if ($booking->status !== $states['from']) {
                $this->fail(409, 'INVALID_STATE', 'Booking is not in the state required for this action.');
            }

            $transitionedAt = CarbonImmutable::now('UTC');
            $booking->status = $states['to'];
            $booking->version++;
            $booking->save();

            DB::table('booking_status_events')->insert([
                'booking_id' => $booking->id,
                'from_status' => $states['from'],
                'to_status' => $states['to'],
                'actor_user_id' => $currentCaregiver->id,
                'source' => 'USER',
                'reason_code' => null,
                'reason_text' => null,
                'created_at' => $transitionedAt->format('Y-m-d H:i:s.u'),
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
