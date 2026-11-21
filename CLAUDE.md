# Vixeo — API et site (vixeo_backend)

API REST Laravel de **Vixeo**, media center multiplateforme (Flutter : Android, Android TV,
Windows, puis iOS ; **plus d'appli web**, demande du développeur, 20/11/2026), **et site de présentation** (ancien dépôt `vixeo_web`, fusionné ici). L'appli
cliente est dans le dépôt [`davybrieu/vixeo_frontend`](https://github.com/davybrieu/vixeo_frontend).

**Un seul projet, un seul site Forge, deux domaines** (décision du développeur) :
`api.vixeo.app` → l'API (`routes/api.php` sous `/api/v1`, `routes/api-host.php`),
`www.vixeo.app` → le site (`routes/site.php`, Vue + Inertia SSR, doc : `docs/site.md`),
`vixeo.app` → 301 vers `www` (**redirection du site Forge**, pas de Laravel). Routage par `Host`
dans `bootstrap/app.php` (`App\Support\Hosts`). **Deux adresses, chacune écrite une seule fois**
dans le `.env` : `APP_URL` = le site (`https://www.vixeo.app`), `API_URL` = l'API
(`https://api.vixeo.app`) ; plus de `WEB_URL` (pas d'appli web, pas de CORS à prévoir) ; `config/vixeo.php`
en tire les domaines avec `parse_url()` (`vixeo.urls`, `vixeo.domains`) : ne pas ajouter de clé
qui répète un domaine. `API_URL` vide ou même hôte que `APP_URL` (en local) : un seul hôte, le
site sur `/`, l'API sur `/api/v1`. Les tests tournent avec les domaines
de `phpunit.xml` (`api.vixeo.test`, `www.vixeo.test`) : URL relative = hôte de l'API (`tests/TestCase.php`), le site se
teste avec des URL absolues (`site('/mentions-legales')` dans `tests/Feature/Site/PagesTest.php`).

**Lecture : uniquement les serveurs de Vixeo** (décision du développeur, ne plus poser la
question). Les applis lisent ce que l'API leur renvoie (sources de lecture, TV en direct, sous-titres,
moments à passer) : l'utilisateur n'a **aucun serveur à configurer**. Les flux des films et
séries viennent **uniquement des plug-ins exécutés par l'appli** (dépôts de scrapers JavaScript
façon Nuvio, `vixeo_frontend/docs/scrapers.md`) : le serveur n'a plus de scraper, de
fournisseurs, d'extracteurs (supprimés le 20/11/2026), ni de plug-ins Stremio, de sources, de
flux favoris ou morts (supprimés le 21/11/2026) ; demande du développeur, ne pas les réintroduire. Pas de serveurs multimédias
de l'utilisateur (Jellyfin / Plex / Emby) : ni dans l'API, ni dans l'appli, ni sur le site.

**Lire d'abord [`docs/CONTEXT.md`](docs/CONTEXT.md)** : décisions produit et techniques,
périmètre légal (§3, ce qui est hors périmètre ne doit pas être implémenté), sécurité (§6),
feuille de route (§10).

**Distribution Android** (décision du développeur, ne plus poser la question) : **pas de Play
Store**. L'appli Android (téléphones, tablettes, Android TV / Google TV) est distribuée en
**APK signé** depuis le site (`www.vixeo.app/#installer`, dans ce dépôt). Conséquences :
- chaque version est signée avec la **même clé de release** (keystore hors du dépôt, dans les
  secrets) : sinon Android refuse d'installer la mise à jour par-dessus l'ancienne version ;
- les mises à jour passent par la vérification intégrée à l'appli (`GET /app/update`), pas par
  un store ;
- vérification des développeurs Android par Google : obligatoire hors Play Store dans 4 pays
  depuis le 30/09/2026, prévue partout en 2027 : enregistrer le développeur dans l'Android
  Developer Console avant.

**Distribution Windows** (demande du développeur, 20/11/2026) : installateur Inno Setup
(`vixeo-setup-<build>.exe`, pour l'utilisateur, sans droits d'administrateur, Windows 10 / 11
64 bits) publié en release GitHub `windows-<build>` par la CI de `vixeo_frontend`, téléchargé
depuis le site (`#installer`) ; pas de Microsoft Store ; **non signé** pour l'instant (SmartScreen
prévient, le site et la FAQ l'expliquent).

## Langue

- Échanges, descriptions de PR, documentation et messages destinés à l'utilisateur : **français**.
- Code, noms de classes/variables, messages de commit : anglais.

## Stack

- Laravel 13, PHP 8.3+, Sanctum (jetons Bearer), Scramble (OpenAPI), Pest 4, Pint.
- Site : Vue 3, Inertia 3 (SSR, `php artisan inertia:start-ssr`), Tailwind CSS 4, Vite.
- Redis pour cache et files d'attente. Base de production : **MySQL** sur le serveur Forge actuel
  (constaté au déploiement du 16/11/2026 ; prévu PostgreSQL au départ). Une migration doit passer
  sur MySQL, PostgreSQL et SQLite, et pouvoir être relancée après un échec (MySQL n'annule pas les
  `ALTER` déjà faits) ; MySQL refuse de supprimer le dernier index utilisable par une clé
  étrangère (erreur 1553) : ajouter le nouvel index avant de supprimer l'ancien
  (`Schema::hasIndex`).
- Les tests tournent sur SQLite en mémoire (`phpunit.xml`) ; les relancer aussi sur MySQL /
  MariaDB et PostgreSQL avant un push qui touche aux migrations ou aux requêtes.

## Commandes

```bash
composer install && npm ci
cp .env.example .env && php artisan key:generate
php artisan migrate

./vendor/bin/pest                 # tests (SQLite en mémoire)
./vendor/bin/pint                 # formatage (vérification : pint --test)
composer openapi                  # régénère docs/openapi.json (échoue si un type est inconnu)
                                  # (conteneur cloud, en root : COMPOSER_ALLOW_SUPERUSER=1)
npm run dev                       # site : Vite (SSR compris) ; npm run build = client + SSR
php artisan serve                 # http://localhost:8000 (site sur /, API sur /api/v1),
                                  # doc interactive sur /docs/api (env local)
```

Dans un conteneur Claude Code cloud, PostgreSQL et Redis ne tournent pas par défaut :
`service postgresql start`, `service redis-server start` ; MariaDB (pour MySQL) :
`apt-get install -y mariadb-server && service mariadb start`. Pour tester sur PostgreSQL :
`DB_CONNECTION=pgsql DB_DATABASE=... DB_USERNAME=... DB_PASSWORD=... ./vendor/bin/pest`.

## Conventions

- **Site de présentation** (`docs/site.md`) : groupe de middleware `site` (ni session, ni CSRF,
  **aucun cookie**, aucun service tiers, polices servies par le site). Erreurs : pages Inertia
  `Error` sur le site, JSON sur l'hôte de l'API et sous `/api/*` (`Hosts::isApi`). Textes honnêtes
  (ce que l'appli fait aujourd'hui, « En préparation » sinon), pas de bouton ni page iOS, écarté :
  import IPTV / M3U / Xtream, radio, replays. SSR : pas de `window` / `document` hors `onMounted`.
  Logo et favicon copiés de `vixeo_frontend/brand`. Mobile d'abord, tester à 390, 834, 1440 px.
  **Une seule page de présentation** : l'accueil, avec les sections `#installer` et `#faq` ; pages
  Installer et FAQ et liens courts supprimés (`/installer`, `/faq`, `/telecharger`, `/android`,
  `/tv` → 404 ; `/apk` reste, adresse à taper sur une TV), barre de navigation
  sans liens ni menu burger (logo + « Télécharger », vers `#installer` : plus d'appli web à ouvrir). Ne pas les recréer.
  **Pages de profil** (demande du développeur, 21/11/2026) : `/@pseudo` (`SiteController::member`,
  page `Member`), le lien du bouton « Partager » de l'appli (`share_url` de
  `GET /social/profiles/{pseudo}`) ; profils adultes avec un pseudo, non indexées ; Android les
  ouvre dans l'appli (`/.well-known/assetlinks.json`, empreinte du certificat de release dans
  `vixeo.android.cert_sha256`). Détails : `docs/site.md`.
  **Build committé** (`public/build`, `bootstrap/ssr`, bundle SSR autonome) : après toute
  modification du site, `npm run build` et committer ; `tests/Unit/FrontendBuildTest.php` le vérifie.

