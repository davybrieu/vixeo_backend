# Contexte projet : Vixeo

> Ce fichier sert de point de départ à une nouvelle session Claude Code.
> Il résume les décisions déjà prises, l'architecture visée, la mise en place
> technique (Claude Code cloud, GitHub Actions, Laravel Forge) et ce qui a été
> appris en analysant l'application ONYX. Les décisions marquées « décidé »
> ne sont pas à rediscuter sans raison ; celles marquées « à valider » sont des pistes.

---

## 1. Le développeur

- Développeur PHP / Laravel et JavaScript / Vue. **Débutant en Flutter et Dart** :
  explique les concepts Flutter quand ils apparaissent (widgets, état, focus, plateformes),
  et fais le parallèle avec Vue/Laravel quand c'est utile.
- Langue de travail : **français** (échanges, commentaires de PR, UI).
  Le code (noms de variables, classes, commits) peut rester en anglais.
- Travaille principalement via **Claude Code cloud** (navigateur et appli mobile),
  donc sans écran sur la machine de build : le rendu se vérifie via des URL de
  prévisualisation (voir §8).

---

## 2. Objectif produit

Une application de type « media center » multiplateforme, inspirée de l'ergonomie
d'ONYX (voir annexe), avec **une seule base de code** pour :

- Web
- Android (téléphone, tablette)
- Android TV / Google TV (navigation à la télécommande)
- Chromebook (via l'APK Android ou la version web)
- iOS (et éventuellement Apple TV plus tard)

**Priorité absolue : l'esthétique et la fluidité.** Interface sur mesure soignée
(style plateforme de streaming), animations fluides, y compris sur les petites box TV.

### Fonctionnalités visées (reprises de l'UX d'ONYX, avec des sources légales)

- Écran des **sources** organisé en onglets (ex. Films/Séries, TV en direct).
- **Accueil** par source : grand carrousel mis en avant + rangées thématiques,
  avec **« Continuer à regarder »** en tête (barre de progression, reprise exacte).
- **Fiches** film / série : note, année, durée, genres, synopsis, distribution
  (acteurs cliquables → filmographie), bande-annonce, saisons et épisodes,
  « Vous pourriez aussi aimer ». Métadonnées via **TMDb**.
- **Recherche globale** sur toutes les sources à la fois, résultats groupés par source,
  historique des recherches ; raccourcis par genre.
- **Lecteur** : plusieurs flux possibles par contenu, choix du flux, bascule automatique
  en cas d'échec, flux favoris (❤) essayés en premier, qualité auto, pistes audio,
  sous-titres, vitesse 0,5×–2×, verrouillage, Picture-in-Picture, Chromecast,
  ouverture dans un lecteur externe (VLC, MX Player) avec reprise de position,
  « passer l'intro », épisode suivant.
- **TV en direct** : chaînes FAST, compte Xtream / IPTV personnel de l'utilisateur,
  dossiers favoris, guide des programmes (EPG) si dispo.
- **Profils** (« Qui regarde ? ») : favoris, historique, reprise et téléchargements séparés.
- **Favoris** avec filtres (films, séries, épisodes, vus…) et tris (récent, A→Z, année…).
- **Appui long** partout = menu contextuel (favori, marquer vu, vu jusqu'ici,
  retirer de « Continuer à regarder »). Sur TV : appui long sur OK.
- **Contrôle parental** : code PIN, âge maximum, sources/dossiers verrouillés,
  recherche qui respecte les verrous.
- **Sauvegarde / synchronisation** entre appareils (compte utilisateur côté API).
- **Mode léger** automatique pour les appareils peu puissants (voir §5.8).
- Thèmes, fond d'écran personnalisable, mode immersif.

---

## 3. API

- **Métadonnées** : TMDb (clé API, attribution « This product uses the TMDB API… » obligatoire).
- **Sous-titres** : OpenSubtitles, avec la clé Vixeo (décision du développeur, 07/11/2026 :
  l'API interroge OpenSubtitles pour les applis et garde les fichiers en cache, voir
  `docs/lecture.md`). Moments à passer (génériques, résumé) : TheIntroDB.

---

## 4. Stack et organisation (décidé)

### Client : Flutter (Dart), canal stable — **décidé**

Raisons : moteur de rendu unique (rendu identique partout, animations fluides),
une base pour mobile, TV, web et desktop, Dart facile à prendre depuis JS/PHP.

Pistes de paquets — **à valider** (vérifier maintenance et compatibilité web/TV avant de choisir) :

| Besoin | Piste |
| - | - |
| Gestion d'état | Riverpod |
| Navigation | go_router |
| HTTP | dio |
| Base locale (mobile + web) | drift |
| Stockage sécurisé des jetons | flutter_secure_storage (moins fort sur le web) |
| Vidéo | video_player (officiel : ExoPlayer / AVPlayer / HTML5) ; évaluer media_kit (basé sur mpv) pour la largeur de codecs ; sur le web, HLS demande un lecteur basé sur hls.js |
| Navigation D-pad TV | système de focus Flutter (`Focus`, `FocusTraversalGroup`, `Shortcuts`/`Actions`) + paquet `dpad` |
| Chromecast | pas de plugin officiel : plugin communautaire à évaluer |
| Client API | généré depuis la spec OpenAPI de l'API (ex. openapi-generator, générateur dart-dio) |

### Backend : Laravel (API REST) — **décidé**

- Dernière version stable de Laravel, PHP 8.3+.
- API versionnée : `/api/v1/...` (les applis mobiles installées ne se mettent pas à jour
  en même temps que l'API : garder la rétrocompatibilité).
- Authentification par jetons (Laravel Sanctum).
- Base PostgreSQL (ou MySQL), Redis pour cache et files d'attente.
- Spec **OpenAPI générée automatiquement** (paquet Scramble) → source de vérité du contrat ;
  le client Dart est généré à partir d'elle.
- Rôles de l'API : comptes et profils, synchronisation (favoris, historique, réglages,
  serveurs média de l'utilisateur), cache des métadonnées TMDb, configuration distante
  (listes de chaînes FAST, catégories) modifiable sans republier l'appli.
- Pour la version web : un **proxy de flux** côté serveur sera nécessaire pour les flux
  qui exigent des en-têtes (Referer, User-Agent) ou n'ont pas de CORS
  (le navigateur ne peut pas envoyer ces en-têtes). Attention au coût en bande passante.

### Dépôts : deux dépôts séparés — **décidé**

- `vixeo_frontend` : l'appli Flutter.
- `vixeo_backend` : l'API Laravel.
- Chaque dépôt a un `CLAUDE.md` (commandes de test/build, conventions, lien vers l'autre dépôt).
- Pour une fonctionnalité qui touche les deux, ouvrir une session Claude Code cloud
  avec les deux dépôts et faire une PR dans chacun.

### Hébergement : Laravel Forge + GitHub Actions — **décidé**

Détails en §8.

---

## 5. Architecture à reprendre (patterns observés dans ONYX)

ONYX est une appli Kotlin dont l'architecture est bien pensée côté ergonomie et robustesse.
Ces patterns sont réutilisables avec des sources légales.

### 5.1 Abstraction « Source »

Chaque source (un serveur Jellyfin / Plex / Emby, un catalogue FAST, les vidéos locales…)
implémente la même interface. Équivalent Dart suggéré :

```dart
abstract class MediaSource {
  String get id;
  String get name;
  SourceGroup get group;          // ex. filmsSeries, live
  SourceCapabilities get caps;    // films, séries, direct, mobileOnly, etc.

  Future<List<HomeSection>> home();
  Future<List<MediaItem>> search(String query, {int page = 1});
  Future<List<MediaItem>> movies({int page = 1});
  Future<List<MediaItem>> shows({int page = 1});
  Future<MovieDetails> movie(String id);
  Future<ShowDetails> show(String id);
  Future<List<Episode>> episodes(String seasonId);
  Future<List<StreamOption>> streams(String itemId, MediaType type); // les « serveurs »
  Future<PlayableStream> resolve(StreamOption option);              // URL finale + en-têtes
}

class PlayableStream {
  final Uri url;
  final Map<String, String> headers; // Referer, User-Agent…
  final StreamFormat format;         // hls, dash, progressive
  final List<SubtitleTrack> subtitles;
  final DrmConfig? drm;
}
```

- Un **registre** déclare toutes les sources avec leur onglet et leurs capacités.
- La source choisie est mémorisée **par profil**.

### 5.2 Chargement progressif des flux

- Les options de lecture arrivent par lots ; la liste s'affiche dès le premier lot.
- Plafond de temps pour la collecte ; si rien n'arrive, message clair
  (« Aucune source disponible pour ce contenu… ») et purge des caches.

### 5.3 Tri des options de lecture

Dans l'ordre : favoris de l'utilisateur (❤), dernier flux qui a fonctionné pour ce titre,
puis langue préférée (ex. VF, puis VOSTFR, puis VO), puis qualité.
Mémoriser « dernier flux qui a fonctionné » par contenu.

### 5.4 Bascule automatique en cas d'erreur

Classer l'erreur du lecteur et réagir :

| Erreur | Réaction |
| - | - |
| Format/codec non supporté (ex. Dolby Vision) | flux suivant, sinon proposer le lecteur externe |
| Réseau / DNS | nouvel essai |
| Lien expiré (HTTP 403) | redemander une URL fraîche à la source (`resolve`) |
| Délai dépassé | flux suivant ; plus rien → « Connexion impossible » |
| Direct (chaîne TV) | relancer la même chaîne si elle avait déjà marché, puis flux de secours |

Garder un cache des flux morts (écartés plus vite) et un cache des résolutions avec durée de vie.

### 5.5 Lecteur

- Couche HTTP adaptée, en-têtes par flux, détection HLS / DASH / MP4.
- Sous-titres externes attachés au média.
- DRM uniquement pour des services officiels qui le fournissent avec le compte de l'utilisateur.
- Lecteur externe : envoyer l'URL + en-têtes à VLC / MX Player en demandant un résultat,
  récupérer la position au retour et la sauvegarder.
- Chromecast : le Chromecast ne peut pas envoyer d'en-têtes ; ONYX passe par un mini serveur
  HTTP local sur le réseau Wi-Fi qui relaie le flux avec les bons en-têtes.

### 5.6 Données locales et reprise

- Base locale **par couple profil + source** (ONYX : un fichier `profil_source.db`).
- Champs utiles : `lastPlaybackPositionMillis`, `durationMillis`, `lastEngagementTimeUtcMillis`,
  `isWatched`, `watchedDate`.
- « Continuer à regarder » = éléments avec `lastEngagement` non nul, triés du plus récent.
- Film terminé → marqué vu ; bandeau « épisode suivant ».

### 5.7 TV

- Détection auto de l'interface TV : fonctionnalité `android.software.leanback`,
  type `android.hardware.type.television`, mode UI télévision, ou absence d'écran tactile ;
  plus un **réglage manuel** (Auto / TV / Mobile) pour les box mal reconnues.
- Menu vertical à gauche, navigation aux flèches, OK pour valider, Retour pour remonter,
  **appui long sur OK = menu contextuel**.
- Côté Android : `LEANBACK_LAUNCHER`, bannière TV, `uses-feature` touchscreen non requis.

### 5.8 Mode léger

- Activé automatiquement si la RAM totale est inférieure à ~1,7 Go ou si la mémoire
  par appli (memory class) est ≤ 128 Mo ; désactivable dans les réglages.
- Effets : moins de requêtes en parallèle (ex. 3 sources à la fois), mémoire tampon
  du lecteur réduite, libération des écrans et caches inutilisés.

### 5.9 Sous-titres

- Recherche OpenSubtitles par l'API, avec la clé Vixeo (fichiers convertis en WebVTT et mis en
  cache), langue préférée réglée dans le profil.
- Traduction sur l'appareil (ONYX : ML Kit de Google).
- Calage automatique (fonction avancée, plus tard) : ONYX analyse le son décodé du film
  (pas le micro) avec un détecteur de voix (TEN VAD) et aligne les sous-titres sur
  les passages parlés.

### 5.10 Démarrage de l'appli (séquence observée)

1. Choix de l'interface (TV / mobile).
2. Initialisations en arrière-plan (réseau, caches, Chromecast différé).
3. Après ~30 min d'inactivité : retour à l'écran des sources et redemande du profil.
4. Profil → source mémorisée → accueil de la source, sinon écran des sources.
5. Vérification des mises à jour (pour une distribution hors store).

---

## 6. Sécurité : erreurs d'ONYX à ne pas reproduire

| Observé dans ONYX | À faire à la place |
| - | - |
| Identifiants de services tiers (email + mot de passe) stockés en clair | `flutter_secure_storage` (Keystore / Keychain) ; préférer des jetons à des mots de passe |
| Transfert entre appareils : tout le profil (y compris mots de passe) envoyé en JSON lisible au serveur | Synchro via le compte utilisateur de l'API, chiffrement de bout en bout pour les données sensibles, jamais de mots de passe tiers |
| Sauvegarde « chiffrée » avec une clé fixe identique pour tous | Clé dérivée d'un secret de l'utilisateur (Argon2 / PBKDF2), ou exclure les secrets de la sauvegarde |
| Vérification des certificats TLS désactivée sur une partie du réseau | Ne jamais désactiver la vérification TLS |
| Interrupteur à distance qui bloque l'appli sans explication | Mode maintenance transparent, sans bloquer les fonctions locales |
| Permissions très larges (accès à tous les fichiers, etc.) | Demander chaque permission au moment où elle sert, au minimum |

Points positifs à garder : aucun SDK de pub ni de pistage, rapports de plantage
envoyés seulement si l'utilisateur le décide. Si des statistiques sont ajoutées :
consentement explicite (RGPD).

---

## 7. Environnement Claude Code cloud

- Sessions lancées depuis claude.ai/code ou l'onglet Code de l'appli Claude.
- Machine Ubuntu 24.04 temporaire (≈ 4 vCPU, 16 Go RAM, 30 Go disque), sans écran.
- Préinstallés : PHP 8.3 + Composer, Node 20/21/22, PostgreSQL 16, Redis 7, Docker, git, gh, jq.
  PostgreSQL et Redis ne tournent pas par défaut : `service postgresql start`, `service redis-server start`.
- **Flutter n'est pas préinstallé** : environnement cloud dédié « flutter »,
  accès réseau **Trusted** (autorise GitHub, pub.dev, storage.googleapis.com),
  avec ce script dans « Setup script » (mis en cache ~7 jours s'il finit en moins de ~5 min) :

```bash
#!/bin/bash
set -euo pipefail
FLUTTER_DIR=/opt/flutter
if [ ! -x "$FLUTTER_DIR/bin/flutter" ]; then
  git clone --depth 1 --branch stable https://github.com/flutter/flutter.git "$FLUTTER_DIR"
fi
git config --global --add safe.directory "$FLUTTER_DIR"
ln -sf "$FLUTTER_DIR/bin/flutter" /usr/local/bin/flutter
ln -sf "$FLUTTER_DIR/bin/dart" /usr/local/bin/dart
export CI=true
flutter --disable-analytics || true
flutter config --no-cli-animations --enable-web
flutter precache --web --no-android --no-ios
flutter --version
```

  État : le clone a été testé (quelques secondes, version détectée correctement) ;
  la suite (téléchargement du SDK Dart depuis storage.googleapis.com) n'a pas pu être
  testée dans l'environnement d'origine mais ce domaine est autorisé en Trusted.
- **Secrets** : ne pas mettre de clés de déploiement dans les variables de l'environnement
  cloud (lisibles par la session) → secrets GitHub.
- Pas d'écran : vérifier le rendu via les URL de prévisualisation / le site de staging.
- Auto-fix : Claude peut surveiller une PR et corriger les échecs de CI et les commentaires de revue.

---

## 8. CI/CD et hébergement

### 8.1 API Laravel sur Forge

- Site Forge (ex. `api.[domaine]`) : type « General PHP / Laravel », dépôt `vixeo_backend`,
  branche `main`, **Push to deploy** activé, **Zero-downtime deployments** activé.
- Script de déploiement : macros Forge `$CREATE_RELEASE()` / `$ACTIVATE_RELEASE()` /
  `$RESTART_QUEUES()`, `composer install --no-dev`, `php artisan migrate --force`, caches.
- Limite Forge : un déploiement est coupé au-delà de 10 minutes.
- CORS : autoriser le domaine du Flutter web (et celui du staging).

### 8.2 Flutter web sur Forge

- **Ne pas compiler sur le serveur** (RAM, limite de 10 min) : GitHub Actions compile,
  puis pousse uniquement `build/web` sur une branche `web-dist`.
- Site Forge (ex. `[domaine]`) : type « static HTML site », dépôt `vixeo_frontend`,
  branche `web-dist`, « Web Directory » = `/`, Push to deploy + Zero-downtime activés
  (la branche est réécrite à chaque build).
- Si l'appli utilise des URL sans `#` (`usePathUrlStrategy`), Nginx doit renvoyer vers l'index :
  `try_files $uri $uri/ /index.html;`

Workflow `.github/workflows/deploy-web.yml` du dépôt Flutter :

```yaml
name: Flutter web (Forge)

on:
  pull_request:
  push:
    branches: [main]

jobs:
  build-and-publish:
    runs-on: ubuntu-latest
    permissions:
      contents: write
    steps:
      - uses: actions/checkout@v4
      - uses: subosito/flutter-action@v2
        with:
          channel: stable
          cache: true
      - run: flutter pub get
      - run: flutter test
      - run: flutter build web --release --dart-define=API_URL=${{ vars.API_URL }}
      - name: Publier le build sur la branche web-dist
        if: github.event_name == 'push'
        env:
          GH_TOKEN: ${{ secrets.GITHUB_TOKEN }}
        run: |
          cd build/web
          git init -q -b web-dist
          git config user.name "github-actions[bot]"
          git config user.email "41898282+github-actions[bot]@users.noreply.github.com"
          git add -A
          git commit -qm "Build web ${GITHUB_SHA::7}"
          git push -f "https://x-access-token:${GH_TOKEN}@github.com/${GITHUB_REPOSITORY}.git" web-dist
```

Variable GitHub à définir : `API_URL`.

### 8.3 Prévisualisations

Forge ne crée pas d'URL par PR. Options :

1. Site Forge de **staging** qui déploie une branche `staging` (le plus simple).
2. **Firebase Hosting** uniquement pour les prévisualisations de PR (gratuit au départ) :
   action `FirebaseExtended/action-hosting-deploy@v0` sans `channelId` sur `pull_request`
   (URL commentée sur la PR, `expires: 7d`), secrets `FIREBASE_SERVICE_ACCOUNT` et
   variable `FIREBASE_PROJECT_ID` (la commande `firebase init hosting:github` les crée).
   `firebase.json` :

```json
{
  "hosting": {
    "public": "build/web",
    "ignore": ["firebase.json", "**/.*", "**/node_modules/**"],
    "rewrites": [{ "source": "**", "destination": "/index.html" }]
  }
}
```

3. Outil tiers forge-previewer (Kirschbaum) qui crée un site Forge par PR :
   vérifier qu'il suit la version actuelle de Forge.

### 8.4 Applis mobiles

- Android : **pas de Play Store** (décision du développeur). APK signé avec une clé de release
  stable (hors du dépôt), distribué depuis le site `vixeo_web` ; mises à jour par la vérification intégrée (`GET /app/update`).
- iOS : distribution web Apple (serveur à construire).

---

## 9. Conventions

- Code (noms, commits) en anglais ; échanges et UI en français.
- API versionnée `/api/v1`, pas de breaking change en v1.
- OpenAPI généré (Scramble) = contrat client Dart.
- Tests Pest, formatage Pint.
- Secrets jamais dans le dépôt ni dans les variables cloud visibles.

---

## 10. Feuille de route suggérée (MVP)

1. Comptes, profils, synchro, catalogue TMDb, lecteur VOD — **fait**.
2. TV en direct : proxy Xtream / IPTV personnel + chaînes FAST + UI TV Hub.
3. Serveurs média utilisateur (Jellyfin / Plex / Emby).
4. EPG, favoris chaînes, mode léger affiné.

---

## 11. Annexe : analyse d'ONYX (référence)

- Dépôt : https://github.com/warbix-prog/ONYX — ne contient **aucun code source**,
  uniquement APK et guide.
- APK décompilé : package `com.streamfr.app`, Kotlin, UI XML + Leanback.
- Base : StreamFlix Reborn (Apache 2.0).
- Patterns utiles (abstraction Source, multi-flux, bascule auto, focus TV) : voir §5.
- Le catalogue et une partie de la TV d'ONYX viennent de sources sans droits → hors périmètre.
- Listes de chaînes : JSON / M3U sur GitHub (mise à jour sans nouvelle version).
- Mises à jour : API GitHub Releases + installeur Android.
