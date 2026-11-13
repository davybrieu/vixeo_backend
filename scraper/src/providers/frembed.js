// Dead domains from Onyx APK v1.8.15 (FrembedProvider.java DEAD_DOMAINS)
const DEAD_DOMAINS = new Set(['audin213', 'frembed.bond', 'frembed.hair', 'frembed.cyou']);

const DEFAULT_BASE_URL = 'https://frembed.bar/';

// Candidate URLs to probe for active domain when the current one fails.
// Order mirrors Onyx: current → default → known fallbacks.
const CANDIDATE_URLS = [
  'https://frembed.bar/',
  'https://frembed.casa/',
  'https://frembed.surf/',
];

let activeBaseUrl = DEFAULT_BASE_URL;

const UA = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0.0.0 Safari/537.36';

function normalizeUrl(value, baseUrl) {
  if (typeof value !== 'string' || !value.trim()) return '';
  try {
    const url = new URL(value, baseUrl);
    return /^https?:$/.test(url.protocol) ? url.href : '';
  } catch {
    return '';
  }
}

function languageLabel(value) {
  const lang = String(value || '').toLowerCase();
  if (lang === 'vf') return 'French';
  if (lang === 'vostfr') return 'VOSTFR';
  if (lang === 'vo') return 'VO';
  return lang ? lang.toUpperCase() : 'Unknown';
}

function hostLabel(value, url) {
  if (value?.host?.name) return value.host.name;
  if (value?.label) return value.label;
  try {
    return new URL(url).hostname.replace(/^www\./, '').split('.')[0];
  } catch {
    return 'Source';
  }
}

/**
 * Discover the active Frembed base URL by calling /api/domain on each
 * candidate. Mirrors Onyx FrembedProvider.domaineViaApi().
 *
 * Returns the new base URL (e.g. "https://frembed.bar/") or null if none
 * of the candidates respond with a valid, non-dead domain.
 */
async function discoverActiveBaseUrl() {
  // Build a de-duplicated list: current active URL first, then the static candidates.
  const seen = new Set();
  const candidates = [];
  for (const url of [activeBaseUrl, ...CANDIDATE_URLS]) {
    // Normalise to "https://host/" so we can append /api/domain cleanly.
    let base;
    try {
      base = new URL(url).origin + '/';
    } catch {
      continue;
    }
    if (!seen.has(base)) {
      seen.add(base);
      candidates.push(base);
    }
  }

  for (const base of candidates) {
    const apiUrl = base + 'api/domain';
    try {
      const response = await fetch(apiUrl, {
        headers: {
          'User-Agent': UA,
          'Accept': 'application/json',
        },
        signal: AbortSignal.timeout(10000),
        redirect: 'follow',
      });
      if (!response.ok) continue;

      const text = await response.text();
      let domain;
      try {
        domain = JSON.parse(text)?.domain;
      } catch {
        continue;
      }

      if (typeof domain !== 'string') continue;

      // Onyx: strip whitespace, lowercase, validate against [a-z0-9.-]+\.[a-z]{2,}
      const cleaned = domain.trim().toLowerCase();
      if (!/^[a-z0-9.-]+\.[a-z]{2,}$/.test(cleaned)) continue;

      // Skip dead domains (match by exact hostname or suffix)
      if ([...DEAD_DOMAINS].some(d => cleaned === d || cleaned.endsWith('.' + d))) continue;

      return `https://${cleaned}/`;
    } catch (err) {
      // Network error on this candidate — try the next one.
      console.debug(`[frembed] /api/domain unreachable on ${base}: ${err.message}`);
    }
  }

  return null;
}

async function resolveFrembedLink(url) {
  try {
    const parsed = new URL(url);
    const response = await fetch(url, {
      method: 'GET',
      headers: {
        'User-Agent': UA,
        'Referer': parsed.origin + '/',
      },
      signal: AbortSignal.timeout(12000),
      redirect: 'manual',
    });

    const location = response.headers.get('location');
    if ([301, 302, 303, 307, 308].includes(response.status) && location) {
      return normalizeUrl(location, url) || url;
    }
  } catch {
    // Keep the original player URL when a host blocks redirect probing.
  }
  return url;
}

