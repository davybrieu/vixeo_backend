# Vixeo — API (vixeo_backend)

API REST Laravel de **Vixeo**, media center multiplateforme (Flutter : web, Android,
Android TV, iOS). L'appli cliente est dans le dépôt
[`davybrieu/vixeo_frontend`](https://github.com/davybrieu/vixeo_frontend).

**Lire d'abord [`docs/CONTEXT.md`](docs/CONTEXT.md)** : décisions produit et techniques,
périmètre légal (§3, ce qui est hors périmètre ne doit pas être implémenté), sécurité (§6),
feuille de route (§10).

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
    (`(int)`, `(string)`, helpers typés) pour que Scramble produise un schéma typé.
  - Tests : `Http::fake()` + `Http::preventStrayRequests()`, jamais d'appel réel ; payloads dans
    `tests/Feature/Api/V1/Catalog/TmdbFixtures.php`.
- **Synchro par profil** (`app/Services/ProfileSync.php`, doc : `docs/synchronisation.md`) :
  un `ProfileItem` par profil + `source` + `item_id`. Ne jamais écrire ces items ailleurs que
  dans `ProfileSync` (révision et dates de changement). Fusion par groupe (favori / progression /
  vu), le plus récent gagne ; chaque écriture prend `++profiles.sync_revision` sous verrou.
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
  saison / personne, recherche groupée et par type), mis en cache, synchro par profil
  (favoris, progression / « Continuer à regarder », vus ; push / pull avec curseur).
- Pas encore fait : filtrage du catalogue selon l'âge maximum du profil (le champ `age_rating`
  est déjà renvoyé sur les fiches), complément en anglais quand le résumé français manque.
- Pas encore synchronisés : réglages du profil (langue audio / sous-titres préférée), historique
  des recherches, flux favoris et « dernier flux qui a fonctionné ».
- Prochaines étapes (feuille de route §10) : sources IPTV de l'utilisateur, configuration
  distante (chaînes FAST), contrôle parental.
