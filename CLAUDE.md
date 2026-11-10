# Vixeo — API (vixeo_backend)

API REST Laravel de **Vixeo**, media center multiplateforme (Flutter : web, Android,
Android TV, iOS). L'appli cliente est dans le dépôt
[`davybrieu/vixeo_frontend`](https://github.com/davybrieu/vixeo_frontend).

**Lire d'abord [`docs/CONTEXT.md`](docs/CONTEXT.md)** : décisions produit et techniques,
périmètre légal (§3, ce qui est hors périmètre ne doit pas être implémenté), sécurité (§6),
feuille de route (§10).

**Distribution Android** (décision du développeur, ne plus poser la question) : **pas de Play
Store**. L'appli Android (téléphones, tablettes, Android TV / Google TV) est distribuée en
**APK signé** depuis le site `vixeo_web` (page `/installer`). Conséquences :
- chaque version est signée avec la **même clé de release** (keystore hors du dépôt, dans les
  secrets) : sinon Android refuse d'installer la mise à jour par-dessus l'ancienne version ;
- les mises à jour passent par la vérification intégrée à l'appli (`GET /app/update`), pas par
  un store ;
- vérification des développeurs Android par Google : obligatoire hors Play Store dans 4 pays
  depuis le 30/09/2026, prévue partout en 2027 : enregistrer le développeur dans l'Android
  Developer Console avant.

## Langue

- Échanges, descriptions de PR, documentation et messages destinés à l'utilisateur : **français**.
- Code, noms de classes/variables, messages de commit : anglais.

## Stack

- Laravel 13, PHP 8.3+, Sanctum (jetons Bearer), Scramble (OpenAPI), Pest 4, Pint.
- PostgreSQL en production, Redis pour cache et files d'attente.
- Les tests tournent sur SQLite en mémoire (`phpunit.xml`) ; les relancer aussi sur PostgreSQL
  avant un push qui touche aux migrations ou aux requêtes.

## Commandes

```bash
composer install
cp .env.example .env && php artisan key:generate
php artisan migrate

./vendor/bin/pest                 # tests (SQLite en mémoire)
./vendor/bin/pint                 # formatage (vérification : pint --test)
composer openapi                  # régénère docs/openapi.json (échoue si un type est inconnu)
                                  # (conteneur cloud, en root : COMPOSER_ALLOW_SUPERUSER=1)
php artisan serve                 # http://localhost:8000, doc interactive sur /docs/api (env local)
```

Dans un conteneur Claude Code cloud, PostgreSQL et Redis ne tournent pas par défaut :
`service postgresql start`, `service redis-server start`. Pour tester sur PostgreSQL :
`DB_CONNECTION=pgsql DB_DATABASE=... DB_USERNAME=... DB_PASSWORD=... ./vendor/bin/pest`.

## Conventions

- Toutes les routes sous `/api/v1` (`routes/api.php`). **Pas de changement cassant en v1** :
  les applis installées ne se mettent pas à jour en même temps que l'API. Ajouter des champs
  est autorisé ; renommer/supprimer → nouvelle version.
- Contrôleurs dans `app/Http/Controllers/Api/V1`, validation dans des Form Requests
  (`app/Http/Requests/Api/V1`), réponses via des API Resources (`app/Http/Resources/V1`),
  autorisations via des Policies (`Gate::authorize`).
- **Contrat** : `docs/openapi.json` est la source de vérité du client Dart (généré, jamais
  recopié à la main). Après toute modification d'une route, d'une requête ou d'une ressource,
  lancer `composer openapi` et committer le fichier.
  Les docblocks des contrôleurs et des règles de validation alimentent la doc.
- Chaque fonctionnalité a ses tests Pest dans `tests/Feature/Api/V1`.
- **Messages d'erreur en français** (locale `fr` par défaut) :
  - validation : `lang/fr/validation.php` ; ajouter le nom français de chaque nouveau champ
    dans `attributes` ;
  - messages propres à l'appli : écrire la clé en anglais avec `__('...')` et ajouter la
    traduction dans `lang/fr.json` ;
  - erreurs HTTP du framework (401, 403, 404, 405, 429, 500) : traduites dans
    `bootstrap/app.php` ; les 404 ne révèlent jamais le nom des modèles.
- **Catalogue TMDb** (`app/Services/Tmdb`) :
  - `TmdbClient` : seul point d'appel à TMDb, met en cache les réponses réussies (durées dans
    `config/services.php` → `tmdb.ttl`), réessaie 2 fois sur erreur réseau / 5xx / 429.
    TMDb 404 → notre 404 ; autre échec ou clé absente → 503 (message traduit).
  - `TmdbCatalog` : ce qu'on demande à TMDb ; pose `media_type` sur chaque élément.
  - Les ressources `app/Http/Resources/V1/Catalog` transforment le JSON de TMDb (chaînes vides
    → `null`, URL d'images, âge minimum, bande-annonce…) via `TmdbFormat`. Caster chaque valeur
    (`(int)`, `(string)`, helpers typés) pour que Scramble produise un schéma typé ; pour une liste
    ou un objet, annoter la clé avec `/** @var list<string> */` (Scramble ignore les types de retour
    des helpers statiques).
  - Filtres et tri de la page Recherche (`TitleFilters` : `genre`, `year_from` / `year_to`,
    `min_rating` ; requêtes : trait `Concerns\FiltersTitles`) : envoyés à TMDb pour
    `/{type}/discover` (« Explorer », tris `DiscoverSort`). La recherche par texte de TMDb n'a ni
    filtres ni tri : avec un filtre ou un tri autre que `relevance` (`SearchSort`), l'API lit les
    `SEARCH_DEPTH` (3) premières pages, filtre, trie et renvoie une seule page. Une recherche
    filtrée ne renvoie pas de personnes ; une note minimale exige `TitleFilters::MIN_VOTES` votes.
  - Fiches film / série : `images` ajouté à l'appel TMDb (`include_image_language=fr,en,null`).
    `TmdbFormat::logo` choisit le logo du titre (français, puis anglais, puis sans langue ; pas de
    SVG) → `logo_url` + `logo_aspect_ratio` ; `TmdbFormat::textlessBackdrop` une image de fond sans
    texte → `textless_backdrop_url`. Null quand TMDb n'en a pas (l'appli écrit le titre / prend
    `backdrop_url`). Accueil et recherche (`search`, `search/{type}`, `discover`) : `TitleSummaries`
    ajoute à chaque titre le logo (`logo_url`), une affiche sans texte (`textless_poster_url`,
    carrousel du téléphone) et `summary` (durée ou nombre de saisons, genres, âge conseillé), pour
    que la grande image et le carrousel affichent tout d'un coup ; un appel TMDb par titre (fiche
    avec `images` et classifications), en un lot (`getMany`, cache d'un jour, partagé). Les autres
    listes n'en ont pas (`summary` à null). `GET /catalog/titles?ids=movie:550,tv:1399` (100 au
    plus, `TitleSummaries::titles`, même cache) donne plusieurs titres d'un coup avec ces champs :
    « Ma liste » et « Continuer à regarder » n'ont pas à demander une fiche par titre.
  - Tests : `Http::fake()` + `Http::preventStrayRequests()`, jamais d'appel réel ; payloads dans
    `tests/Feature/Api/V1/Catalog/TmdbFixtures.php`.
