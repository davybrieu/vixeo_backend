const { chromium } = require('playwright');
const { matchesTitle } = require('./title-matcher');

const PORTAL_URL = 'http://chezlesducs.free.fr/films.php';
const USER_AGENT =
  'Mozilla/5.0 (X11; Ubuntu; Linux x86_64; rv:147.0) Gecko/20100101 Firefox/147.0';

/**
 * Cloudstream / Movix style TMDB-id embeds used by Onyx aploufProvider as backup
 * when the primary Aplouf mirrors do not return players.
 */
function buildCloudstreamBackupSources(payload = {}) {
  const tmdbId = String(payload.tmdb_id ?? '').trim();
  if (!/^\d+$/.test(tmdbId)) return [];

  const type = payload.type === 'tv' ? 'tv' : 'movie';
  const season = Number.parseInt(payload.season, 10);
  const episode = Number.parseInt(payload.episode, 10);

  if (type === 'tv' && (!Number.isInteger(season) || !Number.isInteger(episode))) return [];

  const entries =
    type === 'movie'
      ? [
          ['Cloudstream', `https://cloudneso.vip/embed/movie/${tmdbId}`],
          ['Movix', `https://movix.website/movie/${tmdbId}`],
          ['Aplouf CS', `https://embeds.cloudneso.vip/embed/movie?tmdb=${tmdbId}`],
        ]
      : [
          ['Cloudstream', `https://cloudneso.vip/embed/tv/${tmdbId}/${season}/${episode}`],
          ['Movix', `https://movix.website/tv/${tmdbId}/${season}/${episode}`],
          ['Aplouf CS', `https://embeds.cloudneso.vip/embed/tv?tmdb=${tmdbId}&season=${season}&episode=${episode}`],
        ];

  return entries.map(([name, url]) => ({
    provider: 'aplouf',
    name,
    url,
    sourceProvider: 'cloudstream-backup',
  }));
}

