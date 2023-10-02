<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Plan>
 */
class PlanFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition()
    {
        return [
            'order' => fake()->numberBetween(1, 10),
            'level' => fake()->numberBetween(1, 10),
            'name' => fake()->name(),
            'currency' => fake()->currencyCode(),
            'original_monthly_price' => fake()->numberBetween(1, 10),
            'monthly_price' => fake()->numberBetween(1, 10),
            'original_yearly_price' => fake()->numberBetween(1, 10),
            'yearly_price' => fake()->numberBetween(1, 10),
            'description' => fake()->text(),
            'calendars' => fake()->numberBetween(1, 10),
            'bookings' => fake()->numberBetween(1, 10),
            'teams' => fake()->numberBetween(1, 10),
            'members' => fake()->numberBetween(1, 10),
            'country' => fake()->country(),
        ];
    }
}
