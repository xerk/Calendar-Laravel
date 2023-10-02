<?php

namespace App\Http\Controllers;

use Carbon\Carbon;
use App\Models\Plan;
use App\Models\User;
use GuzzleHttp\Client;
use App\Models\Transaction;
use App\Models\Subscription;
use Illuminate\Http\Request;
use App\Models\PaymentMethod;
use App\Mail\PackageSubscribeMail;
use Illuminate\Support\Facades\Mail;
use App\Mail\PackageSubscribeFailedMail;
use App\Notifications\GeneralNotification;
use App\Http\Resources\SubscriptionResource;
use App\Mail\SubscriptionCancellationMail;
use Paytabscom\Laravel_paytabs\Facades\paypage;

class SubscriptionController extends Controller
{
    // Not API
    public function paygateReturn(Request $request) {
        if($request->cartId) {
            $transaction = Transaction::findOrFail($request->cartId);
            $transaction->status = $request->respStatus;
            $transaction->paygate_response = $request->all();
            $transaction->save();

            $transaction->subscription->paygate_token = $request->token;
            $transaction->subscription->paygate_first_trans_ref = $request->tranRef;

            if($transaction->status === 'A') {
                $transaction->subscription->status = 'active';
                if($transaction->subscription->period == 1) {
                    // Monthly 30 days
                    $expiredAt = now()->addDays(30);
                } else {
                    // Yearly 365 days
                    $expiredAt = now()->addDays(365);
                }
                $transaction->subscription->expires_at = $expiredAt;
                $transaction->subscription->original_expires_at = $expiredAt;

                $transaction->subscription->user->plan_id = $transaction->subscription->plan_id;

                $oldSubscription = null;
                if($transaction->subscription->user->current_subscription_id) {
                    $oldSubscription = Subscription::find($transaction->subscription->user->current_subscription_id);
                }
                $transaction->subscription->user->current_subscription_id = $transaction->subscription->id;
                $transaction->subscription->user->save();

                // Cancel old subscription
                if($oldSubscription) {
                    $oldSubscription->status = 'cancelled';
                    $oldSubscription->cancelled_at = now();
                    $oldSubscription->save();
                }

                // Send Email:3:
                Mail::to($transaction->subscription->user->email)->send(new PackageSubscribeMail(
                    $transaction->subscription->user,
                    ($transaction->subscription->period == 1 ? 'Monthly' : 'Yearly'),
                    $transaction->subscription->plan_data['name'],
                    $transaction->subscription->plan_data['description'],
                ));
            } else {
                $transaction->subscription->status = 'failed';

                // Send Email:4:
                Mail::to($transaction->subscription->user->email)->send(new PackageSubscribeFailedMail(
                    $transaction->subscription->user,
                    $transaction->subscription->plan_data['name'],
                    $request->respMessage,
                ));
            }

            $transaction->subscription->save();
        }
        $status = $request->respStatus ?: 'error';
        $message = $request->respMessage ?: 'Something went wrong, please try again later.';

        //return $request->all();

        // return view('message', [
        //     'request' => $request,
        // ]);

        return redirect( config('saas.app_url') . '/dashboard/plans?status='.$status.'&message='.$message );
    }

    public function getToken($token)
    {
        $apiEndpoint = 'https://secure.paytabs.sa/payment/token';
        $client = new Client(['http_errors' => false]);

        $headers = [
            'Authorization' => config('paytabs.server_key'),
            'Content-Type' => 'application/json',
        ];

        $body = [
            "profile_id" => config('paytabs.profile_id'),
            "token" => $token,
        ];

        $response = $client->request('POST', $apiEndpoint, [
            'headers' => $headers,
            'body' => json_encode($body),
        ]);

        return [
            'code' => $response->getStatusCode(),
            'data' => $response->getBody()? json_decode($response->getBody()) : null,
        ];
    }

