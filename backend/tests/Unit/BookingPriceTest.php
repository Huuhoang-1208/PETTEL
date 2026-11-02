<?php

namespace Tests\Unit;

use App\Domain\Booking\Actions\CalculateBookingPrice;
use Carbon\CarbonImmutable;
use Illuminate\Http\Exceptions\HttpResponseException;
use Tests\TestCase;

class BookingPriceTest extends TestCase
{
    public function test_rounding_preserves_microseconds(): void
    {
        $start = CarbonImmutable::parse('2030-01-01T08:00:00+07:00');

        foreach ([
            ['DAYCARE', 'HOUR', 3_600_000_000, 1],
            ['DAYCARE', 'HOUR', 3_600_000_001, 2],
            ['DAYCARE', 'HOUR', 7_800_000_000, 3],
            ['DAYCARE', 'HOUR', 43_200_000_000, 12],
            ['BOARDING', 'NIGHT', 43_200_000_000, 1],
            ['BOARDING', 'NIGHT', 86_400_000_001, 2],
            ['BOARDING', 'NIGHT', 90_000_000_000, 2],
            ['BOARDING', 'NIGHT', 2_592_000_000_000, 30],
        ] as [$service, $unit, $duration, $quantity]) {
            $result = (new CalculateBookingPrice)->calculate(
                $service,
                $unit,
                '50000',
                $start,
                $start->addMicroseconds($duration),
            );
            $this->assertSame($quantity, $result['quantity']);
            $this->assertSame(50000 * $quantity, $result['price_total_vnd']);
            $this->assertSame(50000, $result['unit_price_vnd']);
            $this->assertSame(1, $result['pricing_version']);
            $this->assertSame(1, $result['cancellation_policy_version']);
        }
    }

    public function test_daycare_uses_vietnam_day_even_when_utc_dates_differ(): void
    {
        $calculator = new CalculateBookingPrice;
        $start = CarbonImmutable::parse('2030-01-01T06:30:00+07:00');
        $end = CarbonImmutable::parse('2030-01-01T08:30:00+07:00');
        $local = $calculator->calculate('DAYCARE', 'HOUR', 50000, $start, $end);
        $utc = $calculator->calculate('DAYCARE', 'HOUR', 50000, $start->utc(), $end->utc());
        $this->assertSame($local, $utc);
        $this->assertSame(2, $utc['quantity']);

        $late = CarbonImmutable::parse('2030-01-01T23:30:00+07:00');
        $this->assertRejected(fn () => $calculator->calculate('DAYCARE', 'HOUR', 50000, $late, $late->addHour()), 422);
    }

    public function test_duration_bounds_are_exact(): void
    {
        $start = CarbonImmutable::parse('2030-01-01T08:00:00+07:00');
        foreach ([
            ['DAYCARE', 'HOUR', -1],
            ['DAYCARE', 'HOUR', 3_599_999_999],
            ['DAYCARE', 'HOUR', 43_200_000_001],
            ['BOARDING', 'NIGHT', 43_199_999_999],
            ['BOARDING', 'NIGHT', 2_592_000_000_001],
        ] as [$service, $unit, $duration]) {
            $this->assertRejected(fn () => (new CalculateBookingPrice)->calculate(
                $service,
                $unit,
                50000,
                $start,
                $start->addMicroseconds($duration),
            ), 422);
        }
    }

    public function test_price_and_unit_must_be_valid(): void
    {
        $start = CarbonImmutable::parse('2030-01-01T08:00:00+07:00');
        foreach ([0, -1, '1.5', (string) PHP_INT_MAX.'0'] as $price) {
            $this->assertRejected(fn () => (new CalculateBookingPrice)->calculate(
                'DAYCARE',
                'HOUR',
                $price,
                $start,
                $start->addHour(),
            ), 409);
        }
        $this->assertRejected(fn () => (new CalculateBookingPrice)->calculate(
            'DAYCARE',
            'NIGHT',
            50000,
            $start,
            $start->addHour(),
        ), 409);
    }

    public function test_total_overflow_is_checked_before_multiplication(): void
    {
        $calculator = new CalculateBookingPrice;
        $start = CarbonImmutable::parse('2030-01-01T08:00:00+07:00');
        $maximumUnitPrice = intdiv(PHP_INT_MAX, 3);
        $result = $calculator->calculate('DAYCARE', 'HOUR', $maximumUnitPrice, $start, $start->addMinutes(130));
        $this->assertSame($maximumUnitPrice * 3, $result['price_total_vnd']);
        $this->assertRejected(fn () => $calculator->calculate(
            'DAYCARE',
            'HOUR',
            $maximumUnitPrice + 1,
            $start,
            $start->addMinutes(130),
        ), 422);
        $single = $calculator->calculate('DAYCARE', 'HOUR', PHP_INT_MAX, $start, $start->addHour());
        $this->assertSame(PHP_INT_MAX, $single['price_total_vnd']);
    }

    private function assertRejected(callable $calculation, int $status): void
    {
        try {
            $calculation();
            $this->fail('Expected invalid pricing input to be rejected.');
        } catch (HttpResponseException $exception) {
            $this->assertSame($status, $exception->getResponse()->getStatusCode());
        }
    }
}
