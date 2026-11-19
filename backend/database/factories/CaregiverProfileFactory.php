<?php

namespace Database\Factories;

use App\Models\CaregiverProfile;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<CaregiverProfile> */
class CaregiverProfileFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'type' => fake()->randomElement(['INDIVIDUAL', 'FACILITY']),
            'display_name' => fake()->company(),
            'bio' => fake()->paragraph(),
            'area_code' => fake()->randomElement([
                'DN_CENTER',
                'DN_NORTH',
                'DN_SOUTH',
                'DN_WEST',
                'DN_EAST',
            ]),
        ];
    }
}
