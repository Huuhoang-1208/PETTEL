<?php

namespace Database\Factories;

use App\Models\CaregiverProfile;
use App\Models\Listing;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Listing> */
class ListingFactory extends Factory
{
    public function definition(): array
    {
        return [
            'caregiver_profile_id' => CaregiverProfile::factory(),
            'title' => fake()->sentence(4),
            'description' => fake()->paragraph(),
            'service_type' => 'DAYCARE',
            'area_code' => fake()->randomElement([
                'DN_CENTER',
                'DN_NORTH',
                'DN_SOUTH',
                'DN_WEST',
                'DN_EAST',
            ]),
            'price_vnd' => fake()->numberBetween(50000, 300000),
            'price_unit' => 'HOUR',
            'capacity' => 1,
            'acceptance_rules_json' => [
                'schema_version' => 1,
                'species' => ['DOG', 'CAT'],
                'sizes' => ['SMALL', 'MEDIUM', 'LARGE'],
            ],
            'status' => 'DRAFT',
            'published_at' => null,
            'version' => 1,
        ];
    }

    public function boarding(): static
    {
        return $this->state(fn (array $attributes): array => [
            'service_type' => 'BOARDING',
            'price_unit' => 'NIGHT',
        ]);
    }

    public function active(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => 'ACTIVE',
            'published_at' => now(),
        ]);
    }
}
