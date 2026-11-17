# Plug-ins (format des add-ons Stremio)

Vixeo accepte des **plug-ins au format des add-ons Stremio** (le même que Nuvio) pour les
**films et les séries** : des catalogues (rangées de l'accueil) et des flux (fournisseurs du
panneau des sources du lecteur). Les chaînes TV ne passent pas (encore) par les plug-ins.

- **Plug-in par défaut** : le **catalogue Vixeo**, intégré à l'API, installé pour tous les comptes.
  On ne peut pas le retirer, mais chaque compte peut le **désactiver** : l'accueil n'a alors plus
  ses rangées ni son carrousel (`GET /catalog/home` vide) ; les fiches, la recherche et le plug-in
  Stremio public restent (ils viennent de TMDb).
- **Plug-ins d'un compte** : installés depuis l'appli (« Paramètres » → « Plug-ins ») avec l'adresse
  de leur manifeste.
- **Plug-ins de tous les comptes** : installés sur le serveur (`php artisan addons:server`).

L'API est **la seule à appeler un plug-in** : les applis ne parlent qu'à l'API (CONTEXT §5.1).

## Le format

Un plug-in est un service HTTP qui répond en JSON :

| Ressource | Adresse | Réponse |
|---|---|---|
| Manifeste | `{base}/manifest.json` | `id`, `name`, `version`, `resources`, `types`, `idPrefixes`, `catalogs` |
| Catalogue | `{base}/catalog/{type}/{id}.json`, `…/{id}/skip=20.json`, `…/search=dune.json` | `{metas: [{id, type, name, poster…}]}` |
| Fiche | `{base}/meta/{type}/{id}.json` | `{meta: {…, videos}}` |
| Flux | `{base}/stream/{type}/{id}.json` | `{streams: [{name, title, url, behaviorHints}]}` |

