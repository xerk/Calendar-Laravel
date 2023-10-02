<?php

namespace App\Jobs;

use App\Models\PaymentMethod;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use GuzzleHttp\Client;
use Illuminate\Support\Str;

class VoidTransaction implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $paymentMethod;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct(PaymentMethod $paymentMethod)
    {
        $this->paymentMethod = $paymentMethod;
    }

    protected $apiEndpoint = 'https://secure.paytabs.sa/payment/request';

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        $paymentMethod = $this->paymentMethod;
        $response = $this->voidTransaction($paymentMethod);

        if ($response['code'] == 200) {
            $data = $response['data'];
            $responseStatus = $data->payment_result->response_status;
            $isVoided = false;
            if(isset($data->payment_result->acquirer_message)) {
                $responseAcquirerMessage = $data->payment_result->acquirer_message;
                if(Str::contains($responseAcquirerMessage, 'has already successfully been voided')) {
                    $isVoided = true;
                }
            }

            if($responseStatus == 'A' || $responseStatus == 'V' || $isVoided) {
                $paymentMethod->voided_at = now();
                $paymentMethod->save();
            }
        }
    }

    function voidTransaction(PaymentMethod $paymentMethod)
    {

        $client = new Client(['http_errors' => false]);

        $headers = [
            'Authorization' => config('paytabs.server_key'),
            'Content-Type' => 'application/json',
        ];

        $body = [
            "profile_id" => config('paytabs.profile_id'),
            "tran_type" => "void",
            "tran_class" => "ecom",
            "cart_id" => $paymentMethod->getCartId(),
            "cart_currency" => 'USD',
            "cart_amount" => 1,
            "cart_description" => "Void payment method auth",
            "tran_ref" => $paymentMethod->paygate_tran_ref,
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
}
