# Connexion d'une TV par code

Sur une TV, taper une adresse e-mail et un mot de passe à la télécommande est pénible. La TV
affiche donc un **code court** ; la personne, déjà connectée dans l'appli Vixeo de son téléphone
(ou de son ordinateur Windows), le tape dans « Paramètres » → « Connecter une TV » ; la TV reçoit
alors son propre jeton.

**Aucun QR code** (demande du développeur, 20/11/2026), ni page web de validation (l'appli web a
été supprimée) : le code se tape dans l'appli.

## Déroulé

1. **TV** : `POST /api/v1/auth/device` avec `device_name` (ex. « Vixeo TV ») →

   ```json
   {
     "device_code": "…48 caractères secrets…",
     "user_code": "K7F-29Q",
     "expires_in": 600,
     "interval": 5
   }
   ```

   La TV affiche `user_code` et garde `device_code` pour elle. Aucun lien ni QR code
   (`verification_url` et `verification_url_complete` supprimés, demande du développeur,
   20/11/2026).
2. **TV** : toutes les `interval` secondes, `POST /api/v1/auth/device/token` avec `device_code` :
   - `202 {"status":"pending"}` : pas encore validé, continuer ;
   - `200 {"status":"approved","token":…,"user":…}` : connectée (jeton remis une seule fois) ;
   - `410 {"status":"expired"}` : code expiré ou déjà utilisé → recommencer à l'étape 1.
3. **Téléphone / ordinateur connecté** (« Paramètres » → « Connecter une TV ») :
   - `POST /api/v1/auth/device/lookup` avec `user_code` → nom de l'appareil, pour confirmer ;
   - `POST /api/v1/auth/device/approve` avec `user_code` → 204, la TV est connectée au compte.

Le code accepte majuscules / minuscules, avec ou sans tiret.

## Sécurité

- `device_code` ne passe jamais dans une URL et n'est stocké qu'**haché** (SHA-256).
- Les codes expirent après **10 minutes** et ne servent qu'**une fois**.
- `user_code` : 6 caractères sans caractères ambigus (pas de 0/O, 1/I/L).
- Essais limités : 10 validations / minute par compte, 20 interrogations / minute par adresse IP.
- Les codes expirés sont supprimés chaque jour (`php artisan model:prune`, planifié).

## Réglage

Aucun.
