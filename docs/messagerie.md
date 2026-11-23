# Messagerie

Demande du développeur (22/11/2026) : des conversations privées entre membres, comme Instagram, avec
des groupes, des photos et des vidéos, en temps réel. Réservée aux **profils adultes qui ont un
pseudo** (`Conversations::allowed` ; sinon 403). Toutes les routes sont sous `/messages`, connectées,
au nom du profil de l'en-tête `X-Profile-Id`, limitées par `throttle:messages` (180 / min ; envoi de
fichiers : `throttle:message-attachments`, 20 / min).

Code : `App\Http\Controllers\Api\V1\DirectMessageController` (routes), `App\Services\Messaging\Conversations`
(**seule** classe qui écrit conversations, membres, messages, lectures et blocages), modèles
`Conversation`, `ConversationMember`, `DirectMessage`, `MessageAttachment`, `MessageRead`,
`MessageBlock`, ressources `App\Http\Resources\V1\Messages`. Tests : `tests/Feature/Api/V1/DirectMessageTest.php`.

## Conversations directes et demandes

- `POST /messages/with/{pseudo}` : la conversation avec ce membre (`{conversation_id}`). S'il n'y en a
  pas, une **demande** (`status: pending`) : seul celui qui l'a faite écrit, **sans fichier**, jusqu'à
  ce que l'autre l'accepte. Deux appuis en même temps n'ouvrent qu'une conversation (verrou).
- `GET /messages/requests` : les demandes reçues (`sender`, `preview` = premier message).
- `POST /messages/{id}/accept` ; `DELETE /messages/{id}/request` (refuser : cachée aux deux, la
  demande peut être refaite).
- Une conversation directe garde ses deux profils dans `profile_low_id` < `profile_high_id`.

## Groupes

- `POST /messages/groups` `{name, usernames[]}` : le profil actif est **administrateur** (`admin`),
  31 membres au plus ; le premier message dit qui a créé le groupe.
- Administrateurs : `PUT /messages/{id}/group` `{name}`, `POST …/group/members` `{usernames[]}` (un
  ancien membre revient sur sa ligne), `DELETE …/group/members/{pseudo}`, `POST …/group/admin/{pseudo}`
  (transmettre le groupe : l'autre devient administrateur, soi-même membre).
- `POST …/group/leave` : un administrateur seul en nomme d'abord un autre ; le dernier membre qui
  part supprime le groupe.
- Un groupe n'a **pas** de paire de profils (`profile_low_id` / `profile_high_id` nuls, migration
  `detach_group_conversations_from_profile_pair`) : supprimer un profil ne supprime jamais le groupe
  des autres. Son administrateur seul supprimé : le plus ancien membre le remplace.

## Messages

- `GET /messages` : les 100 conversations les plus récentes, **sans les demandes reçues**, chacune
  avec `participants` (les autres membres), `participant` (l'autre profil d'une conversation
  directe), `last_message`, `unread_count`, `status`, `updated_at` ; en plus `unread_count` (total) et
  `requests_count`. Lu en un nombre fixe de requêtes, quel que soit le nombre de conversations.
- `GET /messages/{id}` : les 50 derniers messages, du plus ancien au plus récent, ou les 50 avant
  `?before={id}` (`has_more`) ; `conversation` (`type`, `name`, `status`, `is_request`),
  `participants` (avec `role`), `is_admin`, `participant`, `blocked` (le profil actif a bloqué
  l'autre). Un message : `id`, `sender_profile_id`, `body`, `deleted`, `read_by` (profils qui l'ont
  lu), `created_at` (**ISO 8601 avec le fuseau**), `attachments`.
- `POST /messages/{id}` `{body}` (5 000 caractères au plus) → `201 {data: message}`.
- `POST /messages/{id}/read` : lit les messages des autres (accusés de lecture, `unread_count`).
- `DELETE /messages/{id}/{message}` : son propre message, **pour tout le monde** (la ligne reste,
  `deleted: true`, « Ce message a été supprimé. », fichiers effacés).

## Photos et vidéos

`POST /messages/{id}/attachments` (multipart, champ `file`) : JPEG, PNG, WebP, GIF (15 Mo au plus),
MP4, MOV, WebM (100 Mo au plus), une fois la demande acceptée ; un message à part, `body` vide. Les
fichiers sont sur le disque privé `local` (`storage/app/private/message-attachments`), jamais
publics : `GET /messages/attachments/{id}` les sert aux **membres** de la conversation seulement
(jeton et `X-Profile-Id`, `Cache-Control: private`). Le serveur doit accepter des envois de 100 Mo
(`client_max_body_size` de Nginx, `upload_max_filesize` et `post_max_size` de PHP, sur Forge).

Supprimer un profil ou un compte efface aussi les fichiers de ses messages et de ses conversations
directes (`Conversations::forgetProfiles`, avant la suppression : les lignes partent avec les clés
étrangères). L'export des données du compte contient les messages envoyés et les membres bloqués.

## Blocage

`POST /messages/block/{pseudo}`, `DELETE /messages/block/{pseudo}`. Dans les deux sens : ni écrire
à l'autre, ni démarrer une conversation, ni accepter sa demande, ni l'ajouter à un groupe.

## Temps réel et notifications

- Chaque changement est annoncé **après la réponse** sur le canal privé `messages.{id}` (Laravel
  Reverb, `App\Events\ConversationChanged`) : `message.sent` (avec `body`), `message.deleted`,
  `message.read` (seulement quand des messages viennent d'être lus : relire n'annonce rien, pas de
  boucle entre deux applis), `message.updated` (demande acceptée, groupe modifié). Les applis relisent
  alors la conversation. Autorisation : `POST /api/broadcasting/auth` (jeton, `X-Profile-Id`,
  `routes/channels.php` : membres actifs). Installation : `docs/reverb.md`. Sans Reverb configuré
  (clé vide), rien n'est annoncé et la messagerie marche quand même.
- Push (FCM, canal Android `social`, réglage « Activité » de l'appareil) aux autres membres : le nom
  de l'expéditeur (ou du groupe) et le début du message ; « Nouvelle demande de message » pour une
  demande ; `data.path` = `/messages/{id}`.
