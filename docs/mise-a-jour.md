# Mises à jour des applis Android et iOS

Une mise à jour passe par deux niveaux :

1. **L'installation de la nouvelle version**, toujours faite par le système. L'appli ne télécharge
   jamais de code qu'elle exécuterait elle-même : Apple l'interdit (règle 2.5.2), et une
   mise à jour Android passe toujours par l'installateur du système.
2. **La vérification dans l'appli** (cette API) : l'appli demande si elle est à jour, signale la
   nouvelle version, ou bloque si la version installée n'est plus acceptée.

## 1. Installation par le système

| Plateforme | Comment la nouvelle version arrive | Lien envoyé par l'API |
|---|---|---|
| Android, Android TV | **Pas de Play Store** (décision du développeur) : l'appli télécharge elle-même le nouvel APK (`latest.download_url`), vérifie son empreinte SHA-256, puis lance l'installateur d'Android, qui demande une confirmation. L'APK doit être signé avec la même clé de release. | `ANDROID_UPDATE_URL` (`<APP_URL>/#installer` par défaut, la section d'installation de l'accueil), en secours |
| iOS | **Pas d'appli iOS pour l'instant** : l'API répond toujours `up_to_date` pour iOS. Plus tard (distribution web UE) : chaque version notarisée par Apple, paquet hébergé sur le site, que l'iPhone interroge au plus une fois par 24 h (adresse `updates` du fichier `/.well-known/marketplace-kit`). | `IOS_UPDATE_URL` (`<APP_URL>/#installer` par défaut) |

Côté iOS (plus tard), le serveur de distribution **reste à construire**. Il n'est possible qu'une fois la
distribution web acceptée par Apple (voir `APPLE_NOTARIZATION.md` dans `vixeo_frontend`, point
B1). Il comprend :
- le fichier `/.well-known/marketplace-kit` sur le domaine enregistré dans App Store Connect ;
- les adresses `restore` (première installation) et `updates` (mises à jour) ;
- un jeton d'installation signé (`installVerificationToken`) ;
- un **serveur de licences en Swift**, basé sur le *App License Delivery SDK* d'Apple, qu'Apple ne
  fournit qu'aux comptes acceptés.

Documentation Apple (MarketplaceKit) : « Distributing your app from your website », « Installing
your app from your website », « Installing apps from an alternative marketplace » (section
« Serve an app update request »).

## 2. Vérification dans l'appli : `GET /api/v1/app/update`

Sans connexion. Limite : 30 appels par minute et par adresse IP.

`GET /api/v1/app/update?platform=android&build=12` renvoie :

```json
{
  "status": "update_available",
  "current_build": 12,
  "latest": {
    "version": "1.1.0", "build": 14, "notes": "…", "published_at": "…",
    "download_url": "https://github.com/davybrieu/vixeo_frontend/releases/download/android-14/vixeo.apk",
    "sha256": "3f5a…"
  },
  "minimum_build": 10,
  "update_url": "https://www.vixeo.app/#installer"
}
```

- `up_to_date` : rien à faire. C'est aussi la réponse quand la version installée est plus récente
  que la dernière publiée (version de test), quand GitHub ne répond pas, et toujours sur iOS.
- `update_available` : l'appli affiche en haut de ses pages la barre « Une nouvelle mise à jour est
  disponible », avec un bouton « Télécharger » (APK téléchargé et vérifié dans l'appli, puis
  installateur d'Android ; sans APK, page d'installation du site).
- `update_required` : la version installée est plus ancienne qu'une release marquée
  **« [obligatoire] »** (`minimum_build`). L'appli affiche « Mise à jour nécessaire » et reste
  bloquée tant que la mise à jour n'est pas installée.

Seuls les **numéros de build** sont comparés, jamais les noms de version.

## 3. La seule source : les releases GitHub de la CI

La CI de `vixeo_frontend` publie chaque APK signé comme *release* GitHub du dépôt
`APP_UPDATES_GITHUB_REPOSITORY` (par défaut `davybrieu/vixeo_frontend`), avec le tag
`android-<build>`, l'APK et son fichier `.apk.sha256`. L'API lit ces releases
(`App\Services\GitHubReleases`, sans jeton, donc dépôt public ; cache 5 minutes) :

- seules les releases publiées (ni brouillon, ni pré-version) avec un tag `android-<n>` comptent ;
- la plus récente est proposée : version (lue dans le titre), notes (la description de la
  release), APK et empreinte ;
- une release dont le **titre contient `[obligatoire]`** (majuscules ou non) oblige toutes les
  installations plus anciennes que son build à se mettre à jour ;
- si GitHub ne répond pas (panne, limite de 60 requêtes par heure sans jeton), aucune mise à jour
  n'est proposée : l'appli n'est jamais bloquée par une panne ;
- `APP_UPDATES_GITHUB_REPOSITORY=` (vide) désactive les mises à jour ; les tests ne contactent
  jamais GitHub (`phpunit.xml`).

Il n'y a plus de table ni de commande artisan pour déclarer une version (la commande
`vixeo:release` et la table `app_releases` ont été supprimées) : tout se fait sur GitHub.

Attention : avec une release à chaque commit sur `main`, chaque commit est proposé comme mise à
jour à tous les appareils Android.

## Publier une version

1. Si besoin, changer la version affichée dans `vixeo_frontend` (`version:` de `pubspec.yaml`).
   Le numéro de build est donné par la CI : c'est le numéro de son run, qui augmente tout seul.
2. Pousser sur `main` : la CI compile l'APK signé avec la clé de release et publie la release
   `android-<build>` (titre : « Vixeo Android v1.0.0 - build 160 »). Les applis la voient dans les 5 minutes, le site www.vixeo.app aussi (son
   bouton « Télécharger l'APK » pointe vers le même fichier).
3. **Mise à jour obligatoire** (cas sérieux seulement : faille de sécurité, ancienne version
   incompatible avec l'API) : sur GitHub, *Releases* → la release → *Edit*, ajouter
   `[obligatoire]` au titre (« Vixeo Android v1.0.0 - build 160 [obligatoire] »), enregistrer. Le retirer rend la mise à jour facultative à nouveau.
4. **Retirer une version** : la supprimer sur GitHub, ou la passer en pré-version ; la précédente
   redevient la dernière.

La v1 de l'API reste compatible avec les anciennes versions : ajouter des champs est permis,
renommer ou supprimer impose une v2.
