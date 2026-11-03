<?php

namespace App\Http\Controllers;

use App\Domain\Listing\Actions\ListingError;
use App\Domain\Listing\Actions\ListingInput;
use App\Domain\Listing\Actions\ListingWorkflow;
use App\Domain\Listing\Actions\ValidateListingForReview;
use App\Http\Resources\ListingResource;
use App\Models\Listing;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;

class ListingLifecycleController extends Controller
{
    public function caregiverIndex(Request $request): AnonymousResourceCollection
    {
        $filters = app(ListingInput::class)->query($request, ['status', 'page', 'per_page'], [
            'status' => ['sometimes', 'string', 'in:ALL,DRAFT,PENDING_REVIEW,ACTIVE,SUSPENDED,ARCHIVED'],
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:50'],
        ]);
        $query = Listing::query()->whereRelation('caregiverProfile', 'user_id', $request->user()->id);
        if (($filters['status'] ?? 'DRAFT') !== 'ALL') {
            $query->where('status', $filters['status'] ?? 'DRAFT');
        }

        return ListingResource::collection($this->managementQuery($query)->latest('id')->paginate($filters['per_page'] ?? 20));
    }

    public function adminIndex(Request $request): AnonymousResourceCollection
    {
        $filters = app(ListingInput::class)->query($request, ['status', 'page', 'per_page'], [
            'status' => ['sometimes', 'string', 'in:ALL,DRAFT,PENDING_REVIEW,ACTIVE,SUSPENDED,ARCHIVED'],
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:50'],
        ]);
        $query = Listing::query();
        if (($filters['status'] ?? 'PENDING_REVIEW') !== 'ALL') {
            $query->where('status', $filters['status'] ?? 'PENDING_REVIEW');
        }

        return ListingResource::collection($this->managementQuery($query)->orderBy('id')->paginate($filters['per_page'] ?? 20));
    }

    public function show(Request $request, string $id): ListingResource
    {
        $listing = $this->listing($request, $id, $request->is('api/admin/*'));
        app(ListingInput::class)->query($request, [], []);

        return ListingResource::make($this->managementQuery(Listing::query())->whereKey($listing->id)->firstOrFail());
    }

    public function submit(Request $request, string $id, ListingWorkflow $workflow): ListingResource
    {
        $this->listing($request, $id, false);
        app(ListingInput::class)->body($request, [], []);

        return $this->transition($request, $id, false, function (Listing $listing, User $actor, array $before) use ($request, $workflow): void {
            $this->state($listing, ['DRAFT']);
            $listing->status = 'PENDING_REVIEW';
            $listing->version++;
            $listing->save();
            $workflow->audit($request, $actor, $listing, 'LISTING_SUBMITTED', null, $before);
        });
    }

    public function approve(Request $request, string $id, ListingWorkflow $workflow, ValidateListingForReview $review): ListingResource
    {
        $listing = $this->listing($request, $id, true);
        $this->notOwnAdminReview($listing, $request->user());
        $reason = $this->reason($request, false);

        return $this->transition($request, $id, true, function (Listing $listing, User $actor, array $before) use ($request, $workflow, $review, $reason): void {
            $this->notOwnAdminReview($listing, $actor);
            $this->state($listing, ['PENDING_REVIEW']);
            $caregiver = $review->caregiver($listing);
            $review->listing($listing, $caregiver);
            $listing->status = 'ACTIVE';
            $listing->published_at ??= now();
            $listing->version++;
            $listing->save();
            DB::table('media_objects')->whereIn('id', $listing->media()->pluck('media_objects.id'))
                ->update(['visibility' => 'PUBLIC_ELIGIBLE']);
            $workflow->audit($request, $actor, $listing, 'LISTING_APPROVED', $reason, $before);
        });
    }

