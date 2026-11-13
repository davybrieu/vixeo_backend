const { chromium } = require('playwright');
const { normalizeTitle, matchesTitle } = require('./title-matcher');

const DEFAULT_PORTAL_URL = 'https://fstream.info/';
const DEFAULT_BASE_URL = 'https://fs16.lol/';

function normalizeBaseUrl(url) {
  if (!url) return null;
  try {
    const parsed = new URL(url);
    if (!['http:', 'https:'].includes(parsed.protocol)) return null;
    return parsed.href.endsWith('/') ? parsed.href : parsed.href + '/';
  } catch {
    return null;
  }
}

function extractFrenchStreamId(onclick = '') {
  const value = String(onclick || '');
  // The search result may contain a relative or absolute URL. Extract the
  // numeric DLE newsid only; passing the rest of the URL/query string as the
  // film API id can make the provider return an unrelated/default player set.
  const newsId = value.match(/newsid=(\d+)/i);
  if (newsId) return newsId[1];

  const quotedPath = value.match(/['"]([^'"]+)['"]/);
  if (!quotedPath) return '';
  const path = quotedPath[1].trim();
  const pathId = path.match(/(?:^|\/)newsid-(\d+)(?:\D|$)/i);
  return pathId ? pathId[1] : '';
}

/**
 * FrenchStream's search endpoint can return broad/partial matches.
 * Compare normalized full titles instead of accepting any result returned
 * by the search engine. Leading articles and a trailing release year are
 * ignored, but additional title words are never treated as a match.
 */
function isFrenchStreamSeries(title = '') {
  return /(?:^|\s)(?:-|–)?\s*(?:saison|season)\s*\d+\b/i.test(String(title));
}

function normalizeSearchTitle(value = '') {
  return normalizeTitle(value);
}

function matchesRequestedTitle(candidate, options = {}) {
  return matchesTitle(candidate, options);
}

function normalizePlayerEntries(players = {}) {
  const labels = { vff: 'TrueFrench', vfq: 'French', vostfr: 'VOSTFR', vo: 'VO' };
  const output = [];
  for (const [provider, languages] of Object.entries(players || {})) {
    if (!languages || typeof languages !== 'object') continue;
    const seen = new Set();
    for (const lang of ['vff', 'vfq', 'vostfr', 'vo', ...Object.keys(languages)]) {
      if (lang === 'default' || !(lang in languages)) continue;
      const url = languages[lang];
      if (typeof url !== 'string' || !/^https?:\/\//i.test(url) || seen.has(url)) continue;
      if (provider.trim().toLowerCase() === 'dood.stream' && url.includes('/bigwar5/')) continue;
      seen.add(url);
      output.push({
        provider: 'frenchstream',
        name: provider.charAt(0).toUpperCase() + provider.slice(1) + (labels[lang] ? ' (' + labels[lang] + ')' : ''),
        url,
      });
    }
    const fallback = languages.default;
    if (typeof fallback === 'string' && /^https?:\/\//i.test(fallback) && !seen.has(fallback)) {
      if (!(provider.trim().toLowerCase() === 'dood.stream' && fallback.includes('/bigwar5/'))) {
        output.push({ provider: 'frenchstream', name: provider.charAt(0).toUpperCase() + provider.slice(1), url: fallback });
      }
    }
  }
  return output;
}

function normalizeEpisodeEntries(data, requestedEpisode = null) {
  const output = [];
  for (const [key, label] of [['vf', 'VF'], ['vostfr', 'VOSTFR'], ['vo', 'VO']]) {
    const episodes = data?.[key];
    if (!episodes || typeof episodes !== 'object') continue;
    const numbers = requestedEpisode
      ? [String(requestedEpisode)]
      : Object.keys(episodes).sort((a, b) => Number(a) - Number(b));
    for (const number of numbers) {
      const sources = episodes[number];
      if (!sources || typeof sources !== 'object') continue;
      for (const [provider, url] of Object.entries(sources)) {
        if (typeof url === 'string' && /^https?:\/\//i.test(url)) {
          output.push({
            provider: 'frenchstream',
            name: provider.charAt(0).toUpperCase() + provider.slice(1) + ' (Épisode ' + number + ', ' + label + ')',
            url,
          });
        }
      }
    }
  }
  return output;
}

async function searchFrenchStream({ type, title, originalTitle, year, episode }) {
  const browser = await chromium.launch({ headless: true, args: ['--no-sandbox'] });
  const context = await browser.newContext({
    userAgent: 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
    locale: 'fr-FR',
  });
  const page = await context.newPage();

  try {
    let baseUrl = DEFAULT_BASE_URL;
    try {
      await page.goto(DEFAULT_PORTAL_URL, { waitUntil: 'domcontentloaded', timeout: 20000 });
      const currentUrl = await page.locator('div.container > div.url-card a').first().getAttribute('href', { timeout: 4000 });
      baseUrl = normalizeBaseUrl(currentUrl) || baseUrl;
    } catch {
      // The portal may be unavailable; retain the Kotlin provider's default domain.
    }

    const sources = [];
    const seenResults = new Set();
    for (const query of [...new Set([title, originalTitle].filter(Boolean))]) {
      const response = await page.request.post(new URL('engine/ajax/search.php', baseUrl).href, {
        form: { query, page: '1' },
        headers: { 'X-Requested-With': 'XMLHttpRequest', Cookie: 'fsschal=1', Referer: baseUrl },
        timeout: 20000,
      });
      if (!response.ok()) continue;
      const html = await response.text();
      const resultsPage = await context.newPage();
      try {
        await resultsPage.setContent(html, { waitUntil: 'domcontentloaded' });
        const results = await resultsPage.locator('div.search-item').evaluateAll(items =>
          items.map(item => ({
            id: item.getAttribute('onclick') || '',
            title: item.querySelector('div.search-title')?.textContent?.trim() || '',
          }))
        );

        for (const result of results) {
          const id = extractFrenchStreamId(result.id);
          if (!id || seenResults.has(id)) continue;

          const isSeries = id.includes('-saison-') || /\s-\sSaison\s/i.test(result.title);
          if ((type === 'tv' && !isSeries) || (type === 'movie' && isSeries)) continue;
          if (!matchesRequestedTitle(result.title, { title, originalTitle, year })) continue;
          seenResults.add(id);

          const itemId = id;

          try {
            const endpoint = isSeries ? 'engine/ajax/sx.php' : 'engine/ajax/film_api.php';
            const apiResponse = await page.request.get(new URL(endpoint, baseUrl).href, {
              params: { id: itemId },
              headers: {
                Cookie: 'dle_skin=VFV1; fsschal=1',
                'X-Requested-With': 'XMLHttpRequest',
                Referer: baseUrl,
              },
              timeout: 20000,
            });
            if (!apiResponse.ok()) continue;
            const data = await apiResponse.json();
            const found = isSeries
              ? normalizeEpisodeEntries(data, episode)
              : normalizePlayerEntries(data?.players);
            sources.push(...found);
          } catch {
            // A single unavailable item must not abort the other search results.
          }
        }
      } finally {
        await resultsPage.close();
      }
    }

    return sources;
  } finally {
    await context.close();
    await browser.close();
  }
}

module.exports = {
  searchFrenchStream,
  extractFrenchStreamId,
  isFrenchStreamSeries,
  normalizeSearchTitle,
  matchesRequestedTitle,
  normalizePlayerEntries,
  normalizeEpisodeEntries,
};
