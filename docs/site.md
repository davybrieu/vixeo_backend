# Site de présentation (www.vixeo.app)

Site public de Vixeo : présentation de l'appli et mode d'emploi pour l'installer sur **Android**
(téléphone, tablette, Android TV / Google TV) ou sur **Windows** (10 et 11, 64 bits). Il n'y a
plus d'appli web (demande du développeur, 20/11/2026) : le site ne propose plus de l'ouvrir dans
le navigateur. Il vivait dans
le dépôt `vixeo_web` ; il fait maintenant partie de ce projet, servi à l'adresse `APP_URL`.

Vue 3, Inertia 3 avec rendu côté serveur (SSR), Tailwind CSS 4. Pas de compte, pas de formulaire,
**aucun cookie** (groupe de middleware `site` : ni session, ni jeton CSRF), aucun service tiers
(polices servies par le site, `public/fonts`, licence OFL).

## Où sont les fichiers

```
routes/site.php                          pages du site (groupe de middleware `site`)
routes/api-host.php                      racine et robots.txt de api.vixeo.app
app/Support/Hosts.php                    quel hôte ? (domaines tirés de API_URL et APP_URL)
app/Http/Controllers/SiteController.php  pages (Inertia::render), sitemap, robots, redirection vixeo.app → www
app/Http/Middleware/                     HandleInertiaRequests, SecurityHeaders
app/Services/LatestReleases.php          dernières versions Android et Windows (GitHubReleases, comme GET /app/update)
app/Support/Platform.php                 appareil du visiteur d'après le User-Agent : android | tv | windows
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
| `/` | **La seule page de présentation** : accueil, installation (`#installer`, onglets Android / TV / Windows avec le bouton de l'APK ou de l'installateur Windows, l'empreinte SHA-256 et l'adresse courte pour la TV), fonctionnalités, questions fréquentes (`#faq`, `resources/js/data/faq.js`) |
| `/mentions-legales`, `/confidentialite` | Informations légales, confidentialité du site |
| `/installer`, `/faq`, `/telecharger`, `/android`, `/tv` | Anciennes pages et liens courts, supprimés : **404** (demande du développeur) |
| `/apk` | Le fichier APK du moment (adresse à taper sur une TV), 404 sans APK |
| `/pseudo` | **Page publique d'un profil** (demande du développeur, 21/11/2026 ; **sans @, comme Twitter**), le lien du bouton « Partager » de l'appli (`share_url` de `GET /social/profiles/{pseudo}`) : nom, @pseudo, avatar (`AvatarCatalog::find`, pixel art dessiné par `PixelAvatar`), biographie, « Ouvrir dans Vixeo » (Android seulement : lien `intent://`, retour sur `/#installer` sans l'appli) et « Télécharger Vixeo ». Profils adultes avec un pseudo seulement (enfant ou inconnu : 404) ; ni nom de compte, ni e-mail ; **pas indexée** (`X-Robots-Tag: noindex`, `<meta robots>`, absente du sitemap) ; `throttle:members` (60 / min par IP). **Route déclarée en dernier** (`/{username}`, `Usernames::PATTERN`) : les pages du site passent avant, et un pseudo n'est jamais une page du site ni de l'appli (`App\Support\Usernames::RESERVED` : `apk`, `confidentialite`, `accueil`, `recherche`…, refusés par l'inscription et `PUT /profiles/{id}` : « Ce pseudo est réservé »). L'ancienne adresse `/@pseudo` redirige (301) vers `/pseudo` ; « Ouvrir dans Vixeo » ouvre toujours `/@pseudo` dans l'appli (Android 7 et plus, `/pseudo` seulement à partir d'Android 12) |
| `/.well-known/assetlinks.json` | Liens d'appli Android : `www.vixeo.app/pseudo` (Android 12 et plus) et l'ancienne adresse `/@pseudo` s'ouvrent dans l'appli signée avec la clé de release (`vixeo.android.package`, `vixeo.android.cert_sha256` : empreinte SHA-256 du certificat, publique, `VIXEO_ANDROID_CERT_SHA256` pour la changer) |
| `/sitemap.xml`, `/robots.txt` | Référencement (`APP_URL`) |

Barre de navigation : le logo (accueil) et « Télécharger » (`/#installer`), **sans liens de
sections ni menu burger** (demande du développeur). Ne pas recréer de pages Installer, FAQ ou Fonctionnalités :
tout est sur l'accueil.

Ajouter une page publique (si le développeur le demande) : la route (`routes/site.php`), la page
Vue (`resources/js/pages`), et `SiteController::PAGES` (sitemap). Chaque page pose
`<Seo title description>`.

## Réglages

| Variable | Rôle |
| - | - |
| `APP_URL` | Adresse du site (`https://www.vixeo.app`) : son domaine, les liens canoniques, le sitemap |
| `VIXEO_ANDROID_APK_URL`, `VIXEO_ANDROID_APK_SHA256` | Secours : APK et empreinte s'il n'y a pas de release GitHub |
| `VIXEO_PUBLISHER_*`, `VIXEO_PUBLICATION_DIRECTOR`, `VIXEO_CONTACT_EMAIL`, `VIXEO_HOST_*` | Mentions légales (obligatoires avant la mise en ligne) |
| `INERTIA_SSR_ENABLED`, `INERTIA_SSR_URL` | Rendu côté serveur ; le serveur SSR écoute le port de cette URL, sur 127.0.0.1 (lu au démarrage, pas figé dans le build) |

**Pas de Play Store ni de Microsoft Store** : l'APK et l'installateur Windows (et leurs empreintes
SHA-256) viennent des dernières releases GitHub `android-<build>` et `windows-<build>` publiées
par la CI de `vixeo_frontend`, lues directement par `App\Services\GitHubReleases` (la même source
et le même cache que `GET /api/v1/app/update` : pas d'appel HTTP du site vers l'API). Sans
release Android, l'APK de secours du `.env` (pas de secours pour Windows) ; sans fichier, le site affiche « Version
Android bientôt disponible » (ou « Version Windows… »). L'installateur Windows n'est pas signé :
le site prévient que SmartScreen peut afficher un avertissement (« Informations
complémentaires » → « Exécuter quand même »).

## Erreurs

- Sur `www.vixeo.app` : pages d'erreur aux couleurs du site (page Inertia `Error`) pour 403,
  404, 405, 419, 429, 500 et 503 ; en local, une 500 garde la page de débogage de Laravel. Si la
  page d'erreur elle-même échoue (build manquant…), la réponse de Laravel est renvoyée telle quelle.
- `/api/*` répond toujours en JSON, quel que soit l'hôte : l'API n'est pas servie sur
  `www.vixeo.app` (404 JSON).
- Une version Android ou Windows indisponible (GitHub en panne, Redis en panne) n'empêche jamais
  une page de s'afficher : elle est simplement masquée (et journalisée).

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
