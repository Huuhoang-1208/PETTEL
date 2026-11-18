<?php

use App\Domain\Booking\Actions\BookingMessageAccess;
use App\Models\User;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\DB;

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

Broadcast::channel('bookings.{bookingId}', function (User $user, string $bookingId): bool {
    if (! ctype_digit($bookingId)) {
        return false;
    }

    try {
        app(BookingMessageAccess::class)
            ->bookingForRead($user, $bookingId);

        return true;
    } catch (HttpResponseException) {
        return false;
    }
});

Broadcast::channel('inquiries.{inquiryId}', function (User $user, string $inquiryId): bool {
    if (! ctype_digit($inquiryId)) {
        return false;
    }

    return DB::table('service_inquiries')
        ->where('id', $inquiryId)
        ->where(fn ($query) => $query
            ->where('owner_id', $user->id)
            ->orWhere('caregiver_user_id', $user->id))
        ->exists();
});
