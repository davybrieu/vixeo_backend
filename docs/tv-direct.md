# TV en direct

Chaînes de télévision en direct, servies derrière une API **neutre** : l'appli appelle `/tv/…` et
ne sait jamais quel fournisseur sert les chaînes. Le fournisseur est un détail d'implémentation,
choisi et configuré côté serveur ; il peut changer sans que l'appli ne s'en aperçoive.

Le serveur normalise le fournisseur actif dans une forme unique et traduit ses erreurs en messages
neutres. Identifiants, miroirs et signatures ne quittent jamais le serveur : l'appli ne reçoit que
des chaînes et une URL HLS jouable.

## Routes

| Méthode | Route | Rôle |
|---|---|---|
| GET | `/tv/categories` | catégories de chaînes |
| GET | `/tv/channels?category_id=&q=` | chaînes, triées par nom |
| GET | `/tv/channels/{id}/stream` | résout une chaîne en flux jouable |
| GET | `/tv/stream/{token}` | média d'un flux (playlist HLS réécrite ou segment), proxifié |

- `category_id` vient de `/categories` ; `q` filtre sur le nom (insensible à la casse).
- Un identifiant de chaîne est une chaîne libre : il peut être numérique (`1399`) ou texte (`tf1`)
  selon le fournisseur.
- La réponse de `/stream` contient `url`, `sources` (même URL en tête), `format` (`hls`) et
  `headers` (toujours vide). `url` et chaque entrée de `sources` pointent **sur notre propre
  domaine** (`/tv/stream/{token}`), jamais sur celui du fournisseur. `sources` liste, dans l'ordre,
  toutes les URL jouables résolues pour la chaîne (un fournisseur à miroirs en renvoie une par
  miroir) : quand l'une ne démarre pas, l'appli joue la suivante. `url` reste la première.
- La route `/tv/stream/{token}` sert le média lui-même : le lecteur l'appelle **sans jeton
  d'authentification** (un élément `<video>` et ExoPlayer n'en envoient pas). C'est le **jeton
  signé et opaque** présent dans l'URL qui l'autorise, et il expire.
- La TV en direct est **facultative** : sans fournisseur configuré, les routes répondent 503
  « La TV en direct n'est pas configurée sur le serveur. » Un fournisseur injoignable répond aussi
  503 « La TV en direct est momentanément indisponible. »

## Flux proxifié (pourquoi l'appli ne reçoit jamais l'URL du fournisseur)

Certains fournisseurs ne publient leurs flux qu'en **HTTP clair**. Donner cette URL telle quelle à
l'appli casse la lecture : une page servie en HTTPS la bloque (contenu mixte) et une version
Android de production refuse le trafic non chiffré. L'appli reçoit donc une URL **sur notre propre
origine** (HTTPS) :

- `App\Services\Tv\StreamProxy` récupère la playlist côté serveur, **réécrit chaque URI**
  (segments, clés de chiffrement, variantes) vers nos routes, et renvoie la playlist.
- Chaque URI de la playlist pointe vers `/tv/stream/{token}` sur un chemin **relatif** : aucune
  fuite du domaine du fournisseur. Un flux qui n'est pas une playlist (segment, MPEG-TS brut) est
  transmis tel quel.
- Le jeton est chiffré avec `APP_KEY` (`Crypt`) : seul ce serveur peut en fabriquer un. Il
  contient l'URL du fournisseur et ses en-têtes (par ex. le `User-Agent` attendu), qui restent donc
  côté serveur, et une échéance (`TV_PROXY_TTL`). Une URL copiée cesse de fonctionner après.
- Un jeton absent, falsifié ou expiré répond 502 avec un message neutre.

## Fonctionnement interne (côté serveur uniquement)

`App\Services\Tv\LiveTv` est la façade unique : le contrôleur l'appelle, et c'est elle qui choisit
le fournisseur configuré, normalise la réponse et neutralise les erreurs. Ajouter ou remplacer un
fournisseur ne touche donc pas l'API publique.

Deux implémentations de fournisseur sont branchées :

- Un fournisseur à API signée (jeton obtenu sur un endpoint de « ping », envoyé ensuite dans un
  en-tête dédié ; catalogue paginé, puis résolution de l'URL HLS). Plusieurs miroirs et plusieurs
  endpoints de ping sont essayés à tour de rôle ; la résolution interroge les miroirs en parallèle
  et garde les premières réponses distinctes. Les catégories sont déduites du nom de la chaîne,
  faute de taxonomie côté fournisseur.
- Un panneau de streaming personnel légal : catégories et chaînes lues via l'API du panneau, puis
  l'URL directe du flux. Les identifiants restent côté serveur (dans l'URL construite, jamais
  renvoyés tels quels à l'appli).

Chaque fournisseur met ses résultats en cache (durées réglables) pour limiter les appels amont.

## Configuration (Forge, jamais dans le dépôt)

Les variables d'environnement de chaque fournisseur (URL, miroirs, endpoints de ping, pays,
timeouts, TTL de cache, versions de client) sont documentées dans `.env.example`. Elles vivent dans
la configuration, pas dans le code, pour pouvoir être mises à jour sans republier l'appli. Le proxy
lui-même se règle avec `TV_PROXY_TTL` (durée de vie d'un jeton média) et `TV_PROXY_TIMEOUT`.

## À vérifier avant la mise en ligne

Comme pour toute source de TV en direct, l'usage du fournisseur doit être vérifié au regard du
droit d'auteur dans les pays visés. Les API non documentées peuvent changer sans préavis : les
réglages concernés sont dans la configuration, à ajuster si le protocole évolue.
