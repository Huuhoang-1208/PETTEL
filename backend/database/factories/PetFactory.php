<?php

namespace Database\Factories;

use App\Models\Pet;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Pet> */
class PetFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'name' => fake()->firstName(),
            'species' => fake()->randomElement(['DOG', 'CAT', 'OTHER']),
            'birth_date' => fake()->optional()->date(),
            'age_note' => fake()->optional()->words(3, true),
            'sex' => fake()->randomElement(['MALE', 'FEMALE', 'UNKNOWN']),
            'size' => fake()->randomElement(['SMALL', 'MEDIUM', 'LARGE']),
            'diet_notes' => fake()->optional()->sentence(),
            'vaccination_note' => fake()->optional()->sentence(),
            'temperament' => fake()->optional()->sentence(),
            'special_needs' => fake()->optional()->sentence(),
            'status' => 'ACTIVE',
        ];
    }

    public function archived(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => 'ARCHIVED',
        ]);
    }
}
