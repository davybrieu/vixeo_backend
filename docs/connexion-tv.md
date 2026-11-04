# Connexion d'une TV par QR code

Sur une TV, taper une adresse e-mail et un mot de passe à la télécommande est pénible. La TV
affiche donc un **QR code** et un **code court** ; la personne, déjà connectée sur son téléphone
ou son ordinateur, valide ce code ; la TV reçoit alors son propre jeton.

## Déroulé

1. **TV** : `POST /api/v1/auth/device` avec `device_name` (ex. « Vixeo TV ») →

   ```json
   {
     "device_code": "…48 caractères secrets…",
     "user_code": "K7F-29Q",
     "verification_url": "https://vixeo.app/tv",
     "verification_url_complete": "https://vixeo.app/tv?code=K7F29Q",
     "expires_in": 600,
     "interval": 5
   }
   ```

   La TV affiche le QR code de `verification_url_complete`, l'adresse `verification_url` et
   `user_code`. Elle garde `device_code` pour elle.
2. **TV** : toutes les `interval` secondes, `POST /api/v1/auth/device/token` avec `device_code` :
   - `202 {"status":"pending"}` : pas encore validé, continuer ;
   - `200 {"status":"approved","token":…,"user":…}` : connectée (jeton remis une seule fois) ;
   - `410 {"status":"expired"}` : code expiré ou déjà utilisé → recommencer à l'étape 1.
3. **Téléphone / ordinateur connecté** (page `/tv` de l'appli, ouverte par le QR code) :
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

`WEB_URL` (variable d'environnement de l'API) : adresse de l'appli web Vixeo, dont la page `/tv`
valide les codes. Exemple : `WEB_URL=https://vixeo.app`.
