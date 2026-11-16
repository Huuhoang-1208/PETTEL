<?php

namespace App\Http\Controllers;

use App\Domain\Booking\Actions\ApplyListingAvailability;
use App\Domain\Booking\Actions\CalculateBookingPrice;
use App\Domain\Discovery\Actions\ResolveSmartMatchPet;
use App\Domain\Listing\AcceptanceRules;
use App\Http\Requests\SearchListingRequest;
use App\Http\Resources\PublicListingResource;
use App\Http\Resources\SearchListingResource;
use App\Models\AvailabilitySlot;
use App\Models\Booking;
use App\Models\Listing;
use App\Support\PublicReviewCache;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Validator;
use stdClass;

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
        return PublicListingResource::make($this->findPublicListing($id));
    }

    public function services(string $id): AnonymousResourceCollection
    {
        $listing = $this->findPublicListing($id);
        $services = $this->publicListings()
            ->where('listings.caregiver_profile_id', $listing->caregiver_profile_id)
            ->orderBy('listings.service_type')
            ->orderBy('listings.id')
            ->get();

        return PublicListingResource::collection($services);
    }

    public function availability(Request $request, string $id): mixed
    {
        $filters = validator($request->query(), [
            'month' => ['sometimes', 'required', 'date_format:Y-m'],
        ])->validate();
        $listing = $this->findPublicListing($id);
        $timezone = 'Asia/Ho_Chi_Minh';
        $month = CarbonImmutable::parse(($filters['month'] ?? now($timezone)->format('Y-m')).'-01', $timezone)
            ->startOfMonth();
        $today = CarbonImmutable::now($timezone)->startOfDay();
        $now = CarbonImmutable::now($timezone);
        $monthEnd = $month->addMonth()->startOfMonth();
        $monthStartUtc = $month->utc();
        $monthEndUtc = $monthEnd->utc();
        $openSlots = AvailabilitySlot::query()
            ->where('listing_id', $listing->id)
            ->where('start_at', '<', $monthEndUtc)
            ->where('end_at', '>', $monthStartUtc)
            ->orderBy('start_at')
            ->get(['start_at', 'end_at']);
        $heldBookings = Booking::query()
            ->where('listing_id', $listing->id)
            ->whereIn('status', ['AWAITING_PAYMENT', 'CONFIRMED', 'IN_PROGRESS', 'AWAITING_CONFIRMATION'])
            ->where('start_at', '<', $monthEndUtc)
            ->where('end_at', '>', $monthStartUtc)
            ->orderBy('start_at')
            ->get(['start_at', 'end_at']);
        $minimumDuration = $listing->service_type === 'DAYCARE' ? 3600 : 43200;
        $days = [];

        for ($day = 1; $day <= $month->daysInMonth; $day++) {
            $date = $month->day($day);
            $dateKey = $date->format('Y-m-d');
            if ($date->lessThan($today)) {
                $days[] = ['date' => $dateKey, 'status' => 'FULL', 'time_slots' => []];
                continue;
            }

            $timeSlots = $this->availableWindowsForDay($date, $now, $openSlots, $heldBookings, $minimumDuration);
            $days[] = ['date' => $dateKey, 'status' => $timeSlots === [] ? 'FULL' : 'AVAILABLE', 'time_slots' => $timeSlots];
        }

        return response()->json(['data' => $days, 'month' => $month->format('Y-m')]);
    }

    /**
     * @param  iterable<AvailabilitySlot>  $openSlots
     * @param  iterable<Booking>  $heldBookings
     * @return list<array{start_at: string, end_at: string}>
     */
    private function availableWindowsForDay(
        CarbonImmutable $date,
        CarbonImmutable $now,
        iterable $openSlots,
        iterable $heldBookings,
        int $minimumDuration,
    ): array {
        $timezone = 'Asia/Ho_Chi_Minh';
        $dayStart = $date->startOfDay()->utc()->getTimestamp();
        $dayEnd = $date->addDay()->startOfDay()->utc()->getTimestamp();
        $earliestStart = $date->isSameDay($now->setTimezone($timezone))
            ? max($dayStart, $now->addHour()->utc()->getTimestamp())
            : $dayStart;
        $windows = [];

        foreach ($openSlots as $slot) {
            $cursor = max($slot->start_at->utc()->getTimestamp(), $earliestStart);
            $slotEnd = $slot->end_at->utc()->getTimestamp();
            if ($cursor >= $slotEnd || $cursor >= $dayEnd || $slotEnd <= $dayStart) {
                continue;
            }

            foreach ($heldBookings as $booking) {
                $blockedStart = $booking->start_at->utc()->getTimestamp();
                $blockedEnd = $booking->end_at->utc()->getTimestamp();
                if ($blockedEnd <= $cursor || $blockedStart >= $slotEnd) {
                    continue;
                }
                if ($blockedStart > $cursor && $blockedStart - $cursor >= $minimumDuration) {
                    $windows[] = $this->availabilityWindow($cursor, $blockedStart);
                }
                $cursor = max($cursor, $blockedEnd);
                if ($cursor >= $slotEnd) {
                    break;
                }
            }

            if ($slotEnd - $cursor >= $minimumDuration) {
                $windows[] = $this->availabilityWindow($cursor, $slotEnd);
            }
        }

        return $windows;
    }

    /** @return array{start_at: string, end_at: string} */
    private function availabilityWindow(int $start, int $end): array
    {
        return [
            'start_at' => CarbonImmutable::createFromTimestampUTC($start)->format('Y-m-d\\TH:i:s\\Z'),
            'end_at' => CarbonImmutable::createFromTimestampUTC($end)->format('Y-m-d\\TH:i:s\\Z'),
        ];
    }

    /**
     * @response array{data: list<array{id: string, rating: int, body: ?string, is_demo: bool, created_at: string}>, links: array{first: string, last: string, prev: ?string, next: ?string}, meta: array{current_page: int, last_page: int, per_page: int, total: int}, rating_average: ?float, review_count: int}
     */
    public function reviews(Request $request, string $id): AnonymousResourceCollection
    {
        $listing = $this->findPublicListing($id);
        $query = $request->query();

        $validator = validator($query, [
            'page' => ['bail', 'sometimes', 'required', 'integer', 'min:1'],
            'per_page' => ['bail', 'sometimes', 'required', 'integer', 'between:1,50'],
        ]);

        $validator->after(function (Validator $validator) use ($query): void {
            foreach (array_diff(array_keys($query), ['page', 'per_page']) as $field) {
                $validator->errors()->add($field, 'This query parameter is not supported.');
            }

            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $page = (int) ($query['page'] ?? 1);
            $perPage = (int) ($query['per_page'] ?? 20);

            if ($page - 1 > intdiv(PHP_INT_MAX, $perPage)) {
                $validator->errors()->add('page', 'Page exceeds the supported range.');
            }
        });

        if ($validator->fails()) {
            throw new HttpResponseException(response()->json([
                'error' => [
                    'code' => 'VALIDATION_ERROR',
                    'message' => 'The given data was invalid.',
                    'details' => $validator->errors()->toArray(),
                    'request_id' => (string) Str::uuid(),
                ],
            ], 422));
        }

        $filters = $validator->validated();
        $page = (int) ($filters['page'] ?? 1);
        $perPage = (int) ($filters['per_page'] ?? 20);
        $cached = PublicReviewCache::remember((string) $listing->id, $page, $perPage, function () use ($listing, $page, $perPage): array {
            $reviews = $this->visibleReviews()
                ->where('reviews.listing_id', $listing->id)
                ->join('users as review_owners', 'review_owners.id', '=', 'reviews.owner_id')
                ->orderByDesc('reviews.created_at')
                ->orderByDesc('reviews.id')
                ->paginate($perPage, [
                    'reviews.id', 'reviews.rating', 'reviews.body', 'reviews.is_demo', 'reviews.created_at',
                    'review_owners.name as owner_name', 'bookings.pet_snapshot_json',
                ], 'page', $page)
                ->through(fn (stdClass $review): array => [
                    'id' => (string) $review->id,
                    'rating' => (int) $review->rating,
                    'body' => $review->body,
                    'is_demo' => (bool) $review->is_demo,
                    'reviewer_name' => $this->publicReviewerName((string) $review->owner_name),
                    'pet_species' => $this->petSpeciesFromSnapshot($review->pet_snapshot_json),
                    'created_at' => CarbonImmutable::parse($review->created_at, 'UTC')->format('Y-m-d\\TH:i:s.u\\Z'),
                ]);

            return ['items' => $reviews->items(), 'total' => $reviews->total()];
        });
        $reviews = new LengthAwarePaginator(
            $cached['items'],
            $cached['total'],
            $perPage,
            $page,
            ['path' => LengthAwarePaginator::resolveCurrentPath(), 'query' => $filters],
        );

        $attributes = $listing->getAttributes();
        $average = $attributes['rating_average'] ?? null;
        $ratingDistribution = array_fill_keys([1, 2, 3, 4, 5], 0);
        foreach ($this->visibleReviews()
            ->where('reviews.listing_id', $listing->id)
            ->select('reviews.rating', DB::raw('COUNT(*) AS count'))
            ->groupBy('reviews.rating')
            ->get() as $rating) {
            $ratingDistribution[(int) $rating->rating] = (int) $rating->count;
        }

        return JsonResource::collection($reviews)->additional([
            'rating_average' => $average === null ? null : round((float) $average, 2),
            'review_count' => (int) ($attributes['review_count'] ?? 0),
            'rating_distribution' => $ratingDistribution,
        ]);
    }

    private function publicReviewerName(string $name): string
    {
        $parts = preg_split('/\\s+/u', trim($name), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        if (count($parts) < 2) {
            return 'Khách hàng';
        }

        $givenName = array_pop($parts);
        $initial = mb_substr($parts[0], 0, 1, 'UTF-8');

        return $givenName.' '.$initial.'.';
    }

    private function petSpeciesFromSnapshot(mixed $snapshot): ?string
    {
        if (! is_string($snapshot)) {
            return null;
        }

        $pet = json_decode($snapshot, true);
        $species = is_array($pet) ? ($pet['species'] ?? null) : null;

        return match ($species) {
            'DOG' => 'DOG',
            'CAT' => 'CAT',
            'OTHER' => 'OTHER',
            default => null,
        };
    }

    private function findPublicListing(string $id): Listing
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

        return $listing;
    }

    private function visibleReviews(): QueryBuilder
    {
        return DB::table('reviews')
            ->join('bookings', 'bookings.id', '=', 'reviews.booking_id')
            ->whereColumn('bookings.listing_id', 'reviews.listing_id')
            ->whereColumn('bookings.owner_id', 'reviews.owner_id')
            ->where('bookings.status', 'COMPLETED')
            ->where('reviews.status', 'VISIBLE');
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

        $priced = $query->select('listings.*', 'review_ratings.rating_average', 'review_ratings.review_count')->selectRaw(
            'CASE '.implode(' ', $cases).' ELSE NULL END AS price_total_vnd',
            $bindings,
        );

        return Listing::query()
            ->fromSub($priced, 'listings')
            ->with(['media', 'caregiverProfile.user'])
            ->whereNotNull('listings.price_total_vnd');
    }

    /** @return Builder<Listing> */
    private function publicListings(): Builder
    {
        $ratings = $this->visibleReviews()
            ->where('reviews.is_demo', false)
            ->select('reviews.listing_id')
            ->selectRaw('AVG(reviews.rating) AS rating_average, COUNT(*) AS review_count')
            ->groupBy('reviews.listing_id');

        return Listing::query()
            ->select('listings.*', 'review_ratings.rating_average', 'review_ratings.review_count')
            ->leftJoinSub($ratings, 'review_ratings', 'review_ratings.listing_id', '=', 'listings.id')
            ->with(['media', 'caregiverProfile.user'])
            ->where('listings.status', 'ACTIVE')
            ->whereNotExists(fn (QueryBuilder $query) => $query->selectRaw('1')
                ->from('listing_change_requests')
                ->whereColumn('listing_change_requests.listing_id', 'listings.id')
                ->where('listing_change_requests.status', 'PENDING_REVIEW'))
            ->whereRelation('caregiverProfile.user', 'status', 'ACTIVE')
            ->whereRelation('caregiverProfile.user.roles', 'role', 'CAREGIVER');
    }
}
