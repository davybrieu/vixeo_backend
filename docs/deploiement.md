# Déploiement (Laravel Forge)

L'API est déployée par **Laravel Forge** depuis la branche `main` (Push to deploy).
La CI GitHub Actions (`.github/workflows/ci.yml`) lance les tests sur chaque push et PR.

## Site Forge

- Domaine : `api.<domaine>`
- Type : « General PHP / Laravel », PHP 8.3 ou plus
- Dépôt `davybrieu/vixeo_backend`, branche `main`
- **Push to deploy** et **Zero-downtime deployments** activés
- Base PostgreSQL et Redis créés sur le serveur

Pour ne déployer que si la CI est verte, désactiver « Push to deploy » et déclencher le
déploiement depuis GitHub Actions via l'URL de déploiement Forge (secret GitHub
`FORGE_DEPLOY_URL`). À mettre en place si besoin.

## Script de déploiement

```bash
$CREATE_RELEASE()

cd $FORGE_RELEASE_DIRECTORY

$FORGE_COMPOSER install --no-dev --no-interaction --prefer-dist --optimize-autoloader

$FORGE_PHP artisan migrate --force
$FORGE_PHP artisan optimize

$ACTIVATE_RELEASE()

$RESTART_QUEUES()
```

Forge coupe un déploiement au-delà de 10 minutes : rien de lourd à compiler ici
(l'API n'a pas d'assets front).

## Variables d'environnement (onglet Environment de Forge)

```dotenv
APP_NAME=Vixeo
APP_ENV=production
APP_DEBUG=false
APP_URL=https://api.<domaine>
APP_LOCALE=fr

DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=...
DB_USERNAME=...
DB_PASSWORD=...

CACHE_STORE=redis
QUEUE_CONNECTION=redis
SESSION_DRIVER=redis

# Domaines du Flutter web (prod + staging), séparés par des virgules
CORS_ALLOWED_ORIGINS=https://<domaine>,https://staging.<domaine>
```

## Après le premier déploiement

- Worker de file d'attente (onglet Queue) : connexion `redis`.
- Planificateur (onglet Scheduler) : `php artisan schedule:run` chaque minute.
- Vérifier `https://api.<domaine>/up` (healthcheck Laravel).
- La doc interactive `/docs/api` n'est accessible qu'en environnement `local` ;
  la spec publiée est `docs/openapi.json` dans le dépôt.
