<?php

namespace App\Http\Controllers;

use App\Domain\Listing\Actions\ListingError;
use App\Domain\Listing\Actions\ListingInput;
use App\Domain\Listing\Actions\ListingWorkflow;
use App\Domain\Listing\Actions\ValidateListingForReview;
use App\Models\Listing;
use App\Models\ListingChangeRequest;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ListingChangeRequestController extends Controller
{
    private const FIELDS = ['title', 'description', 'service_type', 'area_code', 'price_vnd', 'price_unit', 'acceptance_rules_json', 'media'];

    public function index(Request $request, string $id): mixed
    {
        $listing = $this->listing($request, $id, false);
        $filters = app(ListingInput::class)->query($request, ['page', 'per_page'], [
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:50'],
        ]);
        $page = DB::table('listing_change_requests')->where('listing_id', $listing->id)
            ->orderByDesc('id')->paginate($filters['per_page'] ?? 20, ['*'], 'page', $filters['page'] ?? 1);
        $page->appends($filters);
        $page->getCollection()->transform(fn (object $record): array => $this->format($record));

        return response()->json($this->pagination($page));
    }

    public function queue(Request $request): mixed
    {
        $filters = app(ListingInput::class)->query($request, ['status', 'page', 'per_page'], [
            'status' => ['sometimes', 'string', 'in:ALL,PENDING_REVIEW,APPROVED,REJECTED,WITHDRAWN'],
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:50'],
        ]);
        $query = DB::table('listing_change_requests');
        if (($filters['status'] ?? 'PENDING_REVIEW') !== 'ALL') {
            $query->where('status', $filters['status'] ?? 'PENDING_REVIEW');
        }
        $page = $query->orderBy('id')->paginate($filters['per_page'] ?? 20, ['*'], 'page', $filters['page'] ?? 1);
        $page->appends($filters);
        $page->getCollection()->transform(fn (object $record): array => $this->format($record));

        return response()->json($this->pagination($page));
    }

    public function show(Request $request, string $id, string $requestId): mixed
    {
        $listing = $this->listing($request, $id, false);
        $change = $this->change($listing, $requestId);
        app(ListingInput::class)->query($request, [], []);

        return response()->json(['data' => $this->format($change)]);
    }

    public function adminShow(Request $request, string $id, string $requestId): mixed
    {
        $listing = $this->listing($request, $id, true);
        $this->notOwnAdminReview($listing, $request->user());
        $change = $this->change($listing, $requestId);
        app(ListingInput::class)->query($request, [], []);

        return response()->json(['data' => $this->format($change)]);
    }

    public function store(Request $request, string $id, ListingWorkflow $workflow): mixed
    {
        $listing = $this->listing($request, $id, false);
        $data = $this->validateProposal($request);
        $record = DB::transaction(function () use ($listing, $request, $data, $workflow) {
            $locked = Listing::query()->whereKey($listing->id)->lockForUpdate()->firstOrFail();
            $before = $workflow->snapshot($locked);
            if (! in_array($locked->status, ['ACTIVE', 'SUSPENDED'], true)) {
                ListingError::fail(409, 'INVALID_STATE', 'Listing cannot be changed in this state.');
            }
            if ((int) $data['base_listing_version'] !== (int) $locked->version) {
                ListingError::fail(409, 'VERSION_CONFLICT', 'Listing has changed. Refresh and retry.');
            }
            if (DB::table('listing_change_requests')->where('listing_id', $locked->id)->where('status', 'PENDING_REVIEW')->exists()) {
                ListingError::fail(409, 'INVALID_STATE', 'A change request is already pending.');
            }
            $payload = $this->currentPayload($locked);
            $proposal = array_replace($payload, $data['changes']);
            app(ValidateListingForReview::class)->payload($proposal, $request->user());
            if ($proposal === $payload) {
                ListingError::fail(422, 'VALIDATION_ERROR', 'At least one field must change.', ['changes' => ['No changes were supplied.']]);
            }
            $record = ListingChangeRequest::query()->create([
                'listing_id' => $locked->id,
                'base_listing_version' => $locked->version,
                'proposed_data_json' => ['schema_version' => 1, 'data' => $proposal],
                'status' => 'PENDING_REVIEW',
                'requested_by_user_id' => $request->user()->id,
                'requested_at' => now(),
            ]);
            $workflow->audit($request, $request->user(), $locked, 'LISTING_CHANGE_REQUESTED', null, $before, [
                'change_request_id' => $record->id,
                'change_request_from' => null,
                'change_request_to' => 'PENDING_REVIEW',
            ]);

            return $record;
        }, 3);

        return response()->json(['data' => $this->format($record)], 201);
    }

    public function withdraw(Request $request, string $id, string $requestId, ListingWorkflow $workflow): mixed
    {
        $listing = $this->listing($request, $id, false);
        $this->change($listing, $requestId);
        app(ListingInput::class)->body($request, [], []);
        $record = DB::transaction(function () use ($listing, $request, $requestId, $workflow) {
            $locked = Listing::query()->whereKey($listing->id)->lockForUpdate()->firstOrFail();
            $before = $workflow->snapshot($locked);
            $change = $this->change($locked, $requestId, true);
            if ($change->status !== 'PENDING_REVIEW') {
                ListingError::fail(409, 'INVALID_STATE', 'Only pending requests can be withdrawn.');
            }
            $change->update([
                'status' => 'WITHDRAWN', 'decision_reason' => null,
                'reviewed_by_user_id' => null, 'reviewed_at' => null,
            ]);
            $workflow->audit($request, $request->user(), $locked, 'LISTING_CHANGE_WITHDRAWN', null, $before, [
                'change_request_id' => $change->id,
                'change_request_from' => 'PENDING_REVIEW',
                'change_request_to' => 'WITHDRAWN',
            ], 'LISTING_CHANGE_REQUEST', $change->id);

            return $change->refresh();
        }, 3);

        return response()->json(['data' => $this->format($record)]);
    }

    public function approve(Request $request, string $id, string $requestId, ListingWorkflow $workflow): mixed
    {
        $listing = $this->listing($request, $id, true);
        $this->notOwnAdminReview($listing, $request->user());
        $this->change($listing, $requestId);
        $reason = $this->reason($request, false);
        $record = DB::transaction(function () use ($listing, $request, $requestId, $workflow, $reason): ListingChangeRequest {
            $locked = Listing::query()->whereKey($listing->id)->lockForUpdate()->firstOrFail();
            $before = $workflow->snapshot($locked);
            $this->notOwnAdminReview($locked, $request->user());
            $change = $this->change($locked, $requestId, true);
            if ($change->status !== 'PENDING_REVIEW' || ! in_array($locked->status, ['ACTIVE', 'SUSPENDED'], true)) {
                ListingError::fail(409, 'INVALID_STATE', 'Change request is no longer reviewable.');
            }
            if ((int) $change->base_listing_version !== (int) $locked->version) {
                ListingError::fail(409, 'VERSION_CONFLICT', 'Listing has changed.');
            }
            $wrapper = $change->proposed_data_json;
            $payload = $wrapper['data'];
            $caregiver = app(ValidateListingForReview::class)->caregiver($locked);
            app(ValidateListingForReview::class)->payload($payload, $caregiver, 409);
            $oldStatus = $locked->status;
            $media = $payload['media'];
            unset($payload['media']);
            $locked->fill($payload);
            $locked->version++;
            $locked->save();
            $workflow->setMedia($locked, $media);
            DB::table('media_objects')->whereIn('id', array_column($media, 'media_object_id'))
                ->update(['visibility' => 'PUBLIC_ELIGIBLE']);
            $change->update([
                'status' => 'APPROVED', 'reviewed_by_user_id' => $request->user()->id,
                'reviewed_at' => now(), 'decision_reason' => $reason,
            ]);
            $workflow->audit($request, $request->user(), $locked, 'LISTING_CHANGE_APPROVED', $reason, $before, [
                'change_request_id' => $change->id,
                'change_request_from' => 'PENDING_REVIEW',
                'change_request_to' => 'APPROVED',
                'previous_status' => $oldStatus,
            ]);

            return $change->refresh();
        }, 3);

        return response()->json(['data' => $this->format($record, $listing->refresh())]);
    }

    public function reject(Request $request, string $id, string $requestId, ListingWorkflow $workflow): mixed
    {
        $listing = $this->listing($request, $id, true);
        $this->notOwnAdminReview($listing, $request->user());
        $this->change($listing, $requestId);
        $reason = $this->reason($request);
        $record = DB::transaction(function () use ($listing, $request, $requestId, $workflow, $reason) {
            $locked = Listing::query()->whereKey($listing->id)->lockForUpdate()->firstOrFail();
            $before = $workflow->snapshot($locked);
            $this->notOwnAdminReview($locked, $request->user());
            $change = $this->change($locked, $requestId, true);
            if ($change->status !== 'PENDING_REVIEW') {
                ListingError::fail(409, 'INVALID_STATE', 'Change request is no longer reviewable.');
            }
            $change->update([
                'status' => 'REJECTED', 'reviewed_by_user_id' => $request->user()->id,
                'reviewed_at' => now(), 'decision_reason' => $reason,
            ]);
            $workflow->audit($request, $request->user(), $locked, 'LISTING_CHANGE_REJECTED', $reason, $before, [
                'change_request_id' => $change->id,
                'change_request_from' => 'PENDING_REVIEW',
                'change_request_to' => 'REJECTED',
            ]);

            return $change->refresh();
        }, 3);

        return response()->json(['data' => $this->format($record)]);
    }

    private function listing(Request $request, string $id, bool $admin): Listing
    {
        $query = Listing::query()->whereKey($id);
        if (! $admin) {
            $query->whereRelation('caregiverProfile', 'user_id', $request->user()->id);
        }
        $listing = $query->first();
        if ($listing === null) {
            ListingError::fail(404, 'NOT_FOUND', 'Listing not found.');
        }

        return $listing;
    }

    private function change(Listing $listing, string $id, bool $lock = false): ListingChangeRequest
    {
        $query = ListingChangeRequest::query()->where('listing_id', $listing->id)->whereKey($id);
        if ($lock) {
            $query->lockForUpdate();
        }
        $change = $query->first();
        if ($change === null) {
            ListingError::fail(404, 'NOT_FOUND', 'Change request not found.');
        }

        return $change;
    }

    /** @return array<string, mixed> */
    private function currentPayload(Listing $listing): array
    {
        return [
            ...$listing->only(['title', 'description', 'service_type', 'area_code', 'price_vnd', 'price_unit']),
            'acceptance_rules_json' => $listing->acceptance_rules_json,
            'media' => DB::table('listing_media')->where('listing_id', $listing->id)->orderBy('sort_order')->get()
                ->map(fn ($media) => ['media_object_id' => (string) $media->media_object_id, 'sort_order' => (int) $media->sort_order])->all(),
        ];
    }

    /** @return array<string, mixed> */
    private function validateProposal(Request $request): array
    {
        return app(ListingInput::class)->body($request, ['base_listing_version', 'changes'], [
            'base_listing_version' => ['required', 'integer', 'min:1'],
            'changes' => ['required', 'array:'.implode(',', self::FIELDS), 'min:1'],
            'changes.title' => ['sometimes', 'string', 'max:120'],
            'changes.description' => ['sometimes', 'string', 'max:2000'],
            'changes.service_type' => ['sometimes', 'in:DAYCARE,BOARDING'],
            'changes.area_code' => ['sometimes', 'in:DN_CENTER,DN_NORTH,DN_SOUTH,DN_WEST,DN_EAST'],
            'changes.price_vnd' => ['sometimes', 'integer:strict', 'min:1'],
            'changes.price_unit' => ['sometimes', 'in:HOUR,NIGHT'],
            'changes.acceptance_rules_json' => ['sometimes', 'array:schema_version,species,sizes'],
            'changes.acceptance_rules_json.schema_version' => ['required_with:changes.acceptance_rules_json', 'integer:strict', 'in:1'],
            'changes.acceptance_rules_json.species' => ['required_with:changes.acceptance_rules_json', 'array', 'list', 'min:1'],
            'changes.acceptance_rules_json.species.*' => ['string', 'in:DOG,CAT,OTHER'],
            'changes.acceptance_rules_json.sizes' => ['required_with:changes.acceptance_rules_json', 'array', 'list', 'min:1'],
            'changes.acceptance_rules_json.sizes.*' => ['string', 'in:SMALL,MEDIUM,LARGE'],
            'changes.media' => ['sometimes', 'array', 'max:10', 'list'],
            'changes.media.*' => ['array:media_object_id,sort_order'],
            'changes.media.*.media_object_id' => ['required', 'string', 'regex:/^[1-9][0-9]*$/'],
            'changes.media.*.sort_order' => ['required', 'integer', 'min:1', 'max:10'],
        ]);
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
            $reason = trim(Str::of($data['reason'])->squish()->toString());
            if ($reason === '') {
                ListingError::fail(422, 'VALIDATION_ERROR', 'Reason is required.', ['reason' => ['A reason is required.']]);
            }

            return $reason;
        }

        return null;
    }

    /** @return array<string, mixed> */
    private function format(object $record, ?Listing $listing = null): array
    {
        $proposal = data_get($record, 'proposed_data_json');
        if (is_string($proposal)) {
            $proposal = json_decode($proposal, true, flags: JSON_THROW_ON_ERROR);
        }
        $data = [
            'id' => (string) data_get($record, 'id'),
            'listing_id' => (string) data_get($record, 'listing_id'),
            'base_listing_version' => (int) data_get($record, 'base_listing_version'),
            'proposed_data_json' => $proposal,
            'status' => data_get($record, 'status'),
            'requested_by_user_id' => (string) data_get($record, 'requested_by_user_id'),
            'requested_at' => $this->timestamp(data_get($record, 'requested_at')),
            'reviewed_by_user_id' => data_get($record, 'reviewed_by_user_id') === null
                ? null : (string) data_get($record, 'reviewed_by_user_id'),
            'reviewed_at' => $this->timestamp(data_get($record, 'reviewed_at')),
            'decision_reason' => data_get($record, 'decision_reason'),
        ];
        if ($listing !== null) {
            $data['listing_version'] = $listing->version;
        }

        return $data;
    }

    private function timestamp(mixed $value): ?string
    {
        return $value === null ? null : CarbonImmutable::parse($value, 'UTC')->utc()->format('Y-m-d\TH:i:s.u\Z');
    }

    /** @return array<string, mixed> */
    /** @param LengthAwarePaginator<int, mixed> $page
     * @return array<string, mixed>
     */
    private function pagination(LengthAwarePaginator $page): array
    {
        return ['data' => $page->items(), 'links' => [
            'first' => $page->url(1), 'last' => $page->url($page->lastPage()),
            'prev' => $page->previousPageUrl(), 'next' => $page->nextPageUrl(),
        ], 'meta' => [
            'current_page' => $page->currentPage(), 'last_page' => $page->lastPage(),
            'per_page' => $page->perPage(), 'total' => $page->total(),
        ]];
    }
}
