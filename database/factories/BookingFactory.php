<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Booking>
 */
class BookingFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition()
    {
        // 'user_id',
        // 'calendar_id',
        // 'date',
        // 'date_time',
        // 'start',
        // 'end',
        // 'invitee_name',
        // 'invitee_email',
        // 'invitee_phone',
        // 'invitee_note',
        // 'timezone',
        // 'location',
        // 'other_invitees',
        // 'additional_answers',
        // 'meeting_notes',
        // 'is_confirmed',
        // 'cancelled_at',
        // 'expired_at',
        // 'rescheduled_at'
        return [
            'user_id' => fake()->numberBetween(1, 10),
            'calendar_id' => fake()->numberBetween(1, 10),
            'date' => fake()->date(),
            'date_time' => fake()->dateTime(),
            'start' => fake()->time(),
            'end' => fake()->time(),
            'invitee_name' => fake()->name(),
            'invitee_email' => fake()->email(),
            'invitee_phone' => fake()->phoneNumber(),
            'invitee_note' => fake()->text(),
            'timezone' => fake()->timezone(),
            'location' => fake()->text(),
            'other_invitees' => fake()->text(),
            'additional_answers' => fake()->text(),
            'meeting_notes' => fake()->text(),
            'is_confirmed' => fake()->boolean(),
            'cancelled_at' => fake()->dateTime(),
            'expired_at' => fake()->dateTime(),
            'rescheduled_at' => fake()->dateTime(),
        ];
    }
}
