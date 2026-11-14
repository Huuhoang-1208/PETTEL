<?php

use Illuminate\Support\Facades\Broadcast;
use App\Domain\Booking\Actions\BookingMessageAccess;
use App\Models\User;
use Illuminate\Http\Exceptions\HttpResponseException;

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

Broadcast::channel('bookings.{bookingId}', function (User $user, string $bookingId, ): bool {
    if (!ctype_digit($bookingId)) {
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