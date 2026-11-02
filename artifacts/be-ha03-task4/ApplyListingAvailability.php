<?php

namespace App\Domain\Booking\Actions;

use App\Models\AvailabilitySlot;
use App\Models\Booking;
use App\Models\Listing;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Facades\DB;
use LogicException;

class ApplyListingAvailability
{
    private const BLOCKING_STATUSES = [
        'AWAITING_PAYMENT',
        'CONFIRMED',
        'IN_PROGRESS',
        'AWAITING_CONFIRMATION',
    ];

    /**
     * @param  Builder<Listing>  $query
     * @return Builder<Listing>
     */
    public function apply(
        Builder $query,
        CarbonImmutable $start,
        CarbonImmutable $end,
    ): Builder {
        $startSql = $start->utc()->format('Y-m-d H:i:s.u');
        $endSql = $end->utc()->format('Y-m-d H:i:s.u');

        return $query
            ->whereHas('availabilitySlots', function (Builder $slots) use ($startSql, $endSql): void {
                $slots->where('start_at', '<=', $startSql)
                    ->where('end_at', '>=', $endSql);
            })
            ->whereNotExists(function (QueryBuilder $bookings) use ($startSql, $endSql): void {
                $bookings->selectRaw('1')
                    ->from('bookings')
                    ->whereColumn('bookings.listing_id', 'listings.id')
                    ->whereIn('bookings.status', self::BLOCKING_STATUSES)
                    ->where('bookings.start_at', '<', $endSql)
                    ->where('bookings.end_at', '>', $startSql);
            });
    }

    public function isAvailableFor(Booking $booking, bool $lock = false): bool
    {
        if ($lock && DB::transactionLevel() === 0) {
            throw new LogicException('Calendar locks require a transaction.');
        }

        $start = $booking->start_at->utc()->format('Y-m-d H:i:s.u');
        $end = $booking->end_at->utc()->format('Y-m-d H:i:s.u');
        $slots = AvailabilitySlot::query()->where('listing_id', $booking->listing_id)
            ->where('start_at', '<=', $start)->where('end_at', '>=', $end);
        $conflicts = Booking::query()->where('listing_id', $booking->listing_id)
            ->whereIn('status', self::BLOCKING_STATUSES)
            ->where('start_at', '<', $end)->where('end_at', '>', $start);

        if ($lock) {
            // Locking reads see committed changes even after an earlier snapshot read.
            $slots->sharedLock();
            $conflicts->lockForUpdate();
        }

        return $slots->first(['id']) !== null && $conflicts->first(['id']) === null;
    }
}
