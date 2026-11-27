# Vixeo — API Laravel uniquement (vixeo_backend)

API REST Laravel de **Vixeo**, media center multiplateforme. Le backend ne sert que l'API, Reverb,
les tâches planifiées et les services métier. L'application Flutter (Android, Android TV, Windows
et Web) est dans [`davybrieu/vixeo_frontend`](https://github.com/davybrieu/vixeo_frontend).

**Deux domaines, deux dépôts / responsabilités** : `api.vixeo.app` sert l'API Laravel
(`routes/api.php` sous `/api/v1`, `routes/api-host.php`) ; `app.vixeo.app` sert le build Flutter Web
publié depuis la branche `web-dist` de `vixeo_frontend`. Aucun site, Inertia, SSR ou build web
n'est servi par Laravel. `APP_URL` et `API_URL` pointent vers `https://api.vixeo.app` ;
`VIXEO_APP_URL=https://app.vixeo.app` est uniquement la base des liens de profils publics.
CORS doit autoriser l'origine du frontend Web. Les erreurs Laravel sont toujours JSON.

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
- Aucun frontend dans ce dépôt : ni Vue, ni Inertia, ni Vite, ni SSR. Flutter Web est compilé dans `vixeo_frontend`.
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
composer install
cp .env.example .env && php artisan key:generate
php artisan migrate

./vendor/bin/pest                 # tests (SQLite en mémoire)
./vendor/bin/pint                 # formatage (vérification : pint --test)
composer openapi                  # régénère docs/openapi.json (échoue si un type est inconnu)
                                  # (conteneur cloud, en root : COMPOSER_ALLOW_SUPERUSER=1)
php artisan serve                 # API sur http://localhost:8000/api/v1,
                                  # doc interactive sur /docs/api (env local)
```

Dans un conteneur Claude Code cloud, PostgreSQL et Redis ne tournent pas par défaut :
`service postgresql start`, `service redis-server start` ; MariaDB (pour MySQL) :
`apt-get install -y mariadb-server && service mariadb start`. Pour tester sur PostgreSQL :
`DB_CONNECTION=pgsql DB_DATABASE=... DB_USERNAME=... DB_PASSWORD=... ./vendor/bin/pest`.

## Conventions

, JSON sur l'hôte de l'API et sous `/api/*` (`Hosts::isApi`). Textes honnêtes
  (ce que l'appli fait aujourd'hui, « Bientôt disponible » sinon), écarté : import IPTV / M3U /
  Xtream, radio, replays. **Mac, iPhone et iPad** : listés même avant leur sortie, onglets
  d'installation « Bientôt disponible », sans bouton (demande du développeur, 21/11/2026) ; les
  onglets passent sur plusieurs lignes selon la place, jamais de défilement de côté. Un seul bouton en haut de l'accueil, « Installer
  Vixeo » (vers `#installer`) ; FAQ réduite aux questions les plus utiles ; **pas de mention TMDb**
  dans le pied de page ni sur l'accueil (seulement dans les mentions légales). SSR : pas de `window` / `document` hors `onMounted`.
  Logo et favicon copiés de `vixeo_frontend/brand`. Mobile d'abord, tester à 390, 834, 1440 px.
  **Une seule page de présentation** : l'accueil, avec les sections `#installer` et `#faq` ; pages
  Installer et FAQ et liens courts supprimés (`/installer`, `/faq`, `/telecharger`, `/android`,
  `/tv` → 404 ; `/apk` reste, adresse à taper sur une TV), barre de navigation
  sans liens ni menu burger (logo + « Télécharger », vers `#installer` : plus d'appli web à ouvrir). Ne pas les recréer.
  **Pages de profil** (demande du développeur, 21/11/2026) : `/pseudo`, **sans @, comme Twitter**
  (`SiteController::member`, page `Member`, route `/{username}` déclarée **en dernier**), le lien du
  bouton « Partager » de l'appli (`share_url` de `GET /social/profiles/{pseudo}`) ; un pseudo n'est
  jamais une page du site ni de l'appli (`App\Support\Usernames::RESERVED`, à compléter pour toute
  nouvelle page d'un seul mot) ; « Ouvrir dans Vixeo » (Android) ouvre `/membre/pseudo` dans l'appli ; profils adultes avec
  un pseudo, non indexées ; Android les ouvre dans l'appli (`/.well-known/assetlinks.json`,
  empreinte du certificat de release dans `vixeo.android.cert_sha256`). Détails : `docs/site.md`.
  **Build committé** (`public/build`, `bootstrap/ssr`, bundle SSR autonome) : après toute
  modification du site, `npm run build` et committer ; `tests/Unit/FrontendBuildTest.php` le vérifie.

- **Jamais d'ancienne adresse** (demande du développeur, 23/11/2026) : quand une page du site ou de
  l'appli, ou une route, change d'adresse, l'ancienne est supprimée (ni redirection ni alias ; elle
  répond 404).
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
  - **Accueil préparé à l'avance** (demande du développeur, 23/11/2026 : l'accueil attendait TMDb,
    ~180 requêtes, à chaque expiration du cache) : `php artisan catalog:warm` (`CatalogWarmer`,
    planifiée toutes les 30 min, plus souvent que les listes n'expirent) redemande à TMDb les listes
    et rangées de l'accueil (`TmdbClient::refreshing()` : la réponse en cache est remplacée, gardée
    si TMDb échoue) et les fiches des titres résumés (`TmdbCatalog::summarized`) quand elles
    manquent, toutes redemandées toutes les 12 h (cache d'un jour ; `--details` : tout de suite).
    L'accueil répond alors sans appeler TMDb. Pas préparés : classifications des profils avec
    limite d'âge (`AgeGate`), titres des rangées légères. Tests : `Catalog/CatalogWarmTest.php`.
  - `TmdbCatalog` : ce qu'on demande à TMDb ; pose `media_type` sur chaque élément.
  - Les ressources `app/Http/Resources/V1/Catalog` transforment le JSON de TMDb (chaînes vides
    → `null`, URL d'images, âge minimum, bande-annonce…) via `TmdbFormat`. Caster chaque valeur
    (`(int)`, `(string)`, helpers typés) pour que Scramble produise un schéma typé ; pour une liste
    ou un objet, annoter la clé avec `/** @var list<string> */` (Scramble ignore les types de retour
    des helpers statiques).
  - Filtres et tri de la page Recherche (`TitleFilters` : `genre`, `year_from` / `year_to`,
    `min_rating` / `max_rating`, `min_votes` / `max_votes` : des intervalles, bornes facultatives,
    curseurs à deux poignées des applis ; requêtes : trait `Concerns\FiltersTitles`). **Filtres sans texte** (demande du
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
    « À voir » et « Continuer à regarder » n'ont pas à demander une fiche par titre.
  - **« Distribution » et « Équipe technique »** (demande du développeur, 25/11/2026 : deux
    rangées sur la fiche) : la fiche donne `crew` (`App\Services\Tmdb\TitleCrew`,
    `CrewMemberResource`) : tous sauf les acteurs, **une fois chacun avec tous ses métiers en
    français** (`job` « Réalisation, Scénario », `jobs`), les plus importants d'abord (création,
    réalisation, scénario, musique, photographie, montage, production…, puis par département ;
    série : créateurs d'abord, puis le plus d'épisodes), 20 au plus ; `directors` / `creators`
    restent pour les anciennes applis. « Voir tout » : `credits?group=crew` (20 par page).
  - **« Bandes-annonces »** (demande du développeur, 25/11/2026 : une rangée sur la fiche à la place
    du bouton) : la fiche donne `trailers` (`TmdbFormat::trailers` : toutes les bandes-annonces et
    teasers YouTube de TMDb en français, anglais ou sans langue, le français d'abord, les
    bandes-annonces avant les teasers, officielles d'abord, les plus récentes d'abord ; 20 au plus ;
    `VideoResource` : `type`, `thumbnail_url` (image de YouTube `hqdefault`), `published_at`) ;
    `trailer` (la première) reste pour les anciennes applis.
  - **« Voir tout » des fiches et des personnes** (demande du développeur, 20/11/2026) : la fiche
    ne donne que 20 recommandations et 20 membres de la distribution, la page d'une personne
    **20 titres « Connu pour » et 20 de filmographie** (24/11/2026 ; `TmdbCatalog::PERSON_PAGE`, un
    seul lot de requêtes TMDb, `TitleSummaries::addAndRate`) ; les listes entières viennent, **par
    pages de 20** (`?page=`, `meta` : `page`, `total_pages`, `total_results`), de
    `GET /catalog/{type}/{id}/recommendations?page=` (paginé comme les listes, recommandations
    TMDb sinon titres similaires, même choix que la fiche), `GET /catalog/{type}/{id}/credits?page=`
    (`crew` : réalisation ou création, sur la première page ; `cast` : 20 membres de la
    distribution par page, `CreditsResource::PAGE`) et
    `GET /catalog/person/{id}/credits?sort=popular|date&page=` (chaque titre une fois ; même
    appel TMDb que la page, en cache ; note IMDb pour les 20 de la page seulement). Tous passent par
    `AgeGate` (titre refusé → 403, titres filtrés : une page peut être plus courte).
  - **Pages de 20** (demande du développeur, 24/11/2026 : « éviter les trop grosses requêtes ») :
    toute liste longue se lit **20 éléments à la fois**, les applis demandant la page suivante en
    défilant : catalogue (listes, recherche, recommandations, distribution, titres d'une personne),
    listes d'un membre (`SocialProfileController::PER_PAGE` : « À voir », J'aime, vus, personnes,
    avis, abonnés, abonnements, listes), mes listes (`GET /social/lists?page=`), avis d'un titre,
    « Mes avis » (`per_page=20`), fil « Abonnements » et activité d'un profil (`Feed::PAGE`),
    « Pour toi », notifications (`NotificationController::PER_PAGE`), commentaires
    (`Comments::PAGE`), messages d'une conversation (`Conversations::PAGE`). Une nouvelle liste
    longue suit la même règle.
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
- **Affiches, arrière-plans et logos choisis par un profil** (demande du développeur,
  25/11/2026 ; doc : `docs/images-des-titres.md`) : `GET /catalog/{type}/{id}/images` (images de
  TMDb déjà jointes à la fiche, 60 par sorte, `default` et `chosen`) et
  `PUT /profiles/{id}/artwork/{type}/{id}` (`poster`, `backdrop`, `logo` : un chemin de la liste ou
  null). `App\Services\TitleArtworks`, table `title_artworks` ; le choix est appliqué **par l'API**
  (`TitleArtworks::chosen`, une requête par réponse) dans `MediaItemResource`, `MovieResource` et
  `ShowResource` : toute nouvelle ressource qui donne les images d'un titre au profil actif doit
  l'appliquer aussi.
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
  applis affichent celle de TMDb. **Plus de réglage `rating_source`** (supprimé le 21/11/2026).
  **MDBList revient pour la fenêtre « Notes » seulement** (le développeur, 22/11/2026) :
  `GET /catalog/ratings?ids=movie:550` (`App\Services\Ratings\MdbListRatings`,
  `TitleRatingsResource`) donne Trakt, Letterboxd, Rotten Tomatoes (critiques et public),
  Metacritic et MyAnimeList, chacun sur son échelle, **appelé seulement à l'ouverture de la
  fenêtre** (jamais par carte ni par fiche) ; cache commun de 3 jours (1 jour pour un titre
  inconnu), lots de 200, budget `MDBLIST_DAILY_REQUESTS`, pause après un refus ou une panne ;
  clé `MDBLIST_API_KEY` (vide : `available: false`). Tests :
  `tests/Feature/Api/V1/Catalog/MdbListRatingsTest.php`.
  **Toutes les listes de titres ont `ratings`** (demande du développeur, 21/11/2026 : la note
  IMDb partout, TMDb seulement en secours) : avec le `summary` (`TitleSummaries::add` : accueil,
  recherche, discover, recommandations et leur « Voir tout », « Voir tout » des rangées, « Connu
  pour », `catalog/titles`) ou seule (`TitleSummaries::ratings`, identifiant IMDb gardé 90 jours,
  sinon tiré d'une fiche déjà en cache, sinon des seuls **External IDs** de TMDb, le 24/11/2026 :
  filmographie, `person/{id}/credits`) ; une requête d'IMDb par réponse. Sauf les rangées légères
  de l'accueil (l'appli les complète par `catalog/titles`) : `ratings: null`, l'appli n'affiche
  alors aucune note. Aucune note demandée par carte.
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
  (`tmdb_ratings`, `App\Services\TmdbRatings`), **gardés par Vixeo seulement**. **Un nouvel avis
  marque le film, la série ou l'épisode vu** (par `ProfileSync`, sauf s'il l'est déjà ; demande du
  développeur, 23/11/2026). **Plus de
  liaison d'un compte TMDb ni de synchronisation des notes** (supprimées, demande du développeur,
  20/11/2026 : table `tmdb_links` et colonne `account_id` supprimées ; ne pas les réintroduire).
  Routes sous `/profiles/{id}/tmdb/ratings…` (nom historique).
- **Synchro par profil** (`app/Services/ProfileSync.php`, doc : `docs/synchronisation.md`) :
  un `ProfileItem` par profil + `source` + `item_id`. Ne jamais écrire ces items ailleurs que
  dans `ProfileSync` (révision et dates de changement). Fusion par groupe (« À voir » =
  `favorite`, progression, vu, « J'aime » ❤ = `liked` (films, séries ; rétablis le 20/11/2026,
  demande du développeur ; un J'aime, pas des favoris), personnes suivies = `followed` (items `person:<id>`, type `person`)),
  le plus récent gagne ; listes publiques d'un membre : `/social/profiles/{username}/liked` et
  `/people` ; chaque écriture prend `++profiles.sync_revision` sous verrou.
- **Recherche de membres** (`GET /social/search`, `SocialProfileController`) : profils adultes
  avec un pseudo, **jamais le profil actif lui-même** (demande du développeur, 20/11/2026 : on
  ouvre son propre profil par la navigation, « Profil »). Les listes de comptes (recherche,
  abonnés, abonnements) ne donnent que l'identité (`identity`) ; les compteurs (une sous-requête
  chacun) seulement pour la page d'un membre (`publicProfile(withCounts: true)`), dont
  `episodes_count` et `watch_minutes` (temps de visionnage : durées des films et épisodes de
  `diary_entries`, demande du développeur, 25/11/2026, cartes de chiffres de la page de profil).
  Biographie d'un profil : **160 caractères au plus** (`Profile::BIO_MAX_LENGTH`, demande du
  développeur, 21/11/2026), modifiée par `PUT /profiles/{id}`.
- **Fonctions sociales** (doc : `docs/social.md`, demande du développeur, 21/11/2026, comme
  Twitter) : **réseaux sociaux** du profil (`social_links` : Instagram, X, TikTok, YouTube,
  Letterboxd, Threads, Bluesky, site web ; `App\Support\SocialLinks`), **compte privé**
  (`is_private` : listes, avis, abonnés et abonnements pour les abonnés acceptés seulement, `can_view`,
  403 « Ce compte est privé. ») avec **demandes d'abonnement** (`profile_follows.accepted_at` nul ;
  `GET /social/requests`, accepter / refuser ; écritures par `App\Services\Social\Follows`
  uniquement ; `following()` / `followers()` ne comptent que les acceptés), **J'aime sur les avis**
  (`review_likes`, `PUT|DELETE /social/reviews/{review_id}/like`), **notifications**
  (`social_notifications` : `follow`, `follow_request`, `follow_accepted`, `review_like` ;
  `GET /notifications`, `/count`, `POST /notifications/read`, `GET /notifications/episodes` :
  prochains épisodes de « À voir »), envoyées aussi en push après la réponse (réglage `social`
  de `PUT /push/device`, canal Android `social`).
- **Réseau social des films et séries** (demande du développeur, 23/11/2026, « comme Twitter mais
  sans tweet de texte » ; doc : `docs/social.md`, `docs/moderation.md`) : **toute publication porte
  sur une œuvre** (avis, visionnage, J'aime, liste), la conversation est dans les commentaires.
  Services `App\Services\Social\…` : `Audience` (qui voit quoi : public, abonnés d'un compte
  privé, jamais un profil bloqué dans un sens ou l'autre, rien d'un profil suspendu ni d'un contenu
  masqué ; `constrain()` pour une requête), `SocialCards` (formes JSON communes : profil, avis,
  liste, commentaire), `TitleSnapshots` (ce qu'on garde d'un titre, depuis le cache de
  `TitleSummaries`), `Activities` + `Feed` (`social_activities` : avis, visionnages, J'aime (`liked`,
  `profile_item_id`, écrits et retirés par `ProfileSync`) et listes ; « Fil d'actualité »
  `GET /social/feed` : visionnages et J'aime regroupés par titre ; « Activité » d'un profil
  `GET /social/profiles/{pseudo}/activity`, chaque action avec sa date), `Lists` (titre,
  description, 100 titres **dans l'ordre choisi** ; plus de `kind` top / liste depuis le 23/11/2026 :
  un « Top 10 » est une liste comme les autres ; J'aime), `Comments` (avis et
  listes, spoilers), `Diary` (visionnages **écrits par `ProfileSync`** quand un titre devient vu, bilan
  `stats`), `TitleSocial` (`GET /social/titles/{type}/{id}` : abonnés qui l'ont vu et leur moyenne,
  avis populaires, listes), `Taste` (compatibilité, `GET /social/suggestions`), fil « Pour toi » (`Feed::forYou`,
  `GET /social/feed/for-you?page=`, onglet par défaut des applis) à côté de « Abonnements », `Reports`
  (signalements, `vixeo:reports`, `vixeo:moderate … hide|dismiss|restore --reason=`),
  `SharedCards` (titre, avis ou liste envoyé dans un message, `share` de `POST /messages/{id}`).
  Bloquer (`…/block`) agit **partout** (même table que la messagerie, abonnements supprimés) ;
  masquer (`…/mute`) retire seulement du fil. Profil : `rate_after_watching` (note proposée en fin de lecture, vrai par défaut), `onboarded_at`
  (premier lancement ; les profils existants l'ont déjà), `suspended_at`. Contrôleurs : trait
  `Concerns\ResolvesSocialProfiles` (`viewer`, `author`, `visibleMember`, `assertVisible`).
  **« À voir »** (anciennement « Ma liste », demande du développeur, 23/11/2026) = le groupe
  `favorite` de la synchro, montré par les applis en tête des listes, jamais supprimé, sans titre
  ni description ; pas une ligne de `profile_lists`. Onglets de la page d'un profil : Activité,
  Listes, Avis, Aimés, Vus (`GET /social/profiles/{pseudo}/watched`) ; **plus de « Journal »**
  (supprimé le 23/11/2026 : ni `GET …/diary` ni `DELETE /profiles/{id}/diary/{entrée}` ;
  `diary_entries` reste la source des visionnages, du fil et du bilan).
  Tests : `tests/Feature/Api/V1/SocialNetworkTest.php`.
- **Requêtes** : hors production, `Model::preventLazyLoading()` (`AppServiceProvider`) fait
  échouer une relation lue dans une boucle sans `with()` (N+1) : les tests l'attrapent.
- **Messagerie** (doc : `docs/messagerie.md`, le développeur, 22/11/2026, comme Instagram) :
  conversations directes (une **demande** d'abord, acceptée ou refusée), **groupes** (administrateurs,
  31 membres), photos et vidéos (disque privé, servies aux membres seulement), accusés de lecture,
  suppression d'un message pour tous, blocage ; profils adultes avec un pseudo. Écritures :
  `App\Services\Messaging\Conversations` **uniquement** (modèles `Conversation`,
  `ConversationMember`, `DirectMessage`, `MessageAttachment`, `MessageRead`, `MessageBlock` ; tables
  `direct_*`), listes en un nombre fixe de requêtes. Un groupe n'a pas de paire de profils
  (`profile_low_id` / `profile_high_id` nuls) : supprimer un profil ne supprime pas le groupe des
  autres. Supprimer un profil ou un compte passe d'abord par `Conversations::forgetProfiles`
  (fichiers, nouvel administrateur). **Temps réel : Laravel Reverb** (doc : `docs/reverb.md`) :
  canal privé `messages.{id}` (`routes/channels.php`, `POST /api/broadcasting/auth`),
  `App\Events\ConversationChanged` (`message.sent`, `.deleted`, `.read`, `.updated`) envoyé après
  la réponse ; sans clé Reverb, rien n'est envoyé. Push FCM (canal `social`) aux autres membres.
  Dates en ISO 8601 avec le fuseau.
- **Connexion TV par code** (`App\Services\DeviceLogins`, doc : `docs/connexion-tv.md`) :
  `device_code` secret haché, `user_code` court, 10 min, usage unique ; le code se tape dans
  l'appli du téléphone (« Paramètres » → « Connecter une TV »), **plus de QR code** (demande du
  développeur) ni lien (`verification_url(_complete)` supprimés, 20/11/2026).
- **Connexion avec Google et Apple** (demande du développeur, 25/11/2026 ; doc :
  `docs/connexion-sociale.md`) : « Continuer avec Google / Apple » de l'appli, **dans un
  navigateur** (RFC 8252) ; **l'API est le client OAuth** (`services.google`, `services.apple` :
  `GOOGLE_*`, `APPLE_*` du `.env`, rien dans les applis). `GET /auth/social` (disponibles),
  `GET /auth/social/{google|apple}` (ouvert par le navigateur, `redirect_uri` = `app.vixeo://auth`
  ou `http://127.0.0.1:{port}/auth` seulement, `code_challenge` PKCE S256), retour du fournisseur
  sur `…/callback` (Apple : POST `form_post`), puis l'appli échange `code` + `code_verifier` sur
  `POST /auth/social/token` (réponse de `auth/login` + `created`, `password_removed`).
  `App\Services\SocialLogin\SocialLogins` (états et codes dans le cache, 10 et 5 min, une fois),
  `GoogleSignIn`, `AppleSignIn` (client secret ES256 : `App\Support\Jwt`), table
  `social_accounts` (fournisseur + `subject`). Compte trouvé par le compte lié, sinon par
  l'adresse (vérifiée par le fournisseur ; **adresse jamais prouvée côté Vixeo : mot de passe et
  autres appareils retirés**, contre une inscription faite d'avance avec l'adresse d'un autre),
  sinon créé **sans mot de passe** (`users.password` nullable, `has_password`, `providers` dans
  `UserResource` ; `App\Services\Accounts` partagé avec l'inscription). Le code de « mot de passe
  oublié » prouve l'adresse (`email_verified_at`). **Pas de relation `socialAccounts()` sur
  `User`** : elle faisait perdre à Scramble les formes des profils (`SocialCards::identity`) ;
  passer par `SocialAccount::query()` / `SocialAccount::providersOf`.
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
  **Photo de profil** (demande du développeur, 23/11/2026 ; `docs/social.md`) : en plus de
  l'avatar, `POST / DELETE /profiles/{id}/photo` (`App\Services\ProfilePhotos` : carré central,
  à l'endroit, 512 px, réencodé en WebP sans métadonnées, nom aléatoire sur le disque privé, servi
  publiquement par `GET /profile-photos/{nom}`, gardé un an) ; `photo_url` (null = l'avatar) dans
  toute forme d'un profil (`ProfileResource`, `SocialCards::identity`, `ChatProfileResource`,
  notifications, site) : **toute nouvelle forme d'un profil la donne aussi**. Fichier supprimé avec
  le profil ou le compte ; `vixeo:moderate … photo` la retire.
  **Couverture** (demande du développeur, 24/11/2026 ; `docs/social.md`) : la bannière 3:1 de la
  page d'un profil, une couverture du catalogue `resources/covers/catalog.json` (`GET /covers`, par
  style ; motif + couleurs **dessinés par les applis**, `App\Services\CoverCatalog` ; ne jamais
  renommer ni supprimer un id utilisé) ou son image (`POST / DELETE /profiles/{id}/cover`,
  `App\Services\ProfileCovers` : partie centrale 3:1, 1 500 × 500, même traitement que les photos,
  `ProfileImages` ; `GET /profile-covers/{nom}`). **Un nouveau profil (inscription comprise) reçoit
  une couverture du catalogue au hasard**, comme l'avatar. `cover` / `cover_url` dans
  `ProfileResource`, la page d'un membre et l'export ; fichier supprimé avec le profil ou le compte ;
  `vixeo:moderate … cover` la retire.
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
  ou `base64:…` ; vide = aucune notification). L'appli s'inscrit (`PUT /push/device` :
  **`fid`**, identifiant d'installation Firebase (firebase_messaging 17, le 23/11/2026 ; FCM
  envoie avec `message.fid`), ou `token` (jeton des applis d'avant, toujours accepté), plateforme,
  `new_episodes`, `utc_offset`, profil de `X-Profile-Id` ; `GET`, `DELETE`,
  `POST /push/device/test`) ; table `push_devices` (**une ligne par jeton Sanctum**, supprimée à
  la déconnexion ; `target` = `fid`, `token` ou `realtime` ; identifiant **chiffré**, retrouvé par
  son SHA-256, jamais renvoyé ; oubliée quand FCM ne la connaît plus). **Windows (pas de FCM) :
  canal temps réel** (demande du développeur, 23/11/2026) : `platform: windows`, sans
  identifiant (`target: realtime`) ; `App\Services\Push\RealtimePush` diffuse l'événement
  `notification` (`App\Events\PushNotified`) sur `push-devices.{id}` (autorisé pour le seul jeton
  Sanctum de l'inscription) aux applis qui l'écoutent (canaux occupés de Reverb) ; appli fermée =
  échec (épisodes réessayés à la tâche suivante). Tout envoi passe par `PushDevices::send`, qui
  choisit FCM ou Reverb ; `available()` = l'un des deux configuré, `GET /push/device` donne
  `available` (FCM) et `realtime` (Reverb). `vixeo:push-episodes` (planifiée toutes les 15 min,
  aussi à la main, `--user=`, `--dry-run`) : `EpisodePush` annonce les épisodes sortis depuis moins de 48 h des
  séries de « À voir » (`UpcomingEpisodes::availableBetween`), jamais avant l'ajout de la série
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

- Ce dépôt contient uniquement l'API Laravel. Deux workflows GitHub Actions ajoutés par
  le développeur le 22/11/2026 : « Messaging tests » (`messaging-tests.yml`, les tests de la
  messagerie quand ses fichiers changent) et « Update Composer lock » (`update-composer-lock.yml` :
  met à jour `composer.lock` pour Reverb quand `composer.json` change et pousse sur `main`, ce qui
  redéploie). Pas de CI complète : lancer Pint et Pest avant chaque push.

- Pour l'instant, le travail se fait **directement sur `main`** (demande du développeur).
- `main` est déployée automatiquement par Laravel Forge (Push to deploy, domaine API uniquement) : voir [`docs/deploiement.md`](docs/deploiement.md).
- Secrets de déploiement : dans les secrets GitHub / Forge, jamais dans le dépôt ni dans
  les variables de l'environnement Claude Code cloud.

## État actuel

- 
  À faire avant la mise en ligne : mentions légales dans le `.env` de production.
- Fait : authentification (inscription, connexion, déconnexion, `me`), profils
  (« Qui regarde ? » : CRUD, 5 max par compte, PIN haché, profil enfant + âge maximum),
  messages d'erreur en français, catalogue TMDb (accueil, listes, genres, fiches film / série /
  saison / personne, recherche groupée et par type, filtres et tri), mis en cache, synchro par profil
  (favoris, progression / « Continuer à regarder », vus ; push / pull avec curseur),
  contrôle parental (PIN parental, âge maximum appliqué au catalogue, sources verrouillées),
  avatars de profil par licence, connexion TV
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
- Fait aussi : notifications push (FCM, par identifiant d'installation ; Windows par le canal
  temps réel, appli ouverte) des nouveaux épisodes des séries de « À voir »
  (tâche planifiée) et messages envoyés à la main (`vixeo:push`).
