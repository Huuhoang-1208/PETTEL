<?php

namespace Tests\Feature;

use App\Models\Listing;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ListingValidationTest extends TestCase
{
    use RefreshDatabase;

    #[DataProvider('invalidPayloads')]
    public function test_invalid_input_is_rejected_without_writing(array $changes, string $field): void
    {
        $user = $this->caregiver();
        $this->actingAs($user);
        $listing = $this->draft($user);
        $before = $listing->getRawOriginal();
        $invalid = array_replace($this->payload(), $changes);

        $this->assertValidationError(
            $this->postJson('/api/caregiver/listings', $invalid),
            $field,
        );
        $this->assertDatabaseCount('listings', 1);

        $this->assertValidationError(
            $this->patchJson("/api/caregiver/listings/{$listing->id}", $changes),
            $field,
        );
        $this->assertSame($before, $listing->fresh()->getRawOriginal());
    }

    public static function invalidPayloads(): array
    {
        $rules = [
            'schema_version' => 1,
            'species' => ['CAT'],
            'sizes' => ['SMALL'],
        ];

        return [
            'title null' => [['title' => null], 'title'],
            'title empty' => [['title' => ''], 'title'],
            'title whitespace' => [['title' => '   '], 'title'],
            'title too long' => [['title' => str_repeat('x', 121)], 'title'],
            'title wrong type' => [['title' => ['Cat care']], 'title'],
            'description null' => [['description' => null], 'description'],
            'description whitespace' => [['description' => '   '], 'description'],
            'description too long' => [['description' => str_repeat('x', 2001)], 'description'],
            'area unknown' => [['area_code' => 'DA_NANG'], 'area_code'],
            'area wrong type' => [['area_code' => ['DN_CENTER']], 'area_code'],
            'service unknown' => [['service_type' => 'WALKING'], 'service_type'],
            'service wrong type' => [['service_type' => ['DAYCARE']], 'service_type'],
            'unit unknown' => [['price_unit' => 'DAY'], 'price_unit'],
            'unit wrong type' => [['price_unit' => ['NIGHT']], 'price_unit'],
            'daycare with night' => [['service_type' => 'DAYCARE'], 'price_unit'],
            'boarding with hour' => [['price_unit' => 'HOUR'], 'price_unit'],
            'price zero' => [['price_vnd' => 0], 'price_vnd'],
            'price negative' => [['price_vnd' => -1], 'price_vnd'],
            'price decimal' => [['price_vnd' => 1.5], 'price_vnd'],
            'price string' => [['price_vnd' => '150000'], 'price_vnd'],
            'price boolean' => [['price_vnd' => true], 'price_vnd'],
            'price null' => [['price_vnd' => null], 'price_vnd'],
            'price overflow' => [['price_vnd' => PHP_INT_MAX * 2.0], 'price_vnd'],
            'rules null' => [['acceptance_rules_json' => null], 'acceptance_rules_json'],
            'rules empty' => [['acceptance_rules_json' => []], 'acceptance_rules_json.schema_version'],
            'rules JSON string' => [['acceptance_rules_json' => json_encode($rules)], 'acceptance_rules_json'],
            'version missing' => [['acceptance_rules_json' => ['species' => ['CAT'], 'sizes' => ['SMALL']]], 'acceptance_rules_json.schema_version'],
            'version unsupported' => [['acceptance_rules_json' => array_replace($rules, ['schema_version' => 2])], 'acceptance_rules_json.schema_version'],
            'version string' => [['acceptance_rules_json' => array_replace($rules, ['schema_version' => '1'])], 'acceptance_rules_json.schema_version'],
            'species empty' => [['acceptance_rules_json' => array_replace($rules, ['species' => []])], 'acceptance_rules_json.species'],
            'species string' => [['acceptance_rules_json' => array_replace($rules, ['species' => 'CAT'])], 'acceptance_rules_json.species'],
            'species not a list' => [['acceptance_rules_json' => array_replace($rules, ['species' => ['first' => 'CAT']])], 'acceptance_rules_json.species'],
            'species unknown' => [['acceptance_rules_json' => array_replace($rules, ['species' => ['BIRD']])], 'acceptance_rules_json.species.0'],
            'species null item' => [['acceptance_rules_json' => array_replace($rules, ['species' => [null]])], 'acceptance_rules_json.species.0'],
            'sizes missing' => [['acceptance_rules_json' => ['schema_version' => 1, 'species' => ['CAT']]], 'acceptance_rules_json.sizes'],
            'sizes empty' => [['acceptance_rules_json' => array_replace($rules, ['sizes' => []])], 'acceptance_rules_json.sizes'],
            'sizes string' => [['acceptance_rules_json' => array_replace($rules, ['sizes' => 'SMALL'])], 'acceptance_rules_json.sizes'],
            'sizes unknown' => [['acceptance_rules_json' => array_replace($rules, ['sizes' => ['XL']])], 'acceptance_rules_json.sizes.0'],
            'unknown rule key' => [['acceptance_rules_json' => $rules + ['min_age' => 1]], 'acceptance_rules_json'],
        ];
    }

    public function test_create_requires_all_seven_content_fields(): void
    {
        $this->actingAs($this->caregiver());

        foreach (array_keys($this->payload()) as $field) {
            $payload = $this->payload();
            unset($payload[$field]);

            $this->assertValidationError(
                $this->postJson('/api/caregiver/listings', $payload),
                $field,
            );
        }

        $this->assertDatabaseCount('listings', 0);
    }

    public function test_unicode_limits_and_both_service_pairs_are_accepted(): void
    {
        $this->actingAs($this->caregiver());
        $payload = $this->payload();
        $payload['title'] = '  '.str_repeat("e\u{0301}", 120).'  ';
        $payload['description'] = '  '.str_repeat('đ', 2000).'  ';
        $payload['price_vnd'] = 1;
        $payload['acceptance_rules_json'] = [
            'schema_version' => 1,
            'species' => ['DOG', 'CAT', 'OTHER'],
            'sizes' => ['SMALL', 'MEDIUM', 'LARGE'],
        ];

        $this->postJson('/api/caregiver/listings', $payload)
            ->assertCreated()->assertJsonPath('data.title', str_repeat('é', 120))
            ->assertJsonPath('data.description', str_repeat('đ', 2000))
            ->assertJsonPath('data.capacity', 1);

        $payload['service_type'] = 'DAYCARE';
        $payload['price_unit'] = 'HOUR';
        $payload['price_vnd'] = PHP_INT_MAX;

        foreach (['DN_CENTER', 'DN_NORTH', 'DN_SOUTH', 'DN_WEST', 'DN_EAST'] as $area) {
            $payload['area_code'] = $area;
            $this->postJson('/api/caregiver/listings', $payload)
                ->assertCreated()->assertJsonPath('data.area_code', $area)
                ->assertJsonPath('data.price_vnd', PHP_INT_MAX);
        }

        $this->assertDatabaseCount('listings', 6);
    }

    public function test_capacity_is_fixed_and_cannot_be_supplied_by_client(): void
    {
        $user = $this->caregiver();
        $this->actingAs($user);
        $listing = $this->draft($user);

        foreach ([1, 2, 0, null] as $capacity) {
            $this->assertValidationError(
                $this->postJson('/api/caregiver/listings', $this->payload() + ['capacity' => $capacity]),
                'capacity',
            );
            $this->assertValidationError(
                $this->patchJson("/api/caregiver/listings/{$listing->id}", ['capacity' => $capacity]),
                'capacity',
            );
        }

        $this->assertDatabaseCount('listings', 1);
        $this->assertSame(1, $listing->fresh()->capacity);
    }

    public function test_patch_replaces_rules_and_keeps_omitted_fields(): void
    {
        $user = $this->caregiver();
        $this->actingAs($user);
        $listing = $this->draft($user);
        $rules = ['schema_version' => 1, 'species' => ['DOG'], 'sizes' => ['LARGE']];
        $url = "/api/caregiver/listings/{$listing->id}";

        $this->patchJson($url, ['acceptance_rules_json' => $rules])
            ->assertOk()->assertJsonPath('data.acceptance_rules_json.schema_version', 1)
            ->assertJsonPath('data.acceptance_rules_json.species', ['DOG'])
            ->assertJsonPath('data.acceptance_rules_json.sizes', ['LARGE'])
            ->assertJsonPath('data.title', 'Cat boarding')
            ->assertJsonPath('data.price_vnd', 150000);

        $this->patchJson($url, ['title' => 'Updated'])
            ->assertOk()->assertJsonPath('data.acceptance_rules_json.schema_version', 1)
            ->assertJsonPath('data.acceptance_rules_json.species', ['DOG'])
            ->assertJsonPath('data.acceptance_rules_json.sizes', ['LARGE']);
        $stored = $listing->fresh()->acceptance_rules_json;
        ksort($rules);
        ksort($stored);
        $this->assertSame($rules, $stored);
    }

    private function assertValidationError(TestResponse $response, string $field): void
    {
        $response->assertUnprocessable()
            ->assertJsonPath('error.code', 'VALIDATION_ERROR')
            ->assertJsonStructure([
                'error' => ['code', 'message', 'details', 'request_id'],
            ]);

        $details = $response->json('error.details');
        $this->assertArrayHasKey($field, $details);
        $this->assertNotEmpty($details[$field]);
        $this->assertTrue(Str::isUuid($response->json('error.request_id')));
    }

    private function caregiver(): User
    {
        $user = User::factory()->create();
        DB::table('user_roles')->insert([
            'user_id' => $user->id,
            'role' => 'CAREGIVER',
            'assigned_at' => now(),
        ]);
        $user->caregiverProfile()->create([
            'type' => 'INDIVIDUAL',
            'display_name' => 'Demo',
            'area_code' => 'DN_CENTER',
        ]);

        return $user;
    }

    private function draft(User $user): Listing
    {
        return $user->caregiverProfile()->firstOrFail()->listings()
            ->create($this->payload())->refresh();
    }

    private function payload(): array
    {
        return [
            'title' => 'Cat boarding',
            'description' => 'Indoor care',
            'service_type' => 'BOARDING',
            'area_code' => 'DN_CENTER',
            'price_vnd' => 150000,
            'price_unit' => 'NIGHT',
            'acceptance_rules_json' => [
                'schema_version' => 1,
                'species' => ['CAT'],
                'sizes' => ['SMALL', 'MEDIUM'],
            ],
        ];
    }
}
