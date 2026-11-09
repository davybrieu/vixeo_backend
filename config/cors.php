<?php

$allowedOrigins = array_filter(array_map(
    'trim',
    explode(',', env('CORS_ALLOWED_ORIGINS', '*')),
));

// The browser app (WEB_URL, also the address of the TV QR codes) uses another origin than the
// API: always allowed, even when CORS_ALLOWED_ORIGINS is customized in the server .env.
$allowedOrigins[] = rtrim((string) env('WEB_URL', 'https://app.vixeo.app'), '/');

return [

    /*
    |--------------------------------------------------------------------------
    | Cross-Origin Resource Sharing (CORS) Configuration
    |--------------------------------------------------------------------------
    |
    | Here you may configure your settings for cross-origin resource sharing
    | or "CORS". This determines what cross-origin operations may execute
    | in web browsers. You are free to adjust these settings as needed.
    |
    | To learn more: https://developer.mozilla.org/en-US/docs/Web/HTTP/CORS
    |
    */

    'paths' => ['api/*', 'sanctum/csrf-cookie'],

    'allowed_methods' => ['*'],

    'allowed_origins' => array_values(array_unique($allowedOrigins)),

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 0,

    'supports_credentials' => false,

];
