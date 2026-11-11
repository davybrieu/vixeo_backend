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
| GET | `/tv/channels/{id}` | une chaîne (page de la chaîne dans l'appli) |
| GET | `/tv/channels/{id}/epg` | programme de la chaîne (guide), à partir de celui en cours |
| GET | `/tv/channels/{id}/stream` | résout une chaîne en flux jouable |
| GET | `/tv/stream/{token}` | média d'un flux (playlist HLS réécrite ou segment), proxifié |
| GET | `/tv/logo/{hash}` | logo d'une chaîne (image), proxifié : c'est l'URL `logo` des chaînes |

- `category_id` vient de `/categories` ; `q` filtre sur le nom (insensible à la casse).
- Un identifiant de chaîne est une chaîne libre : il peut être numérique (`1399`) ou texte (`tf1`)
  selon le fournisseur.
- La réponse de `/stream` contient `url`, `format` (`hls`) et `headers` (toujours vide). `url`
  pointe **sur notre propre domaine** (`/tv/stream/{token}`), jamais sur celui du fournisseur.
- La route `/tv/stream/{token}` sert le média lui-même : le lecteur l'appelle **sans jeton
  d'authentification** (un élément `<video>` et ExoPlayer n'en envoient pas). C'est le **jeton
  signé et opaque** présent dans l'URL qui l'autorise, et il expire.
- La TV en direct est **facultative** : sans fournisseur configuré, les routes répondent 503
  « La TV en direct n'est pas configurée sur le serveur. » Un fournisseur injoignable répond aussi
  503 « La TV en direct est momentanément indisponible. »

## Guide des programmes (EPG)

Le programme vient de fichiers **XMLTV** (le format standard des guides TV, `.xml` ou `.xml.gz`)
listés dans `TV_EPG_URLS`, séparés par des virgules, **le préféré d'abord** :

```
TV_EPG_URLS="https://epg.pw/xmltv/epg_FR.xml.gz,https://i.mjh.nz/Plex/fr.xml.gz,https://i.mjh.nz/PlutoTV/fr.xml.gz,https://i.mjh.nz/SamsungTVPlus/fr.xml.gz"
```

- `App\Services\Tv\Epg` télécharge chaque fichier dans un fichier temporaire (plusieurs dizaines
  de Mo : jamais en mémoire), le lit au fil de l'eau et garde en cache les programmes des
  **48 prochaines heures**, **une entrée de cache par chaîne** (une requête ne lit que la chaîne
  demandée), plus un index des noms et des icônes.
- Le téléchargement se fait **hors des requêtes** : `php artisan tv:epg`, planifié toutes les
  **6 heures** (`routes/console.php`, il faut le planificateur de Forge) ; le guide reste en cache
  `TV_EPG_TTL` secondes (12 h par défaut). Sans guide en cache (premier lancement), la première
  demande le télécharge **après avoir répondu** (programme vide en attendant).
- Le **nom affiché** d'une chaîne est nettoyé (`ChannelName::clean`) : sans le suffixe de miroir
  du fournisseur (« 6TER .c » → « 6TER »), ni les mentions de qualité (« HD », « FHD »,
  « (1080P) », « (BACKUP) ») : « 13 EME RUE HD .s » → « 13 EME RUE ».
- Une chaîne du fournisseur est rattachée au guide par son identifiant de guide
  (`epg_channel_id`, donné par un panneau) ou par son **nom** (`ChannelName::key`, sur le nom
  nettoyé : « TF1 HD », « TF1.fr », « FR | TF1 » → `tf1` ; « Canal+ » → `canalplus` ;
  « BFMTV » → `bfm` ; « BE: ELEVENSPORTS 1 » → `elevensports1` ; « 13 EME RUE », « 13ème Rue »
  → `13erue` ; « beIN SPORTS 1 French » → `beinsport1` ; singulier = pluriel et « & », « et »
  ignorés : « TF1 SERIES & FILM » = « TF1 Séries Films » = `tf1-series-films-fr.png`). Si la clé exacte
  ne suffit pas, le rapprochement tente ensuite une similarité de nom (petite faute ou variation) :
  il faut au moins 6 caractères, un score d'au moins 86 % et une avance nette sur le deuxième résultat.
  En cas d'ambiguïté, aucune correspondance approximative n'est retenue. Cette passe s'applique aussi
  aux logos ; les logos gardent en plus leur passe de nom raccourci. Les programmes n'utilisent jamais
  de nom raccourci pour éviter d'afficher le guide d'une autre chaîne. Après un changement de ces règles,
  relancer `php artisan tv:epg` (l'index du guide et des logos est calculé au téléchargement).
  **Le premier fichier qui a des programmes pour la chaîne l'emporte.**
- Un fichier illisible est ignoré (les autres servent) ; si aucun ne l'est, le guide précédent
  est gardé. Sans `TV_EPG_URLS`, `available` vaut `false` : l'appli affiche « Le programme n'est
  pas disponible pour le moment. »

Réponse de `/tv/channels/{id}/epg` : `{"data": [...], "available": true}`, chaque programme avec
`title`, `subtitle`, `description`, `category`, `image` (https seulement, sinon `null`), `start`
et `end` (UTC, ISO 8601). 30 programmes au plus, à partir de celui en cours. L'identifiant de
guide d'une chaîne reste côté serveur (il n'est pas dans les réponses).