    public function reject(Request $request, string $id, ListingWorkflow $workflow): ListingResource
    {
        $listing = $this->listing($request, $id, true);
        $this->notOwnAdminReview($listing, $request->user());
        $reason = $this->reason($request);

        return $this->transition($request, $id, true, function (Listing $listing, User $actor, array $before) use ($request, $workflow, $reason): void {
            $this->notOwnAdminReview($listing, $actor);
            $this->state($listing, ['PENDING_REVIEW']);
            $listing->status = 'DRAFT';
            $listing->version++;
            $listing->save();
            $workflow->audit($request, $actor, $listing, 'LISTING_REJECTED', $reason, $before);
        });
    }

    public function suspend(Request $request, string $id, ListingWorkflow $workflow): ListingResource
    {
        $adminRoute = $request->is('api/admin/*');
        $this->listing($request, $id, $adminRoute);
        $reason = $this->reason($request);

        return $this->transition($request, $id, $adminRoute, function (Listing $listing, User $actor, array $before) use ($request, $workflow, $adminRoute, $reason): void {
            $admin = $adminRoute;
            if (! ($admin && $listing->status === 'SUSPENDED' && $listing->suspension_source === 'CAREGIVER')) {
                $this->state($listing, ['ACTIVE']);
            }
            if ($admin && $listing->status === 'SUSPENDED' && $listing->suspension_source === 'CAREGIVER') {
                $listing->suspension_source = 'ADMIN';
                $listing->version++;
                $listing->save();
            } else {
                $listing->status = 'SUSPENDED';
                $listing->suspension_source = $admin ? 'ADMIN' : 'CAREGIVER';
                $listing->version++;
                $listing->save();
            }
            $workflow->withdrawPending($listing, $actor, 'LISTING_SUSPENDED', $request, $before);
            $workflow->audit($request, $actor, $listing, 'LISTING_SUSPENDED', $reason, $before);
        });
    }

    public function restore(Request $request, string $id, ListingWorkflow $workflow): ListingResource
    {
        $adminRoute = $request->is('api/admin/*');
        $listing = $this->listing($request, $id, $adminRoute);
        if (! $adminRoute && $listing->status === 'SUSPENDED' && $listing->suspension_source !== 'CAREGIVER') {
            ListingError::fail(403, 'FORBIDDEN', 'This listing requires administrator review.');
        }
        $reason = $this->reason($request, $adminRoute);

        return $this->transition($request, $id, $adminRoute, function (Listing $listing, User $actor, array $before) use ($request, $workflow, $adminRoute, $reason): void {
            $this->state($listing, ['SUSPENDED']);
            if (! $adminRoute && $listing->suspension_source !== 'CAREGIVER') {
                ListingError::fail(403, 'FORBIDDEN', 'This listing requires administrator review.');
            }
            $this->ensureCaregiverActive($listing);
            $from = $listing->suspension_source;
            $listing->status = 'ACTIVE';
            $listing->suspension_source = null;
            $listing->version++;
            $listing->save();
            $workflow->withdrawPending($listing, $actor, 'LISTING_RESTORED', $request, $before);
            $workflow->audit($request, $actor, $listing, 'LISTING_RESTORED', $reason, $before, ['from_source' => $from]);
        });
    }

    public function archive(Request $request, string $id, ListingWorkflow $workflow): ListingResource
    {
        $adminRoute = $request->is('api/admin/*');
        $this->listing($request, $id, $adminRoute);
        $reason = $this->reason($request);

        return $this->transition($request, $id, $adminRoute, function (Listing $listing, User $actor, array $before) use ($request, $workflow, $reason): void {
            $this->state($listing, ['ACTIVE', 'SUSPENDED']);
            $held = DB::table('bookings')->where('listing_id', $listing->id)
                ->whereIn('status', ['AWAITING_PAYMENT', 'CONFIRMED', 'IN_PROGRESS', 'AWAITING_CONFIRMATION'])
                ->exists();
            if ($held) {
                ListingError::fail(409, 'INVALID_STATE', 'Listing has active bookings.');
            }
            $listing->status = 'ARCHIVED';
            $listing->suspension_source = null;
            $listing->version++;
            $listing->save();
            $workflow->withdrawPending($listing, $actor, 'LISTING_ARCHIVED', $request, $before);
            $workflow->audit($request, $actor, $listing, 'LISTING_ARCHIVED', $reason, $before);
        });
    }

