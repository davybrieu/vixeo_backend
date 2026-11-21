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
  épisodes) ; les listes (accueil, recherche, recommandations) n'ont que `vote_average` de TMDb :
  aucune requête de note par carte.

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

`App\Services\Imdb\ImdbDatasets`, planifié **chaque jour à 05:17** (`routes/console.php`,
`withoutOverlapping`, en arrière-plan), aussi à lancer à la main (la première fois après le
déploiement). Source : <https://datasets.imdbws.com> (`IMDB_DATASETS_URL`), mise à jour
quotidiennement par IMDb.

- `title.ratings.tsv.gz` → table `imdb_ratings` (`tconst`, `rating`, `votes`) : films, séries,
  épisodes… (environ 1,6 million de lignes).
- `title.episode.tsv.gz` → table `imdb_episodes` (`tconst`, `parent_tconst`, `season`,
  `episode`) : **seuls les épisodes qui ont une note** sont gardés.

Chaque fichier est téléchargé dans un fichier temporaire (15 min au plus, 2 nouvelles tentatives),
lu ligne par ligne et enregistré par lots de 2 000 (`upsert`) : la mémoire reste faible. Chaque
ligne porte le numéro de l'import (`import_id`) ; les lignes qu'IMDb n'a plus sont supprimées à la
fin d'un import **complet** seulement. Un import qui échoue (réseau, fichier vide) garde les notes
de la veille et la commande se termine en erreur (journalisée).

## Licence d'IMDb

Les jeux de données d'IMDb sont fournis **pour un usage personnel et non commercial**
(<https://developer.imdb.com/non-commercial-datasets/>), et IMDb peut retirer cette autorisation.
Un usage commercial demande une licence d'IMDb (AWS Data Exchange).

## Tests

`tests/Feature/Api/V1/Catalog/ImdbRatingsTest.php` : import (fichiers TSV gzippés simulés,
lignes invalides ignorées, anciennes lignes supprimées, échec sans perte), notes d'un film (par
`imdb_id` ou par les *External IDs*), d'une série, d'un titre sans note IMDb et des épisodes (par
numéro, par *External IDs*, épisode pas encore diffusé, TMDb indisponible).
