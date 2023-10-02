<?php

namespace App\Console\Commands;

use App\Models\Plan;
use GuzzleHttp\Client;
use App\Models\Transaction;
use App\Models\Subscription;
use Illuminate\Console\Command;
use App\Notifications\GeneralNotification;

class SubscribeNextPlan extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'saas:next-plan';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Change Plan for next subscription if the user has cancelled his subscription and the subscription is expired and has next plan id';

    protected $apiEndpoint = 'https://secure.paytabs.sa/payment/request';

    /**
     * Create Payment Process for the next subscription related to the current subscription has been cancelled and expired and has next plan id
     *
     * @return int
     */
    public function handle()
    {

        \Log::info('SubscribeNextPlan: Start of SubscribeNextPlan');

        $subscriptions = \App\Models\Subscription::where('status', 'active')
                            ->whereNotNull('cancelled_at')
                            ->whereNotNull('next_plan_id')
                            ->where('original_expires_at', '<', now()->toDateString())
                            ->get();

         // Count subscriptions
         $this->info($subscriptions->count());

         \Log::info('SubscribeNextPlan: Count of subscriptions: ' . $subscriptions->count());

        foreach($subscriptions as $subscription) {
            \Log::info('SubscribeNextPlan: Subscription ID: ' . $subscription->id);

            if (!$subscription->paygate_token || !$subscription->paygate_first_trans_ref) {
                \Log::error("SubscribeNextPlan: No token and trans ref");
                return;
            }

            $user = $subscription->user;
            $nextPlan = $subscription->nextPlan;
            $nextPeriod = $subscription->next_period;
            $nextPrice = $nextPeriod === 1 ? $nextPlan->monthly_price : $nextPlan->yearly_price;
            $nextPlanName = $nextPlan->name  . ' ' . ucfirst($nextPeriod === 1 ? 'monthly' : 'yearly');
            $isYearlyAutoRenew = false;
            config(['paytabs.currency' => $nextPlan->getCurrency($user)]);


            $companySettings = \DB::table('nova_settings')->where('key', 'tax')->first();
                $vatPercentage = $companySettings->value ?? 15;
                $vatAmount = $nextPrice * ($vatPercentage / 100);
                $total = $nextPrice ;
           
            $nextPrice = $nextPrice - $vatAmount;
            $nextPrice = number_format($nextPrice, 2);
            
            $vatAmount = number_format($vatAmount, 2);
            $total = number_format($total, 2);


            $nextPlan->country = $user->current_country;

            $nextPlan->currency = $nextPlan->getCurrency($user);

            // Create New Subscription
            $nextSubscription = Subscription::create([
                'user_id' => $user->id,
                'plan_id' => $nextPlan->id,
                'paygate' => 'paytabs',
                'period' => $nextPeriod,
                'plan_data' => $nextPlan,
                'is_yearly_auto_renew' => $isYearlyAutoRenew,
                'paygate_token' => $subscription->paygate_token,
                'paygate_first_trans_ref' => $subscription->paygate_first_trans_ref
            ]);

            \Log::info('SubscribeNextPlan: Next Subscription ID: ' . $nextSubscription->id);

            $subscriptionUsage = $nextSubscription->subscriptionUsages()->create([
                'subscription_id' => $nextSubscription->id,
                'date' => now()->toDateString(),
            ]);

            $nextSubscription->current_subscription_usage_id = $subscriptionUsage->id;
            $nextSubscription->save();

            // Get Limit of subscription and turn off if user has more than limit
            $user->calendars()->where('is_on', true)->get();

            // Check plan limits
            $calendarLimits = $nextPlan->calendars;
            $nextPlan = Plan::find($nextPlan->id);
            if($user->calendars->count() > $calendarLimits && $calendarLimits != -1) {
                $calendarsToTurnOff = $user->calendars->count() - $calendarLimits;
                $calendarsToTurnOff = $user->calendars->take($calendarsToTurnOff);
                \Log::info('SubscribeNextPlan: Calendars to turn off: ' . $calendarsToTurnOff->count());
                \Log::info('SubscribeNextPlan: Calendars to turn off: ' . json_encode($calendarsToTurnOff));

                foreach($calendarsToTurnOff as $calendar) {
                    \Log::info('SubscribeNextPlan: Calendar ID: ' . $calendar->id);
                    $calendar->is_on = false;
                    $calendar->save();
                }
            }


            $calendars = $user->calendars()->get();
            foreach ($calendars as $calendar) {
                // TODO: calculate from subscription usage
                if($calendar->bookings()->count() < $nextPlan->bookings || $nextPlan->bookings == -1) {
                    $calendar->is_exceed_limit = false;
                    $calendar->save();
                } else {
                    $calendar->is_exceed_limit = true;
                    $calendar->save();
                }
            }

            if($nextSubscription) {

                $transaction = Transaction::create([
                    'user_id' => $user->id,
                    'subscription_id' => $nextSubscription->id,
                    'status' => 'pending',
                    'paygate' => 'paytabs',
                    'type' => 'changed',
                    'amount' => $nextPrice,
                    'billing_address' => $user->billing_address,
                    'billing_city' => $user->billing_city,
                    'billing_region' => $user->billing_region,
                    'billing_country' => $user->billing_country,
                    'billing_zipcode' => $user->billing_zipcode,
                    'vat_amount' => $vatAmount,
                    'vat_percentage' => $vatPercentage,
                    'total' => $total,
                    'vat_country' => $user->current_country,
                ]);

                \Log::info('SubscribeNextPlan: Transaction ID: ' . $transaction->id);

                if ($transaction) {
                    $payment = $this->processPayment($transaction, $nextSubscription);
                    \Log::info('SubscribeNextPlan: Payment: ' . json_encode($payment));
                    if($payment['code'] == 200) {
                        $data = $payment['data'];
                        if(isset($data->payment_result->response_status)) {
                            $responseStatus = $data->payment_result->response_status;

                            // Check if payment success
                            $this->info($responseStatus);

                            // Store transaction status
                            $transaction->status = $responseStatus;
                            $transaction->paygate_response = $data;
                            $transaction->save();

                            \Log::info('SubscribeNextPlan: Response Status: ' . $responseStatus);
                            if($responseStatus == 'A') {
                                // Success payment
                                $nextSubscription->status = 'active';
                                // Update subscription expires date
                                if($nextSubscription->period == 1) {
                                    // Monthly
                                    $nextSubscription->expires_at = now()->addDays(30);
                                } else {
                                    // Yearly
                                    $nextSubscription->expires_at = now()->addDays(365);
                                }
                                $nextSubscription->original_expires_at = $nextSubscription->expires_at;

                                // Reset retry count
                                $nextSubscription->failed_payment_count = 0;
                                $nextSubscription->paygate_first_trans_ref = $data->tran_ref;

                                $user->current_subscription_id = $nextSubscription->id;

                                $subscription->status = 'expired';


                                $user->plan_id = $nextPlan->id;
                                $user->current_subscription_id = $nextSubscription->id;
                                $user->save();
                                $subscription->save();
                                $nextSubscription->save();

                                $calendars = $user->calendars()->get();
                                foreach ($calendars as $calendar) {
                                    // TODO: calculate from subscription usage
                                    if($calendar->bookings()->count() < $nextPlan->bookings || $nextPlan->bookings == -1) {
                                        $calendar->is_exceed_limit = false;
                                        $calendar->save();
                                    } else {
                                        $calendar->is_exceed_limit = true;
                                        $calendar->save();
                                    }
                                }

                                // Send notifications
                                if($nextSubscription->period == 1) {
                                    // Monthly
                                    // Send Email:8:
                                    $nextSubscription->user->notify(new GeneralNotification('Email:8:', 'Email:8:Content'));
                                } else {
                                    // Yearly
                                    // Send Email:15:
                                    $nextSubscription->user->notify(new GeneralNotification('Email:15:', 'Email:15:Content'));
                                }

                                \Log::info('SubscribeNextPlan: Success Payment');
                            } else {
                                // Failed payment
                                $nextSubscription->status = 'failed';
                                $nextSubscription->failed_payment_count = $nextSubscription->failed_payment_count + 1;
                                $nextSubscription->save();

                                $subscription->status = 'expired';
                                $subscription->save();

                                $user->current_subscription_id = null;
                                $user->plan_id = 1;
                                $user->save();

                                $freePlan = Plan::find(1);

                                // Turn off user calendars
                                $calendarLimits = $freePlan->calendars;

                                if($user->calendars->count() > $calendarLimits) {
                                    $calendarsToTurnOff = $user->calendars->count() - $calendarLimits;
                                    $calendarsToTurnOff = $user->calendars->take($calendarsToTurnOff);
                                    \Log::info('SubscribeNextPlan: free Calendars to turn off: ' . $calendarsToTurnOff->count());
                                    \Log::info('SubscribeNextPlan: free Calendars to turn off: ' . json_encode($calendarsToTurnOff));

                                    foreach($calendarsToTurnOff as $calendar) {
                                        $calendar->is_on = false;
                                        $calendar->save();
                                    }
                                }

                                $calendars = $nextSubscription->user->calendars()->get();
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

                                // Send notifications
                                // Send Email:9:
                                $nextSubscription->user->notify(new GeneralNotification('Email:9:', 'Email:9:Content'));

                                \Log::info('SubscribeNextPlan: Failed Payment');
                            }
                        }
                    }
                }
            }

        }

        \Log::info('SubscribeNextPlan: Finish Change Plan Cron');

        return Command::SUCCESS;
    }

    function processPayment(Transaction $transaction, Subscription $subscription)
    {

        $client = new Client(['http_errors' => false]);

        $headers = [
            'Authorization' => config('paytabs.server_key'),
            'Content-Type' => 'application/json',
        ];
        $body = [
            "profile_id" => config('paytabs.profile_id'),
            "tran_type" => "sale",
            "tran_class" => "recurring",
            "cart_id" => $transaction->id,
            "cart_currency" => $subscription->plan_data['currency'],
            "cart_amount" => $transaction->total,
            "cart_description" => "Change plan for next subscription {$subscription->getSubscriptionNumber()}",
            "token" => $subscription->paygate_token,
            "tran_ref" => $subscription->paygate_first_trans_ref,
        ];

        \Log::info('SubscribeNextPlan: ' . json_encode($body));

        $response = $client->request('POST', $this->apiEndpoint, [
            'headers' => $headers,
            'body' => json_encode($body),
        ]);

        return [
            'code' => $response->getStatusCode(),
            'data' => $response->getBody()? json_decode($response->getBody()) : null,
        ];
    }
}
