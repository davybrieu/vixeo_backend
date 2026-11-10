# Vixeo — API et site

Un seul projet Laravel, deux domaines (voir [`docs/deploiement.md`](docs/deploiement.md)) :

- **`api.vixeo.app`** : API REST de Vixeo (comptes, profils, synchronisation, catalogue…), sous
  `/api/v1` ;
- **`www.vixeo.app`** : site de présentation et d'installation (Vue 3 + Inertia, rendu côté
  serveur), voir [`docs/site.md`](docs/site.md). `vixeo.app` redirige vers `www.vixeo.app`.

L'appli Flutter est dans [`vixeo_frontend`](https://github.com/davybrieu/vixeo_frontend).

## Démarrage

```bash
composer install && npm ci
cp .env.example .env
php artisan key:generate
php artisan migrate
npm run build                     # ou `npm run dev` pendant le développement du site
php artisan serve                 # site sur http://localhost:8000, API sur /api/v1
```

En local, `API_URL` est vide : un seul hôte (`APP_URL`) sert le site (`/`) et l'API (`/api/v1`).
En production : `APP_URL=https://www.vixeo.app`, `API_URL=https://api.vixeo.app`,
`WEB_URL=https://app.vixeo.app`.

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
| POST | `/api/v1/auth/password/forgot` · `/auth/password/reset` | Mot de passe oublié : code par e-mail, puis nouveau mot de passe (voir [`docs/compte.md`](docs/compte.md)) |
| GET / DELETE | `/api/v1/me/devices` · `/me/devices/{id}` | Appareils connectés, en déconnecter un (jetons valables 180 jours) |
| POST | `/api/v1/me/export` | Recevoir ses données par e-mail (JSON) |
| POST | `/api/v1/me/delete` | Supprimer le compte et toutes ses données (mot de passe) |
| GET | `/api/v1/app/update` | L'appli Android / iOS est-elle à jour ? (voir [`docs/mise-a-jour.md`](docs/mise-a-jour.md)) |
| POST | `/api/v1/auth/device` · `/auth/device/token` | Connexion d'une TV par QR code (voir [`docs/connexion-tv.md`](docs/connexion-tv.md)) |
| POST | `/api/v1/auth/device/lookup` · `/auth/device/approve` | Valider le code d'une TV depuis un appareil connecté |
| GET / POST | `/api/v1/profiles` | Lister / créer des profils (5 max) |
| GET / PATCH / DELETE | `/api/v1/profiles/{id}` | Lire / modifier / supprimer un profil (PIN parental si activé) |
| POST | `/api/v1/profiles/{id}/verify-pin` | Vérifier le code PIN d'un profil |
| GET | `/api/v1/avatars` | Avatars de profil, regroupés par licence |

### Contrôle parental

PIN parental du compte, profils enfants / âge maximum, sources verrouillées : voir
[`docs/controle-parental.md`](docs/controle-parental.md). Le catalogue applique la limite d'âge
du profil envoyé dans l'en-tête `X-Profile-Id`.

| Méthode | Route | Description |
| - | - | - |
| GET | `/api/v1/parental-control` | Contrôle parental activé ou non |
| PUT | `/api/v1/parental-control/pin` | Définir ou changer le PIN parental |
| POST | `/api/v1/parental-control/disable` | Désactiver le contrôle parental |
| POST | `/api/v1/parental-control/verify` | Vérifier le PIN parental |

### Synchronisation par profil

Favoris, progression et contenus vus, entre les appareils du compte : voir
[`docs/synchronisation.md`](docs/synchronisation.md).

| Méthode | Route | Description |
| - | - | - |
| GET | `/api/v1/profiles/{id}/sync?cursor=` | Recevoir les changements depuis `cursor` |
| POST | `/api/v1/profiles/{id}/sync` | Envoyer des changements (500 max) |
| GET | `/api/v1/profiles/{id}/continue-watching` | Continuer à regarder |
| GET | `/api/v1/profiles/{id}/favorites` | Favoris (filtres, tris, paginés) |
| GET | `/api/v1/profiles/{id}/history` | Historique des contenus vus |

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
