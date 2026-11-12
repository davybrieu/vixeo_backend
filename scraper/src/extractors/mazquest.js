const { extractYandexDisk } = require('./yandexdisk');

async function extractMazQuest(url, fetchImpl = fetch) {
  let parsed;
  try {
    parsed = new URL(url);
  } catch {
    throw new Error('MazQuest: invalid API URL');
  }

  if (parsed.protocol !== 'https:' || parsed.hostname !== 'embed.maz.quest'
    || !parsed.pathname.startsWith('/tv/api/')) {
    throw new Error('MazQuest: unsupported URL');
  }

  const response = await fetchImpl(parsed.href, {
    headers: { Accept: 'application/json' },
    signal: AbortSignal.timeout(15000),
  });

  if (!response.ok) {
    throw new Error(`MazQuest: HTTP ${response.status} for ${parsed.href}`);
  }

  const data = await response.json();
  if (data?.error === true) {
    throw new Error(`MazQuest: API returned error for ${parsed.href}`);
  }

  if (!Array.isArray(data?.links) || data.links.length === 0) {
    throw new Error('MazQuest: no links array or no source for this episode');
  }

  let lastError = null;
  for (const value of data.links) {
    const link = typeof value === 'string' ? value.trim() : '';
    if (!link) continue;

    let upstream;
    try {
      upstream = new URL(link);
    } catch {
      continue;
    }
    if (!['http:', 'https:'].includes(upstream.protocol)) continue;

    if (upstream.pathname.toLowerCase().endsWith('.mp4')) {
      return { url: upstream.href, type: 'mp4' };
    }

    if (['yadi.sk', 'disk.yandex.ru', 'disk.yandex.com'].includes(upstream.hostname.toLowerCase())) {
      try {
        return await extractYandexDisk(upstream.href, fetchImpl);
      } catch (error) {
        lastError = error;
      }
    }
  }

  throw lastError || new Error('MazQuest: no resolvable upstream link');
}

module.exports = { extractMazQuest };
