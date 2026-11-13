/**
 * Coflix provider — WordPress REST API approach (ported from Onyx CoflixSourceProvider.java)
 *
 * Instead of Playwright HTML scraping + exact title match, we use:
 *   1. Mirror discovery via https://coflix.domains/ (same as Onyx CoflixMirrorDiscovery)
 *   2. French title resolution via TMDb API (fr-FR locale)
 *   3. WP REST API search: GET {mirror}/wp-json/wp/v2/{movies|series}?search={slug}&per_page=20
 *   4. Fuzzy score-based matching (ported from pickBestWpApi): exact=100, all query words in
 *      result=90, all result words in query=80; +30 year match, -50 year mismatch >2yr; threshold ≥80
 *   5. Playwright only for the detail page (extract player iframes / data-src links)
 */

const { chromium } = require('playwright');

const DISCOVERY_URL = 'https://coflix.domains/';
const DEFAULT_MIRROR = 'https://coflix.ac';
const FALLBACK_MIRRORS = ['https://coflix.ac', 'https://coflix.esq', 'https://coflix.cloud'];
const EXCLUDED = new Set(['https://coflix.domains', 'https://coflix.blog', 'https://coflix.plus']);
const USER_AGENT =
  'Mozilla/5.0 (Linux; Android 14; Pixel 8) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Mobile Safari/537.36';

const DISCOVERY_TTL_MS = 6 * 60 * 60 * 1000; // 6h like Onyx
const WP_PER_PAGE = 20;

let cachedMirror = null;
let cachedAtMs = 0;

// ---------------------------------------------------------------------------
// Mirror discovery (fetch-based, mirrors Onyx CoflixMirrorDiscovery)
// ---------------------------------------------------------------------------

