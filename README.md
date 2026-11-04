# Vixeo — API

API REST Laravel de Vixeo (comptes, profils, synchronisation, configuration distante).
L'appli Flutter est dans [`vixeo_frontend`](https://github.com/davybrieu/vixeo_frontend).

## Démarrage

```bash
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate
php artisan serve
```

- Tests : `./vendor/bin/pest`
- Formatage : `./vendor/bin/pint`
- Spec OpenAPI : `composer openapi` → [`docs/openapi.json`](docs/openapi.json)
  (doc interactive sur `/docs/api` en environnement local)

## Endpoints v1

Authentification par jeton Bearer (`Authorization: Bearer <token>`).

| Méthode | Route | Description |
| - | - | - |
| POST | `/api/v1/auth/register` | Créer un compte (+ premier profil), renvoie un jeton |
| POST | `/api/v1/auth/login` | Se connecter, renvoie un jeton |
| POST | `/api/v1/auth/logout` | Révoquer le jeton courant |
| GET | `/api/v1/me` | Utilisateur connecté |
| GET / POST | `/api/v1/profiles` | Lister / créer des profils (5 max) |
| GET / PATCH / DELETE | `/api/v1/profiles/{id}` | Lire / modifier / supprimer un profil |
| POST | `/api/v1/profiles/{id}/verify-pin` | Vérifier le code PIN d'un profil |

### Catalogue (métadonnées TMDb, mises en cache)

| Méthode | Route | Description |
| - | - | - |
| GET | `/api/v1/catalog/config` | URL et tailles des images, mention TMDb à afficher |
| GET | `/api/v1/catalog/home?type=` | Accueil : carrousel + rangées (`movie`, `tv` ou les deux) |
| GET | `/api/v1/catalog/genres` | Genres films et séries |
| GET | `/api/v1/catalog/{movie\|tv}/lists/{list}` | « Voir tout » d'une rangée (paginé) |
| GET | `/api/v1/catalog/{movie\|tv}/discover?genre=&sort=` | Par genre, tri `popularity`, `rating` ou `recent` |
| GET | `/api/v1/catalog/movie/{id}` | Fiche film |
| GET | `/api/v1/catalog/tv/{id}` | Fiche série |
| GET | `/api/v1/catalog/tv/{id}/season/{n}` | Saison et épisodes |
| GET | `/api/v1/catalog/person/{id}` | Personne et filmographie |
| GET | `/api/v1/catalog/search?query=` | Recherche groupée : films, séries, personnes |
| GET | `/api/v1/catalog/search/{movie\|tv\|person}?query=&page=` | Recherche d'un type (paginée) |

Les images sont chargées directement depuis le CDN de TMDb (`poster_url`, `backdrop_url`…
ou `image_base_url` + taille + `*_path`).

**Mention obligatoire** : l'appli doit afficher « This product uses the TMDB API but is not
endorsed or certified by TMDB. » avec le logo TMDb (renvoyée par `/catalog/config`).

Plus de détails : [`CLAUDE.md`](CLAUDE.md), [`docs/CONTEXT.md`](docs/CONTEXT.md),
[`docs/deploiement.md`](docs/deploiement.md).
