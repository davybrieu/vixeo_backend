# Contrôle parental

Trois niveaux de protection, du plus simple au plus fort.

## 1. Code PIN de profil

Chaque profil peut avoir un code à 4 chiffres (`pin`), demandé par l'appli pour y entrer
(vérification : `POST /api/v1/profiles/{id}/verify-pin`). **Protéger les profils adultes**
par un PIN, sinon un enfant peut simplement choisir le profil d'un parent.

Changer ou retirer le PIN d'un profil exige ce PIN (`current_pin`) ou le PIN parental.

## 2. Profil enfant et âge maximum

| Champ du profil | Rôle |
| - | - |
| `is_kid` | Profil enfant (interface simplifiée côté appli). Sans `max_age_rating`, limite à **10 ans**. |
| `max_age_rating` | Âge maximum autorisé (ex. 12, 16). `null` = pas de limite (sauf profil enfant). |
| `effective_max_age_rating` | (lecture seule) Limite réellement appliquée. |
| `allow_unrated` | Avec une limite : afficher aussi les titres sans classification connue. Par défaut **non**. |
| `locked_sources` | Sources ou dossiers masqués sur ce profil (voir §4). |

L'âge d'un titre vient de sa classification TMDb : française si elle existe, sinon américaine
(« U » → 0, « 12 » → 12, « PG-13 » → 13, « TV-MA » → 17…).

### Ce que l'API filtre

L'appli envoie **l'identifiant du profil actif dans l'en-tête `X-Profile-Id`** sur toutes les
requêtes du catalogue. Pour un profil limité :

- accueil (carrousel et rangées ; une rangée vide disparaît), listes, découverte par genre,
  recherche (films et séries ; les personnes restent), recommandations, filmographies :
  les titres au-dessus de la limite, ou sans classification (si `allow_unrated` est faux),
  sont retirés ;
- fiches film / série et saisons : réponse **403** « Ce contenu n'est pas disponible sur ce
  profil. ».

Sans en-tête `X-Profile-Id`, rien n'est filtré. Un `X-Profile-Id` d'un autre compte → 403.

Les listes filtrées peuvent contenir moins de 20 éléments par page (la pagination reste celle
de TMDb). La première fois qu'une liste est affichée pour un profil limité, l'API récupère les
classifications manquantes (en parallèle, gardées 7 jours en cache pour tous les comptes) :
un peu plus lent la première fois, instantané ensuite.

## 3. Code PIN parental (compte)

Un code à 4 chiffres pour tout le compte, distinct des PIN de profil. Tant qu'il n'est pas
défini, le contrôle parental est **désactivé** (tout le monde peut tout modifier).

| Route | Rôle |
| - | - |
| `GET /api/v1/parental-control` | `{ "enabled": true/false }` (aussi `parental_control` dans `/me`) |
| `PUT /api/v1/parental-control/pin` | Définir (`pin`) ou changer (`pin` + `current_pin`, ou `password`) |
| `POST /api/v1/parental-control/disable` | Désactiver (`current_pin` ou `password`) |
| `POST /api/v1/parental-control/verify` | Vérifier (`pin`) : 204 ou 422 |

Code oublié : le **mot de passe du compte** (`password`) remplace `current_pin`.

Une fois activé, le PIN parental (`parental_pin` dans le corps) est exigé pour :

- **créer un profil** (sinon un enfant pourrait se créer un profil adulte) ;
- **modifier les restrictions** d'un profil : `is_kid`, `max_age_rating`, `allow_unrated`,
  `locked_sources` (renvoyer les mêmes valeurs n'est pas une modification) ;
- **supprimer un profil** : en-tête `X-Parental-Pin` (une requête DELETE n'a pas de corps) ;
- **retirer ou changer le PIN d'un profil** sans connaître ce PIN.

Le nom, l'avatar et l'ordre restent modifiables sans PIN.

**Essais limités** : après 5 mauvais PIN parentaux (toutes routes confondues), même le bon PIN
est refusé pendant 5 minutes (réponse 429 « Trop de tentatives. Réessayez dans N secondes. »).

Usages côté appli de `verify` : quitter un profil enfant, ouvrir un dossier verrouillé,
accéder aux réglages.

## 4. Sources et dossiers verrouillés

`locked_sources` est une liste (100 max) de chaînes choisies par l'appli, avec la même
convention que la synchro (`source` de `docs/synchronisation.md`) :

- `iptv:<listId>` : toute une liste IPTV ;
- `iptv:<listId>#<groupe>` : un dossier (groupe M3U) de cette liste ;
- `jellyfin:<serverId>`, `jellyfin:<serverId>#<bibliothèque>`, `radio`…

Ces sources vivent dans l'appli : **c'est l'appli qui les masque** (écrans, recherche globale,
« Continuer à regarder », favoris) et qui demande le PIN parental (`verify`) pour les ouvrir.
L'API les stocke et les protège par le PIN parental.

## Limites connues

- Le jeton d'accès vaut pour tout le compte : le filtrage repose sur l'appli, qui envoie le
  bon `X-Profile-Id` et protège les profils adultes par un PIN. Il protège un enfant qui
  utilise l'appli, pas quelqu'un qui écrirait ses propres requêtes HTTP.
- Pas encore de déverrouillage temporaire d'un titre (« regarder quand même » avec le PIN
  parental) : à ajouter si besoin.
