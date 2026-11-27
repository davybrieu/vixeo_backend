# Affiches, arrière-plans et logos d'un titre

Demande du développeur (25/11/2026), d'après une autre appli de films : un profil choisit
l'**affiche**, l'**arrière-plan** et le **logo** d'un film ou d'une série parmi ceux de TMDb ;
l'API les donne ensuite **à ce profil** à la place de ceux de TMDb, partout où elle donne le titre.

## Routes

- `GET /api/v1/catalog/{movie|tv}/{id}/images` (en-tête `X-Profile-Id`) :
  - `default` : le choix de TMDb (chemins et vignettes), montré par « Par défaut » ;
  - `chosen` : le choix du profil (`poster`, `backdrop`, `logo` : un chemin, ou null pour celui de
    TMDb ; `logo_aspect_ratio`) ; null sans profil ;
  - `posters`, `backdrops`, `logos` : `{path, url, language, aspect_ratio}`, en français, en
    anglais et sans texte (`include_image_language=fr,en,null`, les images déjà jointes à la fiche :
    aucun appel TMDb de plus), les mieux notées d'abord, **60 au plus** par sorte. Ordre des
    langues : affiches et logos français, anglais, sans texte ; arrière-plans sans texte d'abord.
    Vignettes : affiche w342, arrière-plan w780, logo w300. Pas de SVG.
- `PUT /api/v1/profiles/{profile}/artwork/{movie|tv}/{id}` avec `poster`, `backdrop`, `logo` (un
  `path` de la liste, ou null pour celui de TMDb ; une clé absente ne change pas) → le choix.
  Une image qui n'est pas une de celles du titre : 422 « Cette image n’est pas une image de ce
  titre. ». Tout remis à TMDb : la ligne est supprimée.

## Où le choix s'applique

`App\Services\TitleArtworks::chosen` (les choix du profil lus **une fois par requête**) dans
`MediaItemResource` (accueil, listes, recherche, recommandations, `catalog/titles` : « À voir »,
« Continuer à regarder »…), `MovieResource` et `ShowResource` (fiche : l'arrière-plan choisi sert
aussi sous le logo, `textless_backdrop_url`). Pas dans les cartes du réseau social (fil, listes
d'un autre membre : images de TMDb gardées avec l'activité).

## Données

Table `title_artworks` (profil, type, id TMDb, chemins TMDb, proportions du logo), supprimée avec
le profil, dans l'export (`title_images`). Tests : `tests/Feature/Api/V1/TitleArtworkTest.php`.
