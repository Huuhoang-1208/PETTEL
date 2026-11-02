<?php

namespace App\Domain\Booking\Actions;

use Carbon\CarbonImmutable;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Str;

class CalculateBookingPrice
{
    /** @return array<string, int|string> */
    public function calculate(
        string $serviceType,
        string $priceUnit,
        int|string $unitPriceVnd,
        CarbonImmutable $start,
        CarbonImmutable $end,
    ): array {
        $unitMicroseconds = match ([$serviceType, $priceUnit]) {
            ['DAYCARE', 'HOUR'] => 3_600_000_000,
            ['BOARDING', 'NIGHT'] => 86_400_000_000,
            default => $this->reject(409, 'INVALID_STATE', 'Invalid listing pricing unit.', 'listing_id'),
        };

        $unitPrice = filter_var($unitPriceVnd, FILTER_VALIDATE_INT, [
            'options' => ['min_range' => 1],
        ]);
        if ($unitPrice === false) {
            $this->reject(409, 'INVALID_STATE', 'Listing price must be a supported positive integer.', 'listing_id');
        }

        $start = $start->utc();
        $end = $end->utc();
        $seconds = $end->getTimestamp() - $start->getTimestamp();

        // Bound the duration before multiplying by one million.
        if ($seconds < 0 || $seconds > 2_592_001) {
            $this->reject(422, 'VALIDATION_ERROR', 'Invalid service duration.', 'end_at');
        }
        $durationMicroseconds = $seconds * 1_000_000
            + (int) $end->format('u') - (int) $start->format('u');

        $isDaycare = $serviceType === 'DAYCARE';
        $minimum = $isDaycare ? 3_600_000_000 : 43_200_000_000;
        $maximum = $isDaycare ? 43_200_000_000 : 2_592_000_000_000;
        $sameLocalDay = $start->setTimezone('Asia/Ho_Chi_Minh')
            ->isSameDay($end->setTimezone('Asia/Ho_Chi_Minh'));

        if (
            $durationMicroseconds < $minimum || $durationMicroseconds > $maximum
            || ($isDaycare && ! $sameLocalDay)
        ) {
            $this->reject(422, 'VALIDATION_ERROR', 'Time range does not meet service requirements.', 'end_at');
        }

        // Integer ceiling division: an extra microsecond adds a billable unit.
        $quantity = intdiv($durationMicroseconds - 1, $unitMicroseconds) + 1;
        if ($unitPrice > intdiv(PHP_INT_MAX, $quantity)) {
            $this->reject(422, 'VALIDATION_ERROR', 'Total price exceeds the supported integer range.', 'listing_id');
        }

        return [
            'price_total_vnd' => $unitPrice * $quantity,
            'unit_price_vnd' => $unitPrice,
            'price_unit' => $priceUnit,
            'quantity' => $quantity,
            'pricing_version' => 1,
            'cancellation_policy_version' => 1,
        ];
    }

    private function reject(int $status, string $code, string $message, string $field): never
    {
        throw new HttpResponseException(response()->json([
            'error' => [
                'code' => $code,
                'message' => $message,
                'details' => [$field => [$message]],
                'request_id' => (string) Str::uuid(),
            ],
        ], $status));
    }
}
