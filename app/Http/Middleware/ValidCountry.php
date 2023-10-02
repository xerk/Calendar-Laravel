<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use App\Services\IPGeoLocation;

class ValidCountry
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure(\Illuminate\Http\Request): (\Illuminate\Http\Response|\Illuminate\Http\RedirectResponse)  $next
     * @return \Illuminate\Http\Response|\Illuminate\Http\RedirectResponse
     */
    public function handle(Request $request, Closure $next)
    {
        // Update user table current country and current ip
        $user = auth()->user();
        if ($user->current_ip === $request->ip()) {
            return $next($request);
        }

        // Hold this step
      //  $countryCode = IPGeoLocation::getCountry($request->ip());
        $countryCode ="+20";
        if ($user) {
            $user->current_ip = $request->ip();
            $user->current_country = $countryCode;
            $user->save();
        }

        return $next($request);
    }
}