- Fait aussi : réseaux sociaux du profil, compte privé et demandes d'abonnement, J'aime sur les
  avis, notifications (page de l'appli et push) et prochains épisodes de « À voir »
  (`docs/social.md`).
- Fait aussi : notes IMDb (jeux de données vérifiés toutes les heures, importés quand IMDb les
  met à jour, `imdb:import`) et TMDb, avec
  leurs votants, sur les fiches et les épisodes (`rating_source` supprimé ; MDBList pour la
  fenêtre « Notes » seulement, depuis le 22/11/2026).
- Fait aussi : messagerie (conversations directes et demandes, groupes, photos et vidéos,
  accusés de lecture, blocage), en temps réel avec Laravel Reverb (`docs/messagerie.md`,
  `docs/reverb.md`).
- Supprimé le 21/11/2026 (inutilisé par l'appli) : plug-ins Stremio côté serveur et sources
  (tables `addons`, `profile_streams`, `dead_streams` supprimées), routes de compatibilité.
- Fait aussi (23/11/2026) : réseau social des films et séries : fil, listes et top 10, commentaires
  et spoilers, bilan de l'année, fiche sociale d'un titre, goûts en commun et comptes à
  suivre, blocage partout et masquage, signalements et modération, partage dans les messages,
  4 favoris, note en fin de lecture, premier lancement (`docs/social.md`, `docs/moderation.md`).
- Prochaine étape possible : configuration distante (chaînes FAST).
- Écarté : serveurs multimédias de l'utilisateur (Jellyfin / Plex / Emby), la lecture passe par
  les serveurs de Vixeo.
