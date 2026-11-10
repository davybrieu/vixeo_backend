# Site de présentation (www.vixeo.app)

Site public de Vixeo : présentation de l'appli et mode d'emploi pour l'installer sur **Android**
(téléphone, tablette, Android TV / Google TV) ou l'ouvrir dans le **navigateur**. Il vivait dans
le dépôt `vixeo_web` ; il fait maintenant partie de ce projet, servi sur `SITE_DOMAIN`.

Vue 3, Inertia 3 avec rendu côté serveur (SSR), Tailwind CSS 4. Pas de compte, pas de formulaire,
**aucun cookie** (groupe de middleware `site` : ni session, ni jeton CSRF), aucun service tiers
(polices servies par le site, `public/fonts`, licence OFL).

## Où sont les fichiers

```
routes/site.php                          pages du site (groupe de middleware `site`)
routes/api-host.php                      racine et robots.txt de api.vixeo.app
app/Support/Hosts.php                    quel hôte ? (API_DOMAIN, SITE_DOMAIN, SITE_REDIRECT_DOMAINS)
app/Http/Controllers/SiteController.php  pages (Inertia::render), sitemap, robots, redirection vixeo.app → www
app/Http/Middleware/                     HandleInertiaRequests, SecurityHeaders
app/Services/LatestAndroidRelease.php    dernière version Android (GitHubReleases, comme GET /app/update)
app/Support/Platform.php                 appareil du visiteur d'après le User-Agent : android | tv | web
app/Providers/AppServiceProvider.php     props partagées : site, platform, path
config/vixeo.php                         domaines, APK de secours, mentions légales
resources/views/app.blade.php            page HTML
resources/js/                            app.js, ssr.js, pages, layouts, components, mock, data
resources/css/app.css                    charte (noir et vert, Sora / Inter)
tests/Feature/Site/PagesTest.php         tests du site
```

## Développer

```bash
npm ci
npm run dev                 # Vite (rechargement à chaud, SSR compris)
php artisan serve           # http://localhost:8000 : le site sur /, l'API sur /api/v1
```

Version de production en local (avec le serveur SSR) :

```bash
npm run build               # client (public/build) + SSR (bootstrap/ssr)
php artisan inertia:start-ssr &
php artisan serve
```

## Build committé

`public/build` (client) et `bootstrap/ssr` (bundle SSR autonome, sans `node_modules`) sont dans
le dépôt : Forge ne lance pas npm. **Après toute modification du site, `npm run build` puis
committer** les deux dossiers ; `tests/Unit/FrontendBuildTest.php` compare l'empreinte des sources
(`bootstrap/ssr/source-hash.txt`, écrite par `vite.config.js`) et échoue si le build est en retard.

## Pages

| Adresse | Contenu |
| - | - |
| `/` | **La seule page de présentation** : accueil, installation (`#installer`, onglets Android / TV / navigateur avec le bouton de l'APK, l'empreinte SHA-256 et l'adresse courte pour la TV), fonctionnalités, questions fréquentes (`#faq`, `resources/js/data/faq.js`) |
| `/mentions-legales`, `/confidentialite` | Informations légales, confidentialité du site |
| `/installer`, `/faq`, `/telecharger`, `/android`, `/tv` | Anciennes pages et liens courts, supprimés : **404** (demande du développeur) |
| `/apk` | Le fichier APK du moment (adresse à taper sur une TV), 404 sans APK |
| `/sitemap.xml`, `/robots.txt` | Référencement (`SITE_URL`) |

Barre de navigation : le logo (accueil) et « Ouvrir Vixeo », **sans liens de sections ni menu
burger** (demande du développeur). Ne pas recréer de pages Installer, FAQ ou Fonctionnalités :
tout est sur l'accueil.

Ajouter une page publique (si le développeur le demande) : la route (`routes/site.php`), la page
Vue (`resources/js/pages`), et `SiteController::PAGES` (sitemap). Chaque page pose
`<Seo title description>`.

## Réglages

| Variable | Rôle |
| - | - |
| `SITE_DOMAIN`, `SITE_URL` | Domaine du site (`www.vixeo.app`) et son adresse publique (liens canoniques, sitemap) |
| `SITE_REDIRECT_DOMAINS` | Domaines redirigés (301) vers le site : `vixeo.app` |
| `WEB_URL` | Appli web Flutter (boutons « Ouvrir dans le navigateur », « Créer un compte ») |
| `VIXEO_ANDROID_APK_URL`, `VIXEO_ANDROID_APK_SHA256` | Secours : APK et empreinte s'il n'y a pas de release GitHub |
| `VIXEO_PUBLISHER_*`, `VIXEO_PUBLICATION_DIRECTOR`, `VIXEO_CONTACT_EMAIL`, `VIXEO_HOST_*` | Mentions légales (obligatoires avant la mise en ligne) |
| `INERTIA_SSR_ENABLED`, `INERTIA_SSR_URL` | Rendu côté serveur ; le serveur SSR écoute le port de cette URL, sur 127.0.0.1 (lu au démarrage, pas figé dans le build) |

**Pas de Play Store** : l'APK (et son empreinte SHA-256) vient de la dernière release GitHub
`android-<build>` publiée par la CI de `vixeo_frontend`, lue directement par
`App\Services\GitHubReleases` (la même source et le même cache que `GET /api/v1/app/update` :
plus d'appel HTTP du site vers l'API). Sans release, l'APK de secours du `.env` ; sans APK du
tout, le site affiche « Version Android bientôt disponible » et propose le navigateur.

## Erreurs

- Sur `www.vixeo.app` : pages d'erreur aux couleurs du site (page Inertia `Error`) pour 403,
  404, 405, 419, 429, 500 et 503 ; en local, une 500 garde la page de débogage de Laravel. Si la
  page d'erreur elle-même échoue (build manquant…), la réponse de Laravel est renvoyée telle quelle.
- `/api/*` répond toujours en JSON, quel que soit l'hôte : l'API n'est pas servie sur
  `www.vixeo.app` (404 JSON).
- La version Android indisponible (GitHub en panne, Redis en panne) n'empêche jamais une page de
  s'afficher : elle est simplement masquée (et journalisée).

## Conventions

- **Textes honnêtes** : ne décrire que ce que l'appli fait aujourd'hui ; ce qui arrive est marqué
  « En préparation ». Rappeler que Vixeo n'héberge ni ne diffuse de contenus, et la mention TMDb.
- **Pas de serveurs multimédias de l'utilisateur** (Jellyfin, Plex, Emby) : la lecture passe par
  les serveurs de Vixeo, via l'API, sans rien à configurer. Ne pas en parler sur le site.
- **Écarté** (ne pas proposer) : import IPTV / M3U / Xtream, radio, replays, iOS (un iPhone voit
  la version web).
- **SSR** : pas d'accès à `window` / `document` pendant le rendu (seulement dans `onMounted`).
- **Appareil du visiteur** détecté côté serveur (`Platform`) : réponses en `Vary: User-Agent`.
- **Logo et favicon** : copiés de `vixeo_frontend/brand`, ne pas les redessiner ici.
- **Responsive** : mobile d'abord, aucun défilement horizontal ; tester à 390, 834 et 1440 px.
- Tailwind ne lit que `resources/js` et `resources/views` (`resources/css/app.css`).