- **Lecture** (doc : `docs/lecture.md`) : réglages du profil `audio_language`, `subtitle_language`
  (`App\Support\Languages`) et `skip_intro` / `skip_recap` / `skip_credits` / `skip_preview` ;
  recherches récentes synchronisées (`profile_searches`, 20 par profil, une recherche qui en
  prolonge une récente la remplace). Moments à passer : TheIntroDB (`Playback\SkipSegments`, cache
  24 h). Sous-titres : OpenSubtitles avec la clé Vixeo commune (`Playback\OpenSubtitles`), fichiers
  servis en WebVTT depuis notre cache (quota quotidien d'OpenSubtitles). Les applis ne parlent
  jamais à ces services.
- **Compte TMDb du profil et notes** (doc : `docs/compte-tmdb.md`) : liaison par la page
  d'autorisation de TMDb (`App\Services\TmdbLinks` : jeton de requête en cache, l'appli interroge
  `link/complete`), table `tmdb_links` (une par profil ; le même compte TMDb peut être lié à
  plusieurs profils, de comptes Vixeo différents ; session TMDb **chiffrée**, jamais envoyée aux
  applis). Notes de films, séries et épisodes (0,5 à 10) lues et écrites **sur TMDb**
  (`Tmdb\TmdbAccount`, sans cache) : TMDb est la source, donc elles sont synchronisées partout.
  L'API de TMDb n'accepte pas d'avis écrits : le commentaire facultatif est gardé par Vixeo
  (`tmdb_ratings`, copie des notes par profil tenue par `App\Services\TmdbRatings` : copiée tout de
  suite quand on note dans Vixeo, relue sur TMDb au plus toutes les 10 min, supprimée en déliant).
  Session refusée par TMDb → liaison supprimée, 409.
  `TmdbClient::send` : appels sans cache pour un compte, ne journalise que le chemin.
