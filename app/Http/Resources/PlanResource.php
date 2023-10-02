<?php

namespace App\Http\Resources;

use Carbon\Carbon;
use Illuminate\Http\Resources\Json\JsonResource;

class PlanResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array|\Illuminate\Contracts\Support\Arrayable|\JsonSerializable
     */
    public function toArray($request)
    {

        return [
            'id' => $this->id,
            'order' => $this->order,
            'level' => $this->level,
            'is_active' => $this->is_active,
            'name' => $this->name,
            'currency' => $this->currency_symbol,
            'original_monthly_price' => $this->original_monthly_price,
            'monthly_price' => $this->monthly_price,
            'original_yearly_price' => $this->original_yearly_price,
            'yearly_price' => $this->yearly_price,
            'description' => $this->description ? explode("\n", $this->description) : [],
            'is_active' => $this->is_active,
            'calendars' => $this->calendars,
            'bookings' => $this->bookings,
            'teams' => $this->teams,
            'members' => $this->members,
            'switch_plan' => $this->proratingPlan(),
        ];
    }

    private function proratingPlan() {
        $user = auth()->user();
        if (!$user) {
            return null;
        }
        $subscription = $user->currentSubscription;
        if (!$subscription) {
            return null;
        }
        $subscriptionPeriod = $subscription->period;
        $subscriptionCreatedAt = $subscription->created_at;
        $subscriptionExpiresAt = $subscription->expires_at;
        $subscriptionOriginalExpiresAt = Carbon::parse($subscription->original_expires_at);

        if ($this->monthly_price == 0) {
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
        // Used subscription days
        $subscriptionUsedDays = $days - $subscriptionRemainingDays;

        // Calculate the remaining amount
        $subscriptionRemainingAmount = $subscriptionPrice / $days * $subscriptionRemainingDays;
        $subscriptionRemainingAmount = $subscriptionRemainingAmount === 0 ? $subscriptionPrice : $subscriptionRemainingAmount;


        $planPriceMonthly = $this->monthly_price;
        $planPriceYearly = $this->yearly_price;

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
        if ($newPriceMonthly < 0 && $planPriceMonthly > 0) {
            $allowM = false;
        }

        if ($newPriceYearly < 0 && $planPriceYearly > 0) {
            $allowY = false;
        }

        $newPriceMonthly = (float)number_format($newPriceMonthly, 2);
        $newPriceYearly = (float)number_format($newPriceYearly, 2);
        $subscriptionRemainingAmount = (float)number_format($subscriptionRemainingAmount, 2);

        return [
            'monthly' => [ // 15.068
                'allow' => $allowM,
                'price' => $newPriceMonthly,
                'remaining_amount' => $subscriptionRemainingAmount,
                'remaining_days' => $subscriptionRemainingDays,
                'current_plan_price' => (float)$subscriptionPrice,
                'new_plan_price' => (float)$planPriceMonthly,
                'new_expire_date' => now()->addDays($subscriptionPeriod == 2 ? (30 - $subscriptionUsedDays) : $subscriptionRemainingDays)->format('Y-m-d'),
                // We will upgrade after 15 days to the new plan
                'message' => $subscriptionRemainingAmount >= $planPriceMonthly ?
                    'You will upgrade after ' . $subscriptionRemainingDays . ' days to the new plan' : 'You will pay ' . $newPriceMonthly . ' ' . $this->currency . ' for the remaining ' . $subscriptionRemainingDays . ' days',
            ],
            'yearly' => [
                'allow' => $allowY,
                'price' => $newPriceYearly,
                'remaining_amount' => $subscriptionRemainingAmount,
                'remaining_days' => $subscriptionPeriod == 1 ? (365 - $subscriptionUsedDays) : $subscriptionRemainingDays,
                'current_plan_price' => (float)$subscriptionPrice,
                'new_plan_price' => (float)$planPriceYearly,
                'new_expire_date' => now()->addDays($subscriptionPeriod == 1 ? (365 - $subscriptionUsedDays) : $subscriptionRemainingDays)->format('Y-m-d'), // 'Apr 15, 2021
                'message' => $subscriptionRemainingAmount >= $planPriceYearly ? 'You will get ' . ($subscriptionPeriod == 1 ? (365 - $subscriptionRemainingDays) : $subscriptionRemainingDays) . ' days for free' : 'You will pay ' . $newPriceYearly . ' ' . $this->currency . ' for the remaining ' . ($subscriptionPeriod == 1 ? (365 - $subscriptionRemainingDays) : $subscriptionRemainingDays) . ' days',
            ],
        ];

    }
}
