<?php

namespace App\Domain\Booking\Actions;

use App\Models\Listing;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;

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
}
