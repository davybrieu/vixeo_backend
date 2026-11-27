<?php

return [

    // Base URL used only to create links that open a public profile in the Flutter app.
    'app_url' => rtrim((string) env('VIXEO_APP_URL', 'https://app.vixeo.app'), '/'),

    'domains' => [
        'api' => strtolower((string) parse_url(env('API_URL', env('APP_URL', 'http://localhost')), PHP_URL_HOST)) ?: null,
    ],

    // Pas de Play Store : l'APK est publié dans les releases GitHub de l'application Flutter.
    'android' => [
        // L'APK et son empreinte viennent d'abord de la dernière release GitHub android-<build>
        // (App\Services\GitHubReleases, la même source que GET /api/v1/app/update). Secours :
        'apk_url' => env('VIXEO_ANDROID_APK_URL'),
        'apk_sha256' => env('VIXEO_ANDROID_APK_SHA256'),
        // minSdk 24 de Flutter (vixeo_frontend/android/app/build.gradle.kts).
        'minimum' => 'Android 7.0',
        // App Links: the Flutter frontend serves /.well-known/assetlinks.json on app.vixeo.app.
        'package' => 'app.vixeo.vixeo',
        'cert_sha256' => env('VIXEO_ANDROID_CERT_SHA256', 'B6:3F:7D:E4:B2:CF:E1:7D:EF:D2:03:CF:12:3D:1E:6A:26:FB:51:13:C1:85:21:74:A1:80:7E:E3:40:38:7C:7B'),
    ],

    // Windows: installer published by the vixeo_frontend CI (GitHub release windows-<build>),
    // pour l'utilisateur courant, sans droits d'administrateur. Windows 10 ou 11, 64 bits.
    'windows' => [
        'minimum' => 'Windows 10 (64 bits)',
    ],

    // Contact used by the moderation report notification e-mail.
    'publisher' => [
        'email' => env('VIXEO_CONTACT_EMAIL'),
    ],

];
