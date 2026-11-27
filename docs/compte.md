# Compte : connexion par e-mail, appareils, suppression et export

Vixeo n'utilise plus de mot de passe. La connexion par e-mail repose sur un lien à usage unique.

## Connexion par lien e-mail

1. `POST /api/v1/auth/email/link` avec `{email, redirect_uri, code_challenge}`.
   La réponse ne révèle pas si l'adresse possède un compte.
2. Vixeo envoie un lien valable 15 minutes. Il ne peut être utilisé qu'une fois.
3. Le lien revient vers l'application avec un code temporaire lié au challenge PKCE.
4. `POST /api/v1/auth/email/token` avec `{code, code_verifier, device_name}` crée le jeton Sanctum de l'appareil.

Le lien et le code d'échange sont à usage unique. Le code est lié au secret PKCE gardé par l'application.
Au plus 3 liens sont envoyés par adresse toutes les 15 minutes. Les adresses inconnues reçoivent la
même réponse que les adresses connues.

## Inscription

`POST /api/v1/auth/register` avec `{name, username, email, redirect_uri, code_challenge}` :
crée le compte et son premier profil sans mot de passe, puis envoie le lien de connexion.
L'adresse devient vérifiée après consommation du lien.

## Changer d'adresse e-mail

`POST /api/v1/me/email` avec `{email, redirect_uri, code_challenge}` envoie un lien de confirmation
à la nouvelle adresse. L'adresse actuelle reste active tant que le lien n'a pas été ouvert ; sa
consommation valide la nouvelle adresse et renvoie un code PKCE que l'application échange comme
pour une connexion normale.

## Appareils connectés

- Chaque connexion crée un jeton Sanctum portant le nom de l'appareil (`device_name`).
- Un jeton expire après 180 jours (`SANCTUM_EXPIRATION`, en minutes ; 0 = jamais).
- `GET /api/v1/me/devices` : liste les appareils connectés.
- `DELETE /api/v1/me/devices/{id}` : déconnecte un appareil.
- `POST /api/v1/auth/logout` : déconnecte l'appareil courant.

## Supprimer le compte

`POST /api/v1/me/delete` sans champ secret supprime le compte, son profil, ses données et tous ses jetons.
La session authentifiée confirme l'action. C'est définitif.

## Exporter ses données

`POST /api/v1/me/export` envoie un fichier JSON à l'adresse du compte : profil, bibliothèque, progression,
appareils et données sociales. Aucun secret d'authentification n'est exporté.

## E-mails

Les liens de connexion et les exports partent avec le mailer configuré (`MAIL_*`). En local,
`MAIL_MAILER=log` les écrit dans `storage/logs/laravel.log`. En production, configurez un service d'envoi
fiable dans Forge.
