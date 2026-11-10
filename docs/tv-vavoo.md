# TV en direct : Vavoo

Chaînes de télévision en direct via l'API « MediaHubMX » de Vavoo, portée depuis l'application
ONYX (`com.streamflixreborn.streamflix.providers.VavooProvider`). Comme pour Xtream, le serveur
garde les secrets et les détails du fournisseur : l'appli ne reçoit que des chaînes et une URL HLS
jouable.

## Routes

| Méthode | Route | Rôle |
|---|---|---|
| GET | `/tv/vavoo/categories` | catégories de chaînes |
| GET | `/tv/vavoo/channels?category_id=&q=` | chaînes, triées par nom |
| GET | `/tv/vavoo/channels/{id}/stream` | résout une chaîne en flux jouable |

- `category_id` vient de `/categories` ; `q` filtre sur le nom (insensible à la casse).
- La réponse de `/stream` contient `url`, `format` (`hls`) et `headers` (le `User-Agent` à envoyer
  avec la requête vidéo). L'URL de Vavoo est signée : elle n'est valable qu'avec cet en-tête.
- La TV Vavoo est **facultative** : sans configuration, les routes répondent 503
  « La TV Vavoo n'est pas configurée sur le serveur. »

## Fonctionnement interne (côté serveur uniquement)

L'API MediaHubMX est **signée**. Pour chaque appel, il faut un jeton `addonSig` obtenu sur un
endpoint de « ping », puis envoyé dans l'en-tête `mediahubmx-signature` :

1. `POST {ping}/api/app/ping` → `{ "addonSig": "…" }` (mis en cache 5 min, comme ONYX).
2. `POST {miroir}/mediahubmx-catalog.json` → le catalogue paginé des chaînes du pays.
3. `POST {miroir}/mediahubmx-resolve.json` → l'URL HLS jouable d'une chaîne.

Plusieurs miroirs (`VAVOO_BASE_URLS`) et plusieurs endpoints de ping (`VAVOO_PING_URLS`) sont
essayés à tour de rôle : si l'un tombe, les autres prennent le relais. La résolution demande tous
les miroirs en parallèle et garde les premières réponses distinctes (`VAVOO_MAX_STREAMS`).

- Catégories : Vavoo ne fournit qu'un `group` (« France », « Sport »…). ONYX en déduit une
  catégorie française à partir du nom de la chaîne (`guessCategoryByName`) ; on fait de même
  (`VavooClient::CATEGORIES`).
- Cache : signature 5 min (`VAVOO_TTL_SIGNATURE`), catalogue 30 min (`VAVOO_TTL_CATALOG`).
- Un identifiant de chaîne inconnu rafraîchit le catalogue une fois avant de répondre 404.

## Configuration (Forge, jamais dans le dépôt)

```
VAVOO_BASE_URLS=https://vavoo.net,https://vavoo.to,https://kool.ws,https://vavoo.top
VAVOO_PING_URLS=https://www.vypn.net/api/app/ping,https://www.lokke.app/api/app/ping,https://www.vavoo.tv/api/app/ping
VAVOO_COUNTRY=France
VAVOO_TIMEOUT=20
VAVOO_MAX_STREAMS=3
VAVOO_TTL_SIGNATURE=300
VAVOO_TTL_CATALOG=1800
```

`VAVOO_COUNTRY` filtre le catalogue sur un pays (« France », « Canada »…) ; « all » prend tout.
Les miroirs et l'endpoint de ping changent avec le temps : ils sont dans la configuration, pas
dans le code, pour pouvoir les mettre à jour sans republier l'appli.

## À vérifier avant la mise en ligne

Comme pour toute source de TV en direct, l'usage de Vavoo doit être vérifié au regard du droit
d'auteur dans les pays visés. L'API est non documentée et peut changer sans préavis : les miroirs
et les versions de client (`VAVOO_CATALOG_CLIENT_VERSION`, `VAVOO_RESOLVE_CLIENT_VERSION`) sont
des réglages, à ajuster si Vavoo modifie son protocole.
