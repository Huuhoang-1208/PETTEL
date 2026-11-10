<?php

namespace App\Policies;

use App\Models\Report;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class ReportPolicy
{
    public function viewAny(User $user): bool
    {
        return $this->isActiveAdmin($user);
    }

    public function view(User $user, Report $report): bool
    {
        return $this->isActiveAdmin($user)
            && $report->exists;
    }

    public function changeStatus(User $user, Report $report): bool
    {
        return $this->isActiveAdmin($user)
            && $report->exists
            && in_array($report->status, ['OPEN', 'IN_REVIEW'], true);
    }

    public function moderate(User $user, Report $report): bool
    {
        return $this->isActiveAdmin($user)
            && $report->exists
            && $report->status === 'IN_REVIEW'
            && ($report->listing_id !== null || $report->review_id !== null);
    }

    public function viewEvidence(User $user, Report $report): bool
    {
        if (! $this->isActiveAdmin($user) || ! $report->exists) {
            return false;
        }

        // Chỉ cho xem chứng cứ riêng tư của report đang được xử lý.
        // Listing/review không thuộc nhánh chứng cứ riêng tư này.
        if ($report->status !== 'IN_REVIEW') {
            return false;
        }

        $hasPrivateTarget = $report->booking_id !== null
            || $report->message_id !== null;

        if (! $hasPrivateTarget) {
            return false;
        }

        // Phòng trường hợp dữ liệu lỗi hoặc bị tạo ngoài constraint:
        // report phải có đúng một target.
        $targetCount = collect([
            $report->listing_id,
            $report->review_id,
            $report->message_id,
            $report->booking_id,
        ])->filter(fn ($value): bool => $value !== null)->count();

        return $targetCount === 1;
    }

    private function isActiveAdmin(User $user): bool
    {
        return DB::table('users')
            ->join('user_roles', 'user_roles.user_id', '=', 'users.id')
            ->where('users.id', $user->id)
            ->where('users.status', 'ACTIVE')
            ->where('user_roles.role', 'ADMIN')
            ->exists();
    }
}
