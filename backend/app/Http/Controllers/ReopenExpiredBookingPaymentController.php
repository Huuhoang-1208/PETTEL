<?php

namespace App\Http\Controllers;

use App\Domain\Booking\Actions\ReopenExpiredBookingPayment;
use App\Http\Resources\BookingResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReopenExpiredBookingPaymentController extends Controller
{
    public function __invoke(Request $request, string $id, ReopenExpiredBookingPayment $reopenBooking): JsonResponse
    {
        $caregiver = $request->user();
        abort_unless($caregiver instanceof User, 401);

        return response()->json(BookingResource::make($reopenBooking->handle($caregiver, $id))->resolve($request));
    }
}
