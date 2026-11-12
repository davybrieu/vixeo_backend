const { chromium } = require('playwright');
const { matchesTitle } = require('./title-matcher');

const DISCOVERY_URL = 'https://coflix.domains/';
const FALLBACK_MIRRORS = ['https://coflix.ac', 'https://coflix.esq', 'https://coflix.cloud', 'https://coflix.red'];
const EXCLUDED = new Set(['https://coflix.domains', 'https://coflix.blog', 'https://coflix.plus']);
const USER_AGENT =
  'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36';

let cachedMirror = null;
let cachedAtMs = 0;
const DISCOVERY_TTL_MS = 6 * 60 * 60 * 1000;

function normalizeMirror(raw) {
  if (!raw) return null;
  try {
    const url = new URL(String(raw).replace(/\\\//g, '/'));
    if (!['http:', 'https:'].includes(url.protocol)) return null;
    const href = `${url.protocol}//${url.host}`;
    if (EXCLUDED.has(href) || EXCLUDED.has(href.replace(/^http:/, 'https:'))) return null;
    return href;
  } catch {
    return null;
  }
}

async function discoverMirror(page) {
  const now = Date.now();
  if (cachedMirror && now - cachedAtMs < DISCOVERY_TTL_MS) return cachedMirror;

  try {
    await page.goto(DISCOVERY_URL, { waitUntil: 'domcontentloaded', timeout: 20000 });
    const html = await page.content();
    const redirect = html.match(/"redirect_url"\s*:\s*"(https?:\\?\/\\?\/[^\"]+)"/i);
    if (redirect) {
      const mirror = normalizeMirror(redirect[1]);
      if (mirror) {
        cachedMirror = mirror;
        cachedAtMs = now;
        return mirror;
      }
    }

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
    // Fall through to static mirrors.
  }

  return FALLBACK_MIRRORS[0];
}

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
    // Skip same-site navigational links.
    try {
      if (new URL(url).hostname.includes('coflix.')) return;
    } catch {
      return;
    }
    seen.add(url);
    sources.push({
      provider: 'coflix',
      name: name || guessHosterName(url),
      url,
    });
  };

  try {
    await page.goto(pageUrl, { waitUntil: 'domcontentloaded', timeout: 25000 });
    await page.waitForTimeout(1200);

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
        for (const match of text.matchAll(/https?:\/\/[^\"'\s<>]+/g)) {
          const url = match[0].replace(/[\\]+$/g, '');
          if (/embed|player|\/e\/|\/v\/|voe|dood|filemoon|streamwish|uqload|vidzy|netu|mixdrop|vidhide/i.test(url)) {
            push(url, '');
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

async function searchOnMirror(page, baseUrl, { type, title, originalTitle, year, season, episode }) {
  const queries = [...new Set([title, originalTitle].filter(Boolean))];
  const candidates = new Map();

  for (const query of queries) {
    const searchUrl = new URL('/', baseUrl);
    searchUrl.searchParams.set('s', query);
    try {
      await page.goto(searchUrl.href, { waitUntil: 'domcontentloaded', timeout: 25000 });
      await page.waitForTimeout(800);

      const results = await page.evaluate(() => {
        const items = [];
        const anchors = [
          ...document.querySelectorAll(
            'article a[href], .result-item a[href], .ml-item a[href], .movies-list a[href], .item a[href], h2 a[href], h3 a[href]',
          ),
        ];
        for (const a of anchors) {
          const href = a.href || '';
          const text = (a.textContent || a.getAttribute('title') || '').trim();
          if (!href || !text) continue;
          items.push({ title: text, url: href });
        }
        return items;
      });

      for (const item of results) {
        const url = item.url;
        const isSeries =
          /\/serie\//i.test(url) ||
          /\/series?\//i.test(url) ||
          /\/tv\//i.test(url) ||
          /saison|season/i.test(item.title);
        const isMovie = /\/film\//i.test(url) || /\/movies?\//i.test(url) || !isSeries;

        if (type === 'tv' && !isSeries) continue;
        if (type !== 'tv' && isSeries) continue;
        if (!matchesTitle(item.title, { title, originalTitle, year })) continue;

        candidates.set(url, { ...item, url, isSeries });
      }
    } catch {
      // Try next query / mirror.
    }
  }

  const ranked = [...candidates.values()].slice(0, 4);
  const sources = [];

  for (const item of ranked) {
    let detailUrl = item.url;

    if (type === 'tv' && season && episode) {
      // Prefer explicit episode URLs when the theme exposes them.
      const episodeCandidates = [
        `${item.url.replace(/\/?$/, '')}/season/${season}/episode/${episode}`,
        `${item.url.replace(/\/?$/, '')}/saison-${season}/episode-${episode}`,
        item.url,
      ];
      for (const candidate of episodeCandidates) {
        const found = await extractPlayersFromPage(page, candidate);
        if (found.length) {
          sources.push(...found);
          break;
        }
      }
    } else {
      const found = await extractPlayersFromPage(page, detailUrl);
      sources.push(...found);
    }

    if (sources.length >= 25) break;
  }

  return sources;
}

/**
 * Coflix source search as used by Onyx TmdbProvider (CoflixSourceProvider).
 * Discovers the active mirror, searches by title, and extracts player links.
 */
async function searchCoflix(payload = {}) {
  const { type, title, originalTitle, year, season, episode } = payload;
  if (!title && !originalTitle) return [];
  if (type === 'tv' && (!season || !episode)) return [];

  const browser = await chromium.launch({ headless: true, args: ['--no-sandbox', '--disable-blink-features=AutomationControlled'] });
  const context = await browser.newContext({
    userAgent: USER_AGENT,
    locale: 'fr-FR',
    extraHTTPHeaders: { 'Accept-Language': 'fr-FR,fr;q=0.9,en;q=0.8' },
  });
  const page = await context.newPage();

  try {
    const primary = await discoverMirror(page);
    const mirrors = [...new Set([primary, ...FALLBACK_MIRRORS].map(normalizeMirror).filter(Boolean))];

    for (const mirror of mirrors) {
      const sources = await searchOnMirror(page, mirror, {
        type,
        title,
        originalTitle,
        year,
        season,
        episode,
      });
      if (sources.length) {
        cachedMirror = mirror;
        cachedAtMs = Date.now();
        const seen = new Set();
        return sources.filter((s) => {
          if (seen.has(s.url)) return false;
          seen.add(s.url);
          return true;
        });
      }
    }

    return [];
  } finally {
    await context.close();
    await browser.close();
  }
}

module.exports = { searchCoflix, discoverMirror, normalizeMirror };
