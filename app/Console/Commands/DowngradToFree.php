<?php

namespace App\Console\Commands;

use App\Models\Plan;
use Illuminate\Console\Command;
use App\Notifications\GeneralNotification;

class DowngradToFree extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'saas:downgrade';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Downgrade to free plan if the user has cancelled his subscription and the subscription is expired';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        // Get all subscriptions that are expired and cancelled
        $subscriptions = \App\Models\Subscription::where('status', 'active')
                            ->whereNotNull('cancelled_at')
                            ->whereNull('next_plan_id')
                            ->where('original_expires_at', '<', now()->toDateString())
                            ->get();

        // Count subscriptions
        $this->info($subscriptions->count());

        foreach($subscriptions as $subscription) {
            // status cancelled
            $subscription->status = 'cancelled';
            $subscription->save();

            // Downgrade to free plan
            $subscription->user->current_subscription_id = null;
            $subscription->user->plan_id = 1;
            $subscription->user->save();

            // Send notifications
            $subscription->user->notify(new GeneralNotification('Email:20:', 'Email:20:Content Downgrade to free plan'));

            // Get Limit of subscription and turn off if user has more than limit
            

            
            // Turn off user calendars
            
                
                    
 
                // TODO: calculate from subscription usage
                   
                   
            /////////////////////////////
               // Get Limit of subscription and turn off if user has more than limit
               $calendars = $subscription->user->calendars()->where('is_on', true)->get();
               $freePlan = Plan::find(1);
               // Free plan
               $calendarLimits = $freePlan->calendars;  
               if($calendars->count() > $calendarLimits && $calendarLimits != -1) {
                   $calendarsToTurnOff = $calendars->count() - $calendarLimits;
                   $calendarsToTurnOff = $calendars->take($calendarsToTurnOff);
   
                   foreach($calendarsToTurnOff as $calendar) {
                       $calendar->is_on = false;
                       $calendar->save();
                   }
               }
   
               $calendars = $subscription->user->calendars()->get();
               foreach ($calendars as $calendar) {
                   // TODO: calculate from subscription usage
                   if($calendar->bookings()->count() < $freePlan->bookings || $freePlan->bookings == -1) {
                       $calendar->is_exceed_limit = false;
                       $calendar->save();
                   } else {
                       $calendar->is_exceed_limit = true;
                       $calendar->save();
                   }
               }

            // Success message
            $this->info('User #'.$subscription->user->email . ' downgraded to free plan');
        }
        return Command::SUCCESS;
    }
}
