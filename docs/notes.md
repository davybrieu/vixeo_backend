# Notes des autres sites (MDBList)

Les applis affichent la note d'un film ou d'une série **sur le site choisi par le profil** :
IMDb (par défaut), TMDb, Rotten Tomatoes (critiques ou public), Metacritic, Letterboxd, Trakt ou
MyAnimeList, **sinon la note TMDb** (`vote_average`, jamais IMDb à la place d'un autre site :
demande du développeur, 20/11/2026). Les notes viennent de [MDBList](https://mdblist.com), avec
**la clé de Vixeo** : les applis ne parlent jamais à MDBList (même règle que TMDb, OpenSubtitles et
TheIntroDB).

## Réglage du profil

`rating_source` (`App\Enums\RatingSource`) : `imdb` (défaut), `tmdb`, `tomatoes`, `audience`,
`metacritic`, `letterboxd`, `trakt`, `mal`. Champ ordinaire du profil (`PATCH /profiles/{id}`,
synchronisé entre les appareils, sans PIN parental), dans l'export des données. `tmdb` : l'appli
affiche `vote_average`, déjà donné avec chaque titre, sans demander MDBList.

## `GET /catalog/ratings?ids=movie:550,tv:1399`

100 titres au plus par requête (même format que `/catalog/titles`), publique comme le reste du
catalogue, limitée à 60 requêtes par minute (`throttle:ratings`). Réponse :

```json
{
  "data": [
    {"type": "movie", "id": 550, "imdb": 8.8, "tmdb": 8.4, "trakt": 88, "letterboxd": 4.3,
     "tomatoes": 79, "audience": 96, "metacritic": 67, "mal": null}
  ],
  "available": true
}
```

- Chaque note est **sur l'échelle de son site** : IMDb, TMDb et MyAnimeList sur 10, Letterboxd
  sur 5, Rotten Tomatoes (`tomatoes` = critiques, `audience` = public) et Trakt en %, Metacritic
  sur 100 ; `null` quand le site n'a pas de note (Letterboxd : films seulement, MyAnimeList :
  animés).
- Un titre que MDBList ne connaît pas revient avec toutes ses notes à `null`.
- `available: false` : MDBList ne peut pas être interrogé maintenant (pas de clé, budget du jour
  dépensé, limite atteinte, panne) ; les titres pas encore gardés **manquent** dans `data`.
  L'appli affiche alors la note TMDb et redemande plus tard. Jamais d'erreur pour une note.
- Pas de note par épisode : MDBList n'en a pas, et les applis n'affichent aucune note sur un
  épisode (ni celle de TMDb ; plus d'avis d'épisode non plus, `docs/avis.md`).
- Aucune donnée de titre n'est renvoyée : pas de filtre d'âge (`AgeGate`), comme les moments à
  passer.

## Quota de MDBList (`App\Services\Ratings\MdbListRatings`)

Le quota est **par compte MDBList**, partagé par toutes ses clés et tous les comptes Vixeo (offre
gratuite : 1 000 requêtes par jour, et 1 000 lectures par 5 minutes ; offres payantes à partir de
10 000 par jour). D'où :

1. **Cache partagé** : les notes d'un titre sont gardées **3 jours** pour tous les comptes
   (`MDBLIST_TTL`), un titre inconnu de MDBList **1 jour**. L'accueil est le même pour tout le
   monde : ses notes ne coûtent qu'une fois.
2. **Par lots** : les titres absents du cache sont demandés ensemble, **200 par requête**
   (`POST https://api.mdblist.com/tmdb/{movie|show}?apikey=…`, corps `{"ids": [550, …]}`), une
   requête par type.
3. **Budget du jour** : `MDBLIST_DAILY_REQUESTS` requêtes au plus par jour (UTC, 900 par défaut :
   une marge sous les 1 000). Au-delà, plus de requête jusqu'au lendemain.
4. **Pause** : après un 429 ou une erreur « limit » de MDBList, ou une clé refusée (401 / 403),
   plus de requête pendant **1 heure** ; après une panne (réseau, 5xx), pendant **1 minute**.
   Délais courts (2 s de connexion, 5 s en tout) : les applis attendent la note avant d'afficher
   les informations d'un titre.
5. **Côté appli** : une requête par rangée ou page de cartes (100 titres), réponses gardées un
   jour sur l'appareil.

La clé passe dans l'URL de MDBList (sa façon de faire) : l'URL n'est jamais journalisée (seuls le
type et le statut). Lecture des réponses : `ratings[].source` / `value` / `score` (score = valeur
sur 100, tous sites) ; `popcorn` (ancien nom `tomatoesaudience`) → `audience`, `myanimelist` →
`mal` ; Letterboxd est sur 10 dans les réponses par lots, d'où `score / 20` ; TMDb est sur 100 chez
MDBList, ramené sur 10 ; une note sans vote est `null`.

## Configuration

```dotenv
MDBLIST_API_KEY=            # clé du compte MDBList de Vixeo (mdblist.com/preferences) ; vide : aucune note
MDBLIST_DAILY_REQUESTS=900  # sous le quota de l'offre (1 000 en gratuit)
```

Tests : `tests/Feature/Api/V1/Catalog/RatingsTest.php` (`Http::fake()`, jamais d'appel réel).
