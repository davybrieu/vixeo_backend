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
        // Page where a person approves Vixeo on their TMDb account (docs/compte-tmdb.md).
        'authenticate_url' => env('TMDB_AUTHENTICATE_URL', 'https://www.themoviedb.org/authenticate'),
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
            // Title logos of the home rows (TitleLogos), fetched in batches.
            'images' => 604800,
        ],
    ],

    /*
    | Subtitles (docs/lecture.md): Vixeo's own OpenSubtitles.com consumer API key, and optionally
    | its account (higher daily download quota). Files are cached, so each is downloaded once.
    */
    'opensubtitles' => [
        'api_key' => env('OPENSUBTITLES_API_KEY'),
        'username' => env('OPENSUBTITLES_USERNAME'),
        'password' => env('OPENSUBTITLES_PASSWORD'),
        'base_url' => env('OPENSUBTITLES_BASE_URL', 'https://api.opensubtitles.com/api/v1'),
        // OpenSubtitles asks for the app name and version.
        'user_agent' => env('OPENSUBTITLES_USER_AGENT', 'Vixeo v1.0'),
    ],

    /*
    | Moments to skip (opening / end credits, recap, preview): TheIntroDB. Works without a key
    | (5 000 lookups a day); a key raises it to 100 000.
    */
    'theintrodb' => [
        'api_key' => env('THEINTRODB_API_KEY'),
        'base_url' => env('THEINTRODB_BASE_URL', 'https://api.theintrodb.org/v4'),
    ],

    /*
    | Where installed apps send people to update (docs/mise-a-jour.md). No Play Store: Android
    | updates come from the install page of the site (the app also downloads the APK itself).
    */
    'app_updates' => [
        'github_repository' => env('APP_UPDATES_GITHUB_REPOSITORY', 'davybrieu/vixeo_frontend'),
        'android_url' => env('ANDROID_UPDATE_URL', rtrim((string) env('SITE_URL', 'http://localhost:8100'), '/').'/installer#android'),
        'ios_url' => env('IOS_UPDATE_URL', rtrim((string) env('SITE_URL', 'http://localhost:8100'), '/').'/telecharger'),
    ],

    /*
    | Node.js scraper worker (Playwright) that returns stream source URLs from FrenchStream, Wiflix, Frembed.
    */
    'scraper' => [
        'base_url' => env('SCRAPER_URL', 'http://127.0.0.1:3100'),
        'timeout' => (int) env('SCRAPER_TIMEOUT', 45),

        // Providers the app shows in the sources panel, in display order. Each is queried
        // on its own so the panel can report a per-provider state (loading, failed, links).
        'providers' => [
            ['id' => 'frenchstream', 'name' => 'FrenchStream'],
            ['id' => 'wiflix', 'name' => 'Wiflix'],
            ['id' => 'frembed', 'name' => 'Frembed'],
        ],
    ],

    /*
    | Personal legal Xtream Codes panel (live TV). Credentials stay on the server only.
    | XTREAM_BASE_URL example: http://panel.example.com:8080
    */
    'xtream' => [
        'base_url' => rtrim((string) env('XTREAM_BASE_URL', ''), '/'),
        'username' => env('XTREAM_USERNAME', ''),
        'password' => env('XTREAM_PASSWORD', ''),
        // Prefer HLS when the panel supports it.
        'stream_format' => env('XTREAM_STREAM_FORMAT', 'm3u8'),
        'timeout' => (int) env('XTREAM_TIMEOUT', 20),
        'ttl' => [
            'categories' => (int) env('XTREAM_TTL_CATEGORIES', 3600),
            'channels' => (int) env('XTREAM_TTL_CHANNELS', 1800),
        ],
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

];
