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
        // An empty variable (as in .env.example) falls back to the site's page too.
        'android_url' => env('ANDROID_UPDATE_URL') ?: rtrim((string) (env('SITE_URL') ?: env('APP_URL', 'http://localhost')), '/').'/installer#android',
        'ios_url' => env('IOS_UPDATE_URL') ?: rtrim((string) (env('SITE_URL') ?: env('APP_URL', 'http://localhost')), '/').'/telecharger',
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
    | Vavoo live TV (docs/lecture.md). Vavoo's MediaHubMX API is signed with an "addonSig"
    | obtained from a ping endpoint; mirrors and signature stay on the server, apps only get
    | the resolved HLS URL and the User-Agent to send. Mirrors and versions are ONYX's.
    */
    'vavoo' => [
        'base_urls' => array_values(array_filter(array_map('trim', explode(',', (string) env(
            'VAVOO_BASE_URLS',
            'https://vavoo.net,https://vavoo.to,https://kool.ws,https://vavoo.top'
        ))))),
        'ping_urls' => array_values(array_filter(array_map('trim', explode(',', (string) env(
            'VAVOO_PING_URLS',
            'https://www.vypn.net/api/app/ping,https://www.lokke.app/api/app/ping,https://www.vavoo.tv/api/app/ping'
        ))))),
        'user_agent' => env('VAVOO_USER_AGENT', 'MediaHubMX/2'),
        'catalog_client_version' => env('VAVOO_CATALOG_CLIENT_VERSION', '3.1.0'),
        'resolve_client_version' => env('VAVOO_RESOLVE_CLIENT_VERSION', '3.0.2'),
        // Country the catalog is filtered on ("France", "Canada"…), or "all" for every country.
        'country' => env('VAVOO_COUNTRY', 'France'),
        // Stream URLs kept per channel when resolving (ONYX keeps 3).
        'max_streams' => (int) env('VAVOO_MAX_STREAMS', 3),
        'timeout' => (int) env('VAVOO_TIMEOUT', 20),
        'ttl' => [
            // ONYX refreshes the signature every 5 min.
            'signature' => (int) env('VAVOO_TTL_SIGNATURE', 300),
            // ONYX caches the channel registry 30 min.
            'catalog' => (int) env('VAVOO_TTL_CATALOG', 1800),
        ],
    ],

    /*
    | Live TV stream proxy. The provider URL and its headers are hidden behind a signed token;
    | these settings only tune the proxy itself. See docs/tv-direct.md.
    */
    'tv' => [
        // Seconds a proxied media token stays valid (a copied URL stops working after that).
        'proxy_ttl' => (int) env('TV_PROXY_TTL', 21600),
        // Seconds to wait for one upstream playlist / segment request.
        'proxy_timeout' => (int) env('TV_PROXY_TIMEOUT', 20),
        // Programme guide: an XMLTV file (gzip accepted). Empty = no guide (docs/tv-direct.md).
        'epg_url' => env('TV_EPG_URL'),
        // Seconds the parsed guide is kept before it is downloaded again.
        'epg_ttl' => (int) env('TV_EPG_TTL', 21600),
        'epg_timeout' => (int) env('TV_EPG_TIMEOUT', 60),
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