    public function subscribe(Request $request, $userId, $planId)
    {

        $plan = Plan::findOrFail($planId);
        $user = User::findOrFail($userId);
        $period = $request->period === 'monthly' ? 1 : 2;
        $isYearlyAutoRenew = $request->is_yearly_auto_renew === 'no' ? false : true;
        $planPrice = $request->period === 'monthly' ? $plan->monthly_price : $plan->yearly_price;
        $planName = $plan->name . ' ' . ucfirst($request->period);
        config(['paytabs.currency' => $plan->getCurrency($user)]);
        $proratingData = $this->proratingData($period, $user, $plan);
        $companySettings = \DB::table('nova_settings')->where('key', 'tax')->first();
        $vatPercentage = $companySettings->value ?? 15;
        $vatAmount = $planPrice * ($vatPercentage / 100);
        $total = $planPrice;
        $planPrice = $planPrice - $vatAmount;

        if($proratingData  !== null || $proratingData === 0) {
            $vatAmount = $proratingData * ($vatPercentage / 100);
            $planPrice = $proratingData - $vatAmount;
            $total = $proratingData;
        }



        // Nubmber format
        $planPrice = number_format($planPrice, 2);
        $vatAmount = number_format($vatAmount, 2);
        $total = number_format($total, 2);

        // append to plan current user country
        $plan->country = $user->current_country;

        $plan->currency = $plan->getCurrency($user);

        $subscription = Subscription::create([
            'user_id' => $user->id,
            'plan_id' => $plan->id,
            'paygate' => 'paytabs',
            'period' => $period,
            'plan_data' => $plan,
            'is_yearly_auto_renew' => $isYearlyAutoRenew,
        ]);

        $subscriptionUsage = $subscription->subscriptionUsages()->create([
            'subscription_id' => $subscription->id,
            'date' => now()->toDateString(),
        ]);

        $subscription->current_subscription_usage_id = $subscriptionUsage->id;
        $subscription->save();

        // Get Limit of subscription and turn off if user has more than limit
        $user->calendars()->where('is_on', true)->get();

        // Check plan limits
        $calendarLimits = $plan->calendars;

        if($user->calendars->count() > $calendarLimits && $calendarLimits != -1) {
            $calendarsToTurnOff = $user->calendars->count() - $calendarLimits;
            $calendarsToTurnOff = $user->calendars->take($calendarsToTurnOff);

            foreach($calendarsToTurnOff as $calendar) {
                $calendar->is_on = false;
                $calendar->save();
            }
        }

        if($subscription) {
            // Remove is_exceed_limit for calendars if the plan has more than limited bookings in the calendar
            $calendars = $user->calendars()->get();
            foreach ($calendars as $calendar) {
                // TODO: calculate from subscription usage
                if($calendar->bookings()->count() < $plan->bookings || $plan->bookings == -1) {
                    $calendar->is_exceed_limit = false;
                    $calendar->save();
                } else {
                    $calendar->is_exceed_limit = true;
                    $calendar->save();
                }
            }
            if ($total == 0 || $request->auto_upgrade) {
                $subscription->status = 'active';

                if ($period == 1) {
                    // Monthly
                    $expiredAt = now()->addDays(30);
                } else {
                    // Yearly
                    $expiredAt = now()->addDays(365);
                }

                $subscription->expires_at = $expiredAt;
                $subscription->original_expires_at = $expiredAt;
                $subscription->plan_id = $plan->id;
                $subscription->save();

                $oldSubscription = null;
                if($subscription->user->current_subscription_id) {
                    $oldSubscription = Subscription::find($subscription->user->current_subscription_id);
                }

                // Cancel old subscription
                if($oldSubscription) {
                    $oldSubscription->status = 'cancelled';
                    $oldSubscription->cancelled_at = now();
                    $oldSubscription->save();
                    // replicate the old subscription to new subscription transaction and don't change created_at date
                    $lastTransaction = $oldSubscription->lastTransaction;
                    // Create new transaction to new subscription from oldtransaction
                    $lastTransaction->replicate([
                        'nid',
                        'created_at',
                    ])->fill([
                        'subscription_id' => $subscription->id,
                    ])->save();
                }

                $user->plan_id = $plan->id;
                $user->current_subscription_id = $subscription->id;
                $user->save();

                // Send Email:3
                Mail::to($user->email)->send(new PackageSubscribeMail(
                    $user,
                    ($period == 1 ? 'Monthly' : 'Yearly'),
                    $plan->name,
                    $plan->description,
                ));

                return redirect( config('saas.app_url') . '/dashboard/plans?status=A&message=Your subscription has been renewed successfully.' );
            }

            $transaction = Transaction::create([
                'user_id' => $user->id,
                'subscription_id' => $subscription->id,
                'status' => 'pending',
                'paygate' => 'paytabs',
                'amount' => $planPrice,
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

            if($transaction) {
                $pay= paypage::sendPaymentCode('all')
                    ->sendTransaction('sale')
                    ->sendCart($transaction->id, $total, $planName) // Cart ID, Cart Amount, Cart Description
                    ->sendCustomerDetails('', $user->email, '', $user->billing_address, $user->billing_city, $user->billing_region, $user->billing_country, $user->billing_zipcode,'')
                    ->sendHideShipping(true)
                    ->sendTokinse(true)
                    ->sendURLs(
                        // env production url or local url
                        config('app.env') == 'production' ? route('subscriptions.paygateReturn') : route('subscriptions.paygateReturn') , 'https://eoe7hlbuxdvibew.m.pipedream.net')
                    ->sendLanguage($user->display_language)
                    ->create_pay_page();
                return $pay;
            }
        }

        return redirect( config('saas.app_url') . '/dashboard/plans' );
    }

    public function cancel($subscriptionId) {
        $subscription = Subscription::findOrFail($subscriptionId);

        $subscription->cancelled_at = now();
        $subscription->failed_payment_count = 0;
        $subscription->next_plan_id = null;
        $subscription->next_period = 0;

        $transaction = Transaction::where('subscription_id', $subscription->id)->orderBy('id', 'desc')->first();
        $diffInHours = now()->diffInHours($transaction->created_at);

        if($diffInHours < $subscription->plan_data['auto_refund_before_hours'] && $transaction->status === 'A') {
            $subscription->status = 'cancelled';

            // Move user to free
            $subscription->user->current_subscription_id = null;
            $subscription->user->plan_id = 1;

            // Refund
            $response = $this->paytabsRefund($transaction);
            if($response['code'] == 200) {
                if(isset($response['data']->payment_result->response_code)) {
                    if($response['data']->payment_result->response_status == 'A') {
                        $transaction->refunded_at = now();
                        $transaction->save();
                        // replicate transaction and change status to refunded
                        $transaction->replicate([
                            'nid'
                        ])->fill([
                            'type' => 'refund',
                            'paygate_response' => $response['data'],
                            'status' => 'auto_refunded',
                            'refunded_at' => now(),
                            'refunded_by' => $transaction->user_id,
                            'refund_reason' => "User cancelled subscription before {$subscription->plan_data['auto_refund_before_hours']} hours.",
                        ])->save();
                    }
                }
            }

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
        }


        if($subscription->save()) {
            if($subscription->user->save()) {
                // Send Email:21: with queue
                $subscription->user->notify(new GeneralNotification('Email:21:', 'Email:21:Content'));
                Mail::to($subscription->user)->send(new SubscriptionCancellationMail(
                    $subscription->user,
                    $subscription->plan_data['name'],
                    Plan::find(1)->name,
                ));
            }
        }

        return response()->json([
            'status' => 'success',
            'token' => 'Subscription cancelled, you will still have access until the end of the current billing period.'
        ]);
    }

    public function paytabsRefund(Transaction $transaction, $refundReason = 'Auto refund')
    {
        $apiEndpoint = 'https://secure.paytabs.sa/payment/request';

        $amount = $transaction->subscription->period === 1 ? $transaction->subscription->plan_data['monthly_price'] : $transaction->subscription->plan_data['yearly_price'];
        if($transaction->type == 'first_payment') {
            $transRef = $transaction->paygate_response['tranRef'];
            $cartId = $transaction->paygate_response['cartId'];
        }
        if($transaction->type == 'recurring') {
            $transRef = $transaction->paygate_response['tran_ref'];
            $cartId = $transaction->paygate_response['cart_id'];
        }

        if($transaction->type == 'changed') {
            $transRef = $transaction->paygate_response['tran_ref'];
            $cartId = $transaction->paygate_response['cart_id'];
        }

        $client = new Client(['http_errors' => false]);

        $headers = [
            'Authorization' => config('paytabs.server_key'),
            'Content-Type' => 'application/json',
        ];

        $body = [
            "profile_id" => config('paytabs.profile_id'),
            "tran_type" => "refund",
            "tran_class" => "ecom",
            "cart_id" => $cartId,
            "cart_currency" => $transaction->subscription->plan_data['currency'],
            "cart_amount" => $amount,
            "cart_description" => $refundReason,
            "tran_ref" => $transRef,
        ];

        $response = $client->request('POST', $apiEndpoint, [
            'headers' => $headers,
            'body' => json_encode($body),
        ]);

        return [
            'code' => $response->getStatusCode(),
            'data' => $response->getBody()? json_decode($response->getBody()) : null,
        ];
    }

    public function processRecurringPayment() {
        $user = auth()->user();
        $subscription = $user->currentSubscription;

        if($subscription) {
            if($subscription->period == 1 || ($subscription->period == 2 && $subscription->is_yearly_auto_renew)) {
                // Get plan price
                $planPrice = $subscription->period === 1 ? $subscription->plan_data['monthly_price'] : $subscription->plan_data['yearly_price'];

                $companySettings = \DB::table('nova_settings')->where('key', 'tax')->first();
                $vatPercentage = $companySettings->value ?? 15;
                $vatAmount = $planPrice * ($vatPercentage / 100);
                $planPrice = $planPrice - $vatAmount;
                $total = $planPrice + $vatAmount;

                // Create new recurring transaction
                $transaction = Transaction::create([
                    'user_id' => $subscription->user_id,
                    'subscription_id' => $subscription->id,
                    'status' => 'pending',
                    'paygate' => 'paytabs',
                    'amount' => $planPrice,
                    'type' => 'recurring',
                    'billing_address' => $subscription->user->billing_address,
                    'billing_city' => $subscription->user->billing_city,
                    'billing_region' => $subscription->user->billing_region,
                    'billing_country' => $subscription->user->billing_country,
                    'billing_zipcode' => $subscription->user->billing_zipcode,
                    'vat_amount' => $vatAmount,
                    'vat_percentage' => $vatPercentage,
                    'total' => $total,
                    'vat_country' => $user->current_country,
                ]);

                // return response()->json([
                //     'status' => 'success',
                //     'message' => $transaction->amount,
                // ]);

                $payment = $this->processPayment($transaction, $subscription);

                if($payment['code'] == 200) {
                    $data = $payment['data'];
                    if(isset($data->payment_result->response_status)) {
                        $responseStatus = $data->payment_result->response_status;
                        //$responseStatus = 'C'; // Test fail payment

                        // Store transaction status
                        $transaction->status = $responseStatus;
                        $transaction->paygate_response = $data;

                        if($responseStatus == 'A') {
                            // Success payment

                            // Update subscription expires date
                            if($subscription->period == 1) {
                                // Monthly
                                $subscription->expires_at = now()->addDays(30);
                            } else {
                                // Yearly
                                $subscription->expires_at = now()->addDays(365);
                            }
                            $subscription->original_expires_at = $subscription->expires_at;

                            // Reset retry count
                            $subscription->failed_payment_count = 0;

                            // Send notifications
                            if($subscription->period == 1) {
                                // Monthly
                                // Send Email:8:
                                $subscription->user->notify(new GeneralNotification('Email:8:', 'Email:8:Content'));
                            } else {
                                // Yearly
                                // Send Email:15:
                                $subscription->user->notify(new GeneralNotification('Email:15:', 'Email:15:Content'));
                            }

                            // create new subscription with new usage and date
                            $subscriptionUsage = $subscription->subscriptionUsages()->create([
                                'subscription_id' => $subscription->id,
                                'date' => now()->toDateString(),
                            ]);

                            $subscription->current_subscription_usage_id = $subscriptionUsage->id;

                            $subscription->save();
                            $transaction->save();

                            return response()->json([
                                'status' => 'success',
                                'message' => 'Your subscription has been renewed successfully.',
                            ]);

                        } else {
                            // Fail payment
                            return response()->json([
                                'status' => 'error',
                                'message' => $data->payment_result->response_message,
                            ]);
                        }
                    }
                } else {
                    $data = $payment['data'];
                    $code = isset($payment['code']) ? $payment['code'] : 'N/A';
                    $message = isset($data->message) ? $data->message : 'N/A';
                    $notificationMessage = "PayTabs API: Failed recurring payment (Code {$code}: {$message})";

                    return response()->json([
                        'status' => 'error',
                        'message' => $notificationMessage,
                    ]);
                }
            }
        }

        return response()->json([
            'status' => 'error',
            'message' => 'No subscription found.',
        ]);
    }

    public function processPayment(Transaction $transaction, Subscription $subscription)
    {
        $apiEndpoint = 'https://secure.paytabs.sa/payment/request';

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
            "cart_description" => "Recurring payment for subscription {$subscription->getSubscriptionNumber()}",
            "token" => $subscription->paygate_token,
            "tran_ref" => $subscription->paygate_first_trans_ref,
        ];

        $response = $client->request('POST', $apiEndpoint, [
            'headers' => $headers,
            'body' => json_encode($body),
        ]);

        return [
            'code' => $response->getStatusCode(),
            'data' => $response->getBody()? json_decode($response->getBody()) : null,
        ];
    }

    public function show($id) {
        $subscription = Subscription::findOrFail($id);

        return new SubscriptionResource($subscription);
    }

    private function proratingData($period, User $user, Plan $plan) {
        if (!$user) {
            return null;
        }

        $subscription = $user->currentSubscription;
        if (!$subscription) {
            return null;
        }

        $subscriptionPeriod = $subscription->period;
        $currentPeriod = $period;
        $subscriptionOriginalExpiresAt = Carbon::parse($subscription->original_expires_at);

        if ($plan->monthly_price == 0) {
            return null;
        }

        if ($subscriptionPeriod == 1) {
            $subscriptionPrice = $subscription->plan_data['monthly_price'];
        }

        if ($subscriptionPeriod == 2) {
            $subscriptionPrice = $subscription->plan_data['yearly_price'];
        }

        $days = $subscriptionPeriod == 1 ? 30 : 365;

        // Calculate the remaining days and consider the subscription period (monthly or yearly) of days
        $subscriptionRemainingDays = $subscriptionOriginalExpiresAt->diffInDays(now()->startOfDay());
        $subscriptionRemainingDays = $subscriptionRemainingDays === 0 ? 1 : $subscriptionRemainingDays;

        // Calculate the remaining amount
        $subscriptionRemainingAmount = $subscriptionPrice / $days * $subscriptionRemainingDays;
        $subscriptionRemainingAmount = $subscriptionRemainingAmount === 0 ? $subscriptionPrice : $subscriptionRemainingAmount;


        $planPriceMonthly = $plan->monthly_price;
        $planPriceYearly = $plan->yearly_price;



        // Calculate the new plan price if the user needs to switch to this plan
        if ($subscriptionPeriod == 1) {
            $planPriceMonthly = $planPriceMonthly / $days * $subscriptionRemainingDays;
        }
        if ($subscriptionPeriod == 2) {
            $planPriceYearly = $planPriceYearly / $days * $subscriptionRemainingDays;
        }

        // dd($planPriceMonthly, $planPriceYearly, $subscriptionRemainingAmount, $subscriptionRemainingDays);

        $newPriceMonthly = $planPriceMonthly - $subscriptionRemainingAmount;
        $newPriceYearly = $planPriceYearly - $subscriptionRemainingAmount;

        $allowM = true;
        $allowY = true;

        // If the remaining subscription amount is more than the new plan price, add more days to the switch subscription
        if ($newPriceMonthly < 0) {
            $allowM = false;
        }

        if ($newPriceYearly < 0) {
            $allowY = false;
        }

        $newPriceMonthly = (float)number_format($newPriceMonthly, 2);
        $newPriceYearly = (float)number_format($newPriceYearly, 2);
        $subscriptionRemainingAmount = (float)number_format($subscriptionRemainingAmount, 2);

        if ($currentPeriod == 1 && $allowM == true) {
            return $newPriceMonthly;
        }

        if ($currentPeriod == 2 && $allowY == true) {
            return $newPriceYearly;
        }

        return null;
    }

    public function cancelAndUpgrade(Request $request, $subscriptionId) {
        $subscription = Subscription::findOrFail($subscriptionId);
        $plan = Plan::findOrFail($request->plan_id);


        $subscription->cancelled_at = now();
        $subscription->failed_payment_count = 0;
        $subscription->next_plan_id = $plan->id;
        $subscription->next_period = $request->period === 'monthly' ? 1 : 2;

        // $transaction = Transaction::where('subscription_id', $subscription->id)->orderBy('id', 'desc')->first();
        // $diffInHours = now()->diffInHours($transaction->created_at);

        // if($diffInHours < $subscription->plan_data['auto_refund_before_hours'] && $transaction->status === 'A') {
        //     $subscription->status = 'cancelled';

        //     // Refund
        //     $response = $this->paytabsRefund($transaction);
        //     if($response['code'] == 200) {
        //         if(isset($response['data']->payment_result->response_code)) {
        //             if($response['data']->payment_result->response_status == 'A') {
        //                 $transaction->refunded_at = now();
        //                 $transaction->save();
        //                 // replicate transaction and change status to refunded
        //                 $transaction->replicate([
        //                     'nid'
        //                 ])->fill([
        //                     'type' => 'refund',
        //                     'paygate_response' => $response['data'],
        //                     'status' => 'auto_refunded',
        //                     'refunded_at' => now(),
        //                     'refunded_by' => $transaction->user_id,
        //                     'refund_reason' => "User cancelled subscription before {$subscription->plan_data['auto_refund_before_hours']} hours.",
        //                 ])->save();
        //             }
        //         }
        //     }

        //     // Get Limit of subscription and turn off if user has more than limit
        //     $calendars = $subscription->user->calendars()->where('is_on', true)->get();

        //     // Free plan
        //     $calendarLimits = Plan::find($plan->id)->calendars;

        //     if($calendars->count() > $calendarLimits && $calendarLimits != -1) {
        //         $calendarsToTurnOff = $calendars->count() - $calendarLimits;
        //         $calendarsToTurnOff = $calendars->take($calendarsToTurnOff);

        //         foreach($calendarsToTurnOff as $calendar) {
        //             $calendar->is_on = false;
        //             $calendar->save();
        //         }
        //     }

        //     $this->subscribe($request, $subscription->user_id, $plan->id);
        // }


        if($subscription->save()) {
            if($subscription->user->save()) {
                // Send Email:21: with queue
                $subscription->user->notify(new GeneralNotification('Email:21:', 'Email:21:Content'));
            }
        }

        return response()->json([
            'status' => 'success',
            'token' => 'Subscription cancelled, you will still have access until the end of the current billing period.'
        ]);
    }

    /**
     * Resume cancelled subscription (if the subscription is cancelled and the user wants to resume it)
     */
    public function resume(Request $request, $subscriptionId) {
        $subscription = Subscription::findOrFail($subscriptionId);

        if($subscription->cancelled_at) {
            $subscription->status = 'active';
            $subscription->cancelled_at = null;
            $subscription->failed_payment_count = 0;
            $subscription->next_plan_id = null;
            $subscription->next_period = 0;
            $subscription->save();

            // Send Email:3:
            Mail::to($subscription->user->email)->send(new PackageSubscribeMail(
                $subscription->user,
                ($subscription->period == 1 ? 'Monthly' : 'Yearly'),
                $subscription->plan_data['name'],
                $subscription->plan_data['description'],
            ));

            return response()->json([
                'status' => 'success',
                'message' => 'Subscription resumed successfully.',
            ]);
        }

        return response()->json([
            'status' => 'error',
            'message' => 'Subscription is not cancelled.',
        ]);
    }
}
