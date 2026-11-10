<?php

/*
| Un seul projet Laravel pour l'API et le site de présentation, distingués par le domaine
| (docs/deploiement.md). Tout se règle dans le .env (Forge : onglet Environment).
*/
return [

    'domains' => [
        // api.vixeo.app : l'API (/api/v1), réponses JSON uniquement.
        'api' => env('API_DOMAIN') ?: null,
        // www.vixeo.app : le site de présentation (Vue + Inertia, SSR).
        'site' => env('SITE_DOMAIN') ?: null,
        // vixeo.app → www.vixeo.app : redirection faite par le site Forge (docs/deploiement.md).
    ],
    // Sans API_DOMAIN ni SITE_DOMAIN (en local) : un seul hôte, le site sur /, l'API sur /api/v1.

    // Pas de Play Store (décision du développeur) : Android = APK téléchargé depuis le site.
    'android' => [
        // L'APK et son empreinte viennent d'abord de la dernière release GitHub android-<build>
        // (App\Services\GitHubReleases, la même source que GET /api/v1/app/update). Secours :
        'apk_url' => env('VIXEO_ANDROID_APK_URL'),
        'apk_sha256' => env('VIXEO_ANDROID_APK_SHA256'),
        // minSdk 24 de Flutter (vixeo_frontend/android/app/build.gradle.kts).
        'minimum' => 'Android 7.0',
    ],

    // Mentions légales (loi pour la confiance dans l'économie numérique, article 6).
    // Une valeur vide masque la ligne correspondante.
    'publisher' => [
        'name' => env('VIXEO_PUBLISHER_NAME'),
        'address' => env('VIXEO_PUBLISHER_ADDRESS'),
        'registration' => env('VIXEO_PUBLISHER_REGISTRATION'),
        'director' => env('VIXEO_PUBLICATION_DIRECTOR'),
        'email' => env('VIXEO_CONTACT_EMAIL'),
    ],

    'host' => [
        'name' => env('VIXEO_HOST_NAME'),
        'address' => env('VIXEO_HOST_ADDRESS'),
        'phone' => env('VIXEO_HOST_PHONE'),
    ],

];
