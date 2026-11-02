<?php

namespace App\Http\Controllers;

use App\Http\Requests\ListingRequest;
use App\Http\Resources\ListingResource;
use App\Models\Listing;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CaregiverListingController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        return ListingResource::collection(
            $this->ownedListings($request)->where('status', 'DRAFT')->latest('id')->paginate(20),
        );
    }

    public function store(ListingRequest $request): JsonResponse
    {
        $profile = $request->user()->caregiverProfile()->first();

        if ($profile === null) {
            $this->reject(409, 'INVALID_STATE', 'Create a caregiver profile first.');
        }

        $listing = new Listing($request->validated());
        $this->ensureServicePair($listing);
        $profile->listings()->save($listing);

        return ListingResource::make($listing->refresh())->response()->setStatusCode(201);
    }

    public function show(Request $request, string $id): ListingResource
    {
        return ListingResource::make($this->ownedListing($request, $id));
    }

    public function update(ListingRequest $request, string $id): ListingResource
    {
        $listing = DB::transaction(function () use ($request, $id): Listing {
            $listing = $this->ownedListing($request, $id, true);
            $this->ensureDraft($listing);
            $listing->fill($request->validated());
            $this->ensureServicePair($listing);
            $listing->save();

            return $listing;
        });

        return ListingResource::make($listing->refresh());
    }

    public function destroy(Request $request, string $id): Response
    {
        DB::transaction(function () use ($request, $id): void {
            $listing = $this->ownedListing($request, $id, true);
            $this->ensureDraft($listing);
            $listing->delete();
        });

        return response()->noContent();
    }

    /** @return Builder<Listing> */
    private function ownedListings(Request $request): Builder
    {
        return Listing::query()->whereRelation('caregiverProfile', 'user_id', $request->user()->id);
    }

    private function ownedListing(Request $request, string $id, bool $lock = false): Listing
    {
        $query = $this->ownedListings($request)->whereKey($id);

        if ($lock) {
            $query->lockForUpdate();
        }

        $listing = $query->first();

        if ($listing === null) {
            $this->reject(404, 'NOT_FOUND', 'Listing not found.');
        }

        return $listing;
    }

    private function ensureDraft(Listing $listing): void
    {
        if ($listing->status !== 'DRAFT') {
            $this->reject(409, 'INVALID_STATE', 'Only DRAFT listings can be changed here.');
        }
    }

    private function ensureServicePair(Listing $listing): void
    {
        if (
            ! in_array([$listing->service_type, $listing->price_unit], [
                ['DAYCARE', 'HOUR'],
                ['BOARDING', 'NIGHT'],
            ], true)
        ) {
            $this->reject(422, 'VALIDATION_ERROR', 'Invalid service and price unit.', [
                'price_unit' => ['Use DAYCARE/HOUR or BOARDING/NIGHT.'],
            ]);
        }
    }

    /** @param array<string, list<string>> $details */
    private function reject(int $status, string $code, string $message, array $details = []): never
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
