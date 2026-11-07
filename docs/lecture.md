# Lecture : réglages du profil, recherches récentes, génériques et sous-titres

Ce que l'API prépare pour le lecteur de l'appli (pas encore construit) et ce qu'elle synchronise
entre les appareils d'un profil.

## Réglages de lecture du profil

Champs du profil (`GET/PATCH /api/v1/profiles/{id}`), les mêmes sur tous les appareils :

| Champ | Valeurs | Par défaut |
|---|---|---|
| `audio_language` | `fr`, `en`, `es`… (`App\Support\Languages::AUDIO`) | `null` = version originale |
| `subtitle_language` | `fr`, `en`, `pt-BR`… (`Languages::SUBTITLES`) | `null` = pas de sous-titres |
| `no_spoiler` | masque les images, titres et descriptions des épisodes jusqu'à leur sélection | `false` |
| `skip_intro` | générique de début passé tout seul | `false` |
| `skip_recap` | résumé « Précédemment… » passé tout seul | `false` |
| `skip_credits` | générique de fin passé tout seul (épisode suivant) | `false` |
| `skip_preview` | aperçu de l'épisode suivant passé tout seul | `false` |

Quand un `skip_*` vaut `false`, le lecteur affiche un bouton « Passer » pendant ce moment.
Ces réglages ne sont pas des restrictions : pas de PIN parental pour les changer. Ils font partie
de l'export des données.

L'appli garde la liste des noms français des langues : en ajouter une = la mettre dans
`Languages` (API) et dans l'appli.

## Recherches récentes (synchronisées)

| Méthode | Route | Rôle |
|---|---|---|
| GET | `/profiles/{id}/searches` | les 20 dernières, la plus récente d'abord |
| POST | `/profiles/{id}/searches` `{query}` | ajoute (ou remonte) une recherche |
| DELETE | `/profiles/{id}/searches/{search}` | retire une recherche |
| POST | `/profiles/{id}/searches/clear` | efface tout |

- Une recherche déjà présente (sans tenir compte des majuscules) remonte en tête au lieu d'être
  ajoutée deux fois ; 20 au plus par profil (`ProfileSearch::MAX_PER_PROFILE`).
- Pendant la frappe, l'appli enregistre « orbi » puis « orbite » : une recherche qui en prolonge
  une enregistrée dans les 2 dernières minutes la remplace.
- Table `profile_searches`, supprimée avec le profil (donc avec le compte), dans l'export.

## Moments à passer : TheIntroDB

`GET /catalog/movie/{id}/segments` et `GET /catalog/tv/{id}/season/{s}/episode/{e}/segments`
(identifiants TMDb) renvoient `intro`, `recap`, `credits` et `preview` : des listes de
`{start_ms, end_ms}` en millisecondes (`start_ms` nul = depuis le début, `end_ms` nul = jusqu'à
la fin). Listes vides quand personne n'a encore minuté le titre.

- Source : [TheIntroDB](https://theintrodb.org), base communautaire, API v4 (`GET /media` avec
  `tmdb_id`, et `season` / `episode` pour une série), `App\Services\Playback\SkipSegments`.
- Cache : 24 h, 6 h pour un titre sans minutage. Panne de TheIntroDB → 503 (le lecteur joue
  sans bouton « Passer »).
- Sans clé : 5 000 requêtes par jour depuis le serveur. `THEINTRODB_API_KEY` (facultatif) monte à
  100 000. Je n'ai pas trouvé de conditions d'utilisation publiées pour les données : à vérifier
  avant la mise en ligne.

## Sous-titres : OpenSubtitles

Clé **Vixeo commune** (décision du développeur) : les applis ne parlent jamais à OpenSubtitles,
l'API le fait pour elles.

- Recherche : `GET /catalog/movie/{id}/subtitles` et
  `GET /catalog/tv/{id}/season/{s}/episode/{e}/subtitles`, paramètre `languages=fr,en` (par défaut
  la langue de sous-titres du profil, sinon le français). Résultats : `file_id`, `language`,
  `release`, `hearing_impaired`, `ai_translated`, `downloads`, langue préférée d'abord puis les plus
  téléchargés (30 au plus). Cache 6 h.
- Fichier : `GET /catalog/subtitles/{file_id}` renvoie le texte en **WebVTT** (`text/vtt`, UTF-8),
  converti depuis le SRT (`SubtitleFormat`). Chaque fichier n'est téléchargé qu'une fois chez
  OpenSubtitles, puis servi depuis notre cache 30 jours (le quota quotidien de téléchargements
  d'OpenSubtitles est compté par compte : le cache est indispensable). Au plus 40 nouveaux fichiers
  par heure et par compte Vixeo (`throttle:subtitles`).
- Erreurs : quota épuisé (406 chez OpenSubtitles) → 503 « La limite quotidienne de sous-titres est
  atteinte » ; pas de clé → 503 « Les sous-titres ne sont pas disponibles pour le moment ».

Configuration (Forge, jamais dans le dépôt) :

```
OPENSUBTITLES_API_KEY=...        # clé « consumer » créée sur opensubtitles.com
OPENSUBTITLES_USERNAME=...       # compte Vixeo sur OpenSubtitles (facultatif, quota plus haut)
OPENSUBTITLES_PASSWORD=...
OPENSUBTITLES_USER_AGENT="Vixeo v1.0"
THEINTRODB_API_KEY=              # facultatif
```

À vérifier avant la mise en ligne : les conditions d'OpenSubtitles pour une appli publique avec
une clé partagée (quota du compte, offre VIP ou commerciale). Je n'ai pas pu lire leur
documentation depuis le conteneur : le format des requêtes suit l'API REST v1 telle que je la
connais (`/subtitles`, `/login`, `/download`), à confirmer avec une vraie clé.

## Confidentialité

Notre serveur interroge TheIntroDB et OpenSubtitles avec l'identifiant TMDb du titre, la saison,
l'épisode et la langue, jamais l'identité de la personne. Les recherches récentes sont gardées
sur notre serveur (politique de confidentialité de l'appli et `PrivacyInfo.xcprivacy` à jour).
