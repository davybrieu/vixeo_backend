# Notifications push

Vixeo envoie des notifications **push** aux applis (Android, iOS plus tard) par **Firebase Cloud
Messaging** (FCM), le service de Google qu'utilisent les téléphones et les Google TV, même pour
une appli installée hors Play Store. L'appli ne programme rien elle-même : le serveur décide
quand prévenir.

**Windows n'a pas FCM** : ses notifications passent par le **canal temps réel** (Reverb,
`docs/reverb.md`) et l'appli les affiche elle-même, **tant qu'elle est ouverte** (une appli
installée par Inno Setup, sans identité de paquet MSIX, ne peut pas recevoir de notification
Windows appli fermée). Mêmes réglages, mêmes messages que sur Android (voir « Le canal temps réel
de Windows »).

- **Nouveaux épisodes** : une tâche planifiée (`vixeo:push-episodes`, toutes les 15 minutes)
  cherche les épisodes sortis des séries de « À voir » et prévient les profils qui l'ont
  demandé.
- **Messages à la main** : `php artisan vixeo:push` (à tout le monde ou à un compte).

## Configuration (Forge)

1. Créer un projet sur <https://console.firebase.google.com> (gratuit), y ajouter une appli
   **Android** au nom de paquet de Vixeo (`app.vixeo.vixeo`).
