# Déploiement de l'API (Laravel Forge)

Le dépôt `davybrieu/vixeo_backend` héberge **uniquement l'API Laravel**.

| Domaine | Hébergement | Rôle |
|---|---|---|
| `api.vixeo.app` | Laravel Forge | API REST `/api/v1`, Reverb, tâches planifiées et fichiers API |
| `vixeo.app` | Hébergement statique séparé | Application Flutter Web, compilée depuis `vixeo_frontend` et publiée dans sa branche `web-dist` |

Aucun site de présentation, aucune page Inertia/Blade, aucun rendu SSR et aucun build frontend ne
sont déployés sur Forge. Le dépôt frontend est responsable du build et de la publication Web.

## 1. DNS et Forge

- Le domaine `api.vixeo.app` pointe vers le serveur Forge (Cloudflare proxifié).
- Le domaine `vixeo.app` pointe vers l'hébergement statique choisi pour le frontend ; il ne
  doit pas être ajouté au site Forge de l'API.
- SSL Cloudflare : mode **Full (strict)**.

Créer ou conserver un seul site Forge :

- Domaine : `api.vixeo.app`
- Type : Laravel, PHP 8.3 ou plus, dossier web `/public`
- Dépôt : `davybrieu/vixeo_backend`, branche `main`
- Push to deploy et Zero-downtime deployments activés
- Base de données et Redis disponibles sur le serveur

Aucun daemon Node, Vite ou Inertia SSR n'est nécessaire.

## 2. Variables d'environnement

Exemple de configuration Forge (conserver les identifiants réels déjà présents) :

```dotenv
APP_NAME=Vixeo
APP_ENV=production
APP_DEBUG=false
APP_KEY=base64:...
APP_LOCALE=fr

# L'URL Laravel est celle de l'API.
APP_URL=https://api.vixeo.app
API_URL=https://api.vixeo.app

# URL du frontend Flutter Web : utilisée uniquement pour les liens de partage de profils.
VIXEO_APP_URL=https://vixeo.app

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=...
DB_USERNAME=...
DB_PASSWORD=...

CACHE_STORE=redis
QUEUE_CONNECTION=redis
SESSION_DRIVER=redis

# Le navigateur Flutter Web appelle l'API depuis vixeo.app.
CORS_ALLOWED_ORIGINS=https://vixeo.app

# Reverb, TMDb, OpenSubtitles, mail, FCM et autres paramètres métier :
# conserver les variables existantes de .env.example.
```

Ne pas supprimer `CORS_ALLOWED_ORIGINS` : contrairement aux applications natives, Flutter Web
effectue ses requêtes depuis un navigateur et a besoin de CORS. Conserver les clés Reverb, TMDb,
mail, FCM, EPG et fournisseurs TV déjà configurées.

Après toute modification des variables : redéployer ou exécuter `php artisan optimize` afin de
rafraîchir le cache de configuration.

## 3. Script de déploiement Forge

Le backend ne lance ni npm ni Node. Exemple de script :

```bash
$CREATE_RELEASE()

cd $FORGE_RELEASE_DIRECTORY

$FORGE_COMPOSER install --no-dev --no-interaction --prefer-dist --optimize-autoloader

$FORGE_PHP artisan migrate --force
$FORGE_PHP artisan optimize

$ACTIVATE_RELEASE()

$RESTART_QUEUES()
```

Aucun build frontend n'est committé dans ce dépôt. Aucun daemon SSR ne doit être configuré.

## 4. Worker et planificateur

- Queue : connexion Redis ; conserver le daemon Laravel Queue existant.
- Scheduler : `php artisan schedule:run` chaque minute.
- Les tâches planifiées (EPG, notifications d'épisodes, nettoyage et préchauffage du catalogue)
  restent dans le backend et doivent continuer à être exécutées.

Reverb reste hébergé avec l'API et doit continuer à être accessible via
`wss://api.vixeo.app`.

## 5. Vérifications après déploiement

```bash
curl -s https://api.vixeo.app/                         # JSON : nom de l'API et URL /api/v1
curl -s https://api.vixeo.app/api/v1/avatars | head -c 200
curl -s https://api.vixeo.app/nexiste-pas              # erreur JSON 404
curl -s https://api.vixeo.app/robots.txt               # Disallow: /
curl -s https://api.vixeo.app/up                       # healthcheck
```

Toutes les erreurs de l'API, y compris les chemins inconnus appelés depuis un navigateur,
doivent renvoyer du JSON. Le domaine `vixeo.app` doit être vérifié séparément depuis
l'hébergement du frontend Web.

## 6. Points à préserver

- Les migrations, modèles, contrôleurs API, services métier, commandes Artisan, scheduler,
  queues, notifications push, Reverb et proxy TV restent dans ce dépôt.
- Les liens publics de profils renvoyés par l'API utilisent `VIXEO_APP_URL` et la route Flutter
  `/membre/{username}`.
- Les fichiers `/.well-known/assetlinks.json` et le routage des profils publics doivent être
  servis par le frontend sur `vixeo.app`.
- Les releases Android et Windows continuent d'être publiées par GitHub Actions dans
  `vixeo_frontend`. L'API les lit pour `GET /api/v1/app/update`.
- La documentation OpenAPI reste générée par Scramble ; la spec publiée est
  `docs/openapi.json`.
