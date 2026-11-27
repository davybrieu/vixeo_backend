<?php

return [

    // Base URL used only to create links that open a public profile in the Flutter app.
    'app_url' => rtrim((string) env('VIXEO_APP_URL', 'https://app.vixeo.app'), '/'),

    'domains' => [
        'api' => strtolower((string) parse_url(env('API_URL', env('APP_URL', 'http://localhost')), PHP_URL_HOST)) ?: null,
    ],

    // Pas de Play Store (décision du développeur) : Android = APK téléchargé depuis le site.
    'android' => [
        // L'APK et son empreinte viennent d'abord de la dernière release GitHub android-<build>
        // (App\Services\GitHubReleases, la même source que GET /api/v1/app/update). Secours :
        'apk_url' => env('VIXEO_ANDROID_APK_URL'),
        'apk_sha256' => env('VIXEO_ANDROID_APK_SHA256'),
        // minSdk 24 de Flutter (vixeo_frontend/android/app/build.gradle.kts).
        'minimum' => 'Android 7.0',
        // Liens d'appli Android (/.well-known/assetlinks.json) : un lien www.vixeo.app/pseudo
        // s'ouvre dans l'appli. Paquet et empreinte SHA-256 du certificat de la clé de release
        // (publique : elle figure dans chaque APK) ; plusieurs empreintes séparées par des virgules.
        'package' => 'app.vixeo.vixeo',
        'cert_sha256' => env('VIXEO_ANDROID_CERT_SHA256', 'B6:3F:7D:E4:B2:CF:E1:7D:EF:D2:03:CF:12:3D:1E:6A:26:FB:51:13:C1:85:21:74:A1:80:7E:E3:40:38:7C:7B'),
    ],

    // Windows : installateur publié par la CI de vixeo_frontend (release GitHub windows-<build>),
    // pour l'utilisateur courant, sans droits d'administrateur. Windows 10 ou 11, 64 bits.
    'windows' => [
        'minimum' => 'Windows 10 (64 bits)',
    ],

];
