# Notes des titres

Les fiches des films et des séries affichent IMDb (importé chaque nuit depuis ses jeux de données),
sinon TMDb. Un appui ouvre le modal « Notes » : IMDb et TMDb sont immédiats ; les sources
supplémentaires sont chargées **à l'ouverture seulement** depuis MDBList (Trakt, Letterboxd,
Rotten Tomatoes critiques et public, Metacritic, MyAnimeList). Aucun réglage « Source des notes »
n'est utilisé.

## Notes supplémentaires (MDBList)

`GET /catalog/ratings?ids=movie:550` ou `tv:1399` renvoie les notes MDBList sur l'échelle de
chaque site. Cette route est appelée uniquement à l'ouverture du modal ; IMDb et TMDb ne sont pas
utilisés depuis cette réponse, car ils viennent déjà de la fiche. Les notes sont mises en cache
pour tous les comptes pendant 3 jours ; les titres inconnus pendant 1 jour. Les lots MDBList sont
limités à 200 titres, le paramètre `MDBLIST_DAILY_REQUESTS` borne les appels quotidiens et une
panne / un quota dépassé suspend temporairement les appels. La clé reste côté serveur dans
`MDBLIST_API_KEY`.


## Réponse de l'API

`GET /catalog/movie/{id}`, `GET /catalog/tv/{id}` et chaque épisode de
`GET /catalog/tv/{id}/season/{n}` ont un champ `ratings` (`RatingsResource`) :

```json
"ratings": {
  "imdb": { "id": "tt0137523", "rating": 8.8, "votes": 2400000 },
  "tmdb": { "rating": 8.4, "votes": 30000 }
}
```

- `imdb` : note sur 10 et nombre de votants d'IMDb ; `null` quand le titre n'a pas d'identifiant
  IMDb ou qu'IMDb ne le note pas (l'appli montre alors celle de TMDb).
- `tmdb` : note sur 10 (arrondie au dixième) et nombre de votants de TMDb ; `rating` à `null` tant
  que personne n'a voté.
- `vote_average` / `vote_count` restent dans les fiches (et `vote_count` est ajouté aux
  épisodes).
