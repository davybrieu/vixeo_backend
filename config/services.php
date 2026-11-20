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
    | Ratings of other sites (IMDb, Rotten Tomatoes, Letterboxd…): MDBList, with Vixeo's key
    | (docs/notes.md). Its quota is per MDBList account (free: 1 000 requests a day, all keys
    | together): titles are asked 200 at a time and kept for every account. No key: no rating.
    */
    'mdblist' => [
        'api_key' => env('MDBLIST_API_KEY'),
        'base_url' => env('MDBLIST_BASE_URL', 'https://api.mdblist.com'),
        // Requests Vixeo may send a day (UTC), under the plan's quota (a margin for other uses).
        'daily_requests' => (int) env('MDBLIST_DAILY_REQUESTS', 900),
        'cache_store' => env('MDBLIST_CACHE_STORE'),
        // Cache durations in seconds: ratings of a title, title MDBList does not know.
        'ttl' => [
            'ratings' => (int) env('MDBLIST_TTL', 259200),
            'unknown' => 86400,
        ],
    ],

    /*
    | Where installed apps send people to update (docs/mise-a-jour.md). No Play Store: Android
    | updates come from the install page of the site (the app also downloads the APK itself).
    */
    'app_updates' => [
        'github_repository' => env('APP_UPDATES_GITHUB_REPOSITORY', 'davybrieu/vixeo_frontend'),
        // An empty variable (as in .env.example) falls back to the site's page too.
        'android_url' => env('ANDROID_UPDATE_URL') ?: rtrim((string) env('APP_URL', 'http://localhost'), '/').'/#installer',
        'ios_url' => env('IOS_UPDATE_URL') ?: rtrim((string) env('APP_URL', 'http://localhost'), '/').'/#installer',
    ],

    /*
    | Push notifications through Firebase Cloud Messaging (docs/notifications.md): the service
    | account key of the Firebase project, as a path to its JSON file (outside the repository),
    | or the JSON itself encoded in base64 ("base64:…"). Empty: no push notification.
    */
    'fcm' => [
        'credentials' => env('FCM_CREDENTIALS'),
    ],

    /*
    | Node.js scraper worker (Playwright) that searches the French-language film and series providers.
    */
    'scraper' => [
        'base_url' => env('SCRAPER_URL', 'http://127.0.0.1:3100'),
        'timeout' => (int) env('SCRAPER_TIMEOUT', 45),

        // Providers the app shows in the sources panel, in display order. Each is queried
        // on its own so the panel can report a per-provider state (loading, failed, links).
        // Aligned with Onyx TMDB FR: Coflix + Aplouf (Cloudstream), no Wiflix.
        'providers' => [
            ['id' => 'tmdb-fr', 'name' => 'TMDB Français (Onyx)'],
            ['id' => 'coflix', 'name' => 'Coflix'],
            ['id' => 'aplouf', 'name' => 'Aplouf'],
            ['id' => 'frenchstream', 'name' => 'FrenchStream'],
            ['id' => 'frembed', 'name' => 'Frembed'],
            ['id' => '1jour1film', 'name' => '1Jour1Film'],
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
        // Programme guide: XMLTV files (gzip accepted), comma-separated, the preferred first.
        // Empty = no guide. Downloaded by `php artisan tv:epg` (scheduled every 6 hours).
        'epg_urls' => array_values(array_filter(array_map('trim', explode(',', (string) env('TV_EPG_URLS', ''))))),
        // Seconds the guide is kept (longer than the 6 hours between two downloads).
        'epg_ttl' => (int) env('TV_EPG_TTL', 43200),
        'epg_timeout' => (int) env('TV_EPG_TIMEOUT', 120),
        // Logo library: a GitHub repository of TV logos and the folders read, the preferred first.
        // Empty repository = no library (logos of the guide and the provider only).
        'logos_repository' => env('TV_LOGOS_REPOSITORY', 'tv-logo/tv-logos'),
        'logos_directories' => array_values(array_filter(array_map('trim', explode(',', (string) env(
            'TV_LOGOS_DIRECTORIES',
            'countries/france,countries/belgium,countries/switzerland,countries/international'
        ))))),
        'logos_ttl' => (int) env('TV_LOGOS_TTL', 86400),
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

    /*
    | Plug-ins (docs/plugins.md): add-ons in the Stremio format that add sources (streams) to the
    | player, installed by an account from their address, or for every account
    | (`php artisan addons:server`). The API is the only one to call them: the apps never do.
    | Their catalogs are ignored: the catalog is TMDb's.
    */
    'addons' => [
        // Seconds to wait for a plug-in (manifest, streams).
        'timeout' => (int) env('ADDONS_TIMEOUT', 8),
        // Plug-ins an account may install.
        'max_per_user' => (int) env('ADDONS_MAX_PER_USER', 30),
        // Largest answer read from a plug-in, in bytes.
        'max_response_bytes' => (int) env('ADDONS_MAX_RESPONSE_BYTES', 5 * 1024 * 1024),
        // Plain http:// plug-ins (local development only): https only otherwise.
        'allow_http' => (bool) env('ADDONS_ALLOW_HTTP', false),
        'ttl' => [
            'manifest' => (int) env('ADDONS_TTL_MANIFEST', 3600),
            'streams' => (int) env('ADDONS_TTL_STREAMS', 300),
        ],
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

];
