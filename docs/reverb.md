# Temps réel (Laravel Reverb)

La messagerie et les notifications de Windows (`docs/notifications.md`) utilisent Reverb sur le
même domaine que l'API : `api.vixeo.app/app/{key}` pour les applis (WebSocket),
`api.vixeo.app/apps/{id}/…` pour Laravel (API HTTP de Reverb : diffusion des événements, canaux
écoutés). Aucun port public n'est nécessaire : Nginx termine TLS sur 443 et relaie les deux vers
Reverb sur `127.0.0.1:8080`.

## Variables Forge

Définir dans l'environnement du site API :

```dotenv
BROADCAST_CONNECTION=reverb
REVERB_APP_ID=...
REVERB_APP_KEY=...
REVERB_APP_SECRET=...
REVERB_SERVER_HOST=127.0.0.1
REVERB_SERVER_PORT=8080
REVERB_HOST=api.vixeo.app
REVERB_PORT=443
REVERB_SCHEME=https
```

Générer un identifiant, une clé publique et un secret aléatoires (le secret ne doit jamais être
intégré dans l'application Flutter). La valeur `REVERB_APP_KEY` doit aussi être définie comme
variable GitHub Actions `REVERB_APP_KEY` dans le dépôt `vixeo_frontend`, afin qu'elle soit
intégrée aux builds Android et Windows.

## Forge : processus Reverb

Créer un daemon dans Forge pour le site API :

- Commande : `php artisan reverb:start --host=127.0.0.1 --port=8080`
- Répertoire : chemin racine du dépôt backend déployé
- Utilisateur : même utilisateur que le site
- Redémarrage automatique : activé

Après avoir modifié les variables d'environnement, redémarrer le daemon et exécuter :

```bash
php artisan optimize:clear
php artisan reverb:restart
```

## Nginx : proxy WebSocket

Dans la configuration Nginx du site `api.vixeo.app`, ajouter ce bloc dans le serveur HTTPS,
avant le bloc PHP/Laravel. Il relaie `/app/` (les applis) **et `/apps/`** (Laravel, qui envoie
les événements à `REVERB_HOST` ; sans lui, ses appels arrivent à Laravel lui-même et ne sont
jamais diffusés) :

```nginx
location ~ ^/apps?/ {
    proxy_http_version 1.1;
    proxy_set_header Host $http_host;
    proxy_set_header Scheme $scheme;
    proxy_set_header SERVER_PORT $server_port;
    proxy_set_header REMOTE_ADDR $remote_addr;
    proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
    proxy_set_header Upgrade $http_upgrade;
    proxy_set_header Connection "Upgrade";
    proxy_read_timeout 60s;
    proxy_send_timeout 60s;
    proxy_pass http://127.0.0.1:8080;
}
```

Valider puis recharger Nginx. Conserver le proxy Cloudflare actif ; les WebSockets doivent être
autorisés pour le domaine API.

## Déploiement

1. Déployer le backend (`composer install --no-interaction --prefer-dist`, `composer.lock` à jour).
2. Définir les variables Forge ci-dessus.
3. Ajouter le proxy Nginx et le daemon Reverb.
4. Définir la variable GitHub Actions `REVERB_APP_KEY` dans `vixeo_frontend`, puis lancer
   un build Android/Windows.
5. Vérifier `wss://api.vixeo.app/app/{REVERB_APP_KEY}` et ouvrir une conversation sur deux
   appareils pour valider la réception instantanée.

Les messages restent stockés et récupérables par l'API. Si le serveur Reverb est indisponible,
la connexion temps réel se reconnecte automatiquement ; il n'y a plus de polling périodique.

## Ce qui passe par Reverb

Une seule connexion par appli (`Realtime` dans `vixeo_frontend`), autorisée canal par canal par
`POST /api/broadcasting/auth` (jeton Bearer, profil du compte résolu automatiquement, `routes/channels.php`) :

- **une conversation ouverte** : `private-messages.{id}` (ses membres actifs). Événements
  (`App\Events\ConversationChanged`, envoyés après la réponse) : `message.sent`,
  `message.deleted`, `message.read`, `message.updated` ; l'appli relit alors la conversation
  (`docs/messagerie.md`) ;
- **les notifications de Windows** : `private-push-devices.{id}` (l'inscription aux
  notifications de l'appli, pour son seul jeton Sanctum), ouvert tant que l'appli tourne.
  Événement `notification` (`App\Events\PushNotified`), affiché par l'appli
  (`docs/notifications.md`).

Sans `REVERB_APP_KEY` côté serveur, rien n'est envoyé ; sans clé dans l'appli (variable GitHub
`REVERB_APP_KEY` absente), elle ne se connecte pas : elle relit la conversation à chaque envoi et
Windows ne reçoit pas de notification.
