<?php

namespace App\Http\Controllers;

use App\Domain\Listing\Actions\ListingError;
use App\Domain\Listing\Actions\ListingInput;
use App\Models\AvailabilitySlot;
use App\Models\Listing;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ListingAvailabilityController extends Controller
{
    private const HOLDS = ['AWAITING_PAYMENT', 'CONFIRMED', 'IN_PROGRESS', 'AWAITING_CONFIRMATION'];

    public function index(Request $request, string $id): mixed
    {
        $listing = $this->owned($request, $id);
        $filters = app(ListingInput::class)->query($request, ['page', 'per_page'], [
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:50'],
        ]);
        $page = $listing->availabilitySlots()->paginate($filters['per_page'] ?? 20, ['*'], 'page', $filters['page'] ?? 1);
        $page->appends($filters);
        $page->getCollection()->transform(fn (AvailabilitySlot $slot) => $this->resource($slot));

        return response()->json(['data' => $page->items(), 'links' => [
            'first' => $page->url(1), 'last' => $page->url($page->lastPage()),
            'prev' => $page->previousPageUrl(), 'next' => $page->nextPageUrl(),
        ], 'meta' => ['current_page' => $page->currentPage(), 'last_page' => $page->lastPage(), 'per_page' => $page->perPage(), 'total' => $page->total()]]);
    }

    public function store(Request $request, string $id): mixed
    {
        $this->owned($request, $id);
        $range = $this->range($request);
        $slot = DB::transaction(function () use ($request, $id, $range): AvailabilitySlot {
            $listing = $this->owned($request, $id, true);
            $this->editable($listing);
            $this->assertNoOverlap($listing, $range['start'], $range['end']);
            $slot = new AvailabilitySlot(['start_at' => $range['start'], 'end_at' => $range['end']]);
            $slot->creator()->associate($request->user());
            $listing->availabilitySlots()->save($slot);

            return $slot;
        }, 3);

        return response()->json(['data' => $this->resource($slot->refresh())], 201);
    }

    public function update(Request $request, string $id, string $slotId): mixed
    {
        $listing = $this->owned($request, $id);
        if (! $listing->availabilitySlots()->whereKey($slotId)->exists()) {
            ListingError::fail(404, 'NOT_FOUND', 'Availability slot not found.');
        }
        $range = $this->range($request);
        $slot = DB::transaction(function () use ($request, $id, $slotId, $range): AvailabilitySlot {
            $listing = $this->owned($request, $id, true);
            $slot = $listing->availabilitySlots()->whereKey($slotId)->lockForUpdate()->first();
            if ($slot === null) {
                ListingError::fail(404, 'NOT_FOUND', 'Availability slot not found.');
            }
            $this->editable($listing);
            $this->assertNoOverlap($listing, $range['start'], $range['end'], (string) $slot->id);
            $slot->start_at = $range['start'];
            $slot->end_at = $range['end'];
            $slot->save();
            $this->assertHeldBookingsCovered($listing);

            return $slot;
        }, 3);

        return response()->json(['data' => $this->resource($slot->refresh())]);
    }

    public function destroy(Request $request, string $id, string $slotId): mixed
    {
        $listing = $this->owned($request, $id);
        if (! $listing->availabilitySlots()->whereKey($slotId)->exists()) {
            ListingError::fail(404, 'NOT_FOUND', 'Availability slot not found.');
        }
        app(ListingInput::class)->body($request, [], []);
        DB::transaction(function () use ($request, $id, $slotId): void {
            $listing = $this->owned($request, $id, true);
            $slot = $listing->availabilitySlots()->whereKey($slotId)->lockForUpdate()->first();
            if ($slot === null) {
                ListingError::fail(404, 'NOT_FOUND', 'Availability slot not found.');
            }
            $this->editable($listing);
            $slot->delete();
            $this->assertHeldBookingsCovered($listing);
        }, 3);

        return response()->noContent();
    }

    private function owned(Request $request, string $id, bool $lock = false): Listing
    {
        $query = Listing::query()->whereKey($id)->whereRelation('caregiverProfile', 'user_id', $request->user()->id);
        if ($lock) {
            $query->lockForUpdate();
        }
        $listing = $query->first();
        if ($listing === null) {
            ListingError::fail(404, 'NOT_FOUND', 'Listing not found.');
        }

        return $listing;
    }

    private function editable(Listing $listing): void
    {
        if ($listing->status === 'ARCHIVED') {
            ListingError::fail(409, 'INVALID_STATE', 'Archived listings cannot be changed.');
        }
    }

    /** @return array{start: CarbonImmutable, end: CarbonImmutable} */
    private function range(Request $request): array
    {
        $data = app(ListingInput::class)->body($request, ['start_at', 'end_at'], [
            'start_at' => ['required', 'date'], 'end_at' => ['required', 'date', 'after:start_at'],
        ]);
        foreach (['start_at', 'end_at'] as $key) {
            if (! preg_match('/(?:Z|[+-]\d{2}:?\d{2})$/i', $data[$key])) {
                ListingError::fail(422, 'VALIDATION_ERROR', 'Timestamps must include a timezone.', [$key => ['Include Z or a UTC offset.']]);
            }
        }
        try {
            $start = CarbonImmutable::parse($data['start_at'])->utc();
            $end = CarbonImmutable::parse($data['end_at'])->utc();
        } catch (\Throwable) {
            ListingError::fail(422, 'VALIDATION_ERROR', 'Invalid timestamp.');
        }
        if (! $start->lessThan($end) || ! $end->greaterThan(now('UTC'))) {
            ListingError::fail(422, 'VALIDATION_ERROR', 'The end must be in the future and after the start.');
        }

        return ['start' => $start, 'end' => $end];
    }

    private function assertNoOverlap(Listing $listing, CarbonImmutable $start, CarbonImmutable $end, ?string $ignore = null): void
    {
        $query = AvailabilitySlot::query()->where('listing_id', $listing->id)
            ->where('start_at', '<', $end->format('Y-m-d H:i:s.u'))
            ->where('end_at', '>', $start->format('Y-m-d H:i:s.u'));
        if ($ignore !== null) {
            $query->where('id', '<>', $ignore);
        }
        if ($query->exists()) {
            ListingError::fail(409, 'AVAILABILITY_CONFLICT', 'Availability slots overlap.');
        }
    }

    private function assertHeldBookingsCovered(Listing $listing): void
    {
        $bookings = DB::table('bookings')->where('listing_id', $listing->id)->whereIn('status', self::HOLDS)->get(['id', 'start_at', 'end_at']);
        foreach ($bookings as $booking) {
            $covered = AvailabilitySlot::query()->where('listing_id', $listing->id)
                ->where('start_at', '<=', $booking->start_at)->where('end_at', '>=', $booking->end_at)->exists();
            if (! $covered) {
                ListingError::fail(409, 'AVAILABILITY_CONFLICT', 'The change would invalidate a held booking.');
            }
        }
    }

    /** @return array{id: string, start_at: string, end_at: string} */
    private function resource(AvailabilitySlot $slot): array
    {
        return ['id' => (string) $slot->id, 'start_at' => $slot->start_at->utc()->format('Y-m-d\TH:i:s.u\Z'), 'end_at' => $slot->end_at->utc()->format('Y-m-d\TH:i:s.u\Z')];
    }
}
