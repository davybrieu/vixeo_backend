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

Plus de détails : [`CLAUDE.md`](CLAUDE.md), [`docs/CONTEXT.md`](docs/CONTEXT.md),
[`docs/deploiement.md`](docs/deploiement.md).
