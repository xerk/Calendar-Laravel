<?php

namespace App\Http\Resources;

use Carbon\Carbon;
use Illuminate\Http\Resources\Json\JsonResource;

class SubscriptionResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array|\Illuminate\Contracts\Support\Arrayable|\JsonSerializable
     */
    public function toArray($request)
    {
        $paymentUpdateDeadline = '';
        if ($this->failed_payment_count > 0) {
            $paymentUpdateDeadline = Carbon::parse($this->original_expires_at)->addDays(10)->toFormattedDateString();
        }
        $expireAt = $this->expires_at ? $this->expires_at->toDateString() : '';

        return [
            'id' => $this->id,
            'current_subscription_usage' => $this->currentSubscriptionUsage,
            'auto_renew' => $this->is_yearly_auto_renew,
            'period' => $this->period,
            'created_at' => $this->created_at,
            'expires_at' => $this->expires_at ? $expireAt : null,
            'original_expires_at' => $this->original_expires_at ? $this->original_expires_at->toDateString() : '',
            'is_expired' => $this->original_expires_at < now()->startOfDay(),
            'is_expired_today' => (now()->toDateString() === $expireAt) ? true : false,
            'failed_payment_count' => $this->failed_payment_count,
            'payment_update_deadline' => $paymentUpdateDeadline,
            'cancelled_at' => $this->cancelled_at ? $this->cancelled_at->toDateString() : '',
            'plan_data' => $this->plan_data,
            'next_plan_id' => $this->next_plan_id,
            'next_period' => $this->next_period,
        ];
    }
}
