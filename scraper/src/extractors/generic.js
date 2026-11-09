// Last-resort extractor: fetch the page, unpack it if needed, follow a couple
// of JS/meta redirects, then look for an m3u8/mp4 source.
const { getText } = require('../lib/http');
const { findMediaUrl, unpackIfNeeded } = require('../lib/player');
const { originOf, absoluteUrl, mediaType } = require('../lib/util');

const USER_AGENT =
  'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36';

const REDIRECT_PATTERNS = [
  /window\.location(?:\.href)?\.replace\(\s*['"]([^'"]+)['"]\s*\)/i,
  /window\.location(?:\.href)?\s*=\s*['"]([^'"]+)['"]/i,
  /location\.replace\(\s*['"]([^'"]+)['"]\s*\)/i,
  /<meta[^>]+http-equiv=["']refresh["'][^>]+content=["'][^"']*url=([^"'>]+)["']/i,
];

function findRedirect(html, baseUrl) {
  for (const pattern of REDIRECT_PATTERNS) {
    const m = pattern.exec(html);
    if (m && m[1] && /^https?:/i.test(m[1])) return absoluteUrl(baseUrl, m[1]);
  }
  return null;
}

async function extractGeneric(url, depth = 0) {
  if (depth > 2) return null;
  try {
    const res = await getText(url, { 'User-Agent': USER_AGENT, Referer: url });
    const origin = originOf(url);
    const html = unpackIfNeeded(res.text);

    const media = findMediaUrl(html, url);
    if (media) {
      return {
        url: media,
        type: mediaType(media) || 'm3u8',
        headers: { Referer: `${origin}/`, Origin: origin, 'User-Agent': USER_AGENT },
      };
    }

    const redirect = findRedirect(html, url);
    if (redirect && redirect !== url) return extractGeneric(redirect, depth + 1);
  } catch (e) {
    return null;
  }
  return null;
}

module.exports = { extractGeneric };
