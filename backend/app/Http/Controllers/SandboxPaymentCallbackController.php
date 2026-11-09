<?php

namespace App\Http\Controllers;

use App\Domain\Booking\Actions\ProcessSandboxPaymentCallback;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class SandboxPaymentCallbackController extends Controller
{
    public function __invoke(Request $request, ProcessSandboxPaymentCallback $processCallback): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'event_id' => ['required', 'uuid'],
            'booking_id' => ['required', 'integer', 'min:1'],
            'attempt_reference' => ['required', 'string', 'max:191'],
            'amount_vnd' => ['required', 'integer', 'min:1'],
            'result' => ['required', 'string', 'in:AUTHORIZED,FAILED'],
        ]);

        if ($validator->fails() || array_diff(array_keys($request->all()), [
            'event_id', 'booking_id', 'attempt_reference', 'amount_vnd', 'result',
        ]) !== []) {
            throw new HttpResponseException(response()->json([
                'error' => [
                    'code' => 'VALIDATION_ERROR',
                    'message' => 'The given data was invalid.',
                    'details' => $validator->errors()->toArray(),
                    'request_id' => (string) Str::uuid(),
                ],
            ], 422));
        }

        $payload = [
            'event_id' => (string) $request->input('event_id'),
            'booking_id' => (string) $request->input('booking_id'),
            'attempt_reference' => (string) $request->input('attempt_reference'),
            'amount_vnd' => (int) $request->input('amount_vnd'),
            'result' => (string) $request->input('result'),
        ];

        return response()->json([
            'data' => $processCallback->handle($payload, (string) $request->header('X-Payment-Signature')),
        ]);
    }
}
