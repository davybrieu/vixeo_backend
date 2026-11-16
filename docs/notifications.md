# Notifications des nouveaux épisodes

Les applis (Android) préviennent quand un nouvel épisode d'une série de « Ma liste » sort. Ce
sont des **notifications locales** : l'appli les programme elle-même sur l'appareil, à l'heure
de sortie prévue. Pas de service de push (Firebase…), pas de jeton d'appareil enregistré par
l'API : l'API dit seulement quels épisodes arrivent et quand.

## `GET /api/v1/catalog/episodes/upcoming?ids=1399,66732`

Public, comme le reste du catalogue (`throttle:catalog`), avec l'en-tête `X-Profile-Id` quand un
profil est actif : les séries que le profil ne peut pas voir (âge maximum) sont retirées
(`AgeGate::allows`).

- `ids` : identifiants TMDb de séries, séparés par des virgules, 100 au plus
  (`UpcomingEpisodesRequest`). L'appli envoie les séries de « Ma liste ».
- Réponse : `{"data": [...]}`, les épisodes disponibles dans les **60 prochains jours**, le plus
  proche d'abord, 200 au plus (`App\Services\Tmdb\UpcomingEpisodes`) :

```json
{
  "show_id": 100088,
  "show_name": "Lupin",
  "poster_url": "https://image.tmdb.org/t/p/w342/…",
  "season_number": 4,
  "episode_number": 1,
  "name": "Chapitre 1",
  "air_date": "2026-11-20",
  "available_at": "2026-11-20T08:00:00+00:00",
  "still_url": "https://image.tmdb.org/t/p/w300/…"
}
```

## D'où viennent les épisodes

Pour chaque série : sa fiche TMDb (le même cache d'un jour que les résumés de l'accueil et de
« Ma liste », `TitleSummaries::shows`), qui donne `next_episode_to_air`, puis la saison de cet
épisode (cache d'un jour, le même que la page de la saison) pour avoir les épisodes suivants.
Saison introuvable : au moins le prochain épisode. Une série terminée n'a pas de prochain
épisode : rien.

## L'heure de sortie (`available_at`)

TMDb ne donne qu'une **date** (`air_date`, dans le pays de la série). L'heure est une estimation
faite pour ne **jamais prévenir trop tôt** :

- plateformes de streaming (Netflix, Prime Video, Disney+, Apple TV+, Hulu, HBO Max,
  Paramount+, Peacock : réseaux TMDb dans `UpcomingEpisodes::STREAMING`) : **08:00 UTC**
  (minuit en Californie, 9 h à Paris en hiver) ;
- autres chaînes : **21:00 dans le fuseau du pays de la série** (`origin_country`, première
  valeur ; `UpcomingEpisodes::ZONES` pour les pays à plusieurs fuseaux, UTC si inconnu). Une
  série américaine diffusée le 17 au soir à New York est annoncée le 18 à 2 h UTC.

L'appli décale ce qui tombe la nuit (23 h – 8 h, heure de l'appareil) à 8 h.
