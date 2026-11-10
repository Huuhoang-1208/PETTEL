<?php

namespace App\Http\Controllers;

use App\Domain\Booking\Actions\ConfirmBookingCompletion;
use App\Http\Resources\BookingResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use LogicException;

class ConfirmBookingCompletionController extends Controller
{
    public function __invoke(
        Request $request,
        string $id,
        ConfirmBookingCompletion $confirmBookingCompletion,
    ): JsonResponse {
        $owner = $request->user();
        abort_unless($owner instanceof User, 401);

        $result = $confirmBookingCompletion->handle($owner, $id);
        $booking = $result['booking'];

        if ($booking->status === 'COMPLETED') {
            return response()->json(BookingResource::make($booking)->resolve($request));
        }

        $operation = $result['operation'];
        if ($operation === null) {
            throw new LogicException('A pending booking release must have an operation.');
        }

        return response()->json([
            'booking' => BookingResource::make($booking)->resolve($request),
            'release' => [
                'id' => (string) $operation->id,
                'status' => $operation->status,
                'replayed' => $result['replayed'],
            ],
        ], 202);
    }
}
