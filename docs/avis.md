# Avis d'un profil

Chaque profil **note** des films, des séries et des épisodes, avec un **commentaire** facultatif,
et retrouve ses avis dans « Mes avis ». **Les avis sont à Vixeo seulement** (décision du
développeur) : ils ne sont ni envoyés à un compte TMDb, ni lus depuis un compte TMDb.

**Plus de liaison d'un compte TMDb** (supprimée à la demande du développeur, 20/11/2026) : ni
liaison, ni synchronisation des notes avec themoviedb.org. La table `tmdb_links` (avec les
sessions TMDb chiffrées) et la colonne `tmdb_ratings.account_id` ont été supprimées
(migration `2026_11_20_150000_drop_tmdb_account_links`). Les notes déjà arrivées de TMDb avant
cette date restent des avis Vixeo comme les autres.

**Avis d'épisodes** : supprimés le 20/11/2026 (migration `2026_11_20_230000_drop_episode_reviews`,
les avis d'épisodes enregistrés alors sont perdus), **rétablis le 21/11/2026** à la demande du
développeur (migration `2026_11_21_100000_restore_episode_reviews` : colonnes `season_number`,
`episode_number`, `episode_name`, `still_path`, routes et filtre `type=episode`).

## Routes

Routes sous `/api/v1/profiles/{profile}/tmdb/…` (nom historique, gardé : les applis installées
l'utilisent), limite `throttle:reviews` (120 requêtes par minute et par compte Vixeo).

| Méthode | Route (`/api/v1/profiles/{profile}/tmdb/…`) | Rôle |
| --- | --- | --- |
| GET | `ratings?type=movie\|tv\|episode&sort=recent\|oldest\|highest\|lowest&page=1&per_page=20` | « Mes avis » : tous les types sans `type`, avec les commentaires (`per_page` jusqu'à 500). Tri `sort` : `recent` (par défaut, notés le plus récemment d'abord), `oldest`, `highest`, `lowest` ; à note égale, le plus récent d'abord. Le tri est fait avant la pagination |
| GET | `ratings/{movie\|tv}/{id}` | note et commentaire du profil pour un film / une série (`value` : `null` si aucune) |
| GET | `ratings/tv/{id}/season/{season}` | notes et commentaires des épisodes d'une saison (seulement ceux notés) |
| PUT | `ratings/{movie\|tv}/{id}` | noter ou modifier (`value` : 0,5 à 10, multiple de 0,5 ; `comment` facultatif, 2000 caractères) |
| DELETE | `ratings/{movie\|tv}/{id}` | supprimer l'avis (note et commentaire) |
| PUT / DELETE | `ratings/tv/{id}/season/{season}/episode/{episode}` | idem pour un épisode |

- **Les avis** (`App\Services\TmdbRatings`, table `tmdb_ratings`, une ligne par profil et
  titre) : note, commentaire, date, et ce que « Mes avis » montre du titre (nom, affiche, image
  de l'épisode, lus sur TMDb avec la clé Vixeo, en cache).
- TMDb injoignable à l'écriture : l'avis est enregistré sans son titre ; « Mes avis » le complète
  à l'affichage suivant (10 avis au plus par affichage, pour ne jamais ralentir la page).
- `comment` absent du PUT : le commentaire est gardé ; `null` ou vide : il est supprimé.
- **Un nouvel avis marque le titre vu** (demande du développeur, 23/11/2026) : le film, la série
  ou l'épisode (`movie:550`, `tv:1399`, `episode:1399:1:2` sous `tv:1399`), par la synchro
  (`ProfileSync::push`, comme le bouton « Vu » : hors de « Continuer à regarder », visionnage et
  fil), sauf s'il l'est déjà. Modifier un avis ne remarque rien : un « Vu » retiré le reste. Les
  applis le reçoivent à leur synchro suivante.
- Supprimer un avis qui n'existe pas répond 204.
- La page publique d'un membre (`/social/profiles/{username}/reviews`) montre ses avis.
- `422` : note hors de l'échelle (validation).

## Anciennes applis

Les applis installées avant la suppression ont encore une page « Connexions ». Pour elles, deux
routes restent (`TmdbAccountController`, marquées obsolètes dans la doc OpenAPI) :

- `GET /api/v1/profiles/{profile}/tmdb` → toujours `{"data":{"linked":false,…}}` (champs à
  `null`) : aucun compte n'est lié ;
- `POST /api/v1/profiles/{profile}/tmdb/link` → toujours `410` : « La liaison d'un compte TMDb
  n'est plus proposée : vos avis restent dans Vixeo. »

`link/complete` et `DELETE …/tmdb` n'existent plus.

## Données

- Export des données (`AccountExport`) : les avis avec leurs commentaires (`tmdb_ratings`).
- Supprimer le profil ou le compte supprime ses avis (`cascadeOnDelete`).
