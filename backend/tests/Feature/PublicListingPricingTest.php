<?php

namespace Tests\Feature;

use App\Models\Listing;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Support\ListingFixture;
use Tests\TestCase;

class PublicListingPricingTest extends TestCase
{
    use RefreshDatabase;

    public function test_totals_match_booking_quotes_and_sort_by_total_then_id(): void
    {
        [$fixture, $listings] = $this->makeListings();

        $response = $this->getJson($this->url($fixture, ['per_page' => '50']))
            ->assertOk();

        $this->assertSame(
            $this->ids($listings, ['cheap', 'base', 'tie', 'costly']),
            array_column($response->json('data'), 'id'),
        );

        $this->assertSame(
            [60000, 150000, 150000, 300000],
            array_column($response->json('data'), 'price_total_vnd'),
        );

        foreach ($response->json('data') as $item) {
            $quote = $this->actingAs($fixture['owner'])
                ->postJson('/api/bookings/quote', [
                    ...$fixture['payload'],
                    'listing_id' => $item['id'],
                ])->assertOk();

            $this->assertSame(
                $quote->json('price_total_vnd'),
                $item['price_total_vnd'],
            );
        }
    }

    public function test_price_bounds_are_inclusive_and_allow_one_sided_filters(): void
    {
        [$fixture, $listings] = $this->makeListings();

        foreach ([
            [
                ['min_price_vnd' => '150000', 'max_price_vnd' => '150000'],
                ['base', 'tie'],
            ],
            [['max_price_vnd' => '60000'], ['cheap']],
            [['min_price_vnd' => '300000'], ['costly']],
            [['max_price_vnd' => '0'], []],
            [['min_price_vnd' => '300001'], []],
        ] as [$filters, $expected]) {
            $response = $this->getJson($this->url($fixture, $filters))->assertOk();

            $this->assertSame(
                $this->ids($listings, $expected),
                array_column($response->json('data'), 'id'),
            );

            $response->assertJsonPath('meta.total', count($expected));
        }
    }

    public function test_pages_have_stable_ties_and_out_of_range_page_is_empty(): void
    {
        [$fixture, $listings] = $this->makeListings();

        $first = $this->getJson($this->url($fixture, [
            'per_page' => '2',
            'page' => '1',
        ]))->assertOk()->assertJsonPath('meta.total', 4)
            ->assertJsonPath('meta.last_page', 2);

        $second = $this->getJson($this->url($fixture, [
            'per_page' => '2',
            'page' => '2',
        ]))->assertOk();

        $ids = [
            ...array_column($first->json('data'), 'id'),
            ...array_column($second->json('data'), 'id'),
        ];

        $this->assertSame(
            $this->ids($listings, ['cheap', 'base', 'tie', 'costly']),
            $ids,
        );
        $this->assertCount(4, array_unique($ids));

        $this->getJson($this->url($fixture, [
            'per_page' => '2',
            'page' => '3',
        ]))->assertOk()->assertJsonCount(0, 'data')
            ->assertJsonPath('meta.total', 4)
            ->assertJsonPath('meta.current_page', 3);
    }

    public function test_without_dates_results_sort_by_id_and_total_is_null(): void
    {
        [, $listings] = $this->makeListings();

        $response = $this->getJson('/api/listings?per_page=50')->assertOk();

        $this->assertSame(
            $this->ids($listings, ['base', 'cheap', 'tie', 'costly']),
            array_column($response->json('data'), 'id'),
        );

        $this->assertSame(
            [null, null, null, null],
            array_column($response->json('data'), 'price_total_vnd'),
        );
    }

    public function test_fractional_seconds_use_booking_rounding(): void
    {
        [$fixture, $listings] = $this->makeListings();
        $start = CarbonImmutable::instance($fixture['start_at']);

        $response = $this->getJson($this->url($fixture, [
            'end_at' => $start->addHour()->addMicrosecond()->format('Y-m-d\TH:i:s.u\Z'),
            'min_price_vnd' => '100000',
            'max_price_vnd' => '100000',
        ]))->assertOk();

        $this->assertSame(
            $this->ids($listings, ['base', 'tie']),
            array_column($response->json('data'), 'id'),
        );

        $this->assertSame(
            [100000, 100000],
            array_column($response->json('data'), 'price_total_vnd'),
        );
    }

    public function test_overflow_is_excluded_but_maximum_single_unit_total_is_supported(): void
    {
        [$fixture, $listings] = $this->makeListings();
        $maximum = $this->copyListing($listings['base'], PHP_INT_MAX);

        $this->getJson($this->url($fixture))->assertOk()
            ->assertJsonCount(4, 'data')
            ->assertJsonMissing(['id' => (string) $maximum->id]);

        $start = CarbonImmutable::instance($fixture['start_at']);

        $this->getJson($this->url($fixture, [
            'end_at' => $start->addHour()->format('Y-m-d\TH:i:s.u\Z'),
            'min_price_vnd' => (string) PHP_INT_MAX,
            'max_price_vnd' => (string) PHP_INT_MAX,
        ]))->assertOk()->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', (string) $maximum->id)
            ->assertJsonPath('data.0.price_total_vnd', PHP_INT_MAX);
    }

