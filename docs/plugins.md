# Plug-ins de sources (format des add-ons Stremio)

Vixeo accepte des **plug-ins au format des add-ons Stremio** (le même que Nuvio), **uniquement
pour les sources** : chaque plug-in qui sert des flux de **films ou de séries** devient un
fournisseur du panneau des sources du lecteur. Les chaînes TV ne passent pas par les plug-ins.

**Le catalogue ne dépend pas des plug-ins** (demande du développeur) : l'accueil, les listes, les
fiches et la recherche restent ceux de TMDb, avec la clé de Vixeo, et ne changent pas. Il n'y a
ni plug-in « catalogue Vixeo », ni rangées ni listes venues d'un plug-in : les catalogues déclarés
dans un manifeste sont ignorés.

- **Plug-ins d'un compte** : installés depuis l'appli (« Paramètres » → « Plug-ins ») avec l'adresse
  de leur manifeste.
- **Plug-ins de tous les comptes** : installés sur le serveur (`php artisan addons:server`).

L'API est **la seule à appeler un plug-in** : les applis ne parlent qu'à l'API (CONTEXT §5.1).

## Le format

Un plug-in est un service HTTP qui répond en JSON. Vixeo n'en lit que deux ressources :

| Ressource | Adresse | Réponse |
|---|---|---|
| Manifeste | `{base}/manifest.json` | `id`, `name`, `version`, `resources`, `types`, `idPrefixes` |
| Flux | `{base}/stream/{type}/{id}.json` | `{streams: [{name, title, url, behaviorHints}]}` |

Types lus par Vixeo : `movie` (film) et `series` (série, `tv` dans l'API de Vixeo). Identifiants :
`tt0137523` (IMDb), `tmdb:550`, et pour un épisode `tt0944947:1:2` / `tmdb:1399:1:2`.

## Plug-ins installés

Table `addons` (`App\Models\Addon`, écrite seulement par `App\Services\Addons\Addons`) :
`user_id` (null = tous les comptes), `transport_url` (**chiffrée** : l'adresse d'un plug-in
configuré contient souvent une clé personnelle), `url_hash` (SHA-256, unicité), `addon_id`,
`manifest`, `position`, `enabled`. Supprimée avec le compte, présente dans son export
(`plugins`).

Routes (compte connecté, `throttle:addons`) :

| Route | Rôle |
|---|---|
| `GET /addons` | ceux de tous les comptes (`shared`), puis ceux du compte |
| `POST /addons {url}` | installe (`stremio://…` accepté, `/manifest.json` ajouté s'il manque) ; 422 `errors.url` sinon |
| `PATCH /addons/{id} {enabled}` | active / désactive un plug-in du compte |
| `PUT /addons/order {ids}` | ordre des plug-ins du compte |
| `DELETE /addons/{id}` | retire un plug-in du compte |

Chaque plug-in renvoie `streams` (sert-il des flux de films ou de séries ?) : un plug-in
« catalogue » installé avant ce changement reste listé, avec `streams: false`, pour pouvoir le
retirer. `catalogs` (toujours 0), `built_in` (toujours `false`) et `manifest_url` (toujours
`null`) restent pour les applis déjà installées (pas de changement cassant en v1).

**L'adresse n'est jamais renvoyée** aux applis : seulement son hôte (`host`). Ni journalisée
(hôte seulement).

Refusé à l'installation (message français) : adresse non https, adresse privée ou du serveur,
introuvable, pas un manifeste, plug-in qui ne répond pas, déjà installé, à configurer d'abord
(`behaviorHints.configurationRequired`), **sans flux de films ou de séries** (« Ce plug-in ne
propose pas de sources de films ou de séries. », par exemple un plug-in qui n'a que des
catalogues), plus de `ADDONS_MAX_PER_USER` (30) plug-ins.

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

## Flux des plug-ins : panneau des sources

**Les plug-ins sont la seule source des flux** : le serveur n'a plus de scraper, de fournisseurs
ni d'extracteurs (supprimés le 20/11/2026, demande du développeur : dossier `scraper/`,
`StreamSources`, table `missing_extractors`).

`GET /catalog/sources/providers` donne chaque plug-in activé qui sert des flux de films ou de
séries : `{id: "addon:12", name}`. Pour ce fournisseur,
`GET /catalog/movie/{id}/sources?provider=addon:12` (et l'équivalent pour un épisode) :

- l'API demande au plug-in les flux du titre par son id IMDb (`external_ids` de TMDb,
  `App\Services\Addons\TitleIds`) ou, s'il n'accepte que `tmdb:`, par son id TMDb ;
- seuls les flux **avec une adresse** (`url` http/https) sont gardés : ni torrent (`infoHash`),
  ni YouTube (`ytId`), ni lien à ouvrir ailleurs (`externalUrl`) ;
- chaque flux arrive **déjà lisible** : `stream` (= `url`), `stream_type` (`m3u8` / `mp4` d'après
  l'adresse) et `headers` (`behaviorHints.proxyHeaders.request`) ; pas de `POST /catalog/resolve` ;
- les flux signalés morts (`DeadStreams`) sont retirés ; cache de `ADDONS_TTL_STREAMS` (5 min) ;
- plug-in qui ne répond pas : 503 « Ce plug-in est momentanément indisponible. ».

Sans `provider` : les flux de tous les plug-ins, dans leur ordre (un plug-in en panne est laissé
de côté). Un autre `provider` (ancien fournisseur du serveur, `frenchstream`…) : liste vide.
`POST /catalog/resolve` (anciennes applis) répond toujours 502 « Cette source n'est pas
lisible. » : le serveur ne résout plus de liens.

Sur le web, un flux d'un plug-in doit autoriser le navigateur (CORS) : l'API ne relaie pas les
vidéos des plug-ins.

## Ancienne route

`GET /catalog/addons/sections` (rangées de l'accueil venues des plug-ins) est **obsolète** : elle
répond toujours `{"sections": []}`, sans appeler de plug-in, pour les applis déjà installées. Les
listes `addon:{n}:{catalogue}` de `GET /catalog/{type}/lists/{list}` et les adresses
`/addons/vixeo/…` n'existent plus (404).

## Serveur

```bash
php artisan addons:server list                                        # ceux de tous les comptes
php artisan addons:server install https://addon.example.com/manifest.json
php artisan addons:server remove 12
```

## Tests

`tests/Feature/Api/V1/AddonTest.php` (DNS remplacé par un faux `HostResolver`, `Http::fake`) :
liste, installation et refus (adresses privées, redirection, plug-in sans flux…), catalogue
inchangé (rangées vides, ni listes ni plug-in Vixeo), fournisseurs et flux, export et suppression
du compte, commande.
