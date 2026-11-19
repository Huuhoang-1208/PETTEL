<?php

namespace Database\Factories;

use App\Models\AvailabilitySlot;
use App\Models\Listing;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<AvailabilitySlot> */
class AvailabilitySlotFactory extends Factory
{
    public function definition(): array
    {
        $startAt = now()
            ->addDays(fake()->numberBetween(1, 14))
            ->startOfHour();

        return [
            'listing_id' => Listing::factory(),
            'start_at' => $startAt,
            'end_at' => $startAt->copy()->addHours(2),
            'created_by_user_id' => User::factory(),
        ];
    }
}
