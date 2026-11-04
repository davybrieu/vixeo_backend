<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Resend, Postmark, AWS, and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    /*
    | TMDb (metadata). Use the "API Read Access Token" (v4 bearer, recommended)
    | or the v3 API key. Responses are cached in the default cache store (Redis).
    */
    'tmdb' => [
        'token' => env('TMDB_READ_ACCESS_TOKEN'),
        'api_key' => env('TMDB_API_KEY'),
        'base_url' => env('TMDB_BASE_URL', 'https://api.themoviedb.org/3'),
        'image_base_url' => env('TMDB_IMAGE_BASE_URL', 'https://image.tmdb.org/t/p/'),
        'language' => env('TMDB_LANGUAGE', 'fr-FR'),
        'region' => env('TMDB_REGION', 'FR'),
        'cache_store' => env('TMDB_CACHE_STORE'),
        // Cache durations in seconds.
        'ttl' => [
            'lists' => 3600,
            'details' => 86400,
            'search' => 3600,
            'genres' => 604800,
            // Age ratings (certifications) of list items, fetched in batches.
            'ratings' => 604800,
        ],
    ],

    /*
    | Where installed apps send people to update (docs/mise-a-jour.md): the Play Store page, or the
    | download page of the website for iOS (EU web distribution).
    */
    'app_updates' => [
        'android_url' => env('ANDROID_UPDATE_URL', 'https://play.google.com/store/apps/details?id=app.vixeo.vixeo'),
        'ios_url' => env('IOS_UPDATE_URL', rtrim((string) env('WEB_URL', 'http://localhost:8080'), '/').'/telecharger'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

];
