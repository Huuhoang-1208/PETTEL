<?php

namespace App\Http\Controllers;

use App\Domain\Booking\Actions\RejectBooking;
use App\Http\Resources\BookingResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RejectBookingController extends Controller
{
    public function __invoke(Request $request, string $id, RejectBooking $rejectBooking): JsonResponse
    {
        $caregiver = $request->user();
        abort_unless($caregiver instanceof User, 401);

        $booking = $rejectBooking->handle($caregiver, $id);

        return response()->json(BookingResource::make($booking)->resolve($request));
    }
}
