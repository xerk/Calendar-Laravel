<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class IPGeoLocation
{
    public static function getCountry($ip)
    {
        $response = Http::get('https://api.ipgeolocation.io/ipgeo', [
            'apiKey' => config('services.ip_geo_location.key'),
            'ip' => $ip,
        ]);

        \Log::info($response->json());

        return $response->json()['country_code2'];
    }
}