- Toutes les routes sous `/api/v1` (`routes/api.php`). **Pas de changement cassant en v1** :
  les applis installées ne se mettent pas à jour en même temps que l'API. Ajouter des champs
  est autorisé ; renommer/supprimer → nouvelle version. Exception décidée par le développeur
  (seul utilisateur pour l'instant, 21/11/2026) : les routes que l'appli n'appelle plus ont été
  supprimées (plug-ins Stremio, sources, `resolve`, flux favoris / morts, `continue-watching`,
  `favorites`, `history`, `catalog/config`, `{type}/discover`, `people/popular`, `episodes/upcoming`,
  compatibilité TMDb, `PATCH /social/profile`) ; ne pas les recréer (la recherche par filtres sans
  texte est revenue le 21/11/2026 sous une autre forme : `GET /catalog/discover`).
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
    `min_rating`, `min_votes` ; requêtes : trait `Concerns\FiltersTitles`). **Filtres sans texte** (demande du
    développeur, 21/11/2026, ex. films d'horreur depuis 2010 notés 8 et plus) :
    `GET /catalog/discover` (films et séries, première page chacun, réponse de la recherche sans
    personnes) et `/catalog/discover/{movie|tv}` (paginé ; `DiscoverRequest`, tri par défaut
    `popularity`, pas de `relevance`) : TMDb discover (genre, années, titres déjà sortis), note
    IMDb sinon TMDb appliquée par l'API (`TmdbCatalog::discover` : TMDb demandé avec 1 point de
    marge, `DISCOVER_DEPTH` = 3 pages TMDb par page, une page peut être courte ou vide). **Note minimale et tri `rating`** (rétablis
    le 21/11/2026, demande du développeur) : **la note d'IMDb, sinon celle de TMDb**, celle que
    montrent les cartes (`TitleFilters::rating`) ; les titres sans note à la fin du tri, écartés par
    `min_rating`. **Nombre minimal de votants** (`min_votes`, demande du développeur, 21/11/2026) :
    les votants de cette même note (IMDb, sinon TMDb, `TitleFilters::votes`), vérifiés par l'API
    (pas demandés à TMDb discover). Les résultats reçoivent leur `summary` (et `imdb_rating`) avant ce filtre. La recherche par
    texte de TMDb n'a ni filtres ni tri : avec un filtre ou un tri autre que `relevance`
    (`SearchSort`), l'API lit les `SEARCH_DEPTH` (3) premières pages, filtre, trie et renvoie une
    seule page. Une recherche filtrée ne renvoie pas de personnes.
  - Fiches film / série : `images` ajouté à l'appel TMDb (`include_image_language=fr,en,null`).
    `TmdbFormat::logo` choisit le logo du titre (français, puis anglais, puis sans langue ; pas de
    SVG) → `logo_url` + `logo_aspect_ratio` ; `TmdbFormat::textlessBackdrop` une image de fond sans
    texte → `textless_backdrop_url`. Null quand TMDb n'en a pas (l'appli écrit le titre / prend
    `backdrop_url`). Accueil et recherche (`search`, `search/{type}`) : `TitleSummaries`
    ajoute à chaque titre le logo (`logo_url`) et `summary` (durée ou nombre de saisons, genres, âge conseillé), pour
    que la grande image et le carrousel affichent tout d'un coup ; un appel TMDb par titre (fiche
    avec `images` et classifications), en un lot (`getMany`, cache d'un jour, partagé). Les
    recommandations d'une fiche (« Vous pourriez aussi aimer », `recommended`) aussi
    (`CatalogTitleController::restricted`), pour le logo des vignettes. Les autres listes n'en ont
    pas (`summary` à null). `GET /catalog/titles?ids=movie:550,tv:1399` (100 au
    plus, `TitleSummaries::titles`, même cache) donne plusieurs titres d'un coup avec ces champs :
    « Ma liste » et « Continuer à regarder » n'ont pas à demander une fiche par titre.
  - **« Voir tout » des fiches et des personnes** (demande du développeur, 20/11/2026) : la fiche
    ne donne que 20 recommandations et 20 membres de la distribution, la page d'une personne
    10 titres « Connu pour » et 100 de filmographie ; les listes entières viennent de
    `GET /catalog/{type}/{id}/recommendations?page=` (paginé comme les listes, recommandations
    TMDb sinon titres similaires, même choix que la fiche), `GET /catalog/{type}/{id}/credits`
    (`crew` : réalisation ou création, `cast` : toute la distribution) et
    `GET /catalog/person/{id}/credits?sort=popular|date` (tous les titres, chacun une fois ; même
    appel TMDb que la page, en cache). Tous passent par `AgeGate` (titre refusé → 403, titres
    filtrés).
  - **Rangées de l'accueil** (demande du développeur) : après les listes de TMDb
    (`CatalogList`, `TmdbCatalog::COMBINED_HOME`) viennent les rangées de `CatalogRows` :
    tendances du jour (juste après celles de la semaine), puis plateformes (`platform_netflix`… :
    fournisseurs de streaming de TMDb pour `TMDB_REGION`, films et séries), chaînes
    (`network_hbo`… : séries), licences / studios (`studio_marvel`… : films) et langues
    (`language_fr`, `anime`…). Presque toutes passent par `discover` ; les identifiants TMDb sont
    dans `CatalogRows` (une ligne par rangée). Leurs premières pages sont demandées ensemble
    (`TmdbClient::getEach`), une rangée que TMDb ne donne pas ou vide est omise. Elles sont
    **légères** (`light`) : pas de logo ni de `summary` par titre (`TitleSummaries` coûterait un
    appel par titre), l'appli lit la fiche. `list` d'une rangée (`SectionResource`) = son nom
    pour « Voir tout » : `GET /catalog/{type}/lists/{list}` accepte les listes de TMDb et celles
    de `CatalogRows`. **Ne jamais renommer un `list` publié** : les applis gardent l'ordre et les
    rangées masquées par clé (`{type}_{list}`). L'accueil d'un seul type (`?type=`, anciennes
    applis) reste inchangé.
  - Tests : `Http::fake()` + `Http::preventStrayRequests()`, jamais d'appel réel ; payloads dans
    `tests/Feature/Api/V1/Catalog/TmdbFixtures.php`.
- **Notes IMDb et TMDb** (doc : `docs/notes.md`, demande du développeur, 21/11/2026) : les
  fiches film / série et chaque épisode d'une saison ont `ratings` = `{imdb: {id, rating, votes}
  | null, tmdb: {rating, votes}}` (`RatingsResource`). **IMDb vient de ses jeux de données**
  (https://datasets.imdbws.com, usage personnel et non commercial) : `php artisan imdb:import`
  **toutes les heures** compare le `Last-Modified` des fichiers (requêtes `HEAD`) à celui du
  dernier import (table `imdb_imports`) et n'importe que s'il a changé (`--force` : toujours)
  (`App\Services\Imdb\ImdbDatasets` : `title.ratings` → table
  `imdb_ratings`, `title.episode` → `imdb_episodes`, épisodes notés seulement ; lecture en flux,
  lots de 2 000, anciennes lignes supprimées après un import complet seulement). Liaison par
  l'identifiant IMDb (`App\Services\Imdb\ImdbRatings`) : `imdb_id` du film / `external_ids` de
  la série, sinon les *External IDs* de TMDb ; épisode : série + saison + numéro dans
  `imdb_episodes`, sinon ses *External IDs* (épisodes diffusés seulement). Sans note IMDb, les
  applis affichent celle de TMDb. **Plus de MDBList, de `GET /catalog/ratings` ni de réglage
  `rating_source`** (supprimés le 21/11/2026, demande du développeur ; ne pas les réintroduire).
  Les titres des listes qui ont un `summary` (accueil, recherche, recommandations,
  `catalog/titles`) ont aussi `ratings` (`TitleSummaries` : une requête d'IMDb par réponse) ;
  ailleurs `ratings: null` (l'appli montre `vote_average`) : aucune note demandée par carte.
- **Lecture** (doc : `docs/lecture.md`) : réglages du profil `audio_language`, `subtitle_language`
  (`App\Support\Languages`) et `skip_intro` / `skip_recap` / `skip_credits` / `skip_preview` ;
  recherches récentes synchronisées (`profile_searches`, 20 par profil, une recherche qui en
  prolonge une récente la remplace). Moments à passer : TheIntroDB (`Playback\SkipSegments`, cache
  24 h). Sous-titres : OpenSubtitles avec la clé Vixeo commune (`Playback\OpenSubtitles`), fichiers
  servis en WebVTT depuis notre cache (quota quotidien d'OpenSubtitles). Les applis ne parlent
  jamais à ces services.
- **Avis du profil** (doc : `docs/avis.md`) : note (0,5 à 10) et commentaire facultatif par
  profil, sur un film, une série ou **un épisode** (avis d'épisodes supprimés le 20/11/2026 puis
  **rétablis le 21/11/2026**, demande du développeur : `PUT / DELETE
  …/ratings/tv/{id}/season/{s}/episode/{e}`, `GET …/ratings/tv/{id}/season/{s}`, filtre
  `type=episode`, migration `restore_episode_reviews`)
  (`tmdb_ratings`, `App\Services\TmdbRatings`), **gardés par Vixeo seulement**. **Plus de
  liaison d'un compte TMDb ni de synchronisation des notes** (supprimées, demande du développeur,
  20/11/2026 : table `tmdb_links` et colonne `account_id` supprimées ; ne pas les réintroduire).
  Routes sous `/profiles/{id}/tmdb/ratings…` (nom historique). Pour les anciennes applis,
  `GET /profiles/{id}/tmdb` répond toujours « non lié » et `POST …/tmdb/link` 410
  (`TmdbAccountController`, obsolète).
- **Synchro par profil** (`app/Services/ProfileSync.php`, doc : `docs/synchronisation.md`) :
  un `ProfileItem` par profil + `source` + `item_id`. Ne jamais écrire ces items ailleurs que
  dans `ProfileSync` (révision et dates de changement). Fusion par groupe (« Ma liste » =
  `favorite`, progression, vu, « Favoris » ❤ = `liked` (films, séries ; rétablis le 20/11/2026,
  demande du développeur), personnes suivies = `followed` (items `person:<id>`, type `person`)),
  le plus récent gagne ; listes publiques d'un membre : `/social/profiles/{username}/liked` et
  `/people` ; chaque écriture prend `++profiles.sync_revision` sous verrou.
- **Recherche de membres** (`GET /social/search`, `SocialProfileController`) : profils adultes
  avec un pseudo, **jamais le profil actif lui-même** (demande du développeur, 20/11/2026 : on
  ouvre son propre profil par la navigation, « Profil »). Les listes de comptes (recherche,
  abonnés, abonnements) ne donnent que l'identité (`identity`) ; les compteurs (une sous-requête
  chacun) seulement pour la page d'un membre (`publicProfile(withCounts: true)`).
  Biographie d'un profil : **160 caractères au plus** (`Profile::BIO_MAX_LENGTH`, demande du
  développeur, 21/11/2026), modifiée par `PUT /profiles/{id}`.
- **Requêtes** : hors production, `Model::preventLazyLoading()` (`AppServiceProvider`) fait
  échouer une relation lue dans une boucle sans `with()` (N+1) : les tests l'attrapent.
- **Connexion TV par code** (`App\Services\DeviceLogins`, doc : `docs/connexion-tv.md`) :
  `device_code` secret haché, `user_code` court, 10 min, usage unique ; le code se tape dans
  l'appli du téléphone (« Paramètres » → « Connecter une TV »), **plus de QR code** (demande du
  développeur) ni lien (`verification_url(_complete)` supprimés, 20/11/2026).
- **Compte** (doc : `docs/compte.md`) : « mot de passe oublié » par code à 6 chiffres envoyé par
  e-mail (`App\Services\PasswordResets` : haché, 15 min, usage unique, 5 essais ; la réponse ne
  révèle pas si l'adresse a un compte ; la réinitialisation déconnecte tous les appareils),
  changement d'adresse e-mail (`POST /me/email`) et de mot de passe (`POST /me/password`,
  déconnecte les autres appareils), tous deux confirmés par le mot de passe,
  appareils connectés (jetons Sanctum de 180 jours, `SANCTUM_EXPIRATION`), suppression du compte
  (`POST /me/delete` avec mot de passe) et export des données par e-mail
  (`App\Services\AccountExport`). Toute nouvelle table liée à un compte doit être supprimée avec
  lui (clé étrangère `cascadeOnDelete`) et ajoutée à l'export.
- **Mises à jour des applis** (doc : `docs/mise-a-jour.md`) : **seule source, les releases
  GitHub** `android-<build>` (APK) et `windows-<build>` (installateur `.exe`) publiées par la CI
  de `vixeo_frontend` (`App\Services\GitHubReleases::for(AppPlatform)`, cache 5 min par
  plateforme : fichier, SHA-256, notes ; `App\Services\LatestReleases` pour le site). Un titre
  contenant `[obligatoire]` force les versions plus anciennes de sa plateforme à se mettre à jour
  (pas de build minimum dans le `.env` : demande du développeur, 20/11/2026). Pas de table ni de commande (`vixeo:release` et `app_releases`
  supprimées, demande du développeur). `GET /app/update` (sans connexion) compare les **builds**
  et répond `up_to_date` / `update_available` / `update_required` ; GitHub en panne → `up_to_date`.
  Pas d'appli iOS pour l'instant : iOS est toujours `up_to_date`. L'installation reste faite par
  un installateur (Android : APK, pas de Play Store ; Windows : installateur lancé en silencieux
  par l'appli, qui se ferme puis redémarre).
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
  `GET /tv/channels/{id}` = une chaîne (page de la chaîne). **Guide des programmes** :
  `GET /tv/channels/{id}/epg` (`{data, available}`), fichiers XMLTV de `TV_EPG_URLS` (virgules,
  le préféré d'abord ; `App\Services\Tv\Epg`), téléchargés par `php artisan tv:epg` toutes les
  6 h (hors requêtes), 48 h de programmes, une entrée de cache par chaîne ; rattachement par
  `epg_channel_id` ou par nom (`ChannelName::key`, sur le nom nettoyé par `ChannelName::clean` :
  « 6TER .c », « TF1 FHD » → « 6TER », « TF1 », aussi le nom affiché), le premier fichier qui a des programmes
  l'emporte. **Logos** : candidats dans l'ordre bibliothèque `tv-logo/tv-logos`
  (`LogoLibrary`, API GitHub une fois par jour, `TV_LOGOS_DIRECTORIES`), icône du guide, logo du
  fournisseur, puis secours sur un nom plus court (`ChannelName::fallbackKeys`, logos seulement,
  jamais le programme) ; `php artisan tv:match` liste les chaînes sans logo ni programme ; jamais leur adresse, mais `GET /tv/logo/{hash}` sur notre domaine
  (`App\Services\Tv\ChannelLogos` : hash SHA-256 stable des candidats, le relais sert le premier
  qui donne une image ; l'image est **relayée, jamais stockée** sur le serveur (décision du
  développeur), vérifiée, gardée 7 jours par l'appli (`Cache-Control`) ; publique,
  sans logo : `null`).
- **Notifications push** (doc : `docs/notifications.md`, demande du développeur : un serveur de
  push, pas de notifications programmées par l'appli) : **Firebase Cloud Messaging**, API HTTP v1
  (`App\Services\Push\Fcm`, compte de service `FCM_CREDENTIALS` hors du dépôt : chemin du JSON
  ou `base64:…` ; vide = aucune notification). L'appli s'inscrit (`PUT /push/device` : jeton FCM,
  plateforme, `new_episodes`, `utc_offset`, profil de `X-Profile-Id` ; `GET`, `DELETE`,
  `POST /push/device/test`) ; table `push_devices` (**une ligne par jeton Sanctum**, supprimée à
  la déconnexion ; jeton FCM **chiffré**, retrouvé par son SHA-256, jamais renvoyé ; oubliée quand
  FCM ne la connaît plus). `vixeo:push-episodes` (planifiée toutes les 15 min, aussi à la main,
  `--user=`, `--dry-run`) : `EpisodePush` annonce les épisodes sortis depuis moins de 48 h des
  séries de « Ma liste » (`UpcomingEpisodes::availableBetween`), jamais avant l'ajout de la série
  ni l'activation des alertes, jamais deux fois (`episode_notifications`), pas la nuit sur
  l'appareil (23 h – 8 h), une notification par série (canal Android `episodes`, `data.path` =
  fiche). `vixeo:push "texte" --all | --user=…` (`SendPush`) : un message écrit à la main
  (canal `general`). Heure de sortie estimée (`UpcomingEpisodes`), jamais trop tôt : streaming
  08:00 UTC, sinon 21:00 dans le pays de la série.
- **Jamais de secret dans une URL** (PIN, mot de passe, jeton) : corps de requête ou en-tête.
  Pas de champ secret sur un DELETE (Scramble le documenterait en paramètre d'URL) ; un test
  de `tests/Feature/OpenApiTest.php` le vérifie.
- Sécurité : jamais de mot de passe de service tiers stocké en clair, PIN et secrets hachés,
  ne jamais désactiver la vérification TLS (voir `docs/CONTEXT.md` §6).

## Branches et déploiement

- Ce dépôt contient l'API et le site de présentation, **pas de workflow GitHub Actions**
  (décision du développeur). Lancer Pint, Pest et `npm run build` localement avant chaque push.

- Pour l'instant, le travail se fait **directement sur `main`** (demande du développeur).
- `main` est déployée automatiquement par Laravel Forge (Push to deploy, un seul site pour
  les deux domaines, daemon SSR) : voir [`docs/deploiement.md`](docs/deploiement.md).
- Secrets de déploiement : dans les secrets GitHub / Forge, jamais dans le dépôt ni dans
  les variables de l'environnement Claude Code cloud.

## État actuel

- Fait aussi : site de présentation sur `www.vixeo.app`, dans ce projet : une page d'accueil
  (installation Android / Android TV / Windows, fonctionnalités, FAQ), mentions légales,
  confidentialité, pages d'erreur, sitemap.
  À faire avant la mise en ligne : mentions légales dans le `.env` de production.
- Fait : authentification (inscription, connexion, déconnexion, `me`), profils
  (« Qui regarde ? » : CRUD, 5 max par compte, PIN haché, profil enfant + âge maximum),
  messages d'erreur en français, catalogue TMDb (accueil, listes, genres, fiches film / série /
  saison / personne, recherche groupée et par type, filtres et tri), mis en cache, synchro par profil
  (favoris, progression / « Continuer à regarder », vus ; push / pull avec curseur),
  contrôle parental (PIN parental, âge maximum appliqué au catalogue, sources verrouillées),
  avatars de profil par licence, format des affiches par profil (`poster_format`), connexion TV
  par code, mot de passe oublié (code par e-mail), appareils connectés, suppression du compte
  et export des données (RGPD), vérification des mises à jour des applis (releases GitHub).
- Pas encore fait : complément en anglais quand le résumé français manque, déverrouillage
  temporaire d'un titre avec le PIN parental.
- Fait aussi : réglages de lecture du profil et recherches récentes synchronisés, moments à passer
  (TheIntroDB) et sous-titres (OpenSubtitles) prêts pour le lecteur.
- Fait aussi : avis par profil (notes des films, séries et épisodes, commentaires), gardés par
  Vixeo (plus de compte TMDb lié).
- Fait aussi : TV en direct (API neutre, fournisseurs côté serveur, guide des programmes XMLTV,
  logos de la bibliothèque tv-logos).
- Fait aussi : notifications push (FCM) des nouveaux épisodes des séries de « Ma liste »
  (tâche planifiée) et messages envoyés à la main (`vixeo:push`).
- Fait aussi : notes IMDb (jeux de données vérifiés toutes les heures, importés quand IMDb les
  met à jour, `imdb:import`) et TMDb, avec
  leurs votants, sur les fiches et les épisodes (MDBList et `rating_source` supprimés).
- Supprimé le 21/11/2026 (inutilisé par l'appli) : plug-ins Stremio côté serveur et sources
  (tables `addons`, `profile_streams`, `dead_streams` supprimées), routes de compatibilité.
- Prochaine étape possible : configuration distante (chaînes FAST).
- Écarté : serveurs multimédias de l'utilisateur (Jellyfin / Plex / Emby), la lecture passe par
  les serveurs de Vixeo.
