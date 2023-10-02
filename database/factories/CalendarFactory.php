<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Calendar>
 */
class CalendarFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition()
    {
        return [
            'user_id' => fake()->numberBetween(1, 10),
            'is_show_on_booking_page' => fake()->boolean(),
            'is_one' => fake()->boolean(),
            'name' => fake()->name(),
            'slug' => fake()->slug(),
            'welcome_message' => fake()->text(),
            'locations' => fake()->text(),
            'availability_id' => fake()->numberBetween(1, 10),
            'disable_guests' => fake()->boolean(),
            'requires_confirmation' => fake()->boolean(),
            'redirect_on_booking' => fake()->boolean(),
            'invitees_emails' => fake()->text(),
            'enable_signup_form_after_booking' => fake()->boolean(),
            'color' => fake()->colorName(),
            'cover_url' => fake()->url(),
            'time_slots_intervals' => fake()->numberBetween(1, 10),
            'duration' => fake()->numberBetween(1, 10),
            'invitees_can_schedule' => fake()->boolean(),
            'buffer_time' => fake()->numberBetween(1, 10),
            'additional_questions' => fake()->text(),
            'is_isolate' => fake()->boolean(),
            'is_exceed_limit' => fake()->boolean(),
        ];
    }
}
