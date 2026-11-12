const { chromium } = require('playwright');

const PORTAL_URL = 'https://ww1.wiflix-adresses.fun/';
const DEFAULT_BASE_URL = 'https://flemmix.team/';

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

function extractLoadVideoUrl(onclick = '') {
  const match = onclick.match(/loadVideo\(['"]([^'"]+)['"]/);
  return match?.[1] || '';
}

function isIgnoredSource(name = '') {
  return ['netu', 'vudeo'].includes(name.trim().toLowerCase());
}

function parseWiflixResults(items, baseUrl) {
  return items.map(item => {
    const href = item.href || '';
    const url = href ? new URL(href, baseUrl).href : '';
    const path = new URL(url || baseUrl).pathname;
    const id = path.split('/').filter(Boolean).pop() || '';
    const isMovie = href.includes('film-en-streaming/') || href.includes('film-ancien/');
    const isSeries = href.includes('serie-en-streaming/') || href.includes('/vf/');
    if (!id || (!isMovie && !isSeries)) return null;
    return {
      id,
      url,
      type: isSeries ? 'tv' : 'movie',
      title: item.title || '',
    };
  }).filter(Boolean);
}

function parseMovieServers(anchors) {
  return anchors.map(anchor => ({
    provider: 'wiflix',
    name: anchor.name || 'Source',
    url: extractLoadVideoUrl(anchor.onclick),
  })).filter(source => source.url && /^https?:\/\//i.test(source.url) && !isIgnoredSource(source.name));
}

function parseEpisodeServers(episodes) {
  const output = [];
  for (const episode of episodes) {
    for (const anchor of episode.anchors || []) {
      const url = extractLoadVideoUrl(anchor.onclick);
      if (url && /^https?:\/\//i.test(url) && !isIgnoredSource(anchor.name)) {
        output.push({
          provider: 'wiflix',
          name: (anchor.name || 'Source') + ' - ' + (episode.label || 'Épisode'),
          url,
        });
      }
    }
  }
  return output;
}

async function searchWiflix({ type, title, originalTitle, episode }) {
  const browser = await chromium.launch({ headless: true, args: ['--no-sandbox'] });
  const context = await browser.newContext({
    userAgent: 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/130.0.0.0 Safari/537.36',
    locale: 'fr-FR',
  });
  const page = await context.newPage();

  try {
    let baseUrl = DEFAULT_BASE_URL;
    try {
      await page.goto(PORTAL_URL, { waitUntil: 'domcontentloaded', timeout: 15000 });
      const activeUrl = await page.locator('div.container > div.url-card a').first().getAttribute('href', { timeout: 4000 });
      baseUrl = normalizeBaseUrl(activeUrl) || baseUrl;
    } catch {
      // Keep the Kotlin provider's default domain when its address portal is unavailable.
    }

    const resultsSeen = new Set();
    const sources = [];
    for (const query of [...new Set([title, originalTitle].filter(Boolean))]) {
      const response = await page.request.post(new URL('index.php?do=search', baseUrl).href, {
        form: {
          story: query,
          do: 'search',
          subaction: 'search',
          search_start: '1',
          full_search: '1',
        },
        headers: { 'X-Requested-With': 'XMLHttpRequest', Referer: baseUrl },
        timeout: 20000,
      });
      if (!response.ok()) continue;

      const resultPage = await context.newPage();
      try {
        await resultPage.setContent(await response.text(), { waitUntil: 'domcontentloaded' });
        const rawResults = await resultPage.locator('div.mov').evaluateAll(items =>
          items.filter(item => !item.closest('#no-results-rec')).map(item => ({
            href: item.querySelector('a.mov-t')?.getAttribute('href') || '',
            title: item.querySelector('a.mov-t')?.textContent?.trim() || '',
          }))
        );

        const results = parseWiflixResults(rawResults, baseUrl);
        for (const result of results.slice(0, 5)) {
          const key = result.url;
          if (resultsSeen.has(key)) continue;
          resultsSeen.add(key);

          if (type && result.type !== type) continue;

          const detailPage = await context.newPage();
          try {
            await detailPage.goto(result.url, { waitUntil: 'domcontentloaded', timeout: 20000 });
            if (result.type === 'movie') {
              const anchors = await detailPage.locator('div.tabs-sel a').evaluateAll(items =>
                items.map(a => ({
                  name: a.querySelector('span')?.textContent?.trim() || '',
                  onclick: a.getAttribute('onclick') || '',
                }))
              );
              sources.push(...parseMovieServers(anchors));
            } else {
              const episodes = await detailPage.locator('div.blocvostfr ul.eplist li, div.blocfr ul.eplist li').evaluateAll((items, requestedEpisode) =>
                items.filter(li => !requestedEpisode || Number((li.textContent || '').match(/Episode\\s+(\\d+)/i)?.[1]) === Number(requestedEpisode))
                  .map(li => {
                    const rel = li.getAttribute('rel') || '';
                    const container = rel ? document.querySelector('div.' + CSS.escape(rel)) : null;
                    const anchors = container ? [...container.querySelectorAll('a')].map(a => ({
                      name: a.querySelector('span')?.textContent?.trim() || '',
                      onclick: a.getAttribute('onclick') || '',
                    })) : [];
                    return { label: li.textContent?.trim() || '', anchors };
                  }), episode
              );
              sources.push(...parseEpisodeServers(episodes));
            }
          } catch {
            // Skip one detail page and continue with the remaining matches.
          } finally {
            await detailPage.close();
          }
        }
      } finally {
        await resultPage.close();
      }
    }

    return sources;
  } finally {
    await context.close();
    await browser.close();
  }
}

module.exports = {
  searchWiflix,
  normalizeBaseUrl,
  extractLoadVideoUrl,
  parseWiflixResults,
  parseMovieServers,
  parseEpisodeServers,
};
