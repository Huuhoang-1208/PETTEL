<?php

namespace App\Http\Controllers\Admin;

use App\Domain\Booking\Actions\AdminConfirmBookingCompletion;
use App\Http\Controllers\Controller;
use App\Models\BookingReleaseOperation;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class BookingCompletionReviewController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $this->requireAdmin($request);

        $latestEvents = DB::table('booking_status_events')
            ->select('booking_id')
            ->selectRaw('MAX(id) as event_id')
            ->groupBy('booking_id');

        $overdueBookings = DB::table('booking_completion_followups as followups')
            ->join('bookings', 'bookings.id', '=', 'followups.booking_id')
            ->joinSub($latestEvents, 'latest_events', function ($join): void {
                $join->on('latest_events.booking_id', '=', 'bookings.id');
            })
            ->join('booking_status_events as latest_event', 'latest_event.id', '=', 'latest_events.event_id')
            ->whereNotNull('followups.admin_queued_at')
            ->whereNull('followups.resolved_at')
            ->where('bookings.status', 'AWAITING_CONFIRMATION')
            ->where('latest_event.to_status', 'AWAITING_CONFIRMATION')
            ->orderBy('followups.admin_queued_at')
            ->limit(100)
            ->get([
                'bookings.id as booking_id',
                'bookings.status',
                'bookings.start_at',
                'bookings.end_at',
                'followups.admin_queued_at',
                'latest_event.created_at as awaiting_since',
            ])
            ->map(fn (object $booking): array => [
                'booking_id' => (string) $booking->booking_id,
                'status' => $booking->status,
                'start_at' => $booking->start_at,
                'end_at' => $booking->end_at,
                'awaiting_since' => $booking->awaiting_since,
                'admin_queued_at' => $booking->admin_queued_at,
            ])
            ->values();

        // Show failed release operations in the same admin work queue.
        // Retrying these requires a separate audited retry action/route.
        $releaseOperations = BookingReleaseOperation::query()
            ->where('status', 'MANUAL_REVIEW')
            ->orderBy('created_at')
            ->limit(100)
            ->get([
                'id',
                'booking_id',
                'status',
                'source',
                'reason_code',
                'attempt_count',
                'last_error_code',
                'created_at',
            ])
            ->map(fn (BookingReleaseOperation $operation): array => [
                'operation_id' => (string) $operation->id,
                'booking_id' => (string) $operation->booking_id,
                'status' => $operation->status,
                'source' => $operation->source,
                'reason_code' => $operation->reason_code,
                'attempt_count' => $operation->attempt_count,
                'last_error_code' => $operation->last_error_code,
                'created_at' => $operation->created_at?->utc()->toISOString(),
            ])
            ->values();

        return response()->json([
            'data' => [
                'overdue_bookings' => $overdueBookings,
                'release_operations_for_review' => $releaseOperations,
            ],
        ]);
    }

    public function complete(
        Request $request,
        string $id,
        AdminConfirmBookingCompletion $action,
    ): JsonResponse {
        $admin = $this->requireAdmin($request);

        $data = $request->validate([
            'reason' => ['required', 'string', 'min:1', 'max:1000'],
        ]);

        $requestId = (string) (
            $request->attributes->get('request_id')
            ?? Str::uuid()
        );

        $result = $action->handle(
            $admin,
            $id,
            trim($data['reason']),
            $requestId,
        );

        return response()->json([
            'data' => [
                'booking_id' => (string) $result['booking']->id,
                'operation_id' => (string) $result['operation']->id,
                'operation_status' => $result['operation']->status,
                'replayed' => $result['replayed'],
            ],
        ], $result['replayed'] ? 200 : 202);
    }

    private function requireAdmin(Request $request): User
    {
        $user = $request->user();

        abort_unless(
            $user instanceof User
            && $user->status === 'ACTIVE'
            && $user->hasRole('ADMIN'),
            403,
        );

        return $user;
    }
}
