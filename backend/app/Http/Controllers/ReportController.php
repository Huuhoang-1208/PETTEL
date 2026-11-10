<?php

namespace App\Http\Controllers;

use App\Models\Report;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ReportController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'listing_id' => ['sometimes', 'nullable', 'integer', 'min:1'],
            'review_id' => ['sometimes', 'nullable', 'integer', 'min:1'],
            'message_id' => ['sometimes', 'nullable', 'integer', 'min:1'],
            'booking_id' => ['sometimes', 'nullable', 'integer', 'min:1'],
            'reason_code' => ['required', 'string', 'max:64'],
            'description' => ['sometimes', 'nullable', 'string', 'max:2000'],
        ]);

        $reporter = $request->user();
        abort_unless($reporter instanceof User, 401);

        $targetFields = ['listing_id', 'review_id', 'message_id', 'booking_id'];
        $targets = array_filter(
            $targetFields,
            fn (string $field): bool => filled($data[$field] ?? null),
        );

        if (count($targets) !== 1) {
            throw ValidationException::withMessages([
                'target' => ['Cần cung cấp đúng một target report.'],
            ]);
        }

        $targetField = array_values($targets)[0];
        $targetId = (int) $data[$targetField];

        // Private booking/message targets are scoped to a booking participant.
        // Return the same 404 for a missing target and a non-participant.
        if ($targetField === 'booking_id') {
            $this->assertCanReportBooking($reporter, $targetId);
        } elseif ($targetField === 'message_id') {
            $this->assertCanReportMessage($reporter, $targetId);
        } elseif ($targetField === 'listing_id') {
            abort_unless(DB::table('listings')->where('id', $targetId)
                ->whereNull('deleted_at')->exists(), 404);
        } else {
            abort_unless(DB::table('reviews')->where('id', $targetId)->exists(), 404);
        }

        $now = CarbonImmutable::now('UTC');
        $requestId = $this->requestId($request);

        $reportId = DB::transaction(function () use ($data, $reporter, $targetField, $targetId, $now, $requestId): int {
            $reportValues = [
                'reporter_user_id' => $reporter->id,
                'listing_id' => null,
                'review_id' => null,
                'message_id' => null,
                'booking_id' => null,
                'reason_code' => trim($data['reason_code']),
                'description' => isset($data['description']) ? trim($data['description']) : null,
                'status' => 'OPEN',
                'resolved_at' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ];
            $reportValues[$targetField] = $targetId;

            $id = (int) DB::table('reports')->insertGetId($reportValues);

            DB::table('report_status_events')->insert([
                'report_id' => $id,
                'actor_user_id' => $reporter->id,
                'from_status' => null,
                'to_status' => 'OPEN',
                'reason' => null,
                'request_id' => $requestId,
                'created_at' => $now,
            ]);

            DB::table('audit_logs')->insert([
                'actor_user_id' => $reporter->id,
                'action' => 'REPORT_CREATED',
                'resource_type' => 'REPORT',
                'resource_id' => $id,
                'reason' => null,
                'request_id' => $requestId,
                'metadata_json' => json_encode([
                    'to' => ['status' => 'OPEN'],
                    'target_type' => strtoupper(str_replace('_id', '', $targetField)),
                    'target_id' => (string) $targetId,
                ], JSON_THROW_ON_ERROR),
                'created_at' => $now,
            ]);

            return $id;
        }, 3);

        return response()->json([
            'data' => [
                'id' => (string) $reportId,
                'status' => 'OPEN',
                'created_at' => $now->toISOString(),
            ],
        ], 201);
    }

    public function mine(Request $request): JsonResponse
    {
        $reporter = $request->user();
        abort_unless($reporter instanceof User, 401);

        $reports = Report::query()
            ->where('reporter_user_id', $reporter->id)
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate(20, [
                'id', 'listing_id', 'review_id', 'message_id', 'booking_id',
                'reason_code', 'status', 'resolved_at', 'created_at',
            ]);

        return response()->json([
            'data' => $reports->getCollection()->map(fn (Report $report): array => [
                'id' => (string) $report->id,
                'target' => $this->targetSummary($report),
                'reason_code' => $report->reason_code,
                'status' => $report->status,
                'resolved_at' => $this->isoTimestamp($report->resolved_at),
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

    private function assertCanReportBooking(User $reporter, int $bookingId): void
    {
        $allowed = DB::table('bookings')
            ->where('id', $bookingId)
            ->where(function ($query) use ($reporter): void {
                $query->where('owner_id', $reporter->id)
                    ->orWhere('caregiver_user_id', $reporter->id);
            })
            ->exists();

        abort_unless($allowed, 404);
    }

    private function assertCanReportMessage(User $reporter, int $messageId): void
    {
        $allowed = DB::table('messages')
            ->join('bookings', 'bookings.id', '=', 'messages.booking_id')
            ->where('messages.id', $messageId)
            ->where(function ($query) use ($reporter): void {
                $query->where('bookings.owner_id', $reporter->id)
                    ->orWhere('bookings.caregiver_user_id', $reporter->id);
            })
            ->exists();

        abort_unless($allowed, 404);
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
