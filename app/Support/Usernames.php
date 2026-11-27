<?php

namespace App\Support;

/**
 * Usernames of the profiles (« pseudo »). A public profile is opened in the Flutter app at /membre/{username}; usernames must not collide
 * with application routes ([RESERVED]).
 */
final class Usernames
{
    /** 3 to 30 letters, digits or « _ » (route constraint of the profile pages). */
    public const PATTERN = '[A-Za-z0-9_]{3,30}';

    /**
     * Words that are never usernames: reserved Flutter application routes and words kept for later pages.
     */
    public const RESERVED = [
        // Reserved app and infrastructure paths.
        'apk', 'confidentialite', 'sitemap', 'robots', 'installer', 'faq', 'telecharger',
        'telechargement', 'download', 'android', 'windows', 'ios', 'api', 'docs', 'build',
        'storage', 'assets', 'static', 'favicon', 'legal', 'privacy', 'terms', 'cgu', 'mentions',
        // App.
        'demarrage', 'connexion', 'inscription', 'accueil', 'films', 'series', 'liste', 'recherche',
        'personne', 'personnes', 'titre', 'titres', 'chaine', 'chaines', 'lecture', 'membre',
        'membres', 'profil', 'profils', 'avis', 'parametres', 'compte', 'notifications', 'plugins',
        'messages', 'description', 'fil', 'listes', 'bienvenue', 'journal', 'bilan',
        // Kept.
        'vixeo', 'www', 'app', 'apps', 'admin', 'administrateur', 'moderation', 'support', 'aide',
        'help', 'contact', 'about', 'login', 'logout', 'register', 'settings', 'search', 'home',
        'blog', 'status', 'officiel', 'official', 'root', 'system', 'null', 'undefined',
    ];
}
