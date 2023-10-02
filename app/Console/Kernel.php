<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * Define the application's command schedule.
     *
     * @param  \Illuminate\Console\Scheduling\Schedule  $schedule
     * @return void
     */
    protected function schedule(Schedule $schedule)
    {
        // $schedule->command('inspire')->hourly();
        /* || * * * * * cd /var/www/grandcalendar-api && /usr/bin/php artisan schedule:run >> /dev/null 2>&1 || */
        $schedule->command('telescope:prune --hours=48')->daily();
        $schedule->command('saas:process_recurring_payments')->everyTwoMinutes(); // 1 hour
        // $schedule->command('saas:void_payment_methods')->everyTwoHours();
        $schedule->command('expired:booking')->everyTwoMinutes();
        $schedule->command('saas:clean_subscriptions')->everyTwoMinutes(); // 1 hour
        // Daily start at 12:00 AM
        // $schedule->command('saas:downgrade')->dailyAt('00:00');
        // $schedule->command('saas:next-plan')->dailyAt('00:00');

        $schedule->command('saas:downgrade')->everyTwoMinutes();
        $schedule->command('saas:next-plan')->everyTwoMinutes();
    }

    /**
     * Register the commands for the application.
     *
     * @return void
     */
    protected function commands()
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }
}
