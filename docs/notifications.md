# Notifications push

Vixeo envoie des notifications **push** aux applis (Android, iOS plus tard) par **Firebase Cloud
Messaging** (FCM), le service de Google qu'utilisent les téléphones et les Google TV, même pour
une appli installée hors Play Store. L'appli ne programme rien elle-même : le serveur décide
quand prévenir.

- **Nouveaux épisodes** : une tâche planifiée (`vixeo:push-episodes`, toutes les 15 minutes)
  cherche les épisodes sortis des séries de « Ma liste » et prévient les profils qui l'ont
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
   `FCM_CREDENTIALS`, il ne le lance pas.

Vérifier : `php artisan vixeo:push "Test" --user=<votre adresse> --force`.

## L'appli s'inscrit : `/api/v1/push/device`

Connecté (bearer), avec l'en-tête `X-Profile-Id` du profil en cours :

- `PUT /push/device` : `token` (jeton FCM de l'appli), `platform` (`android`, `ios`),
  `new_episodes` (le profil veut les nouveaux épisodes sur cet appareil), `social` (facultatif, vrai
  par défaut : J'aime, abonnés et demandes d'abonnement, `docs/social.md`), `utc_offset` (heure de
  l'appareil moins UTC, en minutes). À la connexion, au lancement, quand FCM change le jeton,
  quand on change de profil et quand on active ou désactive les alertes. 201 la première fois,
  200 ensuite.
- `GET /push/device` : `{data, available}` ; `available` = le serveur envoie des notifications
  (FCM configuré).
- `DELETE /push/device` : désinscrit l'appli.
- `POST /push/device/test` : une notification de test sur cet appareil (409 s'il n'est pas
  inscrit, 503 si le serveur n'envoie pas de notification ou si FCM refuse).

Une inscription par appli connectée (`push_devices`, une ligne par jeton Sanctum) : **se
déconnecter la supprime** (clé étrangère sur `personal_access_tokens`, comme la suppression du
compte ou la déconnexion d'un appareil). Le jeton FCM est **chiffré** en base (son SHA-256 sert
à le retrouver) et n'est jamais renvoyé ; la même appli reconnectée remplace son ancienne
inscription. Une appli que FCM ne connaît plus (désinstallée) est oubliée au premier envoi.

## Les nouveaux épisodes (`App\Services\Push\EpisodePush`)

Pour chaque profil qui a au moins un appareil avec `new_episodes` :

1. les séries de « Ma liste » (`profile_items` TMDb, type `tv`, `is_favorite`) ;
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

API HTTP v1 de FCM (`projects/{projet}/messages:send`), un appel par appareil, 20 à la fois.
Connexion par le compte de service : un JWT signé avec sa clé (RS256) contre un jeton d'accès
Google, gardé en cache jusqu'à 5 minutes avant son expiration. Réponses : envoyé, **invalide**
(`UNREGISTERED`, 404, `SENDER_ID_MISMATCH` : l'appareil est supprimé) ou échec (réseau, quota,
5xx : réessayé à la tâche suivante). Les jetons ne sont jamais journalisés.
