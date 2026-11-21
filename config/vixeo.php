<?php

/*
| Un seul projet Laravel pour l'API et le site de présentation, distingués par le domaine
| (docs/deploiement.md). Tout se règle dans le .env (Forge : onglet Environment).
*/
// Chaque adresse n'est écrite qu'une fois dans le .env : les domaines en sont tirés par parse_url().
$siteUrl = rtrim((string) env('APP_URL', 'http://localhost'), '/');
$apiUrl = rtrim((string) (env('API_URL') ?: $siteUrl), '/');
$host = fn (string $url): string => strtolower((string) parse_url($url, PHP_URL_HOST));
// Deux hôtes seulement si l'API et le site ont des adresses différentes. Sinon (en local, API_URL
// vide) : un seul hôte, le site sur /, l'API sur /api/v1.
$split = $host($apiUrl) !== '' && $host($apiUrl) !== $host($siteUrl);

return [

    'urls' => [
        // APP_URL : le site de présentation (https://www.vixeo.app).
        'site' => $siteUrl,
        // API_URL : l'API (https://api.vixeo.app), routes sous /api/v1.
        'api' => $apiUrl,
    ],

    // Tirés des adresses ci-dessus. vixeo.app → www.vixeo.app : redirection du site Forge.
    'domains' => [
        'api' => $split ? $host($apiUrl) : null,
        'site' => $split ? $host($siteUrl) : null,
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
