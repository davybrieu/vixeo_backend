# Synchronisation par profil

« À voir » (anciennement « Ma liste »), progression de lecture (« Continuer à regarder », reprise), contenus vus,
« Favoris » ❤ et personnes suivies sont synchronisés **par profil** entre tous les appareils du compte. L'appli garde une copie
locale (drift) et fonctionne hors ligne ; l'API sert de point de rendez-vous.

## Modèle : un « item » par contenu et par profil

Un item est identifié par **`source` + `item_id`**, choisis par l'appli :

| Contenu | `source` | `item_id` | `type` |
| - | - | - | - |
| Film TMDb | `tmdb` | `movie:550` | `movie` |
| Série TMDb | `tmdb` | `tv:1399` | `tv` |
| Épisode TMDb | `tmdb` | `episode:1399:1:3` (série:saison:épisode) | `episode` |
| Personne TMDb (acteur, réalisateur…) | `tmdb` | `person:287` | `person` |
| Chaîne de la TV en direct (`/tv/…`) | `tv` | `channel:<id de la chaîne>` (ex. `channel:tf1`) | `channel` |
| Chaîne FAST | `fast:<fournisseur>` (ex. `fast:pluto`) | id de la chaîne chez le fournisseur | `channel` |
| Vidéo locale | `local:<appareil>` | chemin ou id du fichier | `video` |

`source` : lettres, chiffres, `.`, `_`, `:`, `-` (100 caractères max). `item_id` : 191 max.
Pour un épisode, renseigner aussi `parent_item_id` (ex. `tv:1399`), `season_number` et
`episode_number`.

Chaque item porte un **instantané d'affichage** (`title`, `year`, `poster_url`,
`backdrop_url`) pour afficher les listes sans interroger la source. Pour une personne : son nom
dans `title` et sa photo dans `poster_url`. **Jamais d'identifiants
dans les URL d'images** (`api_key`, `token`… sont refusés) : utiliser des URL d'images
publiques, ou laisser `poster_url` vide et la reconstruire localement.

Et cinq **groupes d'état**, chacun avec la date du dernier changement :

| Groupe | Champs | Date du changement |
| - | - | - |
| À voir (« favori » historique) | `is_favorite`, `favorited_at` | `favorite_changed_at` |
| Progression | `position_ms`, `duration_ms`, `last_engaged_at` | `progress_changed_at` |
| Vu | `is_watched`, `watched_at` | `watched_changed_at` |
| J'aime ❤ (films, séries) | `is_liked`, `liked_at` | `liked_changed_at` |
| Personne suivie (`person`) | `is_followed`, `followed_at` | `followed_changed_at` |

- « À voir » (à regarder plus tard ; « Ma liste » jusqu'au 23/11/2026, désormais rangée en tête
  des listes du profil, jamais supprimée : `docs/social.md`) porte le nom historique `favorite`
  dans l'API.
- « J'aime » ❤ (`liked`) : les films et séries aimés, indépendants de « À voir » (supprimés le
  11/11/2026, rétablis le 20/11/2026 à la demande du développeur). Aimer de nouveau garde la
  première date. Un titre aimé va au fil d'actualité des abonnés (`social_activities`, `liked`).
- Personnes suivies (`followed`) : items de type `person` (`person:<id TMDb>`), « Suivre » sur la
  page d'une personne. Suivre de nouveau garde la première date.
- « Continuer à regarder » = `last_engaged_at` non nul et `is_watched` faux, du plus récent
  au plus ancien.
- `progress` (0 à 1) est calculé par l'API pour la barre de progression.

## Règle de fusion : le changement le plus récent gagne, groupe par groupe

L'appli envoie, pour chaque groupe modifié, la **date du changement sur l'appareil** (`at`).
L'API n'applique un groupe que si cette date est plus récente (ou égale) que celle déjà
connue. Les groupes sont indépendants : un favori ajouté sur la TV et une progression
enregistrée sur le téléphone ne s'écrasent jamais.

- Dates à la seconde près, en ISO 8601 (`2026-06-01T10:30:00Z`).
- Une date dans le futur (horloge d'appareil déréglée, plus de 5 min d'avance) est ramenée
  à l'heure du serveur.
