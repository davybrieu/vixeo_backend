// Goodstream: the player exposes a JWPlayer source in an inline script.
const { getText } = require('../lib/http');
const { originOf, mediaType, normalizeText } = require('../lib/util');

const USER_AGENT = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36';

async function extractGoodstream(url) {
  const origin = originOf(url);
  if (!origin) return null;

  const response = await getText(url, {
    Referer: origin + '/',
    'User-Agent': USER_AGENT,
    'Accept-Language': 'en-US,en;q=0.9',
  });
  if (!response.ok) return null;

  const html = normalizeText(response.text);
  const scripts = [...html.matchAll(/<script\\b[^>]*>([\\s\\S]*?)<\\/script>/gi)].map((m) => m[1]);
  let source = null;

  for (const script of scripts) {
    if (!/jwplayer/i.test(script) || !/sources/i.test(script)) continue;
    const match = /\\bfile\\s*:\\s*(['"])(https?:\\/\\/[^'"]+|\\/[^'"]+)\\1/i.exec(script);
    if (match) {
      source = new URL(match[2], origin).toString();
      break;
    }
  }

  if (!source) return null;
  return {
    url: source,
    type: mediaType(source) || 'm3u8',
    headers: {
      'User-Agent': USER_AGENT,
      'Accept-Language': 'en-US,en;q=0.9',
      Referer: origin + '/',
    },
  };
}

module.exports = { extractGoodstream };
