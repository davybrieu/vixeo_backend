# Déploiement (Laravel Forge)

**Un seul dépôt** (`davybrieu/vixeo_backend`), **un seul projet Laravel**, **un seul site Forge**,
deux domaines :

| Domaine | Ce qu'il sert |
| - | - |
| `api.vixeo.app` | L'API (`/api/v1/...`), réponses JSON uniquement, `robots.txt` qui interdit tout |
| `www.vixeo.app` | Le site de présentation (Vue + Inertia, SSR), voir [`site.md`](site.md) |
| `vixeo.app` | Redirection 301 vers `https://www.vixeo.app` (même chemin, même requête) |

Laravel choisit les routes d'après l'en-tête `Host` (`bootstrap/app.php`, `config/vixeo.php`,
`App\Support\Hosts`) : Nginx n'a qu'un seul bloc `server` pour les trois domaines. `/up` répond
sur tous les hôtes.

`main` est déployée automatiquement (Push to deploy). Ce dépôt n'utilise pas GitHub Actions :
lancer `./vendor/bin/pint --test`, `./vendor/bin/pest` et `npm run build` avant chaque push sur
`main`, puisque chaque push part en production.

## 1. DNS (Cloudflare)

| Type | Nom | Contenu | Proxy |
| - | - | - | - |
| `A` | `api` | IP du serveur Forge | Proxied (orange) |
| `A` | `www` | IP du serveur Forge | Proxied (orange) |
| `A` | `@` (`vixeo.app`) | IP du serveur Forge | Proxied (orange) |

(`AAAA` en plus si le serveur a une IPv6.) Dans **SSL/TLS → Overview** : mode **Full (strict)**
(Cloudflare vérifie le certificat du serveur ; jamais « Flexible », qui ferait boucler la
redirection HTTPS).

## 2. Le site Forge

### Si deux sites existent déjà (API et ancien `vixeo_web`)

On garde **le site de l'API** (il a déjà la base, Redis, le `.env`, le worker et le planificateur)
et on lui ajoute les domaines du site :

1. **Supprimer le site Forge de `vixeo_web`** (Forge refuse un domaine déjà utilisé par un autre
   site). Le site public est indisponible quelques minutes, jusqu'à l'étape 4.
