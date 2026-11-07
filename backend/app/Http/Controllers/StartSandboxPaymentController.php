<?php

namespace App\Http\Controllers;

use App\Domain\Booking\Actions\StartSandboxPayment;
use App\Models\User;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class StartSandboxPaymentController extends Controller
{
    public function __invoke(
        Request $request,
        string $id,
        StartSandboxPayment $startPayment,
    ): JsonResponse {
        $key = $request->header('Idempotency-Key');

        $validator = Validator::make(
            ['Idempotency-Key' => $key],
            ['Idempotency-Key' => ['required', 'string', 'regex:/\A[a-z0-9._:-]{1,128}\z/']],
        );

        if ($validator->fails()) {
            $this->fail(422, 'VALIDATION_ERROR', 'The given data was invalid.', [
                'Idempotency-Key' => $validator->errors()->get('Idempotency-Key'),
            ]);
        }

        if ($request->all() !== []) {
            $this->fail(422, 'VALIDATION_ERROR', 'Payment amount and outcome are controlled by the server.', [
                'body' => ['Do not send payment amount or result.'],
            ]);
        }

        $owner = $request->user();

        abort_unless($owner instanceof User, 401);

        $result = $startPayment->handle($owner, $id, $key);

        $attempt = $result['attempt'];
        $booking = $result['booking'];

        return response()->json([
            'data' => [
                'id' => (string) $attempt->id,
                'booking_id' => (string) $booking->id,
                'attempt_no' => (int) $attempt->attempt_no,
                'amount_vnd' => (int) $attempt->amount_vnd,
                'status' => $attempt->status,
                'provider_ref' => $attempt->provider_ref,
                'payment_due_at' => $booking->payment_due_at?->utc()->format('Y-m-d\TH:i:s.u\Z'),
                'is_sandbox' => true,
            ],
        ], $result['replayed'] ? 200 : 201);
    }

    /** @param array<string, mixed> $details */
    private function fail(int $status, string $code, string $message, array $details = []): never
    {
        throw new HttpResponseException(response()->json([
            'error' => [
                'code' => $code,
                'message' => $message,
                'details' => $details,
                'request_id' => (string) Str::uuid(),
            ],
        ], $status));
    }
}