- Renvoyer deux fois le même changement est sans effet (rien n'est modifié).

## Algorithme côté appli

État local par profil : la table des items, une file des **changements en attente**,
et le **`cursor`** (entier, 0 au départ).

1. **À chaque action** (favori, lecture, « marquer vu »…) : appliquer localement, ajouter le
   changement à la file avec `at = maintenant`.
2. **Envoyer** (au démarrage, au retour du réseau, toutes les 30 s pendant la lecture, à la
   pause/fin) : `POST /api/v1/profiles/{id}/sync` avec jusqu'à 500 changements. En cas de
   succès, retirer ces changements de la file et remplacer les items locaux par ceux renvoyés
   (`data`, état fusionné).
3. **Recevoir** : `GET /api/v1/profiles/{id}/sync?cursor=<cursor>` ; remplacer les items
   locaux par ceux reçus, sauf les groupes ayant un changement en attente plus récent ;
   enregistrer le nouveau `cursor` ; recommencer tant que `has_more` vaut `true`.
4. Un item reçu sans favori, sans progression, non vu, non aimé et non suivi peut être supprimé
   localement.

Ordre conseillé : envoyer, puis recevoir. Premier lancement sur un appareil : `cursor = 0`
(téléchargement complet, les items vides sont omis).

## Exemples

Lecture en cours puis film terminé (deux changements possibles dans le même envoi) :

```json
POST /api/v1/profiles/3/sync
{
  "changes": [
    {
      "source": "tmdb", "item_id": "movie:550", "type": "movie",
      "title": "Fight Club", "year": 1999,
      "poster_url": "https://image.tmdb.org/t/p/w342/pB8BM7pdSp6B6Ih7QZ4DrQ3PmJK.jpg",
      "progress": { "position_ms": 8340000, "duration_ms": 8340000, "continue_watching": false, "at": "2026-06-01T22:41:00Z" },
      "watched": { "value": true, "at": "2026-06-01T22:41:00Z" }
    }
  ]
}
```

Réponse : `{ "data": [ { …état fusionné… } ], "revision": 42 }`.

Actions de l'appui long :

| Action | Changement |
| - | - |
| Ajouter à / retirer de « À voir » | `favorite: { value: true/false, at }` |
| Aimer / ne plus aimer ❤ | `liked: { value: true/false, at }` |
| Suivre / ne plus suivre une personne | `followed: { value: true/false, at }` (type `person`) |
| Marquer vu / non vu | `watched: { value: true/false, at }` |
| Vu jusqu'ici (épisodes) | un changement `watched` par épisode précédent, dans le même envoi |
| Retirer de « Continuer à regarder » | `progress: { position_ms: <inchangée>, continue_watching: false, at }` |
| Reprendre la lecture | `progress: { position_ms, duration_ms, continue_watching: true, at }` |

## Limites

- 500 changements par envoi, 1 000 items par réception, 120 appels par minute et par compte.
- Un compte n'accède qu'à ses profils ; supprimer un profil supprime ses items.
- Le code PIN d'un profil est une protection d'interface : la synchro n'en dépend pas.

## Pages publiques d'un membre

`GET /api/v1/social/profiles/{username}` donne aussi `liked_count` (J'aime ❤), `people_count`
(personnes suivies), `episodes_count` (épisodes vus) et `watch_minutes` (temps de visionnage, demande
du développeur, 25/11/2026 : la somme des durées des films et des épisodes des visionnages dont TMDb
connaît la durée, revisionnages compris ; une série marquée vue d'un coup ne compte pas, comme dans
le bilan de l'année). Listes paginées (**20 par page**, `?page=`, les plus récents d'abord ; les applis
chargent la page suivante en défilant) :

| Route | Contenu |
| - | - |
| `GET /api/v1/social/profiles/{username}/list` | « À voir » (`is_favorite`) |
| `GET /api/v1/social/profiles/{username}/liked` | J'aime ❤ : `type`, `item_id`, `title`, `year`, `poster_url` |
| `GET /api/v1/social/profiles/{username}/watched` | Vus (films et séries) : mêmes champs et `watched_at` |
| `GET /api/v1/social/profiles/{username}/people` | Personnes suivies : `id` (TMDb), `item_id`, `name`, `profile_url` |
