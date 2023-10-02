<?php

return [
    'account_id' => env('ZOOM_ACCOUNT_ID'),
    'client_id' => env('ZOOM_CLIENT_ID'),
    'client_secret' => env('ZOOM_CLIENT_SECRET'),
    'redirect_uri' => env('ZOOM_REDIRECT_URI'),
    'redirect_uri_callback' => env('ZOOM_REDIRECT_CALLBACK'),
    'base_url' => 'https://api.zoom.us/v2/',
    'authentication_method' => 'Oauth', // Only Oauth compatible at present
    'max_api_calls_per_request' => '5' // how many times can we hit the api to return results for an all() request
];