    public function test_hourly_and_nightly_services_compare_total_prices(): void
    {
        $fixture = ListingFixture::make();
        $daycare = Listing::query()->findOrFail($fixture['active_listing_id']);
        $daycare->fill(['price_vnd' => 20000])->save();

        $start = CarbonImmutable::instance($fixture['start_at']);
        $end = $start->addHours(12);

        DB::table('availability_slots')
            ->where('listing_id', $daycare->id)
            ->update(['end_at' => $end->addHour()->format('Y-m-d H:i:s.u')]);

        $boarding = $this->copyListing($daycare, 150000, 'BOARDING');

        $response = $this->getJson('/api/listings?'.http_build_query([
            'start_at' => $start->format('Y-m-d\TH:i:s.u\Z'),
            'end_at' => $end->format('Y-m-d\TH:i:s.u\Z'),
        ]))->assertOk();

        $this->assertSame(
            [(string) $boarding->id, (string) $daycare->id],
            array_column($response->json('data'), 'id'),
        );

        $this->assertSame(
            [150000, 240000],
            array_column($response->json('data'), 'price_total_vnd'),
        );
    }

    public function test_invalid_price_and_pagination_inputs_return_field_errors(): void
    {
        [$fixture] = $this->makeListings();

        foreach ([
            [['min_price_vnd' => '-1'], 'min_price_vnd'],
            [['max_price_vnd' => '1.5'], 'max_price_vnd'],
            [['max_price_vnd' => '1e5'], 'max_price_vnd'],
            [['max_price_vnd' => ['100000']], 'max_price_vnd'],
            [['max_price_vnd' => (string) PHP_INT_MAX.'0'], 'max_price_vnd'],
            [
                ['min_price_vnd' => '200000', 'max_price_vnd' => '100000'],
                'max_price_vnd',
            ],
            [['per_page' => '0'], 'per_page'],
            [['per_page' => '51'], 'per_page'],
            [['per_page' => '1.5'], 'per_page'],
            [['page' => (string) PHP_INT_MAX, 'per_page' => '50'], 'page'],
        ] as [$filters, $field]) {
            $response = $this->getJson($this->url($fixture, $filters))
                ->assertUnprocessable()
                ->assertJsonPath('error.code', 'VALIDATION_ERROR');

            $this->assertArrayHasKey($field, $response->json('error.details'));
        }
    }

    public function test_pagination_links_preserve_price_filters(): void
    {
        [$fixture] = $this->makeListings();

        $response = $this->getJson($this->url($fixture, [
            'min_price_vnd' => '60000',
            'max_price_vnd' => '150000',
            'species' => 'DOG',
            'per_page' => '1',
        ]))->assertOk();

        parse_str(parse_url($response->json('links.next'), PHP_URL_QUERY), $query);

        $this->assertSame('60000', $query['min_price_vnd']);
        $this->assertSame('150000', $query['max_price_vnd']);
        $this->assertSame('DOG', $query['species']);
        $this->assertSame('1', $query['per_page']);
        $this->assertSame('2', $query['page']);
        $this->assertArrayHasKey('start_at', $query);
        $this->assertArrayHasKey('end_at', $query);
    }

    private function makeListings(): array
    {
        $fixture = ListingFixture::make();
        $base = Listing::query()->findOrFail($fixture['active_listing_id']);

        return [
            $fixture,
            [
                'base' => $base,
                'cheap' => $this->copyListing($base, 20000),
                'tie' => $this->copyListing($base, 50000),
                'costly' => $this->copyListing($base, 100000),
            ],
        ];
    }

    private function copyListing(
        Listing $template,
        int $price,
        string $serviceType = 'DAYCARE',
    ): Listing {
        $listing = $template->replicate();
        $listing->fill([
            'price_vnd' => $price,
            'service_type' => $serviceType,
            'price_unit' => $serviceType === 'DAYCARE' ? 'HOUR' : 'NIGHT',
        ])->save();

        $slot = $template->availabilitySlots()->firstOrFail();

        DB::table('availability_slots')->insert([
            'listing_id' => $listing->id,
            'start_at' => $slot->start_at->format('Y-m-d H:i:s.u'),
            'end_at' => $slot->end_at->format('Y-m-d H:i:s.u'),
            'created_by_user_id' => $slot->created_by_user_id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $listing;
    }

    private function url(array $fixture, array $filters = []): string
    {
        return '/api/listings?'.http_build_query(array_replace([
            'start_at' => $fixture['payload']['start_at'],
            'end_at' => $fixture['payload']['end_at'],
        ], $filters));
    }

    private function ids(array $listings, array $keys): array
    {
        return array_map(
            fn (string $key): string => (string) $listings[$key]->id,
            $keys,
        );
    }
}
