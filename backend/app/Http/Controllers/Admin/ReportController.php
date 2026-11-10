<?php

namespace App\Http\Controllers\Admin;

use App\Domain\Report\Actions\ChangeReportStatus as ChangeReportStatusAction;
use App\Domain\Report\Actions\DecideBookingReport;
use App\Domain\Report\Actions\ModerateReportedTarget;
use App\Http\Controllers\Controller;
use App\Models\Report;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

class ReportController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', Report::class);

        $filters = $request->validate([
            'status' => ['sometimes', 'string', 'in:OPEN,IN_REVIEW,RESOLVED,DISMISSED'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ]);

        $query = Report::query()->orderByDesc('created_at')->orderByDesc('id');
        if (isset($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        $reports = $query->paginate($filters['per_page'] ?? 20, [
            'id', 'reporter_user_id', 'listing_id', 'review_id', 'message_id', 'booking_id',
            'reason_code', 'status', 'resolved_at', 'created_at',
        ]);

        return response()->json([
            'data' => $reports->getCollection()->map(fn (Report $report): array => [
                'id' => (string) $report->id,
                'reporter_user_id' => (string) $report->reporter_user_id,
                'target' => $this->targetSummary($report),
                'reason_code' => $report->reason_code,
                'status' => $report->status,
                'created_at' => $this->isoTimestamp($report->created_at),
            ])->values(),
            'links' => [
                'first' => $reports->url(1),
                'last' => $reports->url($reports->lastPage()),
                'prev' => $reports->previousPageUrl(),
                'next' => $reports->nextPageUrl(),
            ],
            'meta' => [
                'current_page' => $reports->currentPage(),
                'last_page' => $reports->lastPage(),
                'per_page' => $reports->perPage(),
                'total' => $reports->total(),
            ],
        ]);
    }

    public function show(string $id): JsonResponse
    {
        $report = Report::query()->findOrFail($id);
        Gate::authorize('view', $report);

        // Do not include the message, booking snapshot, or private file URLs here.
        return response()->json([
            'data' => [
                'id' => (string) $report->id,
                'reporter_user_id' => (string) $report->reporter_user_id,
                'target' => $this->targetSummary($report),
                'reason_code' => $report->reason_code,
                'description' => $report->description,
                'status' => $report->status,
                'resolved_at' => $this->isoTimestamp($report->resolved_at),
                'created_at' => $this->isoTimestamp($report->created_at),
            ],
        ]);
    }

    /** @response array{data: array{type: string, id: string, booking_id?: string, sender_id?: string, body?: string, listing_id?: string, status?: string, start_at?: string, end_at?: string, created_at: ?string}} */
    public function evidence(Request $request, string $id): JsonResponse
    {
        $admin = $request->user();
        abort_unless($admin instanceof User, 401);

        $report = Report::query()->findOrFail($id);
        Gate::authorize('viewEvidence', $report);

        if ($report->message_id !== null) {
            $message = DB::table('messages')
                ->join('bookings', 'bookings.id', '=', 'messages.booking_id')
                ->where('messages.id', $report->message_id)
                ->select([
                    'messages.id', 'messages.booking_id', 'messages.sender_id',
                    'messages.body', 'messages.created_at',
                ])->first();
            abort_if($message === null, 404);

            $evidence = [
                'type' => 'MESSAGE',
                'id' => (string) $message->id,
                'booking_id' => (string) $message->booking_id,
                'sender_id' => (string) $message->sender_id,
                'body' => $message->body,
                'created_at' => $this->isoTimestamp($message->created_at),
            ];
        } else {
            $booking = DB::table('bookings')
                ->where('id', $report->booking_id)
                ->first(['id', 'listing_id', 'status', 'start_at', 'end_at']);
            abort_if($booking === null, 404);

            // Deliberately omit pet/listing snapshots, instructions and payment data.
            $evidence = [
                'type' => 'BOOKING',
                'id' => (string) $booking->id,
                'listing_id' => (string) $booking->listing_id,
                'status' => $booking->status,
                'start_at' => $this->isoTimestamp($booking->start_at),
                'end_at' => $this->isoTimestamp($booking->end_at),
            ];
        }

        $requestId = $this->requestId($request);
        DB::table('audit_logs')->insert([
            'actor_user_id' => $admin->id,
            'action' => 'REPORT_EVIDENCE_VIEWED',
            'resource_type' => 'REPORT',
            'resource_id' => $report->id,
            'reason' => null,
            'request_id' => $requestId,
            'metadata_json' => json_encode([
                'target_type' => $report->message_id !== null ? 'MESSAGE' : 'BOOKING',
                'target_id' => (string) ($report->message_id ?? $report->booking_id),
            ], JSON_THROW_ON_ERROR),
            'created_at' => CarbonImmutable::now('UTC'),
        ]);

        return response()->json(['data' => $evidence]);
    }

    public function changeStatus(
        Request $request,
        string $id,
        ChangeReportStatusAction $changeReportStatus,
    ): JsonResponse {
        $data = $request->validate([
            'status' => ['required', 'string', 'in:OPEN,IN_REVIEW,RESOLVED,DISMISSED'],
            'reason' => ['required', 'string', 'min:1', 'max:1000'],
        ]);

        $admin = $request->user();
        abort_unless($admin instanceof User, 401);

        $reason = trim($data['reason']);
        $requestId = $this->requestId($request);
        $report = Report::query()->findOrFail($id);
        Gate::authorize('changeStatus', $report);

        if ($report->booking_id !== null && $data['status'] === 'RESOLVED') {
            return response()->json([
                'error' => [
                    'code' => 'BOOKING_DECISION_REQUIRED',
                    'message' => 'Resolve a booking report through the booking-decision endpoint.',
                    'details' => [],
                    'request_id' => $requestId,
                ],
            ], 409);
        }

        $changeReportStatus->handle(
            $admin,
            $id,
            $data['status'],
            $reason,
            $requestId,
        );

        $report->refresh();

        return response()->json([
            'data' => [
                'id' => (string) $report->id,
                'status' => $report->status,
                'resolved_at' => $this->isoTimestamp($report->resolved_at),
            ],
        ]);
    }

    /** @response array{data: array{report_id: string, status: string, decision: string, operation_type: string, operation_id: string, operation_status: string, replayed: bool}} */
    public function bookingDecision(
        Request $request,
        string $id,
        DecideBookingReport $decideBookingReport,
    ): JsonResponse {
        $data = $request->validate([
            'decision' => ['required', 'string', 'in:COMPLETE,CANCEL_REFUND'],
            'reason' => ['required', 'string', 'min:1', 'max:1000'],
        ]);

        $admin = $request->user();
        abort_unless($admin instanceof User, 401);

        $report = Report::query()->findOrFail($id);
        Gate::authorize('view', $report);

        $result = $decideBookingReport->handle(
            $admin,
            $id,
            $data['decision'],
            trim($data['reason']),
            $this->requestId($request),
        );

        return response()->json(['data' => $result], $result['replayed'] ? 200 : 202);
    }

    /** @response array{data: list<array{actor_user_id: ?string, from_status: ?string, to_status: string, reason: ?string, request_id: ?string, created_at: ?string}>, moderation_actions: list<array{actor_user_id: ?string, action: string, target_type: string, target_id: string, reason: ?string, created_at: ?string}>} */
    public function history(string $id): JsonResponse
    {
        $report = Report::query()->findOrFail($id);
        Gate::authorize('view', $report);

        $events = DB::table('moderation_actions')
            ->where('report_id', $id)
            ->orderBy('created_at')
            ->orderBy('id')
            ->get();

        $statusEvents = DB::table('report_status_events')
            ->where('report_id', $id)
            ->orderBy('created_at')
            ->orderBy('id')
            ->get();

        return response()->json([
            'data' => $statusEvents->map(fn (object $event): array => [
                'actor_user_id' => $event->actor_user_id === null ? null : (string) $event->actor_user_id,
                'from_status' => $event->from_status,
                'to_status' => $event->to_status,
                'reason' => $event->reason,
                'request_id' => $event->request_id,
                'created_at' => $this->isoTimestamp($event->created_at),
            ])->values(),
            'moderation_actions' => $events->map(fn (object $event): array => [
                'actor_user_id' => $event->admin_user_id === null ? null : (string) $event->admin_user_id,
                'action' => $event->action,
                'target_type' => $event->target_type,
                'target_id' => (string) $event->target_id,
                'reason' => $event->reason,
                'created_at' => $this->isoTimestamp($event->created_at),
            ])->values(),
        ]);
    }

    /** @response array{data: array{report_id: string, action: string, status: string}} */
    public function moderate(
        Request $request,
        string $id,
        ModerateReportedTarget $moderateReportedTarget,
    ): JsonResponse {
        $data = $request->validate([
            'action' => ['required', 'string', 'in:HIDE,RESTORE'],
            'reason' => ['required', 'string', 'min:1', 'max:1000'],
        ]);

        $admin = $request->user();
        abort_unless($admin instanceof User, 401);

        $report = Report::query()->findOrFail($id);
        Gate::authorize('moderate', $report);

        $moderateReportedTarget->handle(
            $admin,
            $id,
            $data['action'],
            $data['reason'],
            $this->requestId($request),
        );

        return response()->json([
            'data' => [
                'report_id' => (string) $report->id,
                'action' => $data['action'],
                'status' => $report->status,
            ],
        ]);
    }

    /** @return array{type: string, id: string}|null */
    private function targetSummary(Report $report): ?array
    {
        foreach ([
            'listing_id' => 'LISTING',
            'review_id' => 'REVIEW',
            'message_id' => 'MESSAGE',
            'booking_id' => 'BOOKING',
        ] as $field => $type) {
            if ($report->{$field} !== null) {
                return ['type' => $type, 'id' => (string) $report->{$field}];
            }
        }

        return null;
    }

    private function isoTimestamp(mixed $value): ?string
    {
        return $value === null ? null : CarbonImmutable::parse($value)->utc()->toISOString();
    }

    private function requestId(Request $request): string
    {
        $id = $request->attributes->get('request_id');
        if (is_string($id) && Str::isUuid($id)) {
            return $id;
        }

        $headerId = $request->header('X-Request-ID');

        return is_string($headerId) && Str::isUuid($headerId)
            ? $headerId
            : (string) Str::uuid();
    }
}
