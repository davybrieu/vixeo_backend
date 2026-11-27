# Connexion avec Google et Apple

« Continuer avec Google » et « Continuer avec Apple » sur la page d'accueil de l'appli (demande du
développeur, 25/11/2026), à côté de « Continuer avec l'e-mail ». Android, Windows, puis iOS ; pas
sur TV (la TV se connecte par code, `connexion-tv.md`).

La connexion se fait **dans un navigateur** (onglet Chrome sur Android, navigateur par défaut sur
Windows), selon la RFC 8252 (« OAuth 2.0 for Native Apps ») : **l'API est le client OAuth** de
Google et d'Apple, les applis n'ont aucun identifiant ni secret de ces services. Apple n'a pas de
connexion native sur Android ni sur Windows : c'est la connexion web d'Apple, avec un Services ID.

## Déroulé

1. **Appli** : tire un secret aléatoire (`code_verifier`, 43 à 128 caractères), le garde en
   mémoire, et ouvre dans le navigateur :

   ```
   GET /api/v1/auth/social/{google|apple}
       ?redirect_uri=app.vixeo://auth            (Android, iOS)
       ?redirect_uri=http://127.0.0.1:{port}/auth (Windows : petit serveur de l'appli)
       &code_challenge=<base64url(SHA-256(code_verifier))>
       &code_challenge_method=S256
   ```

   Une autre adresse de retour : 422. Le fournisseur sans réglages sur le serveur : retour tout de
   suite à l'appli avec `?error=unavailable` (l'appli le sait déjà par `GET /auth/social`, qui
   donne `available` pour chacun, et ne lance alors pas le navigateur).
2. **API** : garde la demande 10 minutes (cache, sous un `state` aléatoire) et envoie le
   navigateur chez Google ou Apple.
3. **Google / Apple** renvoient le navigateur à
   `/api/v1/auth/social/{fournisseur}/callback` (Google : GET ; Apple : POST, `form_post`).
   L'API échange leur code contre l'ID token (directement auprès de leur serveur, en TLS : la
   signature n'a pas à être vérifiée, OpenID Connect Core 3.1.3.7 ; l'émetteur, le destinataire
   et l'expiration le sont), trouve ou crée le compte, puis renvoie le navigateur à l'appli :
   `redirect_uri?code=…` (5 minutes, une seule fois) ou `redirect_uri?error=…` :
   - `cancelled` : la personne a annulé ;
   - `failed` : le fournisseur a refusé le code ou ne répond pas ;
   - `email` : le fournisseur n'a donné aucune adresse ;
   - `unverified` : l'adresse est celle d'un compte Vixeo, mais le fournisseur ne l'a pas vérifiée.

   Une demande expirée ou déjà utilisée (`state` inconnu) : une page « Cette connexion a expiré »
   (410), puisqu'on ne sait plus où renvoyer.
4. **Appli** : `POST /api/v1/auth/social/token` avec `{code, code_verifier, device_name}` → la
   même réponse que `auth/login` (`token`, `user`), plus `created` (nouveau compte, avec son
   premier profil) et `password_removed` (voir plus bas). Code inconnu, expiré, déjà utilisé ou
   secret faux : 422 « Cette connexion a expiré. Recommencez. » sur `code`.

Quelqu'un qui obtient le code (une autre appli du téléphone, un lien de connexion envoyé à une
autre personne) ne peut rien en faire sans le secret, resté dans l'appli qui a commencé.

## Quel compte ?

Un compte Google ou Apple lié = une ligne de `social_accounts` (fournisseur + `subject`, l'identifiant
stable du fournisseur).

1. Déjà lié : ce compte.
2. Sinon, un compte Vixeo a la même adresse (sans tenir compte des majuscules) : il est lié
   uniquement si le fournisseur a vérifié l'adresse. Si l'adresse Vixeo n'avait jamais été
   vérifiée, les anciennes sessions sont révoquées avant de valider l'adresse.
3. Sinon, un nouveau compte est créé sans mot de passe, avec le nom du fournisseur (ou le début
   de l'adresse, ou « Moi » pour une adresse relais Apple) et son premier profil.

Les comptes e-mail utilisent eux aussi exclusivement les liens de connexion à usage unique
(`docs/compte.md`). Aucun compte Vixeo ne possède de mot de passe.
 
## Réglages (`.env`, jamais dans le dépôt)

- **Google** (console Google Cloud, « API et services » → « Identifiants ») : un client OAuth
  « Application Web », URI de redirection autorisée
  `https://api.vixeo.app/api/v1/auth/social/google/callback` → `GOOGLE_CLIENT_ID`,
  `GOOGLE_CLIENT_SECRET`. L'écran de consentement demande `openid`, `email`, `profile` (portées
  non sensibles) ; pour le publier, Google demande l'adresse de la page d'accueil et d'une
  politique de confidentialité.
- **Apple** (compte Apple Developer, « Certificates, Identifiers & Profiles ») :
  - un App ID avec « Sign in with Apple », puis un **Services ID** (ex. `app.vixeo.signin`) relié
    à cet App ID, domaine `api.vixeo.app`, URL de retour
    `https://api.vixeo.app/api/v1/auth/social/apple/callback` → `APPLE_CLIENT_ID` ;
  - une clé « Sign in with Apple » : son identifiant → `APPLE_KEY_ID`, le fichier `.p8` (chemin
    hors du dépôt, `base64:…` ou le PEM) → `APPLE_PRIVATE_KEY` ;
  - l'identifiant de l'équipe → `APPLE_TEAM_ID`.

  Le client secret est un JWT ES256 de 5 minutes signé avec cette clé (`App\Support\Jwt::es256`).

Sans ces réglages, `available` est faux et l'appli dit que la connexion n'est pas encore
disponible.

## Code

`App\Services\SocialLogin\SocialLogins` (déroulé, comptes), `GoogleSignIn`, `AppleSignIn`
(`SocialProvider`), `App\Http\Controllers\Api\V1\SocialLoginController`, modèle `SocialAccount`
(supprimé avec le compte, dans l'export). Tests : `tests/Feature/Api/V1/SocialLoginTest.php`.
