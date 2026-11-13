const { chromium } = require('playwright');
const { matchesTitle } = require('./title-matcher');

const PORTAL_URL = 'https://1jour1film-officiel.site/';
const DEFAULT_BASE_URL = 'https://1jour1film0126b.site/';
const USER_AGENT = 'Mozilla/5.0 (Linux; Android 13; Pixel 7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/116.0.0.0 Mobile Safari/537.36';

function normalizeUrl(url, base = DEFAULT_BASE_URL) {
  if (!url) return '';
  try {
    const parsed = new URL(url, base);
    return ['http:', 'https:'].includes(parsed.protocol) ? parsed.href : '';
  } catch {
    return '';
  }
}


async function resolvePortal(page) {
  try {
    await page.goto(PORTAL_URL, { waitUntil: 'domcontentloaded', timeout: 15000 });
    const redirected = await page.evaluate(() => {
      const match = document.documentElement.innerHTML.match(/window\.location\.href\s*=\s*["']([^"']+)/);
      return match?.[1] || location.href;
    });
    const url = normalizeUrl(redirected, PORTAL_URL);
    if (url && new URL(url).hostname !== new URL(PORTAL_URL).hostname) {
      return url.endsWith('/') ? url : url + '/';
    }
  } catch {}
  return DEFAULT_BASE_URL;
}

async function expandSpecialPlayer(page, url, referer) {
  const result = [];
  if (url.startsWith('https://api.voirfilm.cam')) {
    try {
      await page.goto(url, { waitUntil: 'domcontentloaded', timeout: 15000 });
      const links = await page.locator('div.top ul.content > li[data-url]').evaluateAll((items) =>
        items.map((item, index) => ({
          name: item.innerText?.trim() || `Server ${index + 1}`,
          url: item.getAttribute('data-url') || '',
        })).filter((item) => item.url)
      );
      return links.map((item) => ({ ...item, url: normalizeUrl(item.url, url) }));
    } catch {
      return result;
    }
  }

  if (url.startsWith('https://onregardeou.site')) {
    try {
      await page.goto(url, { waitUntil: 'domcontentloaded', timeout: 15000 });
      const servers = await page.locator('script').evaluateAll((scripts) => {
        const script = scripts.find((item) => item.textContent.includes('videoData'));
        if (!script) return [];
        const marker = 'const videoData=';
        const start = script.textContent.indexOf(marker);
        if (start < 0) return [];
        const end = script.textContent.indexOf('};', start);
        if (end < 0) return [];
        const raw = script.textContent.slice(start + marker.length, end + 1);
        try {
          const data = JSON.parse(raw);
          return Array.isArray(data.servers) ? data.servers.map((server, index) => ({
            name: server.name || `Server ${index + 1}`,
            url: String(server.url || '').replace(/\\\//g, '/'),
          })).filter((server) => server.url) : [];
        } catch {
          return [];
        }
      });
      return servers.map((item) => ({ ...item, url: normalizeUrl(item.url, url) }));
    } catch {
      return result;
    }
  }
  return result;
}

async function getPlayerSources(page, detailUrl, type, season, episode) {
  await page.goto(detailUrl, { waitUntil: 'domcontentloaded', timeout: 20000 });
  let targetUrl = detailUrl;

  if (type === 'tv') {
    const episodeUrl = await page.evaluate(({ seasonNumber, episodeNumber }) => {
      const seasons = [...document.querySelectorAll('#seasons .se-c')];
      const seasonNode = seasons.find((node, index) => {
        const title = node.querySelector('.title')?.textContent || '';
        const match = title.match(/(?:saison|season)\s*(\d+)/i);
        return match ? Number(match[1]) === Number(seasonNumber) : index + 1 === Number(seasonNumber);
      });
      const links = seasonNode ? [...seasonNode.querySelectorAll('ul.episodios > li a[href]')] : [];
      const episodeNode = links.find((link, index) => {
        const label = link.closest('li')?.textContent || link.textContent || '';
        const match = label.match(/(?:episode|ep)\s*(\d+)/i);
        return match ? Number(match[1]) === Number(episodeNumber) : index + 1 === Number(episodeNumber);
      });
      return episodeNode?.href || '';
    }, { seasonNumber: season, episodeNumber: episode });

    if (!episodeUrl) return [];
    targetUrl = normalizeUrl(episodeUrl, detailUrl);
    await page.goto(targetUrl, { waitUntil: 'domcontentloaded', timeout: 20000 });
  }

  const options = await page.locator('ul#playeroptionsul li.dooplay_player_option').evaluateAll((items) =>
    items.map((item, index) => ({
      index,
      name: item.querySelector('span.title')?.textContent?.trim() || `Server ${index + 1}`,
      nume: item.getAttribute('data-nume') || '',
      post: item.getAttribute('data-post') || '',
    })).filter((item) => item.nume && item.post)
  );

  const sources = [];
  const specialUrls = [];
  for (const option of options) {
    const form = new URLSearchParams({
      action: 'doo_player_ajax',
      post: option.post,
      nume: option.nume,
      ...(type === 'tv' ? { type: 'tv' } : {}),
    });
    const response = await page.request.post(new URL('wp-admin/admin-ajax.php', detailUrl).href, {
      headers: {
        'User-Agent': USER_AGENT,
        'Referer': targetUrl,
        'X-Requested-With': 'XMLHttpRequest',
        'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
      },
      data: form.toString(),
      timeout: 15000,
    });
    if (!response.ok()) continue;

    let payload;
    try { payload = await response.json(); } catch { continue; }
    const embedUrl = normalizeUrl(payload?.embed_url, targetUrl);
    if (!embedUrl) continue;

    if (embedUrl.startsWith('https://api.voirfilm.cam') || embedUrl.startsWith('https://onregardeou.site')) {
      specialUrls.push(embedUrl);
      continue;
    }
    sources.push({ provider: '1jour1film', name: option.name, url: embedUrl });
  }

  for (const specialUrl of specialUrls) {
    const expanded = await expandSpecialPlayer(page, specialUrl, targetUrl);
    for (const item of expanded) {
      sources.push({ provider: '1jour1film', name: item.name, url: item.url });
    }
  }
  return sources;
}

async function searchUnJourUnFilm({ type, title, originalTitle, year, season, episode }) {
  if (!title && !originalTitle) return [];
  if (type === 'tv' && (!season || !episode)) return [];

  const browser = await chromium.launch({ headless: true, args: ['--no-sandbox'] });
  const context = await browser.newContext({ userAgent: USER_AGENT, locale: 'fr-FR' });
  const page = await context.newPage();

  try {
    const baseUrl = await resolvePortal(page);
    const queries = [...new Set([title, originalTitle].filter(Boolean))];
    const candidates = new Map();

    for (const query of queries) {
      const searchUrl = new URL('/', baseUrl);
      searchUrl.searchParams.set('s', query);
      await page.goto(searchUrl.href, { waitUntil: 'domcontentloaded', timeout: 20000 });
      const results = await page.locator('div.result-item > article').evaluateAll((articles) =>
        articles.map((article) => {
          const link = article.querySelector('div.title a[href]');
          return {
            title: link?.textContent?.trim() || '',
            url: link?.href || '',
          };
        }).filter((item) => item.url)
      );

      for (const item of results) {
        const isTv = item.url.includes('/tvshows/');
        const isMovie = item.url.includes('/films/');
        if ((type === 'tv' && !isTv) || (type !== 'tv' && !isMovie)) continue;
        if (matchesTitle(item.title, { title, originalTitle, year })) {
          candidates.set(item.url, { ...item });
        }
      }
    }

    const ranked = [...candidates.values()].slice(0, 5);
    const sources = [];
    for (const item of ranked) {
      const found = await getPlayerSources(page, item.url, type, season, episode);
      sources.push(...found);
      if (sources.length >= 20) break;
    }

    const seen = new Set();
    return sources.filter((source) => {
      if (seen.has(source.url)) return false;
      seen.add(source.url);
      return true;
    });
  } finally {
    await browser.close();
  }
}

module.exports = { searchUnJourUnFilm };
