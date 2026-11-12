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

## Providers not ported in this pass

Streamflix Reborn provider implementations are Kotlin/Android code and are
not drop-in Node.js modules. Their network lifecycle, cookies, WebView and
Android-specific dependencies need individual ports and compatibility tests.
The release notes identify provider updates (including CB01, Cinecalidad,
AnimeSaturn and live-TV sources), but release notes alone do not contain enough
implementation detail to reproduce those providers reliably.

Vixeo's existing provider adapters remain unchanged: FrenchStream, Wiflix and
Frembed. No non-functional provider placeholders have been added.

## Scope

This update adds no DRM handling, CAPTCHA solving, access-control bypasses or
credential harvesting. New integrations must be used only with content and
endpoints Vixeo is authorized to access.
