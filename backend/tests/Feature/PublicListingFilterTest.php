<?php

namespace Tests\Feature;

use App\Models\Listing;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\ListingFixture;
use Tests\TestCase;

class PublicListingFilterTest extends TestCase
{
    use RefreshDatabase;

    /** @return array<string, array{array<string, string>, list<string>}> */
    public static function validFilters(): array
    {
        return [
            'keyword in description or title' => [['q' => 'garden'], ['dog', 'mixed']],
            'normalized vietnamese title' => [['q' => "  Di\u{0323}ch vu\u{0323}  "], ['dog']],
            'empty keyword' => [['q' => '   '], ['dog', 'cat', 'mixed']],
            'service' => [['service_type' => 'BOARDING'], ['cat']],
            'area' => [['area_code' => 'DN_EAST'], ['cat', 'mixed']],
            'species' => [['species' => 'DOG'], ['dog', 'mixed']],
            'explicit other' => [['species' => 'OTHER'], ['mixed']],
            'size' => [['size' => 'SMALL'], ['dog']],
            'all filters' => [
                [
                    'q' => 'garden',
                    'service_type' => 'DAYCARE',
                    'area_code' => 'DN_EAST',
                    'species' => 'DOG',
                    'size' => 'MEDIUM',
                ],
                ['mixed'],
            ],
            'contradicting filters' => [['service_type' => 'BOARDING', 'species' => 'DOG'], []],
        ];
    }

    #[DataProvider('validFilters')]
    public function test_filters_return_only_matching_public_listings(array $filters, array $expected): void
    {
        $listings = $this->makeListings();
        $response = $this->getJson('/api/listings?'.http_build_query($filters))->assertOk();

        $this->assertSame(
            array_map(fn (string $key): string => (string) $listings[$key]->id, $expected),
            array_column($response->json('data'), 'id'),
        );
    }

    public function test_keyword_symbols_are_literal_and_zero_is_not_ignored(): void
    {
        $listings = $this->makeListings();

        foreach (['%', '_', '!', '\\', "'", '0'] as $keyword) {
            $listings['dog']->forceFill(['title' => 'Care '.$keyword, 'description' => 'Plain'])->save();
            $this->getJson('/api/listings?'.http_build_query(['q' => $keyword]))
                ->assertOk()->assertJsonCount(1, 'data')
                ->assertJsonPath('data.0.id', (string) $listings['dog']->id);
        }
    }

    public function test_keyword_cannot_bypass_public_visibility_or_other_filters(): void
    {
        $listings = $this->makeListings();
        $listings['cat']->forceFill(['description' => 'Garden'])->save();

        $this->getJson('/api/listings?q=garden&service_type=BOARDING&area_code=DN_EAST')
            ->assertOk()->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', (string) $listings['cat']->id);
    }

    /** @return array<string, array{array<string, mixed>, string}> */
    public static function invalidFilters(): array
    {
        return [
            'unknown service' => [['service_type' => 'WALKING'], 'service_type'],
            'lowercase service' => [['service_type' => 'daycare'], 'service_type'],
            'empty service' => [['service_type' => ''], 'service_type'],
            'unknown area' => [['area_code' => 'SON_TRA'], 'area_code'],
            'empty area' => [['area_code' => ''], 'area_code'],
            'unknown species' => [['species' => 'BIRD'], 'species'],
            'empty species' => [['species' => ''], 'species'],
            'unknown size' => [['size' => 'XL'], 'size'],
            'array filter' => [['species' => ['DOG']], 'species'],
            'array keyword' => [['q' => ['garden']], 'q'],
            'long keyword' => [['q' => str_repeat('a', 101)], 'q'],
            'page zero' => [['page' => '0'], 'page'],
            'decimal page' => [['page' => '1.5'], 'page'],
            'scientific page' => [['page' => '1e2'], 'page'],
            'forged status' => [['status' => 'DRAFT'], 'status'],
            'unknown query' => [['unexpected' => 'value'], 'unexpected'],
            'missing end date' => [['start_at' => '2030-01-01T00:00:00Z'], 'end_at'],
            'price requires dates' => [['max_price_vnd' => '200000'], 'max_price_vnd'],
            'page size exceeds limit' => [['per_page' => '51'], 'per_page'],
        ];
    }

    #[DataProvider('invalidFilters')]
    public function test_invalid_filters_return_field_errors(array $filters, string $field): void
    {
        $response = $this->getJson('/api/listings?'.http_build_query($filters))
            ->assertUnprocessable()->assertJsonPath('error.code', 'VALIDATION_ERROR');

        $this->assertArrayHasKey($field, $response->json('error.details'));
    }

    public function test_invalid_acceptance_rules_do_not_match_species_filter(): void
    {
        $listings = $this->makeListings();

        foreach ([
            ['schema_version' => 2],
            ['schema_version' => '1'],
            ['species' => 'DOG'],
            ['species' => ['DOG', 'INVALID']],
            ['sizes' => []],
        ] as $invalid) {
            $listings['dog']->forceFill([
                'acceptance_rules_json' => array_replace([
                    'schema_version' => 1,
                    'species' => ['DOG'],
                    'sizes' => ['SMALL'],
                ], $invalid),
            ])->save();

            $this->getJson('/api/listings?species=DOG&size=SMALL')
                ->assertOk()->assertJsonCount(0, 'data');
        }
    }

    public function test_pagination_links_keep_normalized_filters(): void
    {
        $this->makeListings();
        $response = $this->getJson('/api/listings?q=%20garden%20&species=DOG')->assertOk();
        parse_str(parse_url($response->json('links.first'), PHP_URL_QUERY), $query);

        $this->assertSame('garden', $query['q']);
        $this->assertSame('DOG', $query['species']);
    }

    /** @return array<string, Listing> */
    private function makeListings(): array
    {
        $fixture = ListingFixture::make();
        $dog = Listing::query()->findOrFail($fixture['active_listing_id']);
        $dog->forceFill(['title' => 'Dịch vụ chăm chó', 'description' => 'Quiet garden'])->save();

        $cat = $dog->replicate();
        $cat->fill([
            'title' => 'Cat boarding',
            'description' => 'Indoor care',
            'service_type' => 'BOARDING',
            'price_unit' => 'NIGHT',
            'area_code' => 'DN_EAST',
            'acceptance_rules_json' => [
                'schema_version' => 1,
                'species' => ['CAT'],
                'sizes' => ['LARGE'],
            ],
        ])->save();

        $mixed = $dog->replicate();
        $mixed->fill([
            'title' => 'Garden daycare',
            'description' => 'Play area',
            'area_code' => 'DN_EAST',
            'acceptance_rules_json' => [
                'schema_version' => 1,
                'species' => ['DOG', 'CAT', 'OTHER'],
                'sizes' => ['MEDIUM'],
            ],
        ])->save();

        Listing::query()->where('status', 'DRAFT')->update([
            'title' => 'Private garden',
            'description' => 'Private garden',
        ]);

        return ['dog' => $dog, 'cat' => $cat, 'mixed' => $mixed];
    }
}
