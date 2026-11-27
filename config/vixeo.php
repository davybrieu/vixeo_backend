<?php

return [

    // Base URL used only to create links that open a public profile in the Flutter app.
    'app_url' => rtrim((string) env('VIXEO_APP_URL', 'https://vixeo.app'), '/'),

    'domains' => [
        'api' => strtolower((string) parse_url(env('API_URL', env('APP_URL', 'http://localhost')), PHP_URL_HOST)) ?: null,
    ],

    // Contact used by the moderation report notification e-mail.
    'publisher' => [
        'email' => env('VIXEO_CONTACT_EMAIL'),
    ],

];
