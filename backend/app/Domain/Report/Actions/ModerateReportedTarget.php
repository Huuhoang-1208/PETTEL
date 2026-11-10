<?php

namespace App\Domain\Report\Actions;

use App\Domain\Listing\Actions\ListingWorkflow;
use App\Domain\Listing\Actions\ValidateListingForReview;
use App\Domain\Review\Actions\SetReviewVisibility;
use App\Models\Listing;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ModerateReportedTarget
{
    public function handle(
        User $admin,
        string $reportId,
        string $action,
        string $reason,
        string $requestId,
    ): void {
        $reason = trim($reason);
        $action = strtoupper($action);

        abort_unless(in_array($action, ['HIDE', 'RESTORE'], true), 422);
        abort_unless($reason !== '' && mb_strlen($reason) <= 1000, 422);

        $normalizedReason = \Normalizer::normalize($reason, \Normalizer::FORM_C);
        if ($normalizedReason !== false) {
            $reason = $normalizedReason;
        }

        if (! Str::isUuid($requestId)) {
            $requestId = (string) Str::uuid();
        }

        DB::transaction(function () use ($admin, $reportId, $action, $reason, $requestId): void {
            $freshAdmin = User::query()->whereKey($admin->id)->lockForUpdate()->first();
            $adminRole = DB::table('user_roles')
                ->where('user_id', $admin->id)
                ->where('role', 'ADMIN')
                ->lockForUpdate()
                ->first();

            abort_unless($freshAdmin !== null
                && $freshAdmin->status === 'ACTIVE'
                && $adminRole !== null, 403);

            /** @var object{id: int|string, review_id: int|string|null, listing_id: int|string|null, status: string}|null $report */
            $report = DB::table('reports')
                ->where('id', $reportId)
                ->lockForUpdate()
                ->first();

            abort_if($report === null, 404);
            abort_unless($report->status === 'IN_REVIEW', 409);

            if ($report->review_id !== null) {
                $this->moderateReview($freshAdmin, $report, $action, $reason, $requestId);

                return;
            }

            if ($report->listing_id !== null) {
                $this->moderateListing($freshAdmin, $report, $action, $reason, $requestId);

                return;
            }

            // Message và booking không được ẩn qua action này.
            // Chúng cần workflow riêng để tránh ảnh hưởng dữ liệu booking.
            abort(422, 'This report target does not support visibility moderation.');
        }, 3);
    }

    /**
     * @param  object{id: int|string, review_id: int|string|null, listing_id: int|string|null, status: string}  $report
     */
    private function moderateReview(
        User $admin,
        object $report,
        string $action,
        string $reason,
        string $requestId,
    ): void {
        /** @var object{id: int|string, listing_id: int|string}|null $review */
        $review = DB::table('reviews')
            ->where('id', $report->review_id)
            ->first();

        abort_if($review === null, 404);

        // Không cho admin kiểm duyệt review thuộc listing của chính mình.
        $listingOwner = DB::table('listings')
            ->join('caregiver_profiles', 'caregiver_profiles.id', '=', 'listings.caregiver_profile_id')
            ->where('listings.id', $review->listing_id)
            ->value('caregiver_profiles.user_id');

        abort_if((string) $listingOwner === (string) $admin->id, 403);

        app(SetReviewVisibility::class)->handle(
            $admin,
            (string) $report->id,
            $action === 'HIDE' ? 'HIDDEN' : 'VISIBLE',
            $reason,
            $requestId,
        );
    }

    /**
     * @param  object{id: int|string, review_id: int|string|null, listing_id: int|string|null, status: string}  $report
     */
    private function moderateListing(
        User $admin,
        object $report,
        string $action,
        string $reason,
        string $requestId,
    ): void {
        $listing = Listing::query()
            ->whereKey($report->listing_id)
            ->lockForUpdate()
            ->first();

        abort_if($listing === null, 404);

        abort_if(
            (string) $listing->caregiverProfile()->value('user_id') === (string) $admin->id,
            403,
            'An administrator cannot moderate their own listing.'
        );

        $workflow = app(ListingWorkflow::class);
        $before = $workflow->snapshot($listing);
        $now = now('UTC');

        if ($action === 'HIDE') {
            // Lặp lại cùng thao tác không tạo thêm version hoặc audit record.
            if ($listing->status === 'SUSPENDED' && $listing->suspension_source === 'ADMIN') {
                return;
            }

            // Admin có thể tiếp quản suspension do caregiver tạo.
            abort_unless(
                $listing->status === 'ACTIVE'
                || ($listing->status === 'SUSPENDED'
                    && $listing->suspension_source === 'CAREGIVER'),
                409,
                'Listing cannot be hidden from its current state.'
            );

            $listing->status = 'SUSPENDED';
            $listing->suspension_source = 'ADMIN';
            $listing->version++;
            $listing->save();

            $moderationAction = 'LISTING_HIDDEN';
        } else {
            if ($listing->status === 'ACTIVE') {
                return;
            }

            abort_unless(
                $listing->status === 'SUSPENDED'
                && $listing->suspension_source === 'ADMIN',
                409,
                'Only an administrator suspension can be restored here.'
            );

            app(ValidateListingForReview::class)->caregiver($listing);

            $listing->status = 'ACTIVE';
            $listing->suspension_source = null;
            $listing->version++;
            $listing->save();

            $moderationAction = 'LISTING_RESTORED';
        }

        $workflow->withdrawPending($listing, $admin, $moderationAction, $this->auditRequest($requestId), $before);
        $workflow->audit(
            $this->auditRequest($requestId),
            $admin,
            $listing,
            $moderationAction,
            $reason,
            $before,
            ['report_id' => (string) $report->id],
        );

        DB::table('moderation_actions')->insert([
            'report_id' => $report->id,
            'admin_user_id' => $admin->id,
            'action' => $moderationAction,
            'target_type' => 'LISTING',
            'target_id' => $listing->id,
            'reason' => $reason,
            'created_at' => $now,
        ]);
    }

    private function auditRequest(string $requestId): Request
    {
        $request = Request::create('/internal/report-moderation', 'POST');
        $request->attributes->set('listing_audit_request_id', $requestId);

        return $request;
    }
}
