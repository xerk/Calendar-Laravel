<?php

if (! function_exists('getSARExchangeRate')) {
    function getExchangeRate($value, $country, $key = 'sar_exchange_rate', $code = 'SA'): float {
        $sarExchangeRate = \DB::table('nova_settings')->where('key', $key)->first();

        if (!$sarExchangeRate) {
            return $value;
        }

        if ($country === $code) {
            return number_format($value * $sarExchangeRate->value, 2);
        }

        return number_format($value, 2);
    }
}
