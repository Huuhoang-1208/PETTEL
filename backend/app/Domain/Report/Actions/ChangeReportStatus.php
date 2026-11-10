<?php

namespace App\Domain\Report\Actions;

use App\Models\User;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ChangeReportStatus
{
    private const TRANSITIONS = [
        'OPEN' => ['IN_REVIEW', 'DISMISSED'],
        'IN_REVIEW' => ['RESOLVED', 'DISMISSED'],
        'RESOLVED' => [],
        'DISMISSED' => [],
    ];

    public function handle(
        User $admin,
        string $reportId,
        string $toStatus,
        string $reason,
        string $requestId,
    ): void {
        $reason = trim($reason);

        if ($reason === '' || mb_strlen($reason) > 1000) {
            throw ValidationException::withMessages([
                'reason' => ['Lý do là bắt buộc và tối đa 1000 ký tự.'],
            ]);
        }

        DB::transaction(function () use ($admin, $reportId, $toStatus, $reason, $requestId): void {
            $report = DB::table('reports')
                ->where('id', $reportId)
                ->lockForUpdate()
                ->first();

            abort_if($report === null, 404);

            if ($report->booking_id !== null && $toStatus === 'RESOLVED') {
                throw new HttpResponseException(response()->json([
                    'error' => [
                        'code' => 'BOOKING_DECISION_REQUIRED',
                        'message' => 'Resolve a booking report through the booking-decision endpoint.',
                        'details' => [],
                        'request_id' => $requestId,
                    ],
                ], 409));
            }

            $allowed = self::TRANSITIONS[$report->status] ?? [];
            abort_unless(in_array($toStatus, $allowed, true), 409, 'Invalid report status transition.');

            $now = now('UTC');

            DB::table('reports')->where('id', $reportId)->update([
                'status' => $toStatus,
                'resolved_at' => in_array($toStatus, ['RESOLVED', 'DISMISSED'], true)
                    ? $now
                    : null,
                'updated_at' => $now,
            ]);

            DB::table('report_status_events')->insert([
                'report_id' => $reportId,
                'actor_user_id' => $admin->id,
                'from_status' => $report->status,
                'to_status' => $toStatus,
                'reason' => $reason,
                'request_id' => $requestId,
                'created_at' => $now,
            ]);

            DB::table('audit_logs')->insert([
                'actor_user_id' => $admin->id,
                'action' => 'REPORT_STATUS_CHANGED',
                'resource_type' => 'REPORT',
                'resource_id' => $reportId,
                'reason' => $reason,
                'request_id' => $requestId,
                'metadata_json' => json_encode([
                    'from' => ['status' => $report->status],
                    'to' => ['status' => $toStatus],
                ], JSON_THROW_ON_ERROR),
                'created_at' => $now,
            ]);
        }, 3);
    }
}
