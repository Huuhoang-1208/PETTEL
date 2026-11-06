<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\RetryCancellationOperationRequest;
use App\Models\Booking;
use App\Models\BookingCancellationOperation;
use App\Models\PaymentAttempt;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class BookingCancellationQueueController extends Controller
{
    public function index(): JsonResponse
    {
        $operations = BookingCancellationOperation::query()
            ->where('status', 'MANUAL_REVIEW')
            ->orderBy('created_at')
            ->limit(100)
            ->get();

        return response()->json([
            'data' => $operations->map(fn (BookingCancellationOperation $operation): array => [
                'id' => (string) $operation->id,
                'booking_id' => (string) $operation->booking_id,
                'operation_type' => $operation->operation_type,
                'source' => $operation->source,
                'reason_code' => $operation->reason_code,
                'attempt_count' => $operation->attempt_count,
                'last_error_code' => $operation->last_error_code,
                'created_at' => $operation->created_at?->utc()->toISOString(),
            ])->values(),
        ]);
    }

    public function retry(RetryCancellationOperationRequest $request, string $id): JsonResponse
    {
        $admin = $request->user();
        abort_unless($admin instanceof User, 401);

        $operation = DB::transaction(function () use ($admin, $id, $request): BookingCancellationOperation {
            $lookup = BookingCancellationOperation::query()->whereKey($id)->first();
            abort_if($lookup === null, 404);

            // Keep callback/cancel lock ordering: booking, attempt, operation.
            Booking::query()->whereKey($lookup->booking_id)->lockForUpdate()->firstOrFail();
            if ($lookup->payment_attempt_id !== null) {
                PaymentAttempt::query()->whereKey($lookup->payment_attempt_id)
                    ->where('booking_id', $lookup->booking_id)->lockForUpdate()->first();
            }
            $operation = BookingCancellationOperation::query()->whereKey($id)->lockForUpdate()->firstOrFail();
            abort_unless($operation->status === 'MANUAL_REVIEW', 409, 'Cancellation operation is not awaiting review.');
            abort_unless($operation->last_error_code !== 'PAYMENT_STATE_MISMATCH', 409, 'Resolve the payment state before retrying this operation.');

            $now = CarbonImmutable::now('UTC');
            $operation->status = 'PENDING';
            $operation->attempt_count = 0;
            $operation->next_retry_at = $now;
            $operation->lease_expires_at = null;
            $operation->last_error_code = null;
            $operation->save();

            DB::table('audit_logs')->insert([
                'actor_user_id' => $admin->id,
                'action' => 'booking.cancellation.retry',
                'resource_type' => 'booking_cancellation_operation',
                'resource_id' => $operation->id,
                'reason' => $request->validated('reason'),
                'request_id' => (string) Str::uuid(),
                'metadata_json' => json_encode(['booking_id' => (string) $operation->booking_id], JSON_THROW_ON_ERROR),
                'created_at' => $now->format('Y-m-d H:i:s.u'),
            ]);

            return $operation->refresh();
        }, 3);

        return response()->json([
            'id' => (string) $operation->id,
            'status' => 'CANCELLATION_PENDING',
            'booking_id' => (string) $operation->booking_id,
        ], 202);
    }
}
