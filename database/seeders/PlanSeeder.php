<?php

namespace Database\Seeders;

use App\Models\Plan;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class PlanSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        Plan::create([
            'order' => 1,
            'name' => 'Freemium',
            'description' => "One Active Calendar\n20 Calendar Bookings / Month",
        ]);

        Plan::create([
            'order' => 2,
            'level' => 2,
            'name' => 'Promium',
            'currency' => 'USD',
            'original_monthly_price' => 3.50,
            'monthly_price' => 1.75,
            'original_yearly_price' => 1,
            'yearly_price' => 4,
            'description' => "Up to 3 Active Calendars\n50 Calendar Bookings / Month\nUp to 2 Team Members\nUp to 15 people on the same room meeting (if GrandCalendar meeting app used)",
        ]);

        Plan::create([
            'order' => 3,
            'level' => 3,
            'name' => 'Ultimium',
            'currency' => 'USD',
            'original_monthly_price' => 3,
            'monthly_price' => 1.50,
            'original_yearly_price' => 2.5,
            'yearly_price' => 1.25,
            'description' => "Unlimited Active Calendars\nUnlimited Calendar Bookings / Month\nUnlimited Team Members\nUp to 40 people on the same room meeting (if GrandCalendar meeting app used)",
        ]);
    }
}
