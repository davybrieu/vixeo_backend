# Notes des titres (IMDb et TMDb)

Les fiches des films, des séries et les épisodes d'une saison donnent **deux notes** : celle
d'IMDb (importée chaque nuit depuis ses jeux de données) et celle de TMDb, chacune avec son
nombre de votants (demande du développeur, 21/11/2026). Les applis affichent la note d'IMDb,
sinon celle de TMDb, et un appui sur la note ouvre une fenêtre avec les deux.

Remplace les notes des autres sites (MDBList : Rotten Tomatoes, Metacritic, Letterboxd…) et le
réglage du profil « Source des notes » (`rating_source`), supprimés le 21/11/2026 avec la route
`GET /catalog/ratings`.

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
- **Titres des listes** (`MediaItemResource`) : `ratings` est aussi donné avec les titres qui ont
  un résumé (`summary` : accueil, recherche, recommandations d'une fiche, `GET /catalog/titles`
  pour « Ma liste », « Continuer à regarder » et les rangées légères) : `TitleSummaries` lit déjà
  la fiche TMDb de chaque titre (avec `external_ids` pour les séries) et cherche leurs notes IMDb
  en **une requête** par réponse. Ailleurs (« Voir tout », filmographie…) `ratings` est `null` :
  les applis montrent `vote_average` (TMDb). Aucune requête de note par carte.

## Recherche : note minimale et « Mieux notés »

`GET /catalog/search` et `/catalog/search/{movie|tv}` acceptent `min_rating` (0 à 10) et
`sort=rating` (rétablis le 21/11/2026, demande du développeur). La note utilisée
(`App\Services\Tmdb\TitleFilters::rating`) est **celle de TMDb en priorité** :

1. TMDb, si elle a au moins 20 votes (`MIN_TMDB_VOTES`) ;
2. sinon IMDb (les résultats reçoivent leur `summary` et leur note IMDb avant le filtre, comme
   ceux qu'affichent les applis : même cache) ;
3. sinon TMDb avec moins de votes ; sinon aucune note.

`min_rating` écarte les titres sous le seuil et ceux sans note. `sort=rating` classe d'abord les
titres dont la note a assez de votes (cas 1 et 2), puis ceux du cas 3, puis ceux sans note ; à
note égale, l'ordre de TMDb (pertinence). Comme les autres filtres, ils portent sur les 60
premiers résultats du texte, et une recherche avec `min_rating` ne renvoie pas de personnes.

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
