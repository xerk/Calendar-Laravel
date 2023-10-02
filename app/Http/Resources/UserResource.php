<?php

namespace App\Http\Resources;

use App\Models\Plan;
use App\Models\Subscription;
use Carbon\Carbon;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array|\Illuminate\Contracts\Support\Arrayable|\JsonSerializable
     */
    public function toArray($request)
    {
        $subscription = $this->currentSubscription;
        $paymentUpdateDeadline = '';
        if ($subscription) {
            if($subscription->failed_payment_count > 0) {
                $paymentUpdateDeadline = Carbon::parse($subscription->original_expires_at)->addDays(10)->toFormattedDateString();
            }
        }

        $freePlan = Plan::find(1);
        $currentPlan = new PlanResource($freePlan);
        if($this->currentSubscription) {
            $currentPlan = $this->currentSubscription->plan_data;
            $currentPlan['description'] = $currentPlan['description'] ? explode("\n", $currentPlan['description']) : [];
        }

        $subscriptionGracePeriodReason = null;
        if ($subscription) {
            if($subscription->lastTransaction) {
                if($subscription->lastTransaction->type === 'first_payment') {
                    $subscriptionGracePeriodReason = $subscription->lastTransaction->paygate_response['respMessage'];
                }
                if($subscription->lastTransaction->type === 'recurring') {
                    $subscriptionGracePeriodReason = $subscription->lastTransaction->paygate_response['payment_result']['response_message'];
                }
                if($subscription->lastTransaction->type === 'changed') {
                    $subscriptionGracePeriodReason = $subscription->lastTransaction->paygate_response['payment_result']['response_message'];
                }
            }
        }

        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'username' => $this->username,
            'title' => $this->title,
            'description' => $this->description,
            'email_verified_at' => $this->email_verified_at,
            'display_language' => $this->display_language,
            'languages' => $this->languages,
            'timezone' => $this->timezone,
            'profile_photo_url' => $this->profile_photo_url ?: '',
            'default_cover_photo_url' => $this->default_cover_photo_url,
            'is_available' => $this->profile->is_available,
            'booking_page_off_message' => $this->profile->booking_page_off_message ?: '',
            'group_id' => $this->group_id,
            'default_availability_id' => $this->default_availability_id,
            'default_availability' => $this->default_availability_id ? new AvailabilityResource($this->defaultAvailability) : null,
            'current_plan' => $currentPlan,
            'current_subscription' => $this->currentSubscription ? new SubscriptionResource($this->currentSubscription) : null,
            'current_subscription_id' => $subscription ? $subscription->id : null,
            'current_subscription_auto_renew' => $subscription ? $subscription->is_yearly_auto_renew : false,
            'current_subscription_period' => $subscription ? $subscription->period : 1,
            'current_subscription_created_at' => $subscription ? $subscription->created_at : null,
            'current_subscription_expires_at' => $subscription && $subscription->expires_at ? $subscription->expires_at->toDateString() : null,
            'current_subscription_failed_payment_count' => $subscription ? $subscription->failed_payment_count : 0,
            'current_subscription_payment_update_deadline' => $paymentUpdateDeadline,
            'current_subscription_grace_period_reason' => $subscriptionGracePeriodReason,
            'current_subscription_cancelled_at' => $subscription ? ($subscription->cancelled_at ? $subscription->cancelled_at->toDateTimeString() : '') : null,
            'current_subscription_next_plan' => $subscription ? new PlanResource($subscription->nextPlan) : null,
            'suspended_at' => $this->suspended_at,
            'suspended_reason' => $this->suspended_reason,
            'deactivated_at' => $this->deactivated_at,
            'deactivated_reason' => $this->deactivated_reason,
            'billing_address' => $this->billing_address,
            'billing_city' => $this->billing_city,
            'billing_region' => $this->billing_region,
            'billing_country' => $this->billing_country,
            'billing_zipcode' => $this->billing_zipcode,
            'current_country' => $this->current_country,
            'providers' => $this->providers,
        ];
    }
}
