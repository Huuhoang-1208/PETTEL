<?php

namespace App\Domain\Review\Actions;

use App\Models\User;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Normalizer;

class SetReviewVisibility
{
    public function handle(
        User $actor,
        string $reportId,
        string $status,
        string $reason,
        ?string $requestId = null,
    ): void {
        $reason = Str::trim($reason);
        $normalized = Normalizer::normalize($reason, Normalizer::FORM_C);
        $reason = $normalized === false ? $reason : $normalized;
        $requestId = is_string($requestId) && Str::isUuid($requestId)
            ? $requestId : (string) Str::uuid();

        DB::transaction(function () use ($actor, $reportId, $status, $reason, $requestId): void {
            $admin = User::query()->whereKey($actor->id)->lockForUpdate()->first();
            $role = DB::table('user_roles')->where('user_id', $actor->id)
                ->where('role', 'ADMIN')->lockForUpdate()->first();

            if ($admin === null || $admin->status !== 'ACTIVE' || $role === null) {
                $this->fail(403, 'FORBIDDEN', 'An active administrator account is required.');
            }

            $validator = validator(['status' => $status, 'reason' => $reason], [
                'status' => ['required', 'in:VISIBLE,HIDDEN'],
                'reason' => ['required', 'string', 'max:1000'],
            ]);

            if ($validator->fails()) {
                $this->fail(
                    422,
                    'VALIDATION_ERROR',
                    'The given data was invalid.',
                    $validator->errors()->toArray()
                );
            }

            $report = DB::table('reports')->where('id', $reportId)->lockForUpdate()->first();

            if ($report === null || $report->review_id === null) {
                $this->fail(404, 'NOT_FOUND', 'Review report not found.');
            }

            $review = DB::table('reviews')->where('id', $report->review_id)
                ->lockForUpdate()->first();

            if ($review === null) {
                $this->fail(404, 'NOT_FOUND', 'Review not found.');
            }

            if ($review->status === $status) {
                return;
            }

            $action = $status === 'HIDDEN' ? 'REVIEW_HIDDEN' : 'REVIEW_RESTORED';
            $now = now('UTC');

            DB::table('reviews')->where('id', $review->id)->update([
                'status' => $status,
                'updated_at' => $now,
            ]);

            DB::table('moderation_actions')->insert([
                'report_id' => $report->id,
                'admin_user_id' => $admin->id,
                'action' => $action,
                'target_type' => 'REVIEW',
                'target_id' => $review->id,
                'reason' => $reason,
                'created_at' => $now,
            ]);

            DB::table('audit_logs')->insert([
                'actor_user_id' => $admin->id,
                'action' => $action,
                'resource_type' => 'REVIEW',
                'resource_id' => $review->id,
                'reason' => $reason,
                'request_id' => $requestId,
                'metadata_json' => json_encode([
                    'report_id' => (string) $report->id,
                    'from' => ['status' => $review->status],
                    'to' => ['status' => $status],
                ], JSON_THROW_ON_ERROR),
                'created_at' => $now,
            ]);
        }, 3);
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