async function fetchFromFrembed(endpoint) {
  const headers = {
    'User-Agent': UA,
    'Accept': 'application/json',
    'Referer': activeBaseUrl,
  };

  let response = await fetch(endpoint, {
    headers,
    signal: AbortSignal.timeout(20000),
    redirect: 'manual',
  });

  // Case 1 — HTTP redirect (301/302/307/308): follow it, update activeBaseUrl, retry once.
  if ([301, 302, 303, 307, 308].includes(response.status)) {
    const location = response.headers.get('location');
    if (location) {
      const redirected = new URL(location, endpoint);
      activeBaseUrl = `${redirected.protocol}//${redirected.host}/`;
      const retryUrl = new URL(endpoint.pathname + endpoint.search, activeBaseUrl);
      response = await fetch(retryUrl, {
        headers: { ...headers, Referer: activeBaseUrl },
        signal: AbortSignal.timeout(20000),
        redirect: 'manual',
      });
    }
  }

  // Case 2 — 403 (or any failure): domain has rotated without a redirect.
  // Probe /api/domain on candidate URLs to find the new active domain.
  if (!response.ok) {
    const discoveredUrl = await discoverActiveBaseUrl();
    if (discoveredUrl && discoveredUrl !== activeBaseUrl) {
      activeBaseUrl = discoveredUrl;
      const retryUrl = new URL(endpoint.pathname + endpoint.search, activeBaseUrl);
      response = await fetch(retryUrl, {
        headers: { ...headers, Referer: activeBaseUrl },
        signal: AbortSignal.timeout(20000),
        redirect: 'follow',
      });
    }
  }

  if (!response.ok) {
    throw new Error(`Frembed API returned HTTP ${response.status}`);
  }

  return response;
}

async function searchFrembed({ type, tmdb_id, season, episode }) {
  if (!tmdb_id) return [];

  const endpoint = new URL(type === 'movie' ? 'api/films' : 'api/series', activeBaseUrl);
  endpoint.searchParams.set('id', String(tmdb_id));
  endpoint.searchParams.set('idType', 'tmdb');

  if (type === 'tv' && season != null && episode != null) {
    endpoint.searchParams.set('sa', String(season));
    endpoint.searchParams.set('epi', String(episode));
  } else if (type !== 'movie') {
    return [];
  }

  const response = await fetchFromFrembed(endpoint);
  const data = await response.json();
  const baseUrl = response.url ? new URL(response.url).origin + '/' : activeBaseUrl;
  const sources = [];

  const add = (value, index, fallbackLang) => {
    const rawUrl = typeof value === 'string' ? value : value?.url;
    const url = normalizeUrl(rawUrl, baseUrl);
    if (!url) return;

    const lang = value?.lang || fallbackLang;
    const name = hostLabel(value, url);
    sources.push({
      provider: 'frembed',
      name: `${name} (${languageLabel(lang)})`,
      url,
    });
  };

  if (Array.isArray(data.links) && data.links.length > 0) {
    data.links.forEach((link, index) => add(link, index, 'vf'));
  } else {
    const legacyFields = [
      ...Array.from({ length: 7 }, (_, i) => [`link${i + 1}`, 'vf']),
      ...Array.from({ length: 7 }, (_, i) => [`link${i + 1}vostfr`, 'vostfr']),
      ...Array.from({ length: 7 }, (_, i) => [`link${i + 1}vo`, 'vo']),
    ];
    legacyFields.forEach(([key, lang], index) => {
      if (data[key]) add(data[key], index, lang);
    });
  }

  const resolvedSources = await Promise.all(sources.map(async (source) => ({
    ...source,
    url: await resolveFrembedLink(source.url),
  })));

  const seen = new Set();

  return resolvedSources.filter((source) => {
    if (seen.has(source.url)) return false;
    seen.add(source.url);
    return true;
  });
}

module.exports = { searchFrembed, resolveFrembedLink, discoverActiveBaseUrl };
