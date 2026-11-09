# Compte TMDb d'un profil et notes

Chaque profil peut être lié à un compte [TMDb](https://www.themoviedb.org). Une fois lié, le profil
**note** des films, des séries et des épisodes, et retrouve ses notes dans « Mes notes ».

- Les notes sont **enregistrées sur TMDb**, pas chez Vixeo. Elles sont donc identiques sur tous les
  appareils, sur tous les profils liés au même compte TMDb et sur themoviedb.org (synchronisation
  dans les deux sens, sans copie à tenir à jour).
- Le **même compte TMDb** peut être lié à plusieurs profils, y compris de comptes Vixeo différents
  (pas d'index unique sur `account_id`). Chaque liaison a **sa propre session** TMDb.
- L'API de TMDb ne permet que des **notes** (de 0,5 à 10, par demi-points). Les avis écrits ne
  peuvent pas être publiés par l'API.

## Liaison

Le déroulé ressemble à la connexion d'une TV par QR code (`connexion-tv.md`), avec la page
d'autorisation de TMDb :

1. `POST /api/v1/profiles/{profile}/tmdb/link` →

   ```json
   {
     "authorize_url": "https://www.themoviedb.org/authenticate/<request_token>",
     "expires_in": 3600,
     "interval": 5
   }
   ```

   L'API demande un jeton de requête à TMDb (`GET /3/authentication/token/new`) et le garde en
   cache pour ce profil (durée de vie du jeton, 60 min). L'appli ouvre `authorize_url` (téléphone,
   ordinateur) ou l'affiche en QR code (TV) : la personne se connecte à TMDb et autorise Vixeo.
2. Toutes les `interval` secondes, `POST /api/v1/profiles/{profile}/tmdb/link/complete` :
   - `202 {"status":"pending"}` : pas encore autorisé, continuer ;
   - `200 {"status":"linked","data":{…compte…}}` : lié ;
   - `410 {"status":"expired"}` : page expirée (ou rien de commencé) → recommencer à l'étape 1.

   L'API crée la session (`POST /3/authentication/session/new`), lit le compte (`GET /3/account`)
   et l'enregistre dans `tmdb_links`. Un seul essai à la fois par profil (verrou). Une liaison
   refaite remplace la précédente et met fin à son ancienne session.
3. `GET /api/v1/profiles/{profile}/tmdb` → `linked`, `account_id`, `username`, `name`,
   `avatar_url`, `linked_at` (tout à `null` quand aucun compte n'est lié).
4. `DELETE /api/v1/profiles/{profile}/tmdb` → 204 : la liaison est supprimée et sa session est
   fermée sur TMDb (`DELETE /3/authentication/session`). Les notes restent sur TMDb.

## Notes

| Méthode | Route (`/api/v1/profiles/{profile}/tmdb/…`) | Rôle |
| --- | --- | --- |
| GET | `ratings?type=movie\|tv\|episode&page=1` | « Mes notes » : 20 par page, la plus récente d'abord |
| GET | `ratings/{movie\|tv}/{id}` | note du profil pour un film / une série (`value` : `null` si aucune) |
| GET | `ratings/tv/{id}/season/{season}` | notes des épisodes d'une saison (seulement ceux notés) |
| PUT | `ratings/{movie\|tv}/{id}` | noter ou modifier (`value` : 0,5 à 10, multiple de 0,5) |
| DELETE | `ratings/{movie\|tv}/{id}` | supprimer la note |
| PUT / DELETE | `ratings/tv/{id}/season/{season}/episode/{episode}` | idem pour un épisode |

- La liste filtre selon l'âge maximum du profil (`AgeGate`) ; pour un épisode, c'est sa série qui
  compte. Chaque élément donne le titre du film ou de la série (pour un épisode, la série vient de
  `GET /3/tv/{id}`, en cache), l'affiche, la date, la saison et l'épisode, et `value`.
- Supprimer une note qui n'existe pas répond 204.
- Rien n'est mis en cache : une note donnée sur themoviedb.org apparaît tout de suite.

## Erreurs

- `409` « Liez d'abord un compte TMDb à ce profil. » : aucun compte lié.
- `409` « Votre compte TMDb n'est plus lié à ce profil : liez-le à nouveau. » : TMDb refuse la
  session (accès retiré sur themoviedb.org). La liaison est supprimée ; l'appli propose de relier.
- `422` : note hors de l'échelle de TMDb (validation), ou demande refusée par TMDb.
- `503` : TMDb injoignable ou clé Vixeo refusée.
- Limite : `throttle:tmdb-account`, 120 requêtes par minute et par compte Vixeo.

## Sécurité

- La session TMDb donne accès en écriture au compte : elle est **chiffrée** en base (cast
  `encrypted`, clé `APP_KEY`), cachée des ressources, absente de l'export et jamais envoyée aux
  applis. Seule l'API parle à TMDb.
- TMDb v3 exige la session dans l'adresse (`?session_id=`) : `TmdbClient::send` ne journalise
  jamais que le chemin, sans les paramètres.
- Le jeton de requête figure dans `authorize_url` (c'est le fonctionnement de TMDb) : il ne sert
  qu'une fois, après l'accord de la personne, et expire en 60 minutes.
- Supprimer le profil ou le compte Vixeo supprime la liaison (`cascadeOnDelete`). La session reste
  alors valable chez TMDb jusqu'à ce que la personne la retire sur themoviedb.org, mais plus
  personne ne la connaît.
- Export des données (`AccountExport`) : identifiant et nom du compte TMDb, date de liaison.
