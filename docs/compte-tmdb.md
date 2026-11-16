# Avis d'un profil et compte TMDb

Chaque profil **note** des films, des séries et des épisodes, avec un **commentaire** facultatif,
et retrouve ses avis dans « Mes avis ». **Vixeo est propriétaire des avis** (décision du
développeur) : on note, modifie et supprime un avis **sans compte TMDb**.

Un profil peut aussi lier un compte [TMDb](https://www.themoviedb.org). La liaison ne sert qu'à
**synchroniser** les avis avec ce compte :

- **Un compte TMDb ne peut être lié qu'à un seul profil Vixeo** (décision du développeur) : lier un
  compte déjà lié ailleurs répond 409 (`status: taken`).
- **TMDb suit Vixeo** : chaque avis écrit dans Vixeo est envoyé à TMDb (la note seulement) ; à la
  liaison, **tous les avis de Vixeo sont envoyés**, et une note différente sur TMDb pour le même
  titre est **remplacée par celle de Vixeo**. Les notes que TMDb a et que Vixeo n'a pas
  (données sur themoviedb.org) sont **ajoutées** aux avis.
- Une note modifiée ou supprimée sur themoviedb.org **ne change pas** l'avis de Vixeo.
- L'API de TMDb ne permet que des **notes** (de 0,5 à 10, par demi-points) : le commentaire reste
  chez **Vixeo seulement** (table `tmdb_ratings`), jamais envoyé à TMDb.
- Délier le compte **garde les avis** (ils sont à Vixeo) ; les notes restent sur TMDb.

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

   - `409 {"status":"taken"}` : ce compte TMDb est déjà lié à un autre profil Vixeo (la session
     tout juste créée est fermée) ; le délier d'abord de cet autre profil.

   L'API crée la session (`POST /3/authentication/session/new`), lit le compte (`GET /3/account`)
   et l'enregistre dans `tmdb_links`. Un seul essai à la fois par profil (verrou). Une liaison
   refaite remplace la précédente et met fin à son ancienne session. Puis elle **synchronise** les
   avis (`TmdbRatings::sync(all: true)`) : tous les avis de Vixeo partent sur TMDb (Vixeo gagne),
   les autres notes de TMDb arrivent ; TMDb injoignable : fait à la synchronisation suivante.
3. `GET /api/v1/profiles/{profile}/tmdb` → `linked`, `account_id`, `username`, `name`,
   `avatar_url`, `linked_at` (tout à `null` quand aucun compte n'est lié).
4. `DELETE /api/v1/profiles/{profile}/tmdb` → 204 : la liaison est supprimée et sa session est
   fermée sur TMDb (`DELETE /3/authentication/session`). Les avis restent dans Vixeo, les notes
   sur TMDb.

## Avis (« Mes avis »)

Routes sous `/api/v1/profiles/{profile}/tmdb/…` (nom historique) ; **aucune n'exige de compte
TMDb lié**.

| Méthode | Route (`/api/v1/profiles/{profile}/tmdb/…`) | Rôle |
| --- | --- | --- |
| GET | `ratings?type=movie\|tv\|episode&sort=recent\|oldest\|highest\|lowest&page=1&per_page=20` | « Mes avis » : tous les types sans `type`, avec les commentaires (`per_page` jusqu'à 500). Tri `sort` : `recent` (par défaut, notés le plus récemment d'abord), `oldest`, `highest`, `lowest` ; à note égale, le plus récent d'abord. Le tri est fait avant la pagination |
| GET | `ratings/{movie\|tv}/{id}` | note et commentaire du profil pour un film / une série (`value` : `null` si aucune) |
| GET | `ratings/tv/{id}/season/{season}` | notes et commentaires des épisodes d'une saison (seulement ceux notés) |
| PUT | `ratings/{movie\|tv}/{id}` | noter ou modifier (`value` : 0,5 à 10, multiple de 0,5 ; `comment` facultatif, 2000 caractères) |
| DELETE | `ratings/{movie\|tv}/{id}` | supprimer l'avis (note et commentaire) |
| PUT / DELETE | `ratings/tv/{id}/season/{season}/episode/{episode}` | idem pour un épisode |

- **Les avis** (`App\Services\TmdbRatings`, table `tmdb_ratings`, une ligne par profil et
  titre) : note, commentaire, date, titre, affiche, image de l'épisode (lus sur TMDb avec la clé
  Vixeo, en cache) et `account_id`, le compte TMDb auquel la note a été envoyée (`null` : pas
  encore). Toutes les lectures (fiche, saison, « Mes avis ») lisent Vixeo, plus TMDb.
- **Avec un compte lié** :
  - un avis écrit est enregistré, puis sa note envoyée à TMDb ; TMDb injoignable (ou session
    refusée) : l'avis est quand même enregistré (`account_id` à `null`) et envoyé à la
    synchronisation suivante ; une suppression qui n'a pas pu partir est retenue 30 jours et
    renvoyée ;
  - « Mes avis » synchronise au plus toutes les 10 minutes (`STALE_AFTER`, `syncIfStale`) : avis
    pas encore envoyés à ce compte → envoyés ; notes de TMDb (`/account/{id}/rated/…`, 50 pages au
    plus par type) absentes de Vixeo → ajoutées (TMDb ne donne pas leur date : elles gardent
    l'ordre de TMDb). TMDb injoignable : les avis sont affichés tels quels.
- `comment` absent du PUT : le commentaire est gardé ; `null` ou vide : il est supprimé.
- La liste filtre selon l'âge maximum du profil (`AgeGate`) ; pour un épisode, c'est sa série qui
  compte.
- Supprimer un avis qui n'existe pas répond 204.
- La page publique d'un membre (`/social/profiles/{username}/reviews`) montre ses avis.

## Erreurs

- `409` `{"status":"taken"}` (liaison) : « Ce compte TMDb est déjà lié à un autre profil Vixeo :
  déliez-le d'abord de ce profil. »
- Session refusée par TMDb (accès retiré sur themoviedb.org) : la liaison est supprimée, l'avis
  est quand même enregistré ; l'appli voit « non lié » et propose de relier.
- `422` : note hors de l'échelle (validation).
- `503` (liaison seulement) : TMDb injoignable ou clé Vixeo refusée.
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
- Export des données (`AccountExport`) : identifiant et nom du compte TMDb, date de liaison, et
  la copie des notes avec les commentaires. Supprimer le profil ou le compte supprime la copie
  (`cascadeOnDelete`).