2. **Serveur** : *Paramètres du projet → Comptes de service → Générer une nouvelle clé privée*.
   Le fichier JSON est un secret : le déposer sur le serveur **hors du dépôt** (par exemple
   `/home/forge/firebase-vixeo.json`, lisible par l'utilisateur `forge` seulement) et écrire son
   chemin dans le `.env` : `FCM_CREDENTIALS=/home/forge/firebase-vixeo.json`. Ou bien le JSON
   encodé en base64 : `FCM_CREDENTIALS=base64:…` (`base64 -w0 fichier.json`).
3. **Appli** : les valeurs de l'appli Android (fichier `google-services.json` du projet) vont
   dans les secrets GitHub de `vixeo_frontend` (voir `docs/android-release.md` de l'appli).
4. Le planificateur (`schedule:run` chaque minute) lance `vixeo:push-episodes` ; sans
   `FCM_CREDENTIALS` ni Reverb, la commande s'arrête tout de suite.

Vérifier : `php artisan vixeo:push "Test" --user=<votre adresse> --force`.

## L'appli s'inscrit : `/api/v1/push/device`

Connecté (bearer), le serveur résout automatiquement le profil unique du compte :

- `PUT /push/device` : `platform` (`android`, `ios`, `windows`), puis sur Android et iOS
  **`fid`**, l'identifiant d'installation Firebase de l'appli (firebase_messaging 17 :
  `register()` / `onRegistered`), ou `token`, le jeton FCM des applis d'avant (toujours accepté) ;
  rien sur Windows. Et `new_episodes` (le profil veut les nouveaux épisodes sur cet appareil),
  `social` (facultatif, vrai par défaut : J'aime, abonnés, demandes d'abonnement et messages,
  `docs/social.md`), `utc_offset` (heure de l'appareil moins UTC, en minutes). À la connexion, au
  lancement, quand Firebase change l'identifiant, quand on change de profil et quand on active ou
  désactive les alertes. 201 la première fois, 200 ensuite. La réponse donne `id` (le canal temps
  réel de Windows) et `target` : `fid`, `token` ou `realtime`.
- `GET /push/device` : `{data, available, realtime}` ; `available` = le serveur envoie les
  notifications Firebase (FCM configuré), `realtime` = celles du canal temps réel (Reverb
  configuré).
- `DELETE /push/device` : désinscrit l'appli.
- `POST /push/device/test` : une notification de test sur cet appareil (409 s'il n'est pas
  inscrit, 503 si le serveur n'envoie pas de notification, si FCM refuse ou si l'appli Windows
  n'écoute pas son canal).

Une inscription par appli connectée (`push_devices`, une ligne par jeton Sanctum) : **se
déconnecter la supprime** (clé étrangère sur `personal_access_tokens`, comme la suppression du
compte ou la déconnexion d'un appareil). `target` dit comment elle reçoit : `fid`, `token`
(applis d'avant l'identifiant d'installation) ou `realtime` (Windows, sans identifiant).
L'identifiant ou le jeton est **chiffré** en base (son SHA-256 sert à le retrouver) et n'est
jamais renvoyé ; la même appli reconnectée remplace son ancienne inscription, une appli mise à
jour remplace son jeton par son identifiant. Une appli que FCM ne connaît plus (désinstallée) est
oubliée au premier envoi.

## Le canal temps réel de Windows (`App\Services\Push\RealtimePush`)

Une inscription `realtime` a son canal privé **`push-devices.{id}`** (`id` de la réponse de
`PUT /push/device`), autorisé par `POST /api/broadcasting/auth` pour l'appli connectée avec le
jeton Sanctum de cette inscription seulement (`routes/channels.php`). Envoyer une notification,
c'est diffuser l'événement **`notification`** (`App\Events\PushNotified` : `title`, `body`,
`path`, `channel` = `episodes`, `general` ou `social`, `tag`, `image`) sur le canal de chaque
appli **qui l'écoute à ce moment** : le serveur demande d'abord à Reverb les canaux occupés
(`GET /apps/{id}/channels`). Une appli fermée compte comme un **échec** : les nouveaux épisodes
sont donc annoncés à une tâche suivante (toutes les 15 min, tant qu'ils ont moins de 48 h), dès
que l'appli est ouverte ; l'activité et les messages restent dans la page « Notifications » et la
messagerie. Toutes les notifications (épisodes, activité, messages, test, `vixeo:push`) passent
par `PushDevices::send`, qui choisit FCM ou ce canal pour chaque appli. Sans Reverb
(`BROADCAST_CONNECTION` ≠ `reverb` ou sans `REVERB_APP_KEY`), rien n'est envoyé à Windows.

## Les nouveaux épisodes (`App\Services\Push\EpisodePush`)

Pour chaque profil qui a au moins un appareil avec `new_episodes` :

1. les séries de « À voir » (`profile_items` TMDb, type `tv`, `is_favorite`) ;
2. leurs épisodes **sortis dans les 48 dernières heures** (`UpcomingEpisodes::availableBetween` :
   la fiche de la série, cache d'un jour, et la saison du dernier et du prochain épisode) ;
3. sans ceux sortis **avant l'ajout de la série** à la liste, **avant l'activation des
   alertes** (`new_episodes_since`), ni ceux **déjà annoncés** (`episode_notifications`, une ligne
   par profil et épisode, gardée 90 jours) ; l'âge maximum du profil s'applique ;
4. **la nuit sur l'appareil** (23 h – 8 h, d'après le `utc_offset` de l'appareil utilisé en
   dernier), rien n'est envoyé : la notification attend le matin ;
5. **une notification par série** : titre = la série, texte « S2 · É3 « Le Pont » est
   disponible. » ou « 8 nouveaux épisodes de la saison 2 sont disponibles. », image de fond de la
   série, un appui ouvre sa fiche (`data.path` = `/titre/tv/{id}`) ; canal Android `episodes`,
   `tag` `tv-{id}` (une nouvelle notification de la même série remplace l'ancienne).

Un épisode n'est marqué annoncé que si l'envoi a réussi sur au moins un appareil : FCM en panne,
la tâche suivante réessaie (tant que l'épisode a moins de 48 h).

### L'heure de sortie (`available_at`)

TMDb ne donne qu'une **date** (`air_date`, dans le pays de la série). L'heure est une estimation
faite pour ne **jamais prévenir trop tôt** :

- plateformes de streaming (Netflix, Prime Video, Disney+, Apple TV+, Hulu, HBO Max,
  Paramount+, Peacock : réseaux TMDb dans `UpcomingEpisodes::STREAMING`) : **08:00 UTC**
  (minuit en Californie, 9 h à Paris en hiver) ;
- autres chaînes : **21:00 dans le fuseau du pays de la série** (`origin_country`, première
  valeur ; `UpcomingEpisodes::ZONES` pour les pays à plusieurs fuseaux, UTC si inconnu). Une
  série américaine diffusée le 17 au soir à New York sort le 18 à 2 h UTC (annoncée à 8 h en
  France).

## Commandes

```bash
# La tâche planifiée, à la main : tout le monde, ou un compte (ID ou adresse e-mail)
php artisan vixeo:push-episodes
php artisan vixeo:push-episodes --user=davy@exemple.fr
php artisan vixeo:push-episodes --dry-run          # affiche sans envoyer ni marquer

# Un message écrit à la main (canal Android « general »)
php artisan vixeo:push "Nouvelle version disponible" --all
php artisan vixeo:push "Bonjour !" --user=davy@exemple.fr --user=12 --title="Vixeo" --path=/titre/tv/1399
```

`vixeo:push` envoie à **tous les appareils inscrits** (`--all`) ou à ceux des comptes donnés
(`--user`, plusieurs fois possible), après confirmation (`--force` pour ne pas la demander) ;
`--path` = la page de l'appli ouverte par un appui (commence par `/`).

## Envoi (`App\Services\Push\Fcm`)

API HTTP v1 de FCM (`projects/{projet}/messages:send`), un appel par appareil, 20 à la fois ;
l'appli est désignée par `message.fid` (identifiant d'installation) ou, pour les applis d'avant,
`message.token` (déprécié par Google, qui accepte encore les deux).
Connexion par le compte de service : un JWT signé avec sa clé (RS256) contre un jeton d'accès
Google, gardé en cache jusqu'à 5 minutes avant son expiration. Réponses : envoyé, **invalide**
(`UNREGISTERED`, 404, `SENDER_ID_MISMATCH` : l'appareil est supprimé) ou échec (réseau, quota,
5xx : réessayé à la tâche suivante). Ni les identifiants ni les jetons ne sont journalisés.
