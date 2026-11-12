const DEFAULT_BASE_URL = 'https://frembed.casa/';
let activeBaseUrl = DEFAULT_BASE_URL;

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

  try {
    let response = await fetch(endpoint, {
      headers: {
        'User-Agent': 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
        'Accept': 'application/json',
        'Referer': DEFAULT_BASE_URL,
      },
      signal: AbortSignal.timeout(20000),
      redirect: 'manual',
    });

    // Frembed changes domains. The Android provider follows the API redirect,
    // stores the new origin, and retries the same endpoint there.
    if ([301, 302, 303, 307, 308].includes(response.status)) {
      const location = response.headers.get('location');
      if (location) {
        const redirected = new URL(location, endpoint);
        activeBaseUrl = `${redirected.protocol}//${redirected.host}/`;
        const retryUrl = new URL(endpoint.pathname.replace(/^\\//, '') + endpoint.search, activeBaseUrl);
        response = await fetch(retryUrl, {
          headers: {
            'User-Agent': 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
            'Accept': 'application/json',
            'Referer': activeBaseUrl,
          },
          signal: AbortSignal.timeout(20000),
          redirect: 'manual',
        });
      }
    }

    if (!response.ok) return [];
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

    const seen = new Set();
    return sources.filter((source) => {
      if (seen.has(source.url)) return false;
      seen.add(source.url);
      return true;
    });
  } catch (error) {
    console.error('Frembed error:', error.message);
    return [];
  }
}

module.exports = { searchFrembed };
