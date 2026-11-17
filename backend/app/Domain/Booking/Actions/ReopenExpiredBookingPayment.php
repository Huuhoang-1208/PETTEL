<?php

namespace App\Domain\Booking\Actions;

use App\Models\Booking;
use App\Models\BookingCancellationOperation;
use App\Models\CaregiverProfile;
use App\Models\Listing;
use App\Models\PaymentAttempt;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ReopenExpiredBookingPayment
{
    public function handle(User $caregiver, string $id): Booking
    {
        $locator = Booking::query()->whereKey($id)
            ->where('caregiver_user_id', $caregiver->id)
            ->first(['listing_id']);
        if ($locator === null) {
            $this->fail(404, 'NOT_FOUND', 'Không tìm thấy đơn chăm sóc này.');
        }

        return DB::transaction(function () use ($caregiver, $id, $locator): Booking {
            $listing = Listing::withTrashed()->whereKey($locator->listing_id)->lockForUpdate()->first();
            $currentCaregiver = User::query()->whereKey($caregiver->id)->sharedLock()->first();
            $role = DB::table('user_roles')->where('user_id', $caregiver->id)
                ->where('role', 'CAREGIVER')->sharedLock()->first();
            if ($currentCaregiver === null || $currentCaregiver->status !== 'ACTIVE' || $role === null) {
                abort(403);
            }

            $booking = Booking::query()->whereKey($id)
                ->where('caregiver_user_id', $currentCaregiver->id)
                ->lockForUpdate()->first();
            if ($booking === null) {
                $this->fail(404, 'NOT_FOUND', 'Không tìm thấy đơn chăm sóc này.');
            }
            if ($booking->status !== 'CANCELLED') {
                $this->fail(409, 'INVALID_STATE', 'Chỉ có thể mở lại đơn đã hết hạn thanh toán.');
            }

            $now = CarbonImmutable::now('UTC');
            if ($now->greaterThanOrEqualTo($booking->start_at)) {
                $this->fail(409, 'BOOKING_STARTED', 'Không thể mở lại đơn vì thời gian chăm sóc đã bắt đầu.');
            }

            $lastEvent = DB::table('booking_status_events')->where('booking_id', $booking->id)
                ->orderByDesc('id')->first(['to_status', 'source', 'reason_code']);
            if (
                $lastEvent === null
                || $lastEvent->to_status !== 'CANCELLED'
                || $lastEvent->source !== 'SYSTEM'
                || $lastEvent->reason_code !== 'PAYMENT_EXPIRED'
            ) {
                $this->fail(409, 'NOT_REOPENABLE', 'Chỉ có thể mở lại đơn do hệ thống hủy vì quá hạn thanh toán.');
            }

            $operation = BookingCancellationOperation::query()->where('booking_id', $booking->id)
                ->where('source', 'SYSTEM')->where('reason_code', 'PAYMENT_EXPIRED')
                ->latest('id')->first();
            if ($operation === null || $operation->status !== 'COMPLETED') {
                $this->fail(409, 'CANCELLATION_PENDING', 'Đơn đang được xử lý hủy. Vui lòng thử lại sau.');
            }

            $pendingOperation = BookingCancellationOperation::query()->where('booking_id', $booking->id)
                ->whereIn('status', ['PENDING', 'PROCESSING', 'MANUAL_REVIEW'])->exists();
            if ($pendingOperation) {
                $this->fail(409, 'CANCELLATION_PENDING', 'Đơn đang được xử lý hủy. Vui lòng thử lại sau.');
            }

            $attempt = null;
            if ($booking->current_payment_attempt_id !== null) {
                $attempt = PaymentAttempt::query()->whereKey($booking->current_payment_attempt_id)
                    ->where('booking_id', $booking->id)->lockForUpdate()->first();
                if ($attempt === null || ! in_array($attempt->status, ['FAILED', 'VOIDED'], true)) {
                    $this->fail(409, 'PAYMENT_REVIEW_REQUIRED', 'Giao dịch trước cần được kiểm tra trước khi mở lại đơn.');
                }
            }

            $profile = $listing === null
                ? null
                : CaregiverProfile::query()->whereKey($listing->caregiver_profile_id)->sharedLock()->first();
            if (
                $listing === null || $listing->trashed() || $listing->status !== 'ACTIVE'
                || $profile === null || (string) $profile->user_id !== (string) $currentCaregiver->id
            ) {
                $this->fail(409, 'LISTING_UNAVAILABLE', 'Dịch vụ này hiện không nhận đặt lịch nên chưa thể mở lại đơn.');
            }

            if (! app(ApplyListingAvailability::class)->isAvailableFor($booking, lock: true)) {
                $this->fail(409, 'AVAILABILITY_CONFLICT', 'Khung giờ này hiện đã có lịch khác. Không thể mở lại đơn.');
            }

            $booking->status = 'AWAITING_PAYMENT';
            $booking->payment_due_at = $now->addMinutes(15);
            $booking->current_payment_attempt_id = null;
            $booking->version++;
            $booking->save();

            DB::table('booking_status_events')->insert([
                'booking_id' => $booking->id,
                'from_status' => 'CANCELLED',
                'to_status' => 'AWAITING_PAYMENT',
                'actor_user_id' => $currentCaregiver->id,
                'source' => 'USER',
                'reason_code' => 'CAREGIVER_REOPENED_PAYMENT',
                'reason_text' => null,
                'created_at' => $now->format('Y-m-d H:i:s.u'),
            ]);

            return $booking->refresh();
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
