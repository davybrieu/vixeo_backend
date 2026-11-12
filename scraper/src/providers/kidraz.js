const { chromium } = require('playwright');
const { matchesTitle } = require('./title-matcher');

const PORTAL_URL = 'http://chezlesducs.free.fr/films.php';
const DEFAULT_BASE_URL = 'https://www.kidraz.com/saby1jy/home/kidraz/';
const USER_AGENT = 'Mozilla/5.0 (X11; Ubuntu; Linux x86_64; rv:147.0) Gecko/20100101 Firefox/147.0';

function normalizeUrl(value, baseUrl) {
  if (typeof value !== 'string' || !value.trim()) return '';
  try {
    const url = new URL(value, baseUrl);
    return ['http:', 'https:'].includes(url.protocol) ? url.href : '';
  } catch {
    return '';
  }
}

function parseKidrazFilms(films, expected) {
  if (!Array.isArray(films)) return [];
  return films
    .filter((film) => film && typeof film.title === 'string' && matchesTitle(film.title, expected))
    .map((film) => ({
      title: film.title,
      url: film.link || '',
      poster: film.poster || null,
      id: film.id || '',
    }))
    .filter((film) => film.url);
}

function getApiContext(baseUrl) {
  const base = new URL(baseUrl);
  const homePath = base.pathname.replace(/\/+$/, '');
  const apiPath = homePath.split('/home/')[0] || '';
  const folder = apiPath.replace(/^\/+/, '').split('/')[0] || '';
  const pr = homePath.split('/').filter(Boolean).at(-1) || '';
  return { origin: base.origin, apiPath, folder, pr };
}

async function resolveBaseUrl(page) {
  try {
    await page.goto(PORTAL_URL, { waitUntil: 'domcontentloaded', timeout: 12000 });
    const portalLink = await page.locator('a').evaluateAll((links) => {
      const link = links.find((item) => /kidraz/i.test(item.textContent || ''));
      return link?.href || '';
    });
    if (!portalLink) return DEFAULT_BASE_URL;

    await page.goto(portalLink, { waitUntil: 'domcontentloaded', timeout: 15000 });
    const homeLink = await page.locator('a#kidrazc').getAttribute('href', { timeout: 5000 });
    if (!homeLink) return DEFAULT_BASE_URL;

    const resolved = normalizeUrl(homeLink, page.url());
    if (!resolved) return DEFAULT_BASE_URL;
    await page.goto(resolved, { waitUntil: 'domcontentloaded', timeout: 15000 });
    return page.url().endsWith('/') ? page.url() : page.url() + '/';
  } catch {
    return DEFAULT_BASE_URL;
  }
}

async function searchKidraz({ type, title, originalTitle, year }) {
  // The Kotlin provider currently exposes movies only; do not return false TV matches.
  if (type === 'tv' || (!title && !originalTitle)) return [];

  const browser = await chromium.launch({ headless: true, args: ['--no-sandbox'] });
  const context = await browser.newContext({
    userAgent: USER_AGENT,
    locale: 'fr-FR',
    extraHTTPHeaders: { Cookie: 'g=true' },
  });
  const page = await context.newPage();

  try {
    const baseUrl = await resolveBaseUrl(page);
    const { origin, apiPath, folder, pr } = getApiContext(baseUrl);
    const queries = [...new Set([title, originalTitle].filter(Boolean))];
    const candidates = new Map();

    for (const query of queries) {
      const endpoint = new URL(`${apiPath}/api_search.php`, origin);
      endpoint.searchParams.set('searchword', query);
      endpoint.searchParams.set('offset', '0');
      endpoint.searchParams.set('limit', '20');
      endpoint.searchParams.set('folder', folder);
      endpoint.searchParams.set('pr', pr);

      const response = await page.request.get(endpoint.href, {
        headers: { 'User-Agent': USER_AGENT, Referer: baseUrl, Cookie: 'g=true' },
        timeout: 20000,
      });
      if (!response.ok()) continue;

      let payload;
      try { payload = await response.json(); } catch { continue; }
      for (const film of parseKidrazFilms(payload?.films, { title, originalTitle, year })) {
        const url = normalizeUrl(film.url, baseUrl);
        if (url) candidates.set(url, { ...film, url });
      }
    }

    const sources = [];
    for (const film of candidates.values()) {
      const detailPage = await context.newPage();
      try {
        await detailPage.goto(film.url, { waitUntil: 'domcontentloaded', timeout: 20000 });
        const iframe = await detailPage.locator('iframe[src]').first().getAttribute('src', { timeout: 7000 });
        const url = normalizeUrl(iframe, film.url);
        if (!url) continue;
        sources.push({
          provider: 'kidraz',
          name: new URL(url).hostname.replace(/^www\./, ''),
          url,
        });
      } catch {
        // One broken detail page must not abort the other exact matches.
      } finally {
        await detailPage.close();
      }
    }

    return [...new Map(sources.map((source) => [source.url, source])).values()];
  } finally {
    await context.close();
    await browser.close();
  }
}

module.exports = { searchKidraz, normalizeUrl, parseKidrazFilms, getApiContext };
