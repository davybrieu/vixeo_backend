# Fonctions sociales : réseaux, compte privé, J'aime, notifications

Demande du développeur, 21/11/2026, sur le modèle de Twitter. Le reste des pages de profil (pseudo,
biographie, abonnés, listes publiques, « Partager ») est décrit dans `CLAUDE.md` (« Recherche de
membres ») et `docs/site.md` (`www.vixeo.app/pseudo`).

## Réseaux sociaux du profil

`PUT /profiles/{id}` avec `social_links` : un compte par réseau, `instagram`, `x`, `tiktok`,
`youtube`, `letterboxd`, `threads`, `bluesky`, et `website` (une adresse). On peut taper le pseudo,
`@pseudo` ou l'adresse de la page : l'API garde le pseudo (`App\Support\SocialLinks::normalize`),
vérifie sa forme (« Ce compte X n'est pas valide. ») et ajoute `https://` à un site sans lui.
Seuls les réseaux envoyés changent ; `null` en retire un.

Renvoyés (profil, `GET /profiles`, page d'un membre `GET /social/profiles/{pseudo}`, page du site)
dans l'ordre ci-dessus : `[{network, label, handle, url}]`. Le site n'affiche que des liens (rien
n'est chargé depuis ces réseaux : aucun service tiers).

## Compte privé et demandes d'abonnement

`is_private` (`PUT /profiles/{id}`). Comme Twitter : nom, @pseudo, avatar, biographie, réseaux et
compteurs restent visibles ; **listes, favoris, personnes suivies, avis, abonnés et abonnements**
ne le sont que par le profil lui-même et ses **abonnés acceptés** (`can_view` de la page d'un
membre ; sinon ces routes répondent 403 « Ce compte est privé. »). Le compte reste trouvable dans
la recherche (`is_private` dans chaque résultat).

- `POST /social/profiles/{pseudo}/follow` : compte public → suivi tout de suite
  (`follow_status: following`) ; compte privé → **demande** (`requested`, ligne de
  `profile_follows` avec `accepted_at` nul). `DELETE` retire l'abonnement ou la demande.
- `GET /social/requests` : demandes reçues par le profil actif ; `POST /social/requests/{pseudo}/accept`,
  `DELETE /social/requests/{pseudo}` (refuser).
- Un compte qui redevient public accepte toutes les demandes en attente.
- `Profile::following()` / `followers()` et leurs compteurs ne comptent que les abonnements acceptés.
  Écritures : `App\Services\Social\Follows` uniquement.

## J'aime sur les avis

`PUT` / `DELETE /social/reviews/{review_id}/like` (`App\Services\Social\ReviewLikes`, table
`review_likes`). `review_id` est donné par les avis d'un membre (`GET /social/profiles/{pseudo}/reviews`,
avec `likes_count` et `liked`) et par « Mes avis » (`likes_count`). Pas son propre avis (422), ni
celui d'un compte privé qu'on ne suit pas (403).

## Notifications

Table `social_notifications` (`App\Services\Social\SocialNotifications`), une ligne par chose faite
par un autre profil, la dernière gardée (aimer de nouveau = la même notification, non lue) :

| `type` | Quand | Push |
| --- | --- | --- |
| `follow` | quelqu'un suit le profil (ou une demande acceptée, déjà lue) | « Nouvel abonné » |
| `follow_request` | quelqu'un demande à suivre le compte privé | « Demande d'abonnement » |
| `follow_accepted` | le compte privé accepte la demande du profil | « Demande acceptée » |
| `review_like` | quelqu'un aime un avis du profil (`review`) | « J'aime » |

Une action annulée (désabonnement, demande retirée ou refusée, J'aime retiré) retire sa
notification. Routes (profil actif `X-Profile-Id`, pas de profil enfant) : `GET /notifications`
(30 par page, `unread_count`), `GET /notifications/count` (la pastille de la navigation),
`POST /notifications/read` (tout lu, à l'ouverture de la page).

**Push** : envoyées après la réponse (`dispatch()->afterResponse()`, sans file d'attente) aux
applis du profil dont le réglage `social` est actif (`PUT /push/device`, `social`, vrai par défaut ;
une ancienne appli qui ne l'envoie pas ne le change pas), canal Android `social`, `data.path` :
la page du membre (`follow`, `follow_accepted`) ou `/notifications`.

**Prochains épisodes** : `GET /notifications/episodes` (demande du développeur : la page
Notifications de l'appli les montre ; ce n'est pas l'ancienne route `episodes/upcoming`) : pour les
séries de « Ma liste » du profil (`AgeGate` appliqué), `upcoming` = le prochain épisode connu de
chacune (TMDb `next_episode_to_air`), le plus proche d'abord, et `recent` = les épisodes sortis
depuis moins de 7 jours. `available_at` est l'heure estimée de `UpcomingEpisodes::availableAt`
(jamais trop tôt), comme les notifications des nouveaux épisodes (`docs/notifications.md`).

## Données du compte

Les nouvelles tables sont supprimées avec le profil (clés étrangères `cascadeOnDelete`) et
figurent dans l'export (`AccountExport` : `username`, `bio`, `is_private`, `social_links`,
`following`, `followers`, `review_likes`, `notifications`).
