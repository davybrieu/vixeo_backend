# Déploiement (Laravel Forge)

L'API est déployée par **Laravel Forge** depuis la branche `main` (Push to deploy).
Ce dépôt n'utilise pas GitHub Actions : lancer `./vendor/bin/pint --test` et
`./vendor/bin/pest` avant chaque push sur `main`, puisque chaque push part en production.

## Site Forge

- Domaine : `api.<domaine>`
- Type : « General PHP / Laravel », PHP 8.3 ou plus
- Dépôt `davybrieu/vixeo_backend`, branche `main`
- **Push to deploy** et **Zero-downtime deployments** activés
- Base PostgreSQL et Redis créés sur le serveur

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
# Appli web : sa page /tv valide la connexion d'une TV par QR code
WEB_URL=https://<domaine>
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

# TMDb : « API Read Access Token » (https://www.themoviedb.org/settings/api)
TMDB_READ_ACCESS_TOKEN=...

# E-mails (code « mot de passe oublié », export des données) : un service d'envoi
# (Postmark, Amazon SES, Mailgun, Brevo…). Identifiants dans Forge uniquement.
MAIL_MAILER=smtp
MAIL_HOST=...
MAIL_PORT=587
MAIL_USERNAME=...
MAIL_PASSWORD=...
MAIL_FROM_ADDRESS=no-reply@<domaine>
MAIL_FROM_NAME=Vixeo

# Durée de vie des jetons, en minutes (180 jours par défaut ; voir docs/compte.md)
SANCTUM_EXPIRATION=259200
```

Sans service d'envoi configuré, « Mot de passe oublié » et l'export des données ne marchent
pas : l'adresse d'envoi doit être vérifiée auprès du service (SPF, DKIM) pour ne pas finir en
indésirables.

Sans jeton TMDb valide, les routes `/api/v1/catalog/*` répondent 503 et l'erreur est
journalisée (« TMDb is not configured » ou « TMDb rejected our credentials »).
Les réponses TMDb sont mises en cache dans Redis (1 h pour les listes et la recherche,
24 h pour les fiches, 7 jours pour les genres).

## Après le premier déploiement

- Worker de file d'attente (onglet Queue) : connexion `redis`.
- Planificateur (onglet Scheduler) : `php artisan schedule:run` chaque minute. Il supprime
  chaque jour les codes TV et les jetons expirés.
- HTTPS : certificat (Let's Encrypt) et redirection HTTP → HTTPS activés sur le site Forge ;
  ajouter l'en-tête `Strict-Transport-Security` dans la configuration Nginx du site.
- Vérifier `https://api.<domaine>/up` (healthcheck Laravel).
- La doc interactive `/docs/api` n'est accessible qu'en environnement `local` ;
  la spec publiée est `docs/openapi.json` dans le dépôt.
