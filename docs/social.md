# Fonctions sociales : réseaux, compte privé, J'aime, notifications, fil d'actualité, listes, activité

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
| `review_comment` | quelqu'un commente un avis du profil (`review`, `comment`) | « Nouveau commentaire » |
| `list_like` | quelqu'un aime une liste du profil (`list`) | « J'aime » |
| `list_comment` | quelqu'un commente une liste du profil (`list`, `comment`) | « Nouveau commentaire » |
| `moderation` | une décision de la modération (`text`, `docs/moderation.md`) | « Modération » |

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
séries de « À voir » du profil (`AgeGate` appliqué), `upcoming` = le prochain épisode connu de
chacune (TMDb `next_episode_to_air`), le plus proche d'abord, et `recent` = les épisodes sortis
depuis moins de 7 jours. `available_at` est l'heure estimée de `UpcomingEpisodes::availableAt`
(jamais trop tôt), comme les notifications des nouveaux épisodes (`docs/notifications.md`).

## Le réseau social des films et séries (23/11/2026)

Demande du développeur : « un réseau social comme Twitter, mais sans tweet de texte ». **Toute
publication porte sur une œuvre** : un avis, un visionnage, un J'aime ou une liste ; on discute
dans les commentaires. Tout ce qu'un profil publie est vu de tous s'il est public, de ses
abonnés acceptés s'il est privé, jamais d'un profil bloqué (dans un sens ou l'autre), et plus du
tout une fois suspendu ou masqué par la modération (`App\Services\Social\Audience`,
`docs/moderation.md`). Les profils enfants n'y ont pas accès. Formes JSON communes :
`App\Services\Social\SocialCards` (un profil, un avis, une liste, un commentaire).

### Fil d'actualité : « Pour toi » et « Abonnements »

« Fil d'actualité » dans les applis (renommé le 23/11/2026, demande du développeur ; l'adresse de
l'appli reste `/fil`). Deux onglets, **« Pour toi » par défaut** (demande du développeur,
23/11/2026). Le fil montre **les avis, les titres vus, les J'aime et les listes** (demande du
développeur, 23/11/2026).

Table `social_activities` (`App\Services\Social\Activities`) : un **avis** (nouveau, ou avec un
nouveau commentaire : il remonte), un **visionnage** (une entrée de `diary_entries`), un **J'aime**
(`liked`, `profile_item_id` : l'item de la synchro ; écrit par `ProfileSync::push` quand un film ou
une série devient aimé, supprimé quand il ne l'est plus ; aimé de nouveau, il remonte) et une
**liste** (dès son premier titre). `kind` = `review` (`review`), `list` (`list`), `watched`
(`title`, les 3 premiers `profiles`, `profiles_count`, `episodes_count`, `rewatch`) ou `liked`
(`title`, `profiles`, `profiles_count`) : les visionnages et les J'aime d'un même titre sont
regroupés dans une page (« Léa, Max et 1 autre ont vu… », « Léa et Max aiment… »), et omis quand le
même profil a publié un avis du même titre dans la page.

**« Pour toi »** (`GET /social/feed/for-you?page=`, `Feed::forYou`) : les avis et les listes de
tout le réseau que le profil peut voir (pas les siens, ni ceux des profils masqués ou bloqués ; les
300 plus récents), plus les titres vus et aimés **des profils qu'il suit** (les 200 plus récents),
des 30 derniers jours, classés : une liste vaut un peu plus qu'un avis, un avis écrit plus que des
étoiles seules, un titre vu ou aimé moins (davantage quand plusieurs abonnements l'ont vu ou
aimé) ; chaque J'aime et chaque commentaire ajoutent, comme les goûts en commun avec l'auteur
(`Taste::compatibilities`) et le fait de le suivre ; puis le score baisse avec l'âge (environ de
moitié en 2 jours). Plusieurs voix par page : chaque publication précédente d'un même auteur fait
baisser les suivantes. 20 par page, `next` = la page suivante (null à la fin).

