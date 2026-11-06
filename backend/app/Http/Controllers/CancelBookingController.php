<?php

namespace App\Http\Controllers;

use App\Domain\Booking\Actions\CancelBooking;
use App\Http\Requests\CancelBookingRequest;
use App\Http\Resources\BookingResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;

class CancelBookingController extends Controller
{
    public function __invoke(CancelBookingRequest $request, string $id, CancelBooking $cancelBooking): JsonResponse
    {
        $actor = $request->user();
        abort_unless($actor instanceof User, 401);

        $result = $cancelBooking->handle(
            $actor,
            $id,
            $request->validated('Idempotency-Key'),
            $request->validated('reason_note'),
        );

        if ($result['http_status'] === 202) {
            return response()->json([
                'booking' => BookingResource::make($result['booking'])->resolve($request),
                'cancellation' => [
                    'id' => (string) $result['operation']?->id,
                    'status' => 'CANCELLATION_PENDING',
                ],
            ], 202);
        }

        $operation = $result['operation'];
        if ($result['http_status'] === 409
            && $operation !== null
            && $operation->last_error_code === 'PAYMENT_STATE_MISMATCH') {
            return response()->json([
                'error' => [
                    'code' => 'PAYMENT_STATE_MISMATCH',
                    'message' => 'The payment attempt needs reconciliation before cancellation can continue.',
                    'details' => ['operation_id' => (string) $operation->id],
                    'request_id' => (string) Str::uuid(),
                ],
            ], 409);
        }

        if ($result['http_status'] === 502) {
            return response()->json([
                'error' => [
                    'code' => 'PAYMENT_CANCELLATION_FAILED',
                    'message' => 'Payment cancellation needs manual review.',
                    'details' => ['operation_id' => (string) $result['operation']?->id],
                    'request_id' => (string) Str::uuid(),
                ],
            ], 502);
        }

        return response()->json(BookingResource::make($result['booking'])->resolve($request));
    }
}
