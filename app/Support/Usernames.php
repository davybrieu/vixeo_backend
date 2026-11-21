<?php

namespace App\Support;

/**
 * Usernames of the profiles (« pseudo »). A profile's page is www.vixeo.app/pseudo, like Twitter
 * (developer's request, 21/11/2026), and Android opens that address in the app: a username is never
 * a page of the site or of the app ([RESERVED]).
 */
final class Usernames
{
    /** 3 to 30 letters, digits or « _ » (route constraint of the profile pages). */
    public const PATTERN = '[A-Za-z0-9_]{3,30}';

    /**
     * Words that are never usernames: pages of the site (www.vixeo.app/apk…) and of the app
     * (/accueil, /recherche…: the same path on Android), and words kept for later pages.
     */
    public const RESERVED = [
        // Site.
        'apk', 'confidentialite', 'sitemap', 'robots', 'installer', 'faq', 'telecharger',
        'telechargement', 'download', 'android', 'windows', 'ios', 'api', 'docs', 'build',
        'storage', 'assets', 'static', 'favicon', 'legal', 'privacy', 'terms', 'cgu', 'mentions',
        // App.
        'demarrage', 'connexion', 'inscription', 'accueil', 'films', 'series', 'liste', 'recherche',
        'personne', 'personnes', 'titre', 'titres', 'chaine', 'chaines', 'lecture', 'membre',
        'membres', 'profil', 'profils', 'avis', 'parametres', 'compte', 'notifications', 'plugins',
        // Kept.
        'vixeo', 'www', 'app', 'apps', 'admin', 'administrateur', 'moderation', 'support', 'aide',
        'help', 'contact', 'about', 'login', 'logout', 'register', 'settings', 'search', 'home',
        'blog', 'status', 'officiel', 'official', 'root', 'system', 'null', 'undefined',
    ];
}
