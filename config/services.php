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
    | Where installed apps send people to update (docs/mise-a-jour.md). No Play Store: Android
    | updates come from the install page of the site (the app also downloads the APK itself).
    */
    'app_updates' => [
        'android_url' => env('ANDROID_UPDATE_URL', rtrim((string) env('SITE_URL', 'http://localhost:8100'), '/').'/installer#android'),
        'ios_url' => env('IOS_UPDATE_URL', rtrim((string) env('SITE_URL', 'http://localhost:8100'), '/').'/telecharger'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

];