- **Titres des listes** (`MediaItemResource`) : **toutes les listes de titres ont `ratings`**
  (demande du développeur, 21/11/2026 : la note IMDb partout, celle de TMDb seulement en secours),
  avec le résumé (`summary` : accueil, recherche, filtres sans texte, recommandations d'une fiche
  et leur « Voir tout », « Voir tout » des rangées, « Connu pour », `GET /catalog/titles` pour
  « À voir », « Continuer à regarder » et les rangées légères) ou seule (filmographie de la page
  d'une personne et `GET /catalog/person/{id}/credits` : `TitleSummaries::ratings`, l'identifiant
  IMDb de chaque titre gardé 90 jours en cache, sinon lu dans sa fiche si elle est déjà en cache,
  sinon demandé à TMDb par ses **External IDs** seulement (`movie|tv/{id}/external_ids`, quelques
  centaines d'octets au lieu de la fiche avec images et classifications ; demande du développeur,
  24/11/2026). Films et séries partent dans **un seul lot** de requêtes (`TmdbClient::getEach`) ;
  la page d'une personne fait ses deux rangées en un lot (`TitleSummaries::addAndRate`)).
  `TitleSummaries` lit la fiche TMDb de chaque titre (avec `external_ids` pour les séries) et
  cherche leurs notes IMDb en **une requête** par réponse. Seules exceptions : les **rangées
  légères** de l'accueil (`light`, sans `summary`), que l'appli complète par `GET /catalog/titles`,
  et un titre que TMDb n'a pas pu donner : `ratings` est alors `null` et l'appli n'affiche **aucune
  note** (pas celle de TMDb, qui serait prise pour la note choisie). Aucune requête de note par
  carte.

## Recherche : note, votants et « Mieux notés »

`GET /catalog/search` et `/catalog/search/{movie|tv}` acceptent `min_rating` (0 à 10) et
`sort=rating` (rétablis le 21/11/2026, demande du développeur). La note utilisée
(`App\Services\Tmdb\TitleFilters::rating`) est **celle d'IMDb, sinon celle de TMDb** : la même
que celle des cartes des applis, donc l'ordre affiché suit les notes affichées. Les résultats
reçoivent leur `summary` et leur note IMDb avant le filtre (même cache que pour l'affichage).

`min_rating` écarte les titres sous le seuil et ceux sans note. `sort=rating` classe du mieux
noté au moins bien noté, les titres sans note à la fin ; à note égale, l'ordre de TMDb
(pertinence). Comme les autres filtres, ils portent sur les 60
premiers résultats du texte, et une recherche avec `min_rating` ne renvoie pas de personnes.

`min_votes` (entier ≥ 0, demande du développeur, 21/11/2026) : nombre minimal de **votants de la
note affichée** (`TitleFilters::votes`) : ceux d'IMDb quand le titre a une note IMDb, sinon ceux
de TMDb (`vote_count`). Il écarte les titres qui n'atteignent pas ce nombre et ceux sans note ;
il se combine avec `min_rating` (« Note ≥ 8 » et « 100 000 votants et plus »).

**Intervalles** (curseurs à deux poignées des applis, demande du développeur, 21/11/2026) :
`max_rating` (0 à 10, ≥ `min_rating`) et `max_votes` (≥ `min_votes`) bornent la note et les
votants par le haut, bornes comprises (« Note de 6 à 7,5 », « de 1 000 à 50 000 votants »). Chaque
borne est facultative ; dès qu'il y en a une, les titres sans note sont écartés
(`TitleFilters::withinRatingBounds`). Les années ont déjà les leurs (`year_from`, `year_to`).

### Sans texte (`GET /catalog/discover`, `/catalog/discover/{movie|tv}`)

La page de recherche avec des filtres seulement (par exemple : films, « Horreur », depuis 2010,
« Note ≥ 8 »). TMDb discover applique le genre et les années (titres déjà sortis seulement) ; il ne
connaît que sa propre note, alors :

- avec une borne de la note ou des votants, l'API lui demande les titres qui ont au moins 10 votes
  TMDb (et notés sur TMDb au moins `min_rating - 1`, au plus `max_rating + 1` :
  `DISCOVER_RATING_MARGIN`, la note IMDb peut être différente), lit **3 pages TMDb par page de résultats** (`DISCOVER_DEPTH`),
  et ne garde que les titres dont la note IMDb, sinon TMDb, et ses votants sont dans les
  bornes : une page peut avoir moins de 20 titres, voire aucun, sans que ce soit la fin
  (`meta.total_pages`). Le nombre de votants n'est pas demandé à TMDb : ceux d'IMDb sont bien
  plus nombreux (un film à 250 000 votants IMDb peut en avoir 900 sur TMDb) ;
- `sort=rating` suit l'ordre de TMDb (meilleure note TMDb d'abord, 100 votes au moins), affiné page
  par page avec la note IMDb, sinon TMDb ;
- sans borne de la note ou des votants ni ce tri, une page de résultats = une page de TMDb.

Un titre que TMDb note à plus d'un point hors de l'intervalle n'est pas proposé, même si IMDb le note
dedans (rare).

## Liaison avec IMDb (`App\Services\Imdb\ImdbRatings`)

La clé est l'**identifiant IMDb** (`tt…`) :

1. **Film** : `imdb_id` des détails TMDb ; s'il manque, les *External IDs* de TMDb
   (`movie/{id}/external_ids`, en cache un jour).
2. **Série** : `external_ids.imdb_id`, déjà demandé avec la fiche
   (`append_to_response=external_ids`).
3. **Épisode** : l'identifiant IMDb de la série (`tv/{id}/external_ids`), puis la table
   `imdb_episodes` (série, saison, numéro → identifiant de l'épisode) jointe à `imdb_ratings`, en
   une requête par saison. Un épisode déjà diffusé qui n'y est pas (numérotation différente,
   épisode encore absent du jeu de données) : ses *External IDs* de TMDb
   (`tv/{id}/season/{n}/episode/{e}/external_ids`, demandés ensemble, en cache un jour). Pas de
   requête pour un épisode pas encore diffusé.

TMDb indisponible pour ces identifiants : pas d'erreur, la note d'IMDb manque seulement.

## Import des jeux de données (`php artisan imdb:import`)

`App\Services\Imdb\ImdbDatasets`, planifié **toutes les heures** (à la 17e minute,
`routes/console.php`, `withoutOverlapping`, en arrière-plan ; demande du développeur, 21/11/2026).
Source : <https://datasets.imdbws.com> (`IMDB_DATASETS_URL`), mise à jour une fois par jour par IMDb.

1. **Vérification** : une requête `HEAD` par fichier (rien n'est téléchargé) lit son
   `Last-Modified` (sinon son `ETag` ; ni l'un ni l'autre : la date du jour, donc un import par
   jour). Les deux valeurs forment la **version** des jeux de données.
2. Même version que le dernier import réussi (table `imdb_imports`) : rien à faire
   (« IMDb datasets unchanged »).
3. Version nouvelle : **import** des deux fichiers, puis la version est enregistrée dans
   `imdb_imports` (avec le nombre de notes et d'épisodes ; les lignes de plus de 30 jours sont
   supprimées). Un import qui échoue n'enregistre rien : il recommence l'heure suivante.

`php artisan imdb:import --force` importe quelle que soit la version. Un import complet dure
plusieurs minutes (environ 1,6 million de notes et des millions de lignes d'épisodes à lire) :
plus que la limite des « Commands » de Forge, qui arrête la commande (code 143). Depuis Forge, le
lancer en arrière-plan :

```bash
nohup php artisan imdb:import --force > storage/logs/imdb-import.log 2>&1 &
```

ou laisser la tâche planifiée le faire à la 17e minute de l'heure (pas de limite de durée).
**Un seul import à la fois** (verrou `imdb:import` du cache, 3 h au plus) : un deuxième lancement
répond « An IMDb import is already running » sans rien faire. Attention : une commande « Commands »
de Forge coupée (code 143) peut continuer à tourner sur le serveur (`ps aux | grep imdb:import`). Un
import réussi est écrit dans le journal de l'application (« IMDb import: … ») ; un import
interrompu ne casse rien (les notes déjà écrites restent, rien n'est enregistré dans
`imdb_imports`, l'heure suivante recommence).

- `title.ratings.tsv.gz` → table `imdb_ratings` (`tconst`, `rating`, `votes`) : films, séries,
  épisodes… (environ 1,6 million de lignes).
- `title.episode.tsv.gz` → table `imdb_episodes` (`tconst`, `parent_tconst`, `season`,
  `episode`) : **seuls les épisodes qui ont une note** sont gardés.

Chaque fichier est téléchargé dans un fichier temporaire (15 min au plus, 2 nouvelles tentatives),
lu ligne par ligne et enregistré par lots de 2 000 (`upsert`) : la mémoire reste faible. Chaque
ligne porte le numéro de l'import (`import_id`) ; les lignes qu'IMDb n'a plus sont supprimées à la
fin d'un import **complet** seulement. Un import qui échoue (réseau, fichier vide) garde les notes
précédentes et la commande se termine en erreur (journalisée).

## Licence d'IMDb

Les jeux de données d'IMDb sont fournis **pour un usage personnel et non commercial**
(<https://developer.imdb.com/non-commercial-datasets/>), et IMDb peut retirer cette autorisation.
Un usage commercial demande une licence d'IMDb (AWS Data Exchange).

## Tests

`tests/Feature/Api/V1/Catalog/ImdbRatingsTest.php` : import (fichiers TSV gzippés simulés,
lignes invalides ignorées, anciennes lignes supprimées, échec sans perte), vérification horaire
(`HEAD` seulement tant que `Last-Modified` ne change pas, import quand il change, `--force`, IMDb
injoignable), notes d'un film (par
`imdb_id` ou par les *External IDs*), d'une série, d'un titre sans note IMDb et des épisodes (par
numéro, par *External IDs*, épisode pas encore diffusé, TMDb indisponible).
