<?php

namespace App\Http\Controllers;

use App\Domain\Booking\Actions\AdvanceBookingCare;
use App\Http\Resources\BookingResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BookingCareLifecycleController extends Controller
{
    public function start(Request $request, string $id, AdvanceBookingCare $advanceBookingCare): JsonResponse
    {
        return $this->advance($request, $id, $advanceBookingCare, 'START');
    }

    public function requestCompletion(Request $request, string $id, AdvanceBookingCare $advanceBookingCare): JsonResponse
    {
        return $this->advance($request, $id, $advanceBookingCare, 'REQUEST_COMPLETION');
    }

    private function advance(
        Request $request,
        string $id,
        AdvanceBookingCare $advanceBookingCare,
        string $transition,
    ): JsonResponse {
        $caregiver = $request->user();
        abort_unless($caregiver instanceof User, 401);

        $booking = $advanceBookingCare->handle($caregiver, $id, $transition);

        return response()->json(BookingResource::make($booking)->resolve($request));
    }
}
