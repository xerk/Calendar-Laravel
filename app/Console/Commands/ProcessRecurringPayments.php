<?php

namespace App\Console\Commands;

use App\Mail\RecurringAutoSuccessMail;
use App\Mail\RecurringFailMail;
use GuzzleHttp\Client;
use App\Models\Transaction;
use App\Models\Subscription;
use Illuminate\Console\Command;
use App\Mail\RecurringSuccessMail;
use Illuminate\Support\Facades\Mail;
use App\Notifications\GeneralNotification;

class ProcessRecurringPayments extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'saas:process_recurring_payments';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Find today recurring payments and process them';

    protected $apiEndpoint = 'https://secure.paytabs.sa/payment/request';

    /**
     * Execute the console command.
     * https://support.paytabs.com/en/support/solutions/articles/60000709842-3-3-4-pt2-api-endpoints-token-based-transactions-recurring-payments
     * You will need to follow the following steps to get your customer token:
     * - Create a sale/Auth transaction with the first transaction occurrence to tokenize the customer card.
     * - Store your token along with the returned tran_ref for further usage.
     * - For further transactions, just create a normal transaction and pass the tokenization information, and it will be captured directly from the customer.
     *
     * @return int
     */
    public function handle()
    {
        $today = now()->toDateString();
        //$today = '2023-04-14';
        // Get subscriptions that are active and will expire today
        $subscriptions = Subscription::whereNull('cancelled_at')
            ->where('status', 'active')
            ->where('expires_at', '<', $today)
            ->where('failed_payment_count', '<', 4)
            ->get();

        $this->info($subscriptions->count());

        foreach($subscriptions as $subscription) {
            if($subscription->period == 1 || ($subscription->period == 2 && $subscription->is_yearly_auto_renew)) {
                //$this->info($subscription->paygate_token);

                // Get plan price
                $planPrice = $subscription->period === 1 ? $subscription->plan_data['monthly_price'] : $subscription->plan_data['yearly_price'];
                //$planName = $subscription->plan_data['name'] . ' ' . $subscription->period === 1 ? 'Monthly' : 'Yearly';

                $vatPercentage = 15;
                $vatAmount = $planPrice * ($vatPercentage / 100);
                $total = $planPrice;

                // number format
                $planPrice = number_format($planPrice, 2);
                $vatAmount = number_format($vatAmount, 2);
                $total = number_format($total, 2);
                $planPrice = $planPrice - $vatAmount;

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
                    'vat_country' => $subscription->user->current_country,
                ]);

                $payment = $this->processPayment($transaction, $subscription);

                //$this->info($payment['code']);
                if($payment['code'] == 200) {
                    $data = $payment['data'];
                    if(isset($data->payment_result->response_status)) {
                        $responseStatus = $data->payment_result->response_status;
                        //$responseStatus = 'C'; // Test fail payment

                        // Check if payment success
                        $this->info($responseStatus);

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
                                Mail::to($subscription->user)->send(new RecurringSuccessMail($subscription->user, $subscription->plan));
                            } else {
                                // Yearly
                                // Send Email:15:
                                Mail::to($subscription->user)->send(new RecurringAutoSuccessMail($subscription->user, $subscription->plan));
                            }

                        } else {
                            // Fail payment
                            $currentFailedPaymentCount = $subscription->failed_payment_count;

                            // First fail in the expires day
                            if($currentFailedPaymentCount == 0) {
                                $subscription->original_expires_at = $subscription->expires_at;
                                $subscription->expires_at = now()->addDays(3)->toDateString();
                                $subscription->failed_payment_count = $subscription->failed_payment_count + 1; // 1

                                // Send notifications
                                // if($subscription->period == 1) {
                                    // Monthly
                                    // Send Email:9:
                                    $transaction->status = $data->payment_result->response_message;
                                    Mail::to($subscription->user)->send(new RecurringFailMail($subscription->user, $subscription->plan, $data->payment_result->response_message, now()->addDays(3)->toDateString(), $subscription->period));
                                // } else {
                                    // Yearly
                                    // Send Email:16:
                                    // $subscription->user->notify(new GeneralNotification('Email:16:', 'Email:16:Content. Reason:'.$transaction->status = $data->payment_result->response_message));
                                // }
                            }

                            // Retry after 3 days
                            if($currentFailedPaymentCount == 1) {
                                $subscription->expires_at = now()->addDays(4)->toDateString();
                                $subscription->failed_payment_count = $subscription->failed_payment_count + 1; // 2

                                // Send notifications
                                // if($subscription->period == 1) {
                                    // Monthly
                                    // Send Email:10:

                                    Mail::to($subscription->user)->send(new RecurringFailMail($subscription->user, $subscription->plan, $data->payment_result->response_message, now()->addDays(4)->toDateString(), $subscription->period));
                                    // $subscription->user->notify(new GeneralNotification('Email:10:', 'Email:10:Content. Reason:'.$transaction->status = $data->payment_result->response_message));
                                // } else {
                                //     // Yearly
                                //     // Send Email:17:
                                //     $subscription->user->notify(new GeneralNotification('Email:17:', 'Email:17:Content. Reason:'.$transaction->status = $data->payment_result->response_message));
                                // }
                            }

                            // Retry after 7 days
                            if($currentFailedPaymentCount == 2) {
                                $subscription->expires_at = now()->addDays(4)->toDateString();
                                $subscription->failed_payment_count = $subscription->failed_payment_count + 1; // 3

                                // Send notifications
                                // if($subscription->period == 1) {
                                    // Monthly
                                    // Send Email:11:
                                    Mail::to($subscription->user)->send(new RecurringFailMail($subscription->user, $subscription->plan, $data->payment_result->response_message, now()->addDays(4)->toDateString(), $subscription->period));
                                    // $subscription->user->notify(new GeneralNotification('Email:11:', 'Email:11:Content. Reason:'.$transaction->status = $data->payment_result->response_message));
                                // } else {
                                //     // Yearly
                                //     // Send Email:18:
                                //     $subscription->user->notify(new GeneralNotification('Email:18:', 'Email:18:Content. Reason:'.$transaction->status = $data->payment_result->response_message));
                                // }
                            }

                            // Retry after 10 days
                            if($currentFailedPaymentCount == 3) {
                                $subscription->failed_payment_count = $subscription->failed_payment_count + 1; // 4

                                // Send notifications
                                // if($subscription->period == 1) {
                                    // Monthly
                                    // Send Email:12:
                                    Mail::to($subscription->user)->send(new RecurringFailMail($subscription->user, $subscription->plan, $data->payment_result->response_message, now()->toDateString(), $subscription->period));
                                    // $subscription->user->notify(new GeneralNotification('Email:12:', 'Email:12:Content. Reason:'.$transaction->status = $data->payment_result->response_message));
                                // } else {
                                //     // Yearly
                                //     // Send Email:19:
                                //     $subscription->user->notify(new GeneralNotification('Email:19:', 'Email:19:Content. Reason:'.$transaction->status = $data->payment_result->response_message));
                                // }
                            }
                        }

                        $subscription->save();
                        $transaction->save();
                    }
                } else {
                    $data = $payment['data'];
                    $code = isset($payment['code']) ? $payment['code'] : 'N/A';
                    $message = isset($data->message) ? $data->message : 'N/A';
                    $subscriptionId = $subscription->id;
                    \Log::error("PayTabs API: Failed recurring payment Subscription # {$subscriptionId} (Code {$code}: {$message})", $payment);
                    $notificationMessage = "PayTabs API: Failed recurring payment Subscription # {$subscriptionId} (Code {$code}: {$message})";
                    $sendNotification = $this->sendAdminNotification($notificationMessage);
                }
            }
        }

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
            "cart_description" => "Recurring payment for subscription {$subscription->getSubscriptionNumber()}",
            "token" => $subscription->paygate_token,
            "tran_ref" => $subscription->paygate_first_trans_ref,
        ];

        $response = $client->request('POST', $this->apiEndpoint, [
            'headers' => $headers,
            'body' => json_encode($body),
        ]);

        return [
            'code' => $response->getStatusCode(),
            'data' => $response->getBody()? json_decode($response->getBody()) : null,
        ];
    }

    function sendAdminNotification($message)
    {

        $client = new Client(['http_errors' => false]);

        $body = [
            "message" => $message,
        ];

        $response = $client->request('POST', config('saas.admin_url').'/webhooks/notifications', [
            'form_params' => $body,
        ]);

        return [
            'code' => $response->getStatusCode(),
            'data' => $response->getBody()? json_decode($response->getBody()) : null,
        ];
    }
}
