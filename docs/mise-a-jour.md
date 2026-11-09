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
| Android, Android TV | **Pas de Play Store** (décision du développeur) : l'appli télécharge elle-même le nouvel APK (`latest.download_url`), vérifie son empreinte SHA-256, puis lance l'installateur d'Android, qui demande une confirmation. L'APK doit être signé avec la même clé de release. | `ANDROID_UPDATE_URL` (`<SITE_URL>/installer#android` par défaut), en secours |
| iOS (distribution web UE) | Chaque version est notarisée par Apple, qui fournit un paquet de distribution, à héberger sur le site. L'iPhone interroge le site au plus une fois par 24 h (adresse `updates` du fichier `/.well-known/marketplace-kit`), ou quand on le lui demande, et installe la différence. | `IOS_UPDATE_URL` (`<WEB_URL>/telecharger` par défaut) |

Côté iOS, le serveur de distribution **reste à construire**. Il n'est possible qu'une fois la
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
    "download_url": "https://vixeo.app/telechargements/vixeo-1.1.0.apk",
    "sha256": "3f5a…"
  },
  "minimum_build": 10,
  "update_url": "https://vixeo.app/installer#android"
}
```

- `up_to_date` : rien à faire. C'est aussi la réponse quand la version installée est plus récente
  que la dernière publiée (bêta, version en cours de vérification).
- `update_available` : l'appli affiche en haut de ses pages la barre « Une nouvelle mise à jour est
  disponible », avec un bouton « Télécharger » (Android : APK téléchargé et vérifié dans l'appli,
  puis installateur d'Android ; sinon, page d'installation du site).
- `update_required` : la version installée est plus ancienne qu'une version publiée **obligatoire**.
  L'appli affiche « Mise à jour nécessaire » et reste bloquée tant que la mise à jour n'est pas
  installée.

Seuls les **numéros de build** sont comparés, jamais les noms de version. Une version publiée avec
une date future reste invisible jusqu'à cette date.

## Versions Android publiées sur GitHub (CI)

La CI de `vixeo_frontend` publie chaque APK signé comme *release* GitHub du dépôt
`APP_UPDATES_GITHUB_REPOSITORY` (par défaut `davybrieu/vixeo_frontend`), avec le tag
`android-<build>`, l'APK et son fichier `.apk.sha256`. L'API lit ces releases
(`App\Services\GitHubReleases`, sans jeton, donc dépôt public ; cache 5 minutes) :

- seules les releases publiées (ni brouillon, ni pré-version) avec un tag `android-<n>` comptent ;
- la plus récente est comparée à la table `app_releases` : le build le plus élevé gagne ;
- `--required` reste dans la table (`php artisan vixeo:release`) : une release GitHub n'oblige
  jamais à mettre à jour ;
- si GitHub ne répond pas (panne, limite de 60 requêtes par heure sans jeton), l'API utilise la
  table seule ;
- `APP_UPDATES_GITHUB_REPOSITORY=` (vide) désactive cette source ; les tests ne contactent jamais
  GitHub (`phpunit.xml`).

Attention : avec une release à chaque commit sur `main`, chaque commit est proposé comme mise à
jour à tous les appareils Android.

## Publier une version

1. Dans `vixeo_frontend`, augmenter `version:` dans `pubspec.yaml`. Le numéro de build (après le
   `+`) doit toujours augmenter.
2. Compiler l'APK signé avec la clé de release et le publier sur le site `vixeo_web`
   (`VIXEO_ANDROID_APK_URL`, avec son empreinte SHA-256) ; pour iOS (plus tard), passer la
   notarisation et héberger le paquet.
3. **Quand la version est réellement disponible**, la déclarer dans l'API (onglet Commands de
   Forge, ou en SSH) :

```bash
php artisan vixeo:release android 1.1.0 14 \
    --apk=https://vixeo.app/telechargements/vixeo-1.1.0.apk \
    --sha256=$(sha256sum vixeo-1.1.0.apk | cut -d' ' -f1) \
    --notes="Page acteur et connexion TV par QR code."
php artisan vixeo:release ios 1.1.0 14 --required --at="2026-11-10 08:00"
php artisan vixeo:releases           # liste (ou : vixeo:releases ios)
```

- `--apk` (adresse `https://`) et `--sha256` vont ensemble, pour Android seulement. Sans eux,
  l'appli Android envoie seulement vers la page d'installation du site.
- Le site `vixeo_web` lit aussi cette dernière version : son bouton « Télécharger l'APK » pointe
  vers le même fichier.

- Relancer la commande avec la même plateforme et le même build corrige la version (notes,
  obligatoire, date).
- `--required` est réservé aux cas sérieux : faille de sécurité, ou ancienne version incompatible
  avec l'API. La v1 de l'API reste compatible avec les anciennes versions : ajouter des champs est
  permis, renommer ou supprimer impose une v2.
- Déclarer une version avant qu'elle soit dans le store enverrait les utilisateurs vers une fiche
  sans mise à jour.