**« Abonnements »** (`GET /social/feed?before=`) : ce qu'ont fait le profil actif et ceux qu'il
suit, le plus récent d'abord, 40 activités par page (`next` : le `before` de la page suivante, null
à la fin). Profils masqués, bloqués ou suspendus et contenus masqués : absents.

### Activité d'un profil (`GET /social/profiles/{pseudo}/activity?before=`)

L'onglet « Activité » de la page d'un profil (demande du développeur, 23/11/2026 : « la liste de
tes actions avec la date ») : ses avis, listes, titres vus et J'aime, **chacun avec sa date**
(`created_at`), le plus récent d'abord, 40 activités par page ; mêmes éléments que le fil, mais
rien n'est omis (un avis et le visionnage du même titre restent tous deux) et les épisodes d'une
même série vus le même jour sont regroupés (`episodes_count`). Compte privé : ses abonnés
acceptés seulement (403), bloqué : 404 (`Feed::activity`).

### Pages d'un profil : « À voir », J'aime, vus

Les onglets de la page d'un profil dans les applis : Activité, Listes, Avis, Aimés, Vus. **« À voir »**
(anciennement « Ma liste », renommée et rangée parmi les listes le 23/11/2026) est le groupe
`favorite` de la synchronisation (`docs/synchronisation.md`), pas une ligne de `profile_lists` :
toujours là, jamais supprimée, sans titre ni description à choisir, triée par date d'ajout ;
`GET /social/profiles/{pseudo}/list`. J'aime : `…/liked` ; **vus** : `GET
/social/profiles/{pseudo}/watched` (films et séries vus, le plus récent d'abord, avec
`watched_at`). Le « Journal » a été supprimé (demande du développeur, 23/11/2026) : ni page, ni
`GET /social/profiles/{pseudo}/diary`, ni `DELETE /profiles/{id}/diary/{entrée}`.

### Listes (`/social/lists…`)

`profile_lists` + `profile_list_items` (`App\Services\Social\Lists`) : un titre (100 caractères),
une description (1 000) et jusqu'à 100 titres **dans l'ordre choisi par l'auteur** ; un « Top 10 »
est une liste comme les autres (plus de `kind` `top` / `list` depuis le 23/11/2026, demande du
développeur). Chaque titre garde ce que la liste en montre (nom, année, images), tiré de TMDb à
l'ajout (`TitleSnapshots`, cache de `TitleSummaries`) : afficher une liste n'interroge pas TMDb.
Routes : `GET /social/lists?item=movie:550` (mes listes, `contains` : la fenêtre « Ajouter à une
liste » des applis, avec « À voir » en tête), `POST /social/lists`, `GET|PUT|DELETE
/social/lists/{id}` (`items` : la liste entière dans le nouvel ordre), `POST
/social/lists/{id}/items` (`item`, ajouté à la fin), `DELETE /social/lists/{id}/items/{type}/{id}`,
`PUT|DELETE /social/lists/{id}/like` (`list_likes`), `GET|POST /social/lists/{id}/comments`,
`GET /social/profiles/{pseudo}/lists` (celles qui ont un titre, sauf pour leur auteur).

### Avis : spoilers et commentaires

`contains_spoilers` sur un avis (`PUT …/ratings…`, gardé s'il n'est pas envoyé) et sur un
commentaire : les applis masquent le texte derrière « Afficher ». `GET /social/reviews/{id}`
(l'avis comme publication : auteur, J'aime, `comments_count`), `GET|POST
/social/reviews/{id}/comments` (les 50 derniers, du plus ancien au plus récent ; `before` pour les
précédents), `DELETE /social/comments/{id}` (son auteur, ou l'auteur de l'avis ou de la liste).
Table `social_comments` (`App\Services\Social\Comments`, 1 000 caractères), l'auteur est notifié.

### Visionnages et bilan de l'année

`diary_entries` (`App\Services\Social\Diary`) : **écrit tout seul** quand un film ou une série
devient « vu » par la synchronisation (le lecteur à la fin, le bouton « Vu » ;
`ProfileSync::push`, après la transaction), daté du `watched_at` du changement ; un épisode vu
jusqu'au bout ou un nouveau visionnage s'ajoute par `POST /profiles/{id}/diary` (`item`, saison et
épisode facultatifs, `watched_at`), marqué `rewatch` quand le titre était déjà vu. Chaque entrée va
au fil et à l'« Activité » du profil. `GET /social/profiles/{pseudo}/stats?year=` (« Bilan de
l'année » : titres, films, épisodes, séries, revisionnages, minutes, mois, 5 genres, meilleures
notes, série la plus vue ; durée et genres tirés de TMDb à l'écriture, complétés à la lecture si
TMDb était en panne).

### Page d'un titre (`GET /social/titles/{type}/{id}`)

`friends` : les profils suivis qui l'ont vu ou noté (`rating` null s'ils l'ont seulement vu), notés
d'abord, et `friends_average` ; `reviews` : les 3 avis avec commentaire les plus aimés
(`reviews_count`) ; `lists` : les listes qui l'ont, les plus aimées d'abord. Tous les avis :
`GET /social/titles/{type}/{id}/reviews?sort=popular|recent`.

### Goûts en commun et comptes à suivre

`App\Services\Social\Taste` : `compatibility` de `GET /social/profiles/{pseudo}` (`score` 0 à
100 : 100 − 20 × l'écart moyen des notes des films et séries notés par les deux, null en dessous de
5 titres communs ; `common`). `GET /social/suggestions` : suivis par mes abonnements, puis goûts
proches, puis les plus suivis ; jamais ceux que je suis ou bloque ; avec la `reason` à afficher.

### Bloquer, masquer

`POST|DELETE /social/profiles/{pseudo}/block` : **partout** (table `direct_message_blocks`, la
même que la messagerie) : les abonnements dans les deux sens sont supprimés, ni l'un ni l'autre ne
voit, ne suit, n'aime, ne commente ni n'écrit à l'autre ; la page d'un membre qui m'a bloqué
répond 404, celle d'un membre que j'ai bloqué dit `is_blocked`. `POST|DELETE …/mute` (« Masquer »,
`profile_mutes`) : hors de mon fil seulement, toujours suivi, sans le lui dire. `GET
/social/hidden` : comptes bloqués et masqués (réglages de l'appli).

### Profil : 4 favoris, note en fin de lecture, premier lancement

`PUT /profiles/{id}` : `pinned_titles` (« Mes 4 favoris », `movie:550`… dans l'ordre, gardés avec
ce que le profil en montre ; `pinned` de `GET /social/profiles/{pseudo}`), `rate_after_watching`
(le lecteur propose de noter à la fin, vrai par défaut) et `onboarded: true` (le premier lancement
a été fait ou passé ; `onboarded` de la réponse ; les profils existants le sont déjà, un nouveau
non).

### Partager dans un message

`POST /messages/{id}` accepte `share` = `{type: title|review|list, id}` (« Recommander à un
ami »), avec ou sans texte : ce que la conversation montre (titre, affiche, note, extrait sans
spoiler, auteur, couverture d'une liste) est gardé avec le message (`direct_messages.shared`,
`SharedCards`) ; l'expéditeur doit le voir (404 sinon).

## Données du compte

Les nouvelles tables sont supprimées avec le profil (clés étrangères `cascadeOnDelete`) et
figurent dans l'export (`AccountExport` : `username`, `bio`, `is_private`, `social_links`,
`following`, `followers`, `review_likes`, `notifications`, `lists`, `list_likes`, `comments`,
`diary`, `muted`, `reports`, `pinned_titles`, `rate_after_watching`).