2. Sur le site de l'API : **Domains → Add domain** : `www.vixeo.app`, puis `vixeo.app`, comme
   **alias** du site (pas de redirection Forge : Laravel s'en charge).
3. Compléter l'environnement (section 4), le script de déploiement (section 5) et le daemon SSR
   (section 6), puis **Deploy now**.
4. SSL : nouveau certificat Let's Encrypt couvrant les trois domaines (section 3).

### Si on part de zéro

**New site** :

- Domaine principal : `api.vixeo.app` ; alias : `www.vixeo.app`, `vixeo.app`
- Type : **Laravel**, PHP **8.3** ou plus, dossier web **`/public`**
- Dépôt `davybrieu/vixeo_backend`, branche `main`
- **Push to deploy** et **Zero-downtime deployments** activés
- Base PostgreSQL et Redis créés sur le serveur
- Node **22** ou plus installé sur le serveur (serveur SSR) : `node -v`

## 3. HTTPS et Nginx

- **SSL → Let's Encrypt** : un certificat pour `api.vixeo.app`, `www.vixeo.app` et `vixeo.app`
  (les trois cochés). Fonctionne derrière le proxy Cloudflare.
- Redirection HTTP → HTTPS activée.
- **Edit Nginx configuration** : vérifier que le bloc `server` liste les trois domaines, et ajouter
  l'en-tête HSTS :

```nginx
server {
    listen 443 ssl;
    listen [::]:443 ssl;
    http2 on;
    server_name api.vixeo.app www.vixeo.app vixeo.app;
    # ... (le reste généré par Forge, sans y toucher)

    add_header Strict-Transport-Security "max-age=31536000" always;
```

Rien d'autre à ajouter : pas de `server` séparé par domaine, pas de règle de redirection Nginx.

## 4. Variables d'environnement (onglet Environment)

```dotenv
APP_NAME=Vixeo
APP_ENV=production
APP_DEBUG=false
APP_KEY=base64:...                  # celle du site de l'API, ne pas la changer
APP_URL=https://api.vixeo.app
APP_LOCALE=fr

# Un projet, deux domaines (config/vixeo.php)
API_DOMAIN=api.vixeo.app
SITE_DOMAIN=www.vixeo.app
SITE_URL=https://www.vixeo.app
SITE_REDIRECT_DOMAINS=vixeo.app

# Appli web Flutter (vixeo_frontend) : sa page /tv valide la connexion d'une TV par QR code.
# Elle n'est pas servie par ce projet : garder son adresse (ex. https://app.vixeo.app).
WEB_URL=https://app.vixeo.app
# Origines du Flutter web autorisées à appeler l'API, séparées par des virgules
CORS_ALLOWED_ORIGINS=https://app.vixeo.app

DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=...
DB_USERNAME=...
DB_PASSWORD=...

CACHE_STORE=redis
QUEUE_CONNECTION=redis
SESSION_DRIVER=redis

# Site : rendu côté serveur (daemon de la section 6). Le port de INERTIA_SSR_URL sert aux
# deux côtés : Laravel s'y connecte, le serveur SSR l'écoute (127.0.0.1 seulement). Pour changer
# de port (ex. 13715 si un autre site du serveur prend déjà 13714) : modifier l'URL, redéployer,
# puis redémarrer le daemon SSR.
INERTIA_SSR_ENABLED=true
INERTIA_SSR_URL=http://127.0.0.1:13714

# TMDb : « API Read Access Token » (https://www.themoviedb.org/settings/api)
TMDB_READ_ACCESS_TOKEN=...

# Sous-titres et génériques (docs/lecture.md)
OPENSUBTITLES_API_KEY=...
OPENSUBTITLES_USERNAME=...
OPENSUBTITLES_PASSWORD=...
THEINTRODB_API_KEY=

# E-mails (code « mot de passe oublié », export des données) : un service d'envoi
# (Postmark, Amazon SES, Mailgun, Brevo…). Identifiants dans Forge uniquement.
MAIL_MAILER=smtp
MAIL_HOST=...
MAIL_PORT=587
MAIL_USERNAME=...
MAIL_PASSWORD=...
MAIL_FROM_ADDRESS=no-reply@vixeo.app
MAIL_FROM_NAME=Vixeo

# Durée de vie des jetons, en minutes (180 jours ; voir docs/compte.md)
SANCTUM_EXPIRATION=259200

# Mises à jour des applis (docs/mise-a-jour.md). Vides : SITE_URL/installer#android et
# SITE_URL/telecharger, c'est-à-dire https://www.vixeo.app/... Pas de Play Store.
ANDROID_UPDATE_URL=
IOS_UPDATE_URL=
APP_UPDATES_GITHUB_REPOSITORY=davybrieu/vixeo_frontend

# Site : APK de secours, seulement s'il n'y a pas encore de release GitHub android-<build>
VIXEO_ANDROID_APK_URL=
VIXEO_ANDROID_APK_SHA256=

# Mentions légales du site (obligatoires avant la mise en ligne ; guillemets s'il y a des espaces)
VIXEO_PUBLISHER_NAME="…"
VIXEO_PUBLISHER_ADDRESS="…"
VIXEO_PUBLISHER_REGISTRATION="…"
VIXEO_PUBLICATION_DIRECTOR="…"
VIXEO_CONTACT_EMAIL=contact@vixeo.app
VIXEO_HOST_NAME="…"
VIXEO_HOST_ADDRESS="…"
VIXEO_HOST_PHONE="…"

# (TV en direct, scraper : inchangés, voir .env.example)
```

Les anciennes variables du site `VIXEO_APP_URL` et `VIXEO_API_URL` n'existent plus : le site lit
`WEB_URL` et la dernière version Android directement (sans appel HTTP vers l'API).

`php artisan optimize` met la configuration et les routes en cache : **après une modification de
l'environnement, redéployer** (ou `php artisan optimize` dans `current`), sinon l'ancienne valeur
reste utilisée. Les domaines sont lus à ce moment-là.

## 5. Script de déploiement (onglet Deployments)

```bash
$CREATE_RELEASE()

cd $FORGE_RELEASE_DIRECTORY

$FORGE_COMPOSER install --no-dev --no-interaction --prefer-dist --optimize-autoloader

# Site : le build (public/build, bootstrap/ssr) est dans le dépôt, pas de npm ici.
# Garde-fou : ne jamais activer une release sans bundle SSR.
test -f bootstrap/ssr/ssr.js || { echo "bootstrap/ssr/ssr.js manquant : npm run build et commit"; exit 1; }

$FORGE_PHP artisan migrate --force
$FORGE_PHP artisan optimize

$ACTIVATE_RELEASE()

$RESTART_QUEUES()

# Serveur SSR : l'arrêter pour que Forge le relance depuis la nouvelle release.
# --graceful : ne fait pas échouer le déploiement si le serveur SSR ne tourne pas.
$FORGE_PHP artisan inertia:stop-ssr --graceful
```

Sans `--graceful`, un serveur SSR arrêté (daemon pas encore créé, ou en panne) fait échouer
le déploiement juste après « Activating release » avec « Unable to connect to Inertia SSR
server. » : la nouvelle release est déjà active, mais Forge marque le déploiement en échec.

**Le build du site est committé** (`public/build` et `bootstrap/ssr`) : le serveur ne lance ni
`npm ci` ni `npm run build`, il a seulement besoin de Node pour exécuter le serveur SSR. Le bundle
SSR est autonome (`ssr.noExternal` dans `vite.config.js`) : il tourne sans `node_modules`. Après
toute modification du site (`resources/js`, `resources/css`, `resources/views`, `vite.config.js`,
`package-lock.json`), lancer `npm run build` et committer le résultat :
`tests/Unit/FrontendBuildTest.php` échoue tant que le build committé ne correspond pas aux
sources (empreinte dans `bootstrap/ssr/source-hash.txt`).

## 6. Serveur SSR (onglet Daemons / Processes)

| Champ | Valeur |
| - | - |
| Commande | `php artisan inertia:start-ssr` |
| Dossier | `/home/forge/<dossier du site>/current` |
| Utilisateur | `forge` |
| Processus | 1 |

Le dossier `current` est le lien vers la release active : le daemon relancé après chaque
déploiement (dernière ligne du script) prend le nouveau bundle. Sans lui, le site reste
fonctionnel mais n'est rendu que dans le navigateur (moins bon pour le référencement).
`php artisan inertia:check-ssr` vérifie qu'il répond.

Le serveur SSR lit son adresse au démarrage dans `INERTIA_SSR_URL` (variable d'environnement,
sinon le `.env` du dossier `current`) : port de l'URL, interface `127.0.0.1` (jamais joignable
depuis l'extérieur). Défaut : `http://127.0.0.1:13714`. Le message « Starting SSR server on port
… » du journal du daemon indique le port utilisé ; il doit être le même que celui de l'URL.
Un port déjà pris (`EADDRINUSE`) : souvent l'ancien daemon SSR d'un autre site (ancien
`vixeo_web`) encore actif, à supprimer, ou bien choisir un autre port.

## 7. Worker et planificateur (inchangés)

- **Queue** : connexion `redis`.
- **Scheduler** : `php artisan schedule:run` chaque minute (supprime chaque jour les codes TV et
  les jetons expirés).

## 8. Vérifier après le déploiement

```bash
curl -sI https://vixeo.app/installer         # 301 → https://www.vixeo.app/installer
curl -s  https://api.vixeo.app/              # {"name":"Vixeo","api":"https://api.vixeo.app/api/v1"}
curl -s  https://api.vixeo.app/api/v1/avatars | head -c 200
curl -s  https://api.vixeo.app/nexiste-pas    # {"message":"Ressource introuvable."} (404)
curl -s  https://api.vixeo.app/robots.txt     # Disallow: /
curl -sI https://www.vixeo.app/              # 200, text/html, aucun Set-Cookie
curl -s  https://www.vixeo.app/ | grep -c 'Choisissez'   # > 0 : le SSR répond
curl -sI https://www.vixeo.app/nexiste-pas   # 404, page d'erreur du site
curl -s  https://www.vixeo.app/sitemap.xml   # adresses en https://www.vixeo.app
curl -s  https://www.vixeo.app/up            # healthcheck (aussi sur api.vixeo.app/up)
```

## Gestion des erreurs, par hôte

| Cas | Réponse |
| - | - |
| `api.vixeo.app`, n'importe quelle erreur | JSON `{"message": "..."}` en français, même pour un navigateur ; une 404 ne révèle jamais le nom d'un modèle ; erreur inattendue : « Erreur interne du serveur. » sans détail (`APP_DEBUG=false`) |
| `/api/*` sur un autre hôte | 404 JSON : l'API n'est servie que sur `api.vixeo.app` |
| `www.vixeo.app` | Page d'erreur aux couleurs du site (403, 404, 405, 419, 429, 500, 503) ; si elle échoue elle-même (build manquant), la réponse brute de Laravel |
| `vixeo.app` | Toujours 301 vers `www.vixeo.app` |
| GitHub ou Redis en panne | Le site s'affiche sans la version Android ; l'API répond `up_to_date` (docs/mise-a-jour.md) |
| Serveur SSR arrêté | Le site s'affiche quand même (rendu dans le navigateur) |
| Mode maintenance (`php artisan down`) | 503 : JSON sur l'API, page « Maintenance en cours » sur le site |

Les erreurs sont journalisées dans `storage/logs` (partagé entre les releases).

## Notes

- **APK** : servis par les releases GitHub de `vixeo_frontend` (lien `https://github.com/...`).
  Avec les déploiements sans interruption, un fichier déposé dans `public/` disparaît au
  déploiement suivant ; pour un APK de secours hébergé ici, utiliser un dossier hors release
  (`/home/forge/<dossier du site>/telechargements`) et ajouter au script, avant
  `$ACTIVATE_RELEASE()` : `ln -sfn /home/forge/<dossier du site>/telechargements public/telechargements`.
- Cloudflare et Nginx terminent le TLS : l'origine reçoit du HTTP. L'application fait confiance
  aux proxys (`trustProxies(at: '*')` dans `bootstrap/app.php`) pour lire `X-Forwarded-Proto` et
  construire des URL `https://`. Sans cela, le proxy TV remet au lecteur une URL `http://`, que le
  navigateur bloque (contenu mixte) et qu'une version Android de production refuse (trafic clair) :
  écran noir sur les deux plateformes. À garder cohérent si la topologie réseau change.
- Sans service d'envoi configuré, « Mot de passe oublié » et l'export des données ne marchent
  pas : l'adresse d'envoi doit être vérifiée auprès du service (SPF, DKIM).
- Sans jeton TMDb valide, les routes `/api/v1/catalog/*` répondent 503 et l'erreur est
  journalisée. Les réponses TMDb sont mises en cache dans Redis.
- La doc interactive `/docs/api` n'est accessible qu'en environnement `local` ; la spec publiée
  est `docs/openapi.json` dans le dépôt.
