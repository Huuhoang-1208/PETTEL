<?php

namespace App\Http\Controllers;

use App\Domain\Booking\Actions\ApplyListingAvailability;
use App\Domain\Booking\Actions\CalculateBookingPrice;
use App\Domain\Discovery\Actions\ResolveSmartMatchPet;
use App\Domain\Listing\AcceptanceRules;
use App\Http\Requests\SearchListingRequest;
use App\Http\Resources\PublicListingResource;
use App\Http\Resources\SearchListingResource;
use App\Models\Listing;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Str;

class PublicListingController extends Controller
{
    public function index(
        SearchListingRequest $request,
        ApplyListingAvailability $availability,
        ResolveSmartMatchPet $resolveSmartMatchPet,
    ): AnonymousResourceCollection {
        $filters = $request->filters();
        $pet = isset($filters['pet_id'])
            ? $resolveSmartMatchPet->handle($request->user(), (string) $filters['pet_id'], $filters)
            : null;

        if ($pet !== null) {
            $filters['species'] = $pet->species;
            $filters['size'] = $pet->size;
        }

        $range = $request->timeRange();
        $query = $this->publicListings();

        if ($pet !== null) {
            $query->whereRelation('caregiverProfile', 'user_id', '!=', $pet->user_id);
        }

        $keyword = $filters['q'] ?? null;

        if (is_string($keyword) && $keyword !== '') {
            $pattern = '%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $keyword).'%';

            $query->where(function (Builder $query) use ($pattern): void {
                $query->whereRaw("title LIKE ? ESCAPE '!'", [$pattern])
                    ->orWhereRaw("description LIKE ? ESCAPE '!'", [$pattern]);
            });
        }

        foreach (['service_type', 'area_code'] as $field) {
            if (isset($filters[$field])) {
                $query->where($field, $filters[$field]);
            }
        }

        if (isset($filters['species']) || isset($filters['size']) || $range !== null) {
            $query->whereRaw('JSON_SCHEMA_VALID(?, acceptance_rules_json)', [
                json_encode(AcceptanceRules::jsonSchema(), JSON_THROW_ON_ERROR),
            ])->whereRaw("JSON_TYPE(JSON_EXTRACT(acceptance_rules_json, '$.schema_version')) = 'INTEGER'");
        }

        foreach (['species' => 'species', 'size' => 'sizes'] as $filter => $key) {
            if (isset($filters[$filter])) {
                $query->whereJsonContains('acceptance_rules_json->'.$key, $filters[$filter]);
            }
        }

        if ($range !== null) {
            $query->whereIn('service_type', $range['service_types']);
            $query = $availability->apply($query, $range['start'], $range['end']);
            $query = $this->pricedListings($query, $range);

            if (isset($filters['min_price_vnd'])) {
                $query->where('listings.price_total_vnd', '>=', (int) $filters['min_price_vnd']);
            }

            if (isset($filters['max_price_vnd'])) {
                $query->where('listings.price_total_vnd', '<=', (int) $filters['max_price_vnd']);
            }

            $query->orderBy('listings.price_total_vnd');

            $filters['start_at'] = $range['start']->format('Y-m-d\TH:i:s.u\Z');
            $filters['end_at'] = $range['end']->format('Y-m-d\TH:i:s.u\Z');
        }

        $query->orderBy('listings.id');

        $page = (int) ($filters['page'] ?? 1);
        $perPage = (int) ($filters['per_page'] ?? 20);

        $results = $query->paginate($perPage, ['*'], 'page', $page)->appends($filters);
        $reasons = [];

        if (isset($filters['species'])) {
            $reasons[] = 'SPECIES_ACCEPTED';
        }

        if (isset($filters['size'])) {
            $reasons[] = 'SIZE_ACCEPTED';
        }

        if ($range !== null) {
            $reasons[] = 'AVAILABLE_FOR_REQUESTED_RANGE';
            $reasons[] = 'TOTAL_PRICE_CALCULATED';
        }

        if (isset($filters['min_price_vnd']) || isset($filters['max_price_vnd'])) {
            $reasons[] = 'WITHIN_REQUESTED_BUDGET';
        }

        $results->getCollection()->each(
            fn (Listing $listing) => $listing->setAttribute('search_match_reasons', $reasons),
        );

        $response = SearchListingResource::collection($results);

        if ($results->isEmpty()) {
            $response->additional([
                'match_summary' => [
                    'code' => 'NO_MATCHING_LISTINGS',
                    'message' => 'No listings match the selected criteria.',
                    'suggestions' => ['Try adjusting your dates, area, or price range.'],
                ],
            ]);
        }

        return $response;
    }

    public function show(string $id): PublicListingResource
    {
        $listing = $this->publicListings()->whereKey($id)->first();

        if ($listing === null) {
            throw new HttpResponseException(response()->json([
                'error' => [
                    'code' => 'NOT_FOUND',
                    'message' => 'Listing not found.',
                    'request_id' => (string) Str::uuid(),
                ],
            ], 404));
        }

        return PublicListingResource::make($listing);
    }

    /**
     * @param  Builder<Listing>  $query
     * @param array{
     *     start: CarbonImmutable,
     *     end: CarbonImmutable,
     *     service_types: list<string>
     * } $range
     * @return Builder<Listing>
     */
    private function pricedListings(Builder $query, array $range): Builder
    {
        $cases = [];
        $bindings = [];

        foreach ($range['service_types'] as $type) {
            $unit = $type === 'DAYCARE' ? 'HOUR' : 'NIGHT';

            $pricing = app(CalculateBookingPrice::class)->calculate(
                $type,
                $unit,
                1,
                $range['start'],
                $range['end'],
            );

            $quantity = (int) $pricing['quantity'];

            $cases[] = 'WHEN listings.service_type = ?'
                .' AND listings.price_unit = ?'
                .' AND listings.price_vnd BETWEEN 1 AND ?'
                .' THEN CAST(listings.price_vnd AS DECIMAL(20, 0)) * ?';

            array_push(
                $bindings,
                $type,
                $unit,
                intdiv(PHP_INT_MAX, $quantity),
                $quantity,
            );
        }

        $priced = $query->select('listings.*')->selectRaw(
            'CASE '.implode(' ', $cases).' ELSE NULL END AS price_total_vnd',
            $bindings,
        );

        return Listing::query()
            ->fromSub($priced, 'listings')
            ->with('media')
            ->whereNotNull('listings.price_total_vnd');
    }

    /** @return Builder<Listing> */
    private function publicListings(): Builder
    {
        return Listing::query()
            ->with('media')
            ->where('status', 'ACTIVE')
            ->whereRelation('caregiverProfile.user', 'status', 'ACTIVE')
            ->whereRelation('caregiverProfile.user.roles', 'role', 'CAREGIVER');
    }
}