`php artisan tv:match` compte les chaînes avec un logo et un programme, et liste les noms
sans l'un ou l'autre avec leur clé de comparaison (entre crochets) : c'est la clé à retrouver
dans le guide ou dans le nom de fichier de la bibliothèque.

`php artisan tv:epg` affiche, pour chaque fichier, le nombre de chaînes et de programmes lus (ou
l'erreur), puis le nombre de logos de la bibliothèque. Il termine par un bilan de correspondance
sur le catalogue TV réel (chaînes avec logo et avec programme) et affiche les 10 premiers noms
sans correspondance de chaque type.

## Logos des chaînes (relayés par notre domaine, jamais stockés)

Chaque chaîne a jusqu'à trois logos possibles, **dans cet ordre** :

1. la **bibliothèque de logos** : le dépôt GitHub public `tv-logo/tv-logos` (`TV_LOGOS_REPOSITORY`),
   PNG transparents nommés d'après la chaîne (`countries/france/tf1-fr.png`). Les dossiers de
   `TV_LOGOS_DIRECTORIES` (par défaut France, Belgique, Suisse, international, le premier
   l'emporte) sont lus **une fois par jour** avec l'API de GitHub (`App\Services\Tv\LogoLibrary`,
   une requête par dossier) ; le nom du fichier sans son suffixe de pays (`-fr`) est comparé au nom
   de la chaîne comme pour le guide ;
2. l'**icône du guide des programmes** (`<icon>` de la chaîne dans les fichiers XMLTV) ;
3. le logo du **fournisseur** ;
4. **passe de secours**, si aucun des trois ne connaît le nom complet : le logo de la bibliothèque,
   puis du guide, pour un **nom plus court** (`ChannelName::fallbackKeys` : on retire les derniers
   mots, au moins 4 lettres) : « RMC SPORT LIVE 16 » → `rmcsportlive`, puis `rmcsport`. Jamais pour
   le programme : ce serait celui d'une autre chaîne. Trop court (« BFM 2 » → `bfm`) : pas de
   secours, plutôt pas de logo qu'un mauvais.

Le relais sert **le premier qui donne une image valable** : un logo introuvable dans la
bibliothèque ou refusé passe au suivant. Les logos restent la propriété des chaînes ; ils servent
seulement à reconnaître la chaîne.

Le `logo` d'une chaîne n'est jamais l'adresse d'origine : `App\Services\Tv\ChannelLogos` la
remplace par `https://<API_URL>/api/v1/tv/logo/<hash>` (SHA-256 des adresses possibles). Sans
cela, les logos ne s'affichaient pas : Flutter web télécharge les images avec `fetch` (il faut des
en-têtes CORS, que les hébergeurs de logos n'envoient pas) et une version Android de production
refuse les images en HTTP clair.

- **L'image n'est jamais stockée sur notre serveur** (décision du développeur) : à chaque demande,
  elle est lue chez le fournisseur, vérifiée sur ses octets (PNG, JPEG, WebP ou GIF ; pas de SVG ;
  512 Ko au plus) et transmise. Si le serveur du logo casse la connexion HTTPS (erreur TLS),
  le relais réessaie la même adresse en `http://` : l'appli reçoit toujours l'image en HTTPS,
  depuis notre domaine. Seule la correspondance hash → adresse est gardée dans le cache
  (du texte, 7 jours), pour que l'URL reste la même.
- L'URL est **stable** pour un même logo : l'appli et le navigateur gardent l'image **7 jours**
  (`Cache-Control: public, max-age=604800`). La route est publique (une image se charge sans
  jeton) et limitée à 600 requêtes par minute et par IP (`throttle:tv-logos`).
- Introuvable ou invalide : 404, et l'appli affiche son icône. Une chaîne sans aucun logo garde
  `logo: null`.
- Logos qui ne s'affichent pas : `php artisan tv:logos` (dans le dossier `current` du site)
  compte les chaînes qui ont un logo (bibliothèque, guide ou fournisseur) et essaie d'en relayer
  quelques-uns ; la
  raison de chaque refus est dans `storage/logs` (« Channel logo refused / unreachable »).

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
timeouts, TTL de cache, versions de client), du guide (`TV_EPG_URLS`, `TV_EPG_TTL`,
`TV_EPG_TIMEOUT`) et de la bibliothèque de logos (`TV_LOGOS_REPOSITORY`, `TV_LOGOS_DIRECTORIES`)
sont documentées dans `.env.example`. Elles vivent dans
la configuration, pas dans le code, pour pouvoir être mises à jour sans republier l'appli. Le proxy
lui-même se règle avec `TV_PROXY_TTL` (durée de vie d'un jeton média) et `TV_PROXY_TIMEOUT`.

## À vérifier avant la mise en ligne

Comme pour toute source de TV en direct, l'usage du fournisseur doit être vérifié au regard du
droit d'auteur dans les pays visés. Vérifier aussi les conditions d'utilisation des fichiers de
guide (`TV_EPG_URLS`) et de la bibliothèque de logos. Les API non documentées peuvent changer sans préavis : les
réglages concernés sont dans la configuration, à ajuster si le protocole évolue.
