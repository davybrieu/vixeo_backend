const { chromium } = require('playwright');

const PORTAL_URL = 'https://1jour1film-officiel.site/';
const DEFAULT_BASE_URL = 'https://1jour1film0126b.site/';
const USER_AGENT = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 Chrome/130.0.0.0 Safari/537.36';

function normalizeUrl(url, base = DEFAULT_BASE_URL) {
  if (!url) return '';
  try {
    return new URL(url, base).href;
  } catch {
    return '';
  }
}

function titleScore(candidate, expected) {
  const clean = (value) => String(value || '').toLowerCase()
    .normalize('NFD').replace(/[\u0300-\u036f]/g, '')
    .replace(/[^a-z0-9]+/g, ' ').trim();
  const a = clean(candidate);
  const b = clean(expected);
  if (!a || !b) return 0;
  if (a === b) return 3;
  if (a.includes(b) || b.includes(a)) return 2;
  const words = b.split(' ').filter(Boolean);
  const overlap = words.filter((word) => a.split(' ').includes(word)).length;
  return overlap >= Math.ceil(words.length * 0.7) ? 1 : 0;
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
        const match = script.textContent.match(/const\s+videoData\s*=\s*(\{[\s\S]*?\})\s*;?/);
        if (!match) return [];
        try {
          const data = JSON.parse(match[1]);
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
  let specialUrl = '';
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
      specialUrl = embedUrl;
      break;
    }
    sources.push({ provider: '1jour1film', name: option.name, url: embedUrl });
  }

  if (specialUrl) {
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
      const results = await page.locator('article.item a[itemprop="url"], article.item a[href]').evaluateAll((links) =>
        links.map((link) => ({
          title: link.textContent?.trim() || link.closest('article')?.querySelector('h2, .title')?.textContent?.trim() || '',
          url: link.href,
          classes: link.closest('article')?.className || '',
        })).filter((item) => item.url)
      );

      for (const item of results) {
        const isTv = /tvshows|tvshow|series/i.test(item.url + ' ' + item.classes);
        if ((type === 'tv') !== isTv) continue;
        const score = Math.max(titleScore(item.title, title), titleScore(item.title, originalTitle));
        if (score > 0) candidates.set(item.url, { ...item, score });
      }
    }

    const ranked = [...candidates.values()].sort((a, b) => b.score - a.score).slice(0, 3);
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