- **Synchro par profil** (`app/Services/ProfileSync.php`, doc : `docs/synchronisation.md`) :
  un `ProfileItem` par profil + `source` + `item_id`. Ne jamais écrire ces items ailleurs que
  dans `ProfileSync` (révision et dates de changement). Fusion par groupe (favori / progression /
  vu), le plus récent gagne ; chaque écriture prend `++profiles.sync_revision` sous verrou.
- **Connexion TV par QR code** (`App\Services\DeviceLogins`, doc : `docs/connexion-tv.md`) :
  `device_code` secret haché, `user_code` court, 10 min, usage unique ; la page de validation
  est `<WEB_URL>/tv` (appli web).
- **Compte** (doc : `docs/compte.md`) : « mot de passe oublié » par code à 6 chiffres envoyé par
  e-mail (`App\Services\PasswordResets` : haché, 15 min, usage unique, 5 essais ; la réponse ne
  révèle pas si l'adresse a un compte ; la réinitialisation déconnecte tous les appareils),
  appareils connectés (jetons Sanctum de 180 jours, `SANCTUM_EXPIRATION`), suppression du compte
  (`POST /me/delete` avec mot de passe) et export des données par e-mail
  (`App\Services\AccountExport`). Toute nouvelle table liée à un compte doit être supprimée avec
  lui (clé étrangère `cascadeOnDelete`) et ajoutée à l'export.
- **Mises à jour des applis** (doc : `docs/mise-a-jour.md`) : **seule source, les releases
  GitHub** `android-<build>` publiées par la CI de `vixeo_frontend` (`App\Services\GitHubReleases`,
  cache 5 min : APK, SHA-256, notes). Un titre contenant `[obligatoire]` force les versions plus
  anciennes à se mettre à jour. Pas de table ni de commande (`vixeo:release` et `app_releases`
  supprimées, demande du développeur). `GET /app/update` (sans connexion) compare les **builds**
  et répond `up_to_date` / `update_available` / `update_required` ; GitHub en panne → `up_to_date`.
  Pas d'appli iOS pour l'instant : iOS est toujours `up_to_date`. L'installation reste faite par
  le système (Android : APK, pas de Play Store).
- **Avatars de profil** : `resources/avatars/catalog.json`, regroupés par licence (`GET /avatars`,
  triés par nom). Un avatar = pixel art 12×12 (`pixels` + `palette`) ou `image_url`. Le profil
  stocke l'`id` (validé contre le catalogue) : ne jamais renommer ni supprimer un id utilisé.
  Un nouveau profil (y compris le premier, créé à l'inscription) reçoit un avatar du catalogue au
  hasard s'il n'en choisit pas (`AvatarCatalog::randomId`).
  Les avatars fournis sont des créations originales « Vixeo » : n'ajouter des images de vraies
  licences de jeux vidéo qu'avec les droits d'utilisation.
- **Contrôle parental** (doc : `docs/controle-parental.md`) : PIN parental du compte
  (`App\Services\ParentalControl`, 5 essais / 5 min), exigé pour créer / supprimer un profil et
  changer ses restrictions (`Profile::RESTRICTION_FIELDS`). Le catalogue filtre selon le profil de
  l'en-tête `X-Profile-Id` (`ResolveProfile`, `AgeGate`) : toute nouvelle route du catalogue qui
  renvoie des titres doit passer par `AgeGate`.
- **TV en direct** (doc : `docs/tv-direct.md`) : **API neutre** sous `/tv/…` (`throttle:catalog`),
  l'appli ne sait jamais quel fournisseur sert les chaînes. `App\Services\Tv\LiveTv` choisit le
  fournisseur configuré, normalise sa réponse dans une forme unique et traduit ses erreurs en
  messages neutres. Les fournisseurs sont un détail serveur (identifiants, miroirs et signatures
  ne partent jamais vers les applis) ; ajouter ou remplacer un fournisseur ne touche pas l'API
  publique. Le média passe par `App\Services\Tv\StreamProxy` : l'appli reçoit une URL **sur notre
  domaine** (`GET /tv/stream/{token}`, **publique**, autorisée par le jeton signé et non par le
  bearer) et la playlist HLS est réécrite côté serveur. Certains fournisseurs ne publient qu'en
  HTTP clair : le proxy évite le contenu mixte (web HTTPS) et le blocage du trafic non chiffré
  (Android de production). La TV est facultative : sans fournisseur configuré, 503.
- **Flux favoris et dernier flux qui a marché** (doc : `docs/lecture.md`) : par profil et par
  contenu (`profile_streams`, `App\Services\Playback\StreamPreferences`). Le lecteur essaie les
  favoris (❤) d'abord, puis le dernier flux qui a marché, puis la liste normale. Flux morts :
  signalés par l'appli (`POST /catalog/streams/dead`), mis en cache 24 h pour tous les comptes
  (`dead_streams`, `App\Services\Playback\DeadStreams`), retirés de la liste des sources.
- **Jamais de secret dans une URL** (PIN, mot de passe, jeton) : corps de requête ou en-tête.
  Pas de champ secret sur un DELETE (Scramble le documenterait en paramètre d'URL) ; un test
  de `tests/Feature/OpenApiTest.php` le vérifie.
- Sécurité : jamais de mot de passe de service tiers stocké en clair, PIN et secrets hachés,
  ne jamais désactiver la vérification TLS (voir `docs/CONTEXT.md` §6).

## Branches et déploiement

- Ce dépôt est **uniquement l'API** : pas de front, pas d'assets, et **pas de workflow
  GitHub Actions** (décision du développeur). Lancer Pint et Pest localement avant chaque push.

- Pour l'instant, le travail se fait **directement sur `main`** (demande du développeur).
- `main` est déployée automatiquement par Laravel Forge (Push to deploy) :
  voir [`docs/deploiement.md`](docs/deploiement.md).
- Secrets de déploiement : dans les secrets GitHub / Forge, jamais dans le dépôt ni dans
  les variables de l'environnement Claude Code cloud.

## État actuel

- Fait : authentification (inscription, connexion, déconnexion, `me`), profils
  (« Qui regarde ? » : CRUD, 5 max par compte, PIN haché, profil enfant + âge maximum),
  messages d'erreur en français, catalogue TMDb (accueil, listes, genres, fiches film / série /
  saison / personne, recherche groupée et par type, filtres et tri), mis en cache, synchro par profil
  (favoris, progression / « Continuer à regarder », vus ; push / pull avec curseur),
  contrôle parental (PIN parental, âge maximum appliqué au catalogue, sources verrouillées),
  avatars de profil par licence, format des affiches par profil (`poster_format`), connexion TV
  par QR code, mot de passe oublié (code par e-mail), appareils connectés, suppression du compte
  et export des données (RGPD), vérification des mises à jour des applis (releases GitHub).
- Pas encore fait : complément en anglais quand le résumé français manque, déverrouillage
  temporaire d'un titre avec le PIN parental.
- Fait aussi : réglages de lecture du profil et recherches récentes synchronisés, moments à passer
  (TheIntroDB) et sous-titres (OpenSubtitles) prêts pour le lecteur.
- Fait aussi : compte TMDb par profil, notes (films, séries, épisodes) synchronisées avec TMDb,
  commentaires gardés par Vixeo.
- Fait aussi : TV en direct (API neutre, fournisseurs côté serveur) et flux favoris / « dernier flux qui a
  fonctionné » synchronisés par profil, avec cache partagé des flux morts.
- Prochaines étapes possibles : serveurs média de l'utilisateur (Jellyfin / Plex / Emby),
  configuration distante (chaînes FAST).
