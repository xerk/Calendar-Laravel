<?php

namespace App\Http\Controllers;

use App\Http\Resources\PlanResource;
use App\Models\Plan;
use Illuminate\Http\Request;

class PlanController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        $user = auth()->user();

        $plans = Plan::where('is_active', true);

        // if ($user->current_country) {
        //     $planCount = Plan::where('country', $user->current_country)->count();
        //     if ($planCount > 0) {
        //         $plans = $plans->where('country', $user->current_country)->orWhere('id', 1);
        //     } else {
        //         $plans = $plans->whereNull('country');
        //     }
        // }


        if ($user->currentSubscription) {
            $plans = $plans->where('id', '!=', $user->currentSubscription->plan_id);
        } else {
            $plans = $plans->where('id', '!=', 1);
        }

        $plans = $plans->get();

        return PlanResource::collection($plans);
    }
}