function normalizeMirror(raw) {
  if (!raw) return null;
  try {
    const url = new URL(String(raw).replace(/\\\//g, '/'));
    if (!['http:', 'https:'].includes(url.protocol)) return null;
    const href = `https://${url.host}`;
    if (EXCLUDED.has(href) || EXCLUDED.has(`http://${url.host}`)) return null;
    return href;
  } catch {
    return null;
  }
}

async function discoverMirrorViaFetch() {
  const now = Date.now();
  if (cachedMirror && now - cachedAtMs < DISCOVERY_TTL_MS) return cachedMirror;

  try {
    const res = await fetch(DISCOVERY_URL, {
      headers: { 'User-Agent': USER_AGENT, 'Accept-Language': 'fr-FR,fr;q=0.9' },
      redirect: 'follow',
      signal: AbortSignal.timeout(10000),
    });
    const html = await res.text();

    // 1. Try redirect_url JSON field
    const redirectMatch = html.match(/"redirect_url"\s*:\s*"(https?:\\?\/\\?\/[^"]+)"/i);
    if (redirectMatch) {
      const mirror = normalizeMirror(redirectMatch[1]);
      if (mirror) {
        cachedMirror = mirror;
        cachedAtMs = now;
        return mirror;
      }
    }

    // 2. Find most-frequent coflix.* URL in page (like Onyx groupingBy)
    const counts = new Map();
    for (const match of html.matchAll(/https?:\/\/coflix\.[a-zA-Z0-9-]+/g)) {
      const mirror = normalizeMirror(match[0]);
      if (!mirror) continue;
      counts.set(mirror, (counts.get(mirror) || 0) + 1);
    }
    let best = null;
    let bestCount = 0;
    for (const [mirror, count] of counts) {
      if (count > bestCount) {
        best = mirror;
        bestCount = count;
      }
    }
    if (best) {
      cachedMirror = best;
      cachedAtMs = now;
      return best;
    }
  } catch {
    // Fall through to default
  }

  return DEFAULT_MIRROR;
}

// ---------------------------------------------------------------------------
// Slugify (ported from CoflixSourceProvider.slugify — same accent map)
// ---------------------------------------------------------------------------

function slugify(title) {
  if (!title) return '';
  return title
    .toLowerCase()
    .replace(/é/g, 'e')
    .replace(/è/g, 'e')
    .replace(/ê/g, 'e')
    .replace(/ë/g, 'e')
    .replace(/à/g, 'a')
    .replace(/â/g, 'a')
    .replace(/ä/g, 'a')
    .replace(/ô/g, 'o')
    .replace(/ö/g, 'o')
    .replace(/ù/g, 'u')
    .replace(/û/g, 'u')
    .replace(/ü/g, 'u')
    .replace(/ç/g, 'c')
    .replace(/î/g, 'i')
    .replace(/ï/g, 'i')
    .replace(/[^a-z0-9]+/g, '-')
    .replace(/^-+|-+$/g, '');
}

// ---------------------------------------------------------------------------
// Fuzzy scoring (ported from CoflixSourceProvider.pickBestWpApi)
// ---------------------------------------------------------------------------

/**
 * Normalize a title for word-overlap scoring (lowercase, strip non-alphanum, collapse spaces).
 * Mirrors the Java normalization in pickBestWpApi.
 */
function normalizeForScoring(text) {
  if (!text) return '';
  return text
    .toLowerCase()
    .normalize('NFD')
    .replace(/[̀-ͯ]/g, '') // strip combining diacritics
    .replace(/[^a-z0-9 ]/g, ' ')
    .replace(/\s+/g, ' ')
    .trim();
}

/**
 * Returns the set of words longer than 1 character (like Onyx filter `length > 1`).
 */
function wordSet(normalized) {
  return new Set(normalized.split(' ').filter((w) => w.length > 1));
}

/**
 * Compute match score between query and a WP API result item.
 * Ported directly from pickBestWpApi scoring logic.
 *
 * @param {string} queryNorm  - normalized query title
 * @param {string} resultNorm - normalized result title
 * @param {Set}    queryWords - word set of queryNorm (pre-computed)
 * @param {string|null} resultYear - year extracted from result URL slug
 * @param {number|null} queryYear  - year from the search payload
 * @returns {number} score (0 = no match, ≥80 = accepted)
 */
function scoreResult(queryNorm, resultNorm, queryWords, resultYear, queryYear) {
  const resultWords = wordSet(resultNorm);

  // Length ratio for approximate equality (Onyx uses dAbs > 0.3 to downgrade)
  const maxLen = Math.max(resultNorm.length, queryNorm.length);
  const lenDiff = maxLen > 0 ? Math.abs(resultNorm.length - queryNorm.length) / maxLen : 0;

  let score;
  if (resultNorm === queryNorm) {
    score = 100; // exact match
  } else if (queryWords.size > 0 && containsAll(resultWords, queryWords) && lenDiff <= 0.3) {
    score = 90; // all query words found in result
  } else if (resultWords.size > 0 && containsAll(queryWords, resultWords) && lenDiff <= 0.3) {
    score = 80; // all result words found in query
  } else {
    score = 0;
  }

  if (score > 0) {
    if (queryYear != null && resultYear != null && String(resultYear) === String(queryYear)) {
      score += 30; // year match bonus
    } else if (
      queryYear != null &&
      resultYear != null &&
      !isNaN(Number(resultYear)) &&
      Math.abs(Number(resultYear) - queryYear) > 2
    ) {
      score -= 50; // year mismatch penalty
    }
  }

  return score;
}

function containsAll(superSet, subSet) {
  for (const item of subSet) {
    if (!superSet.has(item)) return false;
  }
  return true;
}

/**
 * Extract the year from a Coflix URL slug (last path segment, look for 4-digit number).
 * Onyx: strI0 = last segment after final "/", strP0 = strip trailing "/" first.
 */
function extractYearFromUrl(url) {
  if (!url) return null;
  try {
    const path = new URL(url).pathname.replace(/\/$/, '');
    const segment = path.split('/').pop() || '';
    const match = segment.match(/(\d{4})/);
    return match ? match[1] : null;
  } catch {
    return null;
  }
}

// ---------------------------------------------------------------------------
// WP REST API search
// ---------------------------------------------------------------------------

/**
 * Call Coflix WP REST API and return the best matching item.
 *
 * @param {string} mirror   - base URL e.g. https://coflix.ac
 * @param {string} rawTitle - title to search (will be slugified)
 * @param {number|null} year
 * @param {'movies'|'series'} wpType
 * @returns {{ title: string, url: string, year: string|null } | null}
 */
async function wpApiSearchBest(mirror, rawTitle, year, wpType) {
  const slug = slugify(rawTitle);
  if (!slug) return null;

  const apiUrl = `${mirror}/wp-json/wp/v2/${wpType}?search=${encodeURIComponent(slug)}&per_page=${WP_PER_PAGE}`;

  let json;
  try {
    const res = await fetch(apiUrl, {
      headers: {
        'User-Agent': USER_AGENT,
        'Accept-Language': 'fr-FR,fr;q=0.9',
        Accept: 'application/json',
      },
      signal: AbortSignal.timeout(12000),
    });
    if (!res.ok) return null;
    json = await res.json();
  } catch {
    return null;
  }

  if (!Array.isArray(json) || json.length === 0) return null;

  const queryNorm = normalizeForScoring(rawTitle);
  const queryWords = wordSet(queryNorm);

  // Expected subtype values: "movies" / "series" / "animes"
  const expectedSubtype = wpType; // "movies" or "series"

  let best = null;
  let bestScore = -1;

  for (const item of json) {
    // Filter by subtype (Onyx: skip if subtype !== expected, except series also allows "animes")
    const subtype = item.subtype || '';
    if (subtype !== expectedSubtype) {
      if (!(expectedSubtype === 'series' && subtype === 'animes')) continue;
    }

    const itemTitle = item.title || '';
    const itemUrl = item.url || (item.link) || '';
    if (!itemTitle || !itemUrl) continue;

    const resultYear = extractYearFromUrl(itemUrl);
    const resultNorm = normalizeForScoring(itemTitle);

    const score = scoreResult(queryNorm, resultNorm, queryWords, resultYear, year || null);

    if (score >= 80 && score > bestScore) {
      bestScore = score;
      best = { title: itemTitle, url: itemUrl, year: resultYear };
    }
  }

  return best;
}

// ---------------------------------------------------------------------------
// Player extraction from detail page (Playwright, single page load)
// ---------------------------------------------------------------------------

function guessHosterName(url) {
  try {
    const host = new URL(url).hostname.replace(/^www\./, '');
    const base = host.split('.')[0];
    return base.charAt(0).toUpperCase() + base.slice(1);
  } catch {
    return 'Source';
  }
}

async function extractPlayersFromPage(page, pageUrl) {
  const sources = [];
  const seen = new Set();

  const add = (url, name) => {
    if (typeof url !== 'string' || !/^https?:\/\//i.test(url)) return;
    if (seen.has(url)) return;
    try {
      if (new URL(url).hostname.includes('coflix.')) return;
    } catch {
      return;
    }
    seen.add(url);
    sources.push({ provider: 'coflix', name: name || guessHosterName(url), url });
  };

  try {
    await page.goto(pageUrl, { waitUntil: 'domcontentloaded', timeout: 25000 });
    await page.waitForTimeout(1500);

    const extracted = await page.evaluate(() => {
      const out = [];
      const push = (url, name) => {
        if (url && typeof url === 'string') out.push({ url, name: name || '' });
      };

      document.querySelectorAll('iframe[src], iframe[data-src]').forEach((el) => {
        push(el.getAttribute('src') || el.getAttribute('data-src'), el.getAttribute('title') || '');
      });

      document.querySelectorAll('[data-url], [data-src], [data-link], [data-player]').forEach((el) => {
        push(
          el.getAttribute('data-url') ||
            el.getAttribute('data-src') ||
            el.getAttribute('data-link') ||
            el.getAttribute('data-player'),
          el.textContent?.trim() || el.getAttribute('title') || '',
        );
      });

      document.querySelectorAll('a[href]').forEach((el) => {
        const href = el.getAttribute('href') || '';
        if (/embed|player|video|stream|watch/i.test(href) || /voe|dood|filemoon|streamwish|uqload|vidzy|netu|mixdrop/i.test(href)) {
          push(href, el.textContent?.trim() || '');
        }
      });

      for (const script of document.querySelectorAll('script')) {
        const text = script.textContent || '';
        for (const match of text.matchAll(/https?:\/\/[^"'\s<>]+/g)) {
          const u = match[0].replace(/[\\]+$/g, '');
          if (/embed|player|\/e\/|\/v\/|voe|dood|filemoon|streamwish|uqload|vidzy|netu|mixdrop|vidhide/i.test(u)) {
            push(u, '');
          }
        }
      }

      return out;
    });

    for (const item of extracted) {
      let url = item.url;
      try {
        url = new URL(item.url, pageUrl).href;
      } catch {
        continue;
      }
      add(url, item.name);
    }
  } catch {
    // One detail page failure must not abort the whole provider.
  }

  return sources;
}

// ---------------------------------------------------------------------------
// Main entry point
// ---------------------------------------------------------------------------

/**
 * Coflix source search using the WordPress REST API (Onyx approach).
 *
 * @param {object} payload
 * @param {'movie'|'tv'} payload.type
 * @param {string} payload.title          - primary (French) title
 * @param {string} [payload.originalTitle] - fallback title
 * @param {number} [payload.year]
 * @param {number} [payload.season]
 * @param {number} [payload.episode]
 */
async function searchCoflix(payload = {}) {
  const { type, title, originalTitle, year, season, episode } = payload;
  if (!title && !originalTitle) return [];
  if (type === 'tv' && (!season || !episode)) return [];

  // 1. Discover active mirror (fetch-based, no Playwright needed for this step)
  const mirror = await discoverMirrorViaFetch();
  const mirrors = [...new Set([mirror, ...FALLBACK_MIRRORS].map(normalizeMirror).filter(Boolean))];

  const wpType = type === 'tv' ? 'series' : 'movies';
  const titlesToTry = [...new Set([title, originalTitle].filter(Boolean))];

  // 2. Find best WP API match (try each title × each mirror until a hit)
  let matchUrl = null;
  let matchMirror = null;

  outer: for (const mirror of mirrors) {
    for (const t of titlesToTry) {
      const match = await wpApiSearchBest(mirror, t, year || null, wpType);
      if (match) {
        matchUrl = match.url;
        matchMirror = mirror;
        // Update mirror cache on success
        cachedMirror = mirror;
        cachedAtMs = Date.now();
        break outer;
      }
    }
  }

  if (!matchUrl) return [];

  // 3. Load detail page with Playwright and extract player links
  const browser = await chromium.launch({
    headless: true,
    args: ['--no-sandbox', '--disable-blink-features=AutomationControlled'],
  });
  const context = await browser.newContext({
    userAgent: USER_AGENT,
    locale: 'fr-FR',
    extraHTTPHeaders: { 'Accept-Language': 'fr-FR,fr;q=0.9,en;q=0.8' },
  });
  const page = await context.newPage();

  try {
    let sources = [];

    if (type === 'tv' && season && episode) {
      // Try episode-specific URLs first (common Coflix URL patterns)
      const basePath = matchUrl.replace(/\/?$/, '');
      const episodeCandidates = [
        `${basePath}/saison-${season}/episode-${episode}`,
        `${basePath}/season/${season}/episode/${episode}`,
        matchUrl,
      ];
      for (const candidate of episodeCandidates) {
        const found = await extractPlayersFromPage(page, candidate);
        if (found.length) {
          sources = found;
          break;
        }
      }
    } else {
      sources = await extractPlayersFromPage(page, matchUrl);
    }

    // Deduplicate
    const seen = new Set();
    return sources.filter((s) => {
      if (seen.has(s.url)) return false;
      seen.add(s.url);
      return true;
    });
  } finally {
    await context.close();
    await browser.close();
  }
}

module.exports = { searchCoflix, discoverMirrorViaFetch, normalizeMirror, slugify, wpApiSearchBest };