async function resolvePortalMirrors(page) {
  try {
    await page.goto(PORTAL_URL, { waitUntil: 'domcontentloaded', timeout: 20000 });
    const links = await page.evaluate(() =>
      [...document.querySelectorAll('a[href]')]
        .map((a) => a.href)
        .filter((href) => /^https?:\/\//i.test(href)),
    );
    return [...new Set(links)].slice(0, 12);
  } catch {
    return [];
  }
}

async function searchMirror(page, baseUrl, payload) {
  const { type, title, originalTitle, year, season, episode } = payload;
  const queries = [...new Set([title, originalTitle].filter(Boolean))];
  const sources = [];
  const seen = new Set();

  for (const query of queries) {
    try {
      const searchUrl = new URL('/', baseUrl);
      // Common patterns used by the Aplouf mirror family.
      searchUrl.searchParams.set('s', query);
      await page.goto(searchUrl.href, { waitUntil: 'domcontentloaded', timeout: 20000 });
      await page.waitForTimeout(600);

      const results = await page.evaluate(() => {
        const items = [];
        for (const a of document.querySelectorAll('a[href]')) {
          const href = a.href || '';
          const text = (a.textContent || a.getAttribute('title') || '').trim();
          if (!href || text.length < 2) continue;
          if (/\/(film|movie|serie|series|tv|watch|voir)\//i.test(href) || /\?p=\d+/.test(href)) {
            items.push({ title: text, url: href });
          }
        }
        return items.slice(0, 30);
      });

      for (const item of results) {
        if (!matchesTitle(item.title, { title, originalTitle, year })) continue;

        try {
          await page.goto(item.url, { waitUntil: 'domcontentloaded', timeout: 20000 });
          await page.waitForTimeout(700);

          if (type === 'tv' && season && episode) {
            const episodeHref = await page.evaluate(
              ({ seasonNumber, episodeNumber }) => {
                const links = [...document.querySelectorAll('a[href]')];
                const hit = links.find((a) => {
                  const t = `${a.textContent || ''} ${a.href}`;
                  return (
                    new RegExp(`saison\\s*${seasonNumber}|season\\s*${seasonNumber}|S${seasonNumber}\\b`, 'i').test(t) &&
                    new RegExp(`episode\\s*${episodeNumber}|E${episodeNumber}\\b|EP?\\.?\\s*${episodeNumber}\\b`, 'i').test(t)
                  );
                });
                return hit?.href || '';
              },
              { seasonNumber: season, episodeNumber: episode },
            );
            if (episodeHref) {
              await page.goto(episodeHref, { waitUntil: 'domcontentloaded', timeout: 20000 });
              await page.waitForTimeout(700);
            }
          }

          const players = await page.evaluate(() => {
            const out = [];
            const push = (url, name) => {
              if (url && /^https?:\/\//i.test(url)) out.push({ url, name: name || '' });
            };
            document.querySelectorAll('iframe[src], iframe[data-src]').forEach((el) => {
              push(el.getAttribute('src') || el.getAttribute('data-src'), el.getAttribute('title') || '');
            });
            document.querySelectorAll('[data-url], [data-src], [data-link]').forEach((el) => {
              push(
                el.getAttribute('data-url') || el.getAttribute('data-src') || el.getAttribute('data-link'),
                el.textContent?.trim() || '',
              );
            });
            for (const script of document.querySelectorAll('script')) {
              const text = script.textContent || '';
              for (const match of text.matchAll(/https?:\/\/[^\"'\s<>]+/g)) {
                const url = match[0];
                if (/embed|player|\/e\/|voe|dood|filemoon|streamwish|uqload|vidzy|netu|mixdrop/i.test(url)) {
                  push(url, '');
                }
              }
            }
            return out;
          });

          for (const player of players) {
            let url = player.url;
            try {
              url = new URL(player.url, item.url).href;
            } catch {
              continue;
            }
            if (seen.has(url)) continue;
            try {
              if (new URL(url).hostname === new URL(baseUrl).hostname) continue;
            } catch {}
            seen.add(url);
            sources.push({
              provider: 'aplouf',
              name: player.name || 'Aplouf',
              url,
            });
          }
        } catch {
          // Next result.
        }

        if (sources.length >= 20) return sources;
      }
    } catch {
      // Next query.
    }
  }

  return sources;
}

/**
 * Aplouf as used by Onyx TmdbProvider:
 * 1) resolve portal mirrors
 * 2) search title on a few mirrors and extract players
 * 3) if nothing found, attach Cloudstream / Movix TMDB-id backups
 */
async function searchAplouf(payload = {}) {
  const { type, title, originalTitle, season, episode } = payload;
  if (!title && !originalTitle && !payload.tmdb_id) return [];
  if (type === 'tv' && (!season || !episode) && !payload.tmdb_id) return [];

  let primarySources = [];

  if (title || originalTitle) {
    const browser = await chromium.launch({
      headless: true,
      args: ['--no-sandbox', '--disable-blink-features=AutomationControlled'],
    });
    const context = await browser.newContext({
      userAgent: USER_AGENT,
      locale: 'fr-FR',
    });
    const page = await context.newPage();

    try {
      const mirrors = await resolvePortalMirrors(page);
      for (const mirror of mirrors.slice(0, 5)) {
        const found = await searchMirror(page, mirror, payload);
        if (found.length) {
          primarySources = found;
          break;
        }
      }
    } finally {
      await context.close();
      await browser.close();
    }
  }

  if (primarySources.length > 0) {
    const seen = new Set();
    return primarySources.filter((s) => {
      if (seen.has(s.url)) return false;
      seen.add(s.url);
      return true;
    });
  }

  // Onyx: when Aplouf mirrors are empty, fall back to Cloudstream / Movix by TMDB id.
  return buildCloudstreamBackupSources(payload);
}

module.exports = {
  searchAplouf,
  buildCloudstreamBackupSources,
};
