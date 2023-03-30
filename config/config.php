<?php

return [
    'api_key' => env('ZOOM_CLIENT_KEY'),
    'api_secret' => env('ZOOM_CLIENT_SECRET'),
    'account_id' => env('ZOOM_ACCOUNT_ID'),
    'base_url' => 'https://api.zoom.us/v2/',
    'oauth_token_url' => 'https://zoom.us/oauth/token',
    'token_life' => 60 * 60 * 24 * 7, // In seconds, default 1 week
    'authentication_method' => env('ZOOM_AUTH_METHOD', 'jwt'), 
    'max_api_calls_per_request' => '5' // how many times can we hit the api to return results for an all() request
    'token_json'                => env('ZOOM_AUTH_TOKEN_JSON'),
];
