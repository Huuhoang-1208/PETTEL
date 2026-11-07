<?php

namespace App\Http\Controllers;

use App\Domain\Booking\Actions\ProcessSandboxPaymentCallback;
use App\Domain\Booking\Payment\SandboxCallbackSignature;
use App\Models\Booking;
use App\Models\PaymentAttempt;
use App\Models\User;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class SimulateSandboxPaymentController extends Controller
{
    public function __invoke(
        Request $request,
        string $id,
        string $attemptId,
        SandboxCallbackSignature $signatures,
        ProcessSandboxPaymentCallback $processCallback,
    ): JsonResponse {
        abort_unless((bool) config('services.payment_sandbox.simulation_enabled'), 404);

        $validator = Validator::make($request->all(), [
            'result' => ['required', 'in:SUCCESS,FAILURE,TIMEOUT'],
        ]);

        if ($validator->fails() || array_diff(array_keys($request->all()), ['result']) !== []) {
            throw new HttpResponseException(response()->json([
                'error' => [
                    'code' => 'VALIDATION_ERROR',
                    'message' => 'The given data was invalid.',
                    'details' => $validator->errors()->toArray(),
                    'request_id' => (string) Str::uuid(),
                ],
            ], 422));
        }

        $user = $request->user();
        abort_unless($user instanceof User, 401);

        $booking = Booking::query()->whereKey($id)->where('owner_id', $user->id)->firstOrFail();
        $attempt = PaymentAttempt::query()
            ->whereKey($attemptId)
            ->where('booking_id', $booking->id)
            ->firstOrFail();

        if ((string) $booking->current_payment_attempt_id !== (string) $attempt->id) {
            return response()->json([
                'error' => [
                    'code' => 'INVALID_STATE',
                    'message' => 'Only the current payment attempt can be simulated.',
                    'details' => [],
                    'request_id' => (string) Str::uuid(),
                ],
            ], 409);
        }

        $result = (string) $request->input('result');
        if ($result === 'TIMEOUT') {
            if ($attempt->status !== 'PENDING') {
                return response()->json([
                    'error' => [
                        'code' => 'INVALID_STATE',
                        'message' => 'Only a pending attempt can time out.',
                        'details' => [],
                        'request_id' => (string) Str::uuid(),
                    ],
                ], 409);
            }

            return response()->json([
                'data' => [
                    'attempt_id' => (string) $attempt->id,
                    'booking_id' => (string) $booking->id,
                    'status' => 'PENDING',
                    'result' => 'TIMEOUT',
                    'is_sandbox' => true,
                ],
            ], 202);
        }

        $payload = [
            'event_id' => (string) Str::uuid(),
            'booking_id' => (string) $booking->id,
            'attempt_reference' => (string) $attempt->provider_ref,
            'amount_vnd' => (int) $attempt->amount_vnd,
            'result' => $result === 'SUCCESS' ? 'AUTHORIZED' : 'FAILED',
        ];

        $processed = $processCallback->handle($payload, $signatures->sign($payload));

        return response()->json([
            'data' => [
                ...$processed,
                'attempt_id' => (string) $attempt->id,
                'is_sandbox' => true,
            ],
        ]);
    }
}
