# Streamflix Reborn compatibility notes

This document records the compatibility work carried out against the public
Streamflix Reborn v1.7.231 release notes.

## Existing overlap in Vixeo

Vixeo already had implementations for these extractor families:

- Voe
- VidHide
- StreamWish
- Rpmvid

The StreamWish alias `hanerix.com` and VidHide alias `morencius.com` were
already present in Vixeo's registry.

## Changes in this update

- Added the Voe aliases `rebeccapracticeloss.com` and
  `johnbeyondnation.com` listed in Streamflix Reborn v1.7.231.
- Added a Node.js built-in test suite for extractor registration, supported
  aliases, Rpmvid host-family matching, malformed URLs and lookalike domains.
- Added `npm test` using Node's built-in test runner; no runtime dependency
  was added.
- Reworked FrenchStream and Wiflix adapters to follow the request methods,
  selectors, language mapping and player extraction flows used by their Kotlin
  counterparts. Added focused parser tests for both providers.
- Added the initial 1Jour1Film adapter and aligned Frembed with its Kotlin API
  endpoints; both remain unverified against live provider responses.

## Extractors ported from the current mirror

The source mirror is on Streamflix Reborn v1.7.251. The following extractors
have now been ported as standalone CommonJS modules and registered in Vixeo:

- **Goodstream** (`goodstream.one`): scans inline JavaScript for the JWPlayer
  source and preserves the required User-Agent, Accept-Language and Referer.
- **VidMoLy** (`vidmoly.me`, `vidmoly.net`, `vidmoly.org`, `vidmoly.to`):
  resolves the page to the current `vidmoly.to` host and extracts the HLS
  source with the player Referer.

The registry tests now assert these host mappings. The implementations have
not yet been exercised against live third-party pages; CI/runtime tests remain
necessary before considering the ports production-verified.

## Initial provider triage from source inspection

This is a compatibility assessment, not a claim that these providers have
already been ported:

| Provider family | Initial assessment | Reason |
|---|---|---|
| TioAnime, Fanpelis | Adaptable | HTTP/HTML or JSON API flows; replace Android models and Retrofit with Node fetch/parser logic |
| AnimeSaturn, JKAnime, SeriesTurcas | Adaptable with extra work | More involved parsing, form/API calls, cookies and custom URL handling |
| CB01 | Complex adaptation | Multiple APIs, signed/HMAC fallback and Uprot resolution; needs crypto/API parity and independent tests |
| HDFilme, Ridomovies | Complex adaptation | Multi-stage resolution and custom extraction dependencies; some flows depend on services outside the provider itself |
| AnimeOnlineNinja, Zaluknij | Not a direct port | Android WebView/Cronet/cookie synchronization fallback cannot be copied as-is; a compliant HTTP-only path must be independently demonstrated |
| TvporinternetHD, CableVisionHD, TvLibrefutbol, PelotaLibreTvHd | Out of Vixeo's current VOD provider scope | These implement live-TV/IPTV channel and playlist handling, including local ServerSocket proxy behavior, not movie/series source search |

The assessment is based on the inspected Kotlin implementations in the mirror.
It should be expanded against the complete provider tree before making a final
portable/non-portable inventory.

## Providers not ported in this pass

Streamflix Reborn provider implementations are Kotlin/Android code and are
not drop-in Node.js modules. Their network lifecycle, cookies, WebView and
Android-specific dependencies need individual ports and compatibility tests.
The release notes identify provider updates (including CB01, Cinecalidad,
AnimeSaturn and live-TV sources), but release notes alone do not contain enough
implementation detail to reproduce those providers reliably.

FrenchStream and Wiflix have now been revised against their Kotlin source
implementations. Frembed and 1Jour1Film are initial ports, not production-
verified integrations. These adapters still require runtime tests and live
response validation; no production compatibility is claimed.

## Scope

This update adds no DRM handling, CAPTCHA solving, access-control bypasses or
credential harvesting. New integrations must be used only with content and
endpoints Vixeo is authorized to access.


## Additional French provider and extractor ports

- Added **Kidraz** as a French-language movie provider. Its dynamic portal URL is resolved before querying the provider JSON search endpoint. Candidate titles and years are checked against the requested TMDB metadata before detail pages are opened. The source provider currently exposes movies only, so the Node adapter does not report TV results.
- Ported the dedicated **ApiVoirFilm** and **OnRegardeOu** expanders into reusable Node.js helpers and wired them into 1Jour1Film.
- Added unit tests for strict title matching, Kidraz API path derivation, and both special server expanders.
