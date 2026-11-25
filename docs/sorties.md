# Sorties de la semaine

## API

`GET /api/v1/catalog/releases?from=YYYY-MM-DD&to=YYYY-MM-DD&type=all`

- `from` et `to` sont des dates calendaires TMDB inclusives, sur sept jours maximum.
- `type` : `all`, `movie`, `tv` ou `episode`.
- Les dates ne sont pas converties dans le fuseau du serveur.
- Les éléments contiennent `type`, `tmdb_id`, `title`, `poster_url`, `release_date`, les champs propres au contenu et `navigation`.

## Agrégation

`App\Services\Tmdb\WeeklyReleases` utilise le client TMDB commun (`TmdbClient`) : ses réponses Discover et détails bénéficient donc du cache partagé et de la gestion des erreurs existante.

- Films : `discover/movie` avec dates inclusives, types de sortie cinéma/numérique et sans contenu adulte. Les dates de `release_dates` par pays sont examinées ; la première date renseignée dans la période est affichée.
- Séries : `discover/tv` avec `first_air_date`.
- Épisodes : séries TMDB présentes dans les éléments de profils Vixeo, complétées par `discover/tv` filtré sur `air_date`. Le service `UpcomingEpisodes` existant récupère en lots les fiches et les saisons contenant un épisode connu. Les dates affichées restent les valeurs calendaires `air_date` de TMDB.

La découverte des épisodes est volontairement progressive : elle ne parcourt pas l'intégralité du catalogue TMDB. Les résultats dépendent des séries connues/découvertes et des dates publiées par TMDB. Les données peuvent être incomplètes ou modifiées par TMDB.

## Cache et planification

- Résultat agrégé par période : cache partagé de 12 heures.
- `php artisan vixeo:releases-sync` rafraîchit la semaine courante et la suivante.
- Planification quotidienne à 04:20, sans chevauchement et en arrière-plan.
- Aucune migration : les identifiants de séries existants et le cache Laravel suffisent.
