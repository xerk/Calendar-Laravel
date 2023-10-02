<?php

namespace App\Console\Commands;

use App\Models\Plan;
use App\Models\Subscription;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;
use App\Mail\RecurringDowngradedMail;
use App\Notifications\GeneralNotification;
use Illuminate\Contracts\Database\Eloquent\Builder;

class CleanSubscriptions extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'saas:clean_subscriptions';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Clean expired subscriptions';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $today = now()->toDateString();
        // Get subscriptions that are active and will expire today
        $subscriptions = Subscription::where('status', 'active') // False
            ->where('expires_at', '<=', $today) // True
            ->where('failed_payment_count', '>=', 4) // False
            ->whereNull('cancelled_at') // False
            // ->orWhere(function(Builder $query) use ($today) { // False
            //     $query->where('status', 'active') // False
            //         ->where('expires_at', '<', $today) // True
            //         ->whereNotNull('cancelled_at'); // True
            // })
            ->orWhere(function(Builder $query) use ($today) { // False
                $query->where('status', 'active') // False
                    ->where('expires_at', '<=', $today) // True
                    ->where('period', 2) //yearly
                    ->where('is_yearly_auto_renew', false);
            })
            ->get();

        $this->info($subscriptions->count());

        foreach($subscriptions as $subscription) {
            $subscription->status = 'expired';

            if($subscription->save()) {
                $subscription->user->current_subscription_id = null;
                $subscription->user->plan_id = 1;
                $subscription->user->save();

                // Send notifications
                // $subscription->user->notify(new GeneralNotification('Email:20:', 'Email:20:Content'));
                Mail::to($subscription->user)->send(new RecurringDowngradedMail($subscription->user, $subscription->plan->name, Plan::find(1)->name));

                // Get Limit of subscription and turn off if user has more than limit

               
                // Free plan
                

               
                
                    // TODO: calculate from subscription usage
                        
                       
                    
                        

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

            }
        }

        return Command::SUCCESS;
    }
}
