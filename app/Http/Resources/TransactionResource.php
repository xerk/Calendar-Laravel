<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class TransactionResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array|\Illuminate\Contracts\Support\Arrayable|\JsonSerializable
     */
    public function toArray($request)
    {


        // Cache company settings 5 minutes
        $companySettings = \DB::table('nova_settings')->get();
        $subscriptionDateRange = $this->subscription->period == 1 ?  'Start ' . $this->subscription->created_at->format('M d, Y').' - '. 'End ' .optional($this->subscription->original_expires_at)->format('M d, Y') ?? 'End ' . $this->subscription->created_at->addMonth()->format('M d, Y') : 'Start ' . $this->subscription->created_at->format('M d, Y').' - '. 'End ' .optional($this->subscription->original_expires_at)->format('M d, Y') ?? 'End ' .$this->subscription->created_at->addYear()->format('M d, Y');

        $companySettings = $companySettings->mapWithKeys(function ($setting) {
            if ($setting->key === 'company_logo') { // config saas.admin_url nested of current url
                return [$setting->key => config('saas.admin_url') . '/storage/' . $setting->value];
            }
            return [$setting->key => $setting->value];
        });

        // format 0.00 USD
        $vatAmount = number_format($this->vat_amount, 2);
        $amount = number_format($this->amount, 2);
        $total = number_format($this->total, 2);

        return [
            'id' => $this->id,
            'hid' => $this->getHid(),
            'type' => $this->type,
            'subscription_number' => $this->getSubscriptionNumber(),
            'trans_ref' => $this->getPaymentTransRef(),
            'name' => $this->subscription->plan_data['name'].' '.($this->subscription->period === 1 ? 'Monthly' : 'Yearly'),
            'amount' => (float)$amount . ' ' . 'SAR',
            'vat_amount' => (float)$vatAmount . ' ' . 'SAR',
            'total' => (float)$total . ' ' . 'SAR',
            'currency' => 'SAR',
            'invoice_url' => route('transaction.invoice', $this->id),
            'payment_method' => $this->paygate === 'paytabs' ? 'Credit Card' : 'N/A',
            'billing_address' => $this->billing_address,
            'billing_city' => $this->billing_city,
            'billing_region' => $this->billing_region,
            'billing_country' => $this->billing_country,
            'billing_zipcode' => $this->billing_zipcode,
            'refund_reason' => $this->refund_reason,
            'refunded_at' => $this->refunded_at,
            'created_at' => $this->created_at->format('Y-m-d H:i'),
            'plan_data' => $this->subscription->plan_data,
            'subscription_period' => $this->subscription->period == 1 ? 'Monthly' : 'Yearly',
            'subscription_date_range' => $subscriptionDateRange,
            'company_settings' => $companySettings,
        ];
    }
}