    private function transition(Request $request, string $id, bool $admin, callable $change): ListingResource
    {
        $listing = DB::transaction(function () use ($request, $id, $admin, $change): Listing {
            $record = $this->listing($request, $id, $admin, true);
            $before = app(ListingWorkflow::class)->snapshot($record);
            $change($record, $request->user(), $before);

            return $record->refresh();
        }, 3);

        return ListingResource::make($this->managementQuery(Listing::query())->whereKey($listing->id)->firstOrFail());
    }

    private function listing(Request $request, string $id, bool $admin, bool $lock = false): Listing
    {
        $query = Listing::query()->whereKey($id);
        if (! $admin) {
            $query->whereRelation('caregiverProfile', 'user_id', $request->user()->id);
        }
        if ($lock) {
            $query->lockForUpdate();
        }
        $listing = $query->first();
        if ($listing === null) {
            ListingError::fail(404, 'NOT_FOUND', 'Listing not found.');
        }

        return $listing;
    }

    /** @param list<string> $allowed */
    private function state(Listing $listing, array $allowed): void
    {
        if (! in_array($listing->status, $allowed, true)) {
            ListingError::fail(409, 'INVALID_STATE', 'Listing is in an invalid state.');
        }
    }

    private function ensureCaregiverActive(Listing $listing): void
    {
        app(ValidateListingForReview::class)->caregiver($listing);
    }

    private function notOwnAdminReview(Listing $listing, User $actor): void
    {
        if ((string) $listing->caregiverProfile()->value('user_id') === (string) $actor->id) {
            ListingError::fail(403, 'FORBIDDEN', 'An administrator cannot review their own listing.');
        }
    }

    private function reason(Request $request, bool $required = true): ?string
    {
        $data = app(ListingInput::class)->body($request, ['reason'], [
            'reason' => [$required ? 'required' : 'sometimes', 'string', 'max:1000'],
        ]);
        if (isset($data['reason'])) {
            $data['reason'] = trim($data['reason']);
            $normalized = \Normalizer::normalize($data['reason'], \Normalizer::FORM_C);
            if ($normalized !== false) {
                $data['reason'] = $normalized;
            }
            if ($data['reason'] === '') {
                ListingError::fail(422, 'VALIDATION_ERROR', 'Reason is required.', ['reason' => ['A reason is required.']]);
            }
        }

        return $data['reason'] ?? null;
    }

    /** @param Builder<Listing> $query
     * @return Builder<Listing>
     */
    private function managementQuery(Builder $query): Builder
    {
        $decisionActions = ['LISTING_APPROVED', 'LISTING_REJECTED', 'LISTING_CHANGE_APPROVED', 'LISTING_CHANGE_REJECTED'];
        $latestDecision = DB::table('audit_logs')->where('resource_type', 'LISTING')
            ->whereColumn('resource_id', 'listings.id')->whereIn('action', $decisionActions);

        return $query->with('media')->addSelect([
            'latest_decision_action' => (clone $latestDecision)->select('action')->orderByDesc('id')->limit(1),
            'latest_decision_reason' => (clone $latestDecision)->select('reason')->orderByDesc('id')->limit(1),
            'latest_decision_at' => (clone $latestDecision)->select('created_at')->orderByDesc('id')->limit(1),
            'pending_change_request_id' => DB::table('listing_change_requests')->select('id')
                ->whereColumn('listing_id', 'listings.id')->where('status', 'PENDING_REVIEW')
                ->orderByDesc('id')->limit(1),
        ]);
    }
}
