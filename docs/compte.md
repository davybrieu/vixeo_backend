# Compte : adresse e-mail, mot de passe, appareils, suppression et export

Ces routes répondent aux exigences du RGPD (accès, portabilité, effacement) et à celles de la
distribution d'Apple (transparence et contrôle des données ; voir `APPLE_NOTARIZATION.md` dans
`vixeo_frontend`).

## Mot de passe oublié (`App\Services\PasswordResets`)

On envoie un **code à 6 chiffres par e-mail**, que l'utilisateur saisit dans l'appli avec son
nouveau mot de passe. Il n'y a pas de lien à cliquer : le fonctionnement est le même sur
téléphone, TV et web, il n'y a aucun lien profond à configurer, et aucun secret ne passe dans une
URL.

1. `POST /api/v1/auth/password/forgot` avec `{email}`. La réponse est toujours la même
   (« Si un compte utilise cette adresse, un code vient d'y être envoyé. »), que l'adresse ait un
   compte ou non. Au plus 3 codes par adresse toutes les 15 minutes.
2. `POST /api/v1/auth/password/reset` avec `{email, code, password, device_name}` :
   - le mot de passe change ;
   - **tous les appareils sont déconnectés** ;
   - la réponse est la même que `login` (`token`, `user`) pour l'appareil qui fait la demande.

Règles du code :
- seul son haché est enregistré (table `password_reset_tokens`) ;
- il est valable 15 minutes et ne sert qu'une fois ;
- un nouveau code remplace le précédent ;
- 5 codes faux l'invalident (« Trop de codes incorrects. Demandez un nouveau code. »).

## Changer d'adresse e-mail ou de mot de passe

Depuis « Paramètres » → « Compte » dans l'appli. Toujours confirmé par le mot de passe, envoyé dans
le corps de la requête (jamais dans une URL). Au plus 5 essais par minute (`throttle:account`).

- `POST /api/v1/me/email` avec `{email, password}` → l'utilisateur (`UserResource`) avec la nouvelle
  adresse. Adresse déjà utilisée ou invalide : 422 sur `email` ; mot de passe faux : 422
  « Mot de passe incorrect. » sur `password`. Un code « mot de passe oublié » en cours pour
  l'ancienne adresse est annulé ; `email_verified` repasse à faux.
- `POST /api/v1/me/password` avec `{current_password, password}` → 204. Le nouveau mot de passe
  suit les mêmes règles qu'à l'inscription (8 caractères au moins). **Les autres appareils sont
  déconnectés** ; celui qui fait la demande reste connecté. Mot de passe actuel faux : 422
  « Mot de passe incorrect. » sur `current_password`.

**Compte sans mot de passe** (créé avec Google ou Apple, `has_password` faux,
`connexion-sociale.md`) : `POST /me/password` sans `current_password` crée son premier mot de
passe ; `POST /me/email` répond 422 « Créez d’abord un mot de passe dans « Mon compte ». ». Le
code de « Mot de passe oublié » marque aussi l'adresse comme vérifiée.

## Appareils connectés

- Chaque connexion crée un jeton Sanctum, qui porte le nom de l'appareil (`device_name`).
- Un jeton **expire après 180 jours** (`SANCTUM_EXPIRATION`, en minutes ; 0 = jamais).
  L'appareil doit alors se reconnecter. Les jetons expirés depuis plus d'un jour sont supprimés
  chaque jour (`sanctum:prune-expired`, `routes/console.php`).
- `GET /api/v1/me/devices` : liste des appareils (`name`, `current`, `signed_in_at`,
  `last_used_at`, `expires_at`), les plus récemment utilisés en premier.
- `DELETE /api/v1/me/devices/{id}` : déconnecte un appareil du compte. Pour l'appareil courant,
  utiliser `POST /api/v1/auth/logout`.

## Supprimer le compte

`POST /api/v1/me/delete` avec `{password}` (sans mot de passe pour un compte qui n'en a pas :
Google, Apple). C'est un POST et non un DELETE, pour ne jamais mettre de secret dans une URL.
- Sont supprimés : le compte, ses profils, leur liste, leur progression, leur historique, les
  connexions TV en attente, les comptes Google / Apple liés, les codes de réinitialisation et
  tous les jetons.
- C'est définitif.
- Au plus 5 essais par minute (mot de passe faux : 422 « Mot de passe incorrect. »).

## Exporter ses données

`POST /api/v1/me/export` envoie à l'adresse du compte un e-mail avec un fichier
`vixeo-donnees.json` (`App\Services\AccountExport`) : compte, profils (réglages de lecture et
recherches récentes compris), bibliothèque, appareils.
- Contenu : le compte, les profils (sans les hachés de PIN), leur liste et leur progression, les
  appareils connectés.
- Au plus 3 exports par heure.

Pourquoi un e-mail plutôt qu'un téléchargement :
- ça fonctionne de la même façon sur iOS, Android, la TV et le web ;
- seul le titulaire de l'adresse reçoit les données.

## E-mails

Les deux e-mails (code et export) partent de façon synchrone avec le mailer configuré
(`MAIL_*`). En local, `MAIL_MAILER=log` les écrit dans `storage/logs/laravel.log`. En
production, il faut un vrai service d'envoi (voir `docs/deploiement.md`).