Types lus par Vixeo : `movie` (film) et `series` (série, `tv` dans l'API de Vixeo). Identifiants :
`tt0137523` (IMDb), `tmdb:550`, et pour un épisode `tt0944947:1:2` / `tmdb:1399:1:2`.

## Le catalogue Vixeo (plug-in par défaut)

`App\Services\Addons\VixeoCatalogAddon`, servi publiquement sous
`https://api.vixeo.app/api/v1/addons/vixeo/` (sans compte, `Access-Control-Allow-Origin: *`) :
toute appli qui installe des add-ons Stremio peut l'ajouter avec
`…/addons/vixeo/manifest.json`.

- `resources` : `catalog`, `meta` ; `types` : `movie`, `series` ; `idPrefixes` : `tmdb:`.
- Catalogues : les listes de TMDb (`vixeo.trending`, `vixeo.popular`, `vixeo.top_rated`,
  `vixeo.now_playing`, `vixeo.upcoming`, `vixeo.on_the_air`, `vixeo.airing_today`) et
  `vixeo.search` (`search` obligatoire). `skip` : 20 titres par page (pages de TMDb).
- Fiches : celles de TMDb (affiche, fond sans texte, logo, résumé, genres, distribution,
  réalisation, `imdb_id`, bande-annonce) ; série : tous les épisodes (`videos`), une requête TMDb
  par saison, en cache.

Les applis Vixeo continuent de lire les routes `/catalog/*` (logos, résumés, filtre d'âge) : c'est
le même catalogue, avec plus de données.

## Plug-ins installés

Table `addons` (`App\Models\Addon`, écrite seulement par `App\Services\Addons\Addons`) :
`user_id` (null = tous les comptes), `transport_url` (**chiffrée** : l'adresse d'un plug-in
configuré contient souvent une clé personnelle), `url_hash` (SHA-256, unicité), `addon_id`,
`manifest`, `position`, `enabled`. Supprimée avec le compte, présente dans son export
(`plugins`).

Table `addon_preferences` (`App\Models\AddonPreference`) : le choix d'un compte pour un plug-in
qu'il ne possède pas, le catalogue Vixeo (`addon_key` = `vixeo`) ou un plug-in de tous les comptes
(son numéro) ; pas de ligne = activé. Supprimée avec le compte, présente dans son export
(`plugin_settings`). Le champ `enabled` de `GET /addons` est l'état **pour ce compte**.

Routes (compte connecté, `throttle:addons`) :

| Route | Rôle |
|---|---|
| `GET /addons` | le catalogue Vixeo (`id: "vixeo"`, `built_in`), puis ceux de tous les comptes (`shared`), puis ceux du compte |
| `POST /addons {url}` | installe (`stremio://…` accepté, `/manifest.json` ajouté s'il manque) ; 422 `errors.url` sinon |
| `PATCH /addons/{id} {enabled}` | active / désactive un plug-in pour ce compte : les siens, ceux de tous les comptes, et le catalogue Vixeo (`/addons/vixeo`) |
| `PUT /addons/order {ids}` | ordre des plug-ins du compte |
| `DELETE /addons/{id}` | retire un plug-in du compte |

**L'adresse n'est jamais renvoyée** aux applis : seulement son hôte (`host`). Ni journalisée
(hôte seulement).

Refusé à l'installation (message français) : adresse non https, adresse privée ou du serveur,
introuvable, pas un manifeste, plug-in qui ne répond pas, déjà installé, à configurer d'abord
(`behaviorHints.configurationRequired`), sans catalogue ni flux de films ou de séries, plus de
`ADDONS_MAX_PER_USER` (30) plug-ins.

### Sécurité des appels (`App\Services\Addons\AddonHttp`)

L'adresse d'un plug-in est choisie par la personne qui l'installe : chaque requête est contrôlée.

- `https://` seulement (`ADDONS_ALLOW_HTTP=true` pour du développement local) ; pas
  d'identifiants dans l'adresse ;
- l'hôte est résolu et **toutes** ses adresses doivent être publiques (`FILTER_FLAG_GLOBAL_RANGE` :
  ni réseau privé, ni boucle locale, ni `169.254.169.254`…), puis **l'adresse vérifiée est
  épinglée** pour la connexion (`CURLOPT_RESOLVE`) : une réponse DNS qui change entre-temps n'est
  pas suivie ;
- **aucune redirection**, délai de `ADDONS_TIMEOUT` (8 s), réponse limitée à
  `ADDONS_MAX_RESPONSE_BYTES` (5 Mo).

## Catalogues des plug-ins : rangées de l'accueil

`GET /catalog/addons/sections` (public ; avec un compte, ses plug-ins en plus de ceux de tous)
renvoie des rangées comme celles de l'accueil, que l'appli place **après** les siennes :

- jusqu'à `ADDONS_HOME_CATALOGS` (6) catalogues par plug-in, ceux sans paramètre obligatoire
  (pas les recherches), films et séries ;
- `key` = `{type}_addon:{n}:{catalogue}`, `title` = « Nom du catalogue · Nom du plug-in »,
  `list` = `addon:{n}:{catalogue}` (« Voir tout » : `GET /catalog/{type}/lists/addon:{n}:{catalogue}`),
  rangées **légères** (sans logo ni résumé) ;
- chaque titre est rattaché à TMDb (`App\Services\Addons\TitleIds` : `tt…` par `find` de TMDb,
  `tmdb:…` directement ; cache d'une semaine) : la carte ouvre la fiche Vixeo habituelle et le
  **filtre d'âge** du profil s'applique. Un titre que TMDb ne connaît pas est laissé de côté ;
- un plug-in pour adultes (`behaviorHints.adult`) n'a pas de rangée pour un profil avec une limite
  d'âge ;
- cache de `ADDONS_TTL_CATALOG` (15 min) ; un plug-in qui ne répond pas n'a pas de rangée.

« Voir tout » : pages suivantes seulement si le catalogue accepte `skip`, de la taille de la
première page.

## Flux des plug-ins : panneau des sources

`GET /catalog/sources/providers` ajoute, après les fournisseurs du serveur, chaque plug-in qui sert
des flux de films ou de séries : `{id: "addon:12", name}`. Pour ce fournisseur,
`GET /catalog/movie/{id}/sources?provider=addon:12` (et l'équivalent pour un épisode) :

- l'API demande au plug-in les flux du titre par son id IMDb (`external_ids` de TMDb) ou, s'il
  n'accepte que `tmdb:`, par son id TMDb ;
- seuls les flux **avec une adresse** (`url` http/https) sont gardés : ni torrent (`infoHash`),
  ni YouTube (`ytId`), ni lien à ouvrir ailleurs (`externalUrl`) ;
- chaque flux arrive **déjà lisible** : `stream` (= `url`), `stream_type` (`m3u8` / `mp4` d'après
  l'adresse) et `headers` (`behaviorHints.proxyHeaders.request`) ; pas de `POST /catalog/resolve` ;
- les flux signalés morts (`DeadStreams`) sont retirés ; cache de `ADDONS_TTL_STREAMS` (5 min) ;
- plug-in qui ne répond pas : 503 « Ce plug-in est momentanément indisponible. ».

Sur le web, un flux d'un plug-in doit autoriser le navigateur (CORS) : l'API ne relaie pas les
vidéos des plug-ins.

## Serveur

```bash
php artisan addons:server list                                        # ceux de tous les comptes
php artisan addons:server install https://addon.example.com/manifest.json
php artisan addons:server remove 12
```

## Tests

`tests/Feature/Api/V1/AddonTest.php` (DNS remplacé par un faux `HostResolver`, `Http::fake`) :
manifeste et catalogues du plug-in Vixeo, installation et refus (adresses privées, redirection…),
rangées, pages, fournisseurs et flux, export et suppression du compte, commande.
