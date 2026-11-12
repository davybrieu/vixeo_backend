const { getText } = require('../lib/http');

const HOST = 'jkanime.net';
const USER_AGENT = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36';

function decodeBase64Url(value) {
  try {
    const decoded = Buffer.from(value, 'base64').toString('utf8').trim();
    return /^https?:\/\//i.test(decoded) ? decoded.replace(/\\\//g, '/') : null;
  } catch {
    return null;
  }
}

function findJkPlayerSource(html) {
  const encoded = /url\s*:\s*atob\(\s*['"]([^'"]+)['"]\s*\)/i.exec(html)?.[1];
  const decoded = encoded ? decodeBase64Url(encoded) : null;
  if (decoded) return decoded;
  const raw = /(?:url\s*:\s*|loadSource\(\s*)['"](https?:\/\/[^'"]+\.m3u8[^'"]*)['"]/i.exec(html)?.[1];
  return raw ? raw.replace(/\\\//g, '/') : null;
}

async function extractJkPlayer(url) {
  let parsed;
  try { parsed = new URL(url); } catch { return null; }
  if (parsed.hostname !== HOST || !parsed.pathname.startsWith('/jkplayer/')) return null;
  const response = await getText(url, {
    'User-Agent': USER_AGENT,
    Referer: 'https://jkanime.net/',
  });
  if (!response.ok) return null;
  const source = findJkPlayerSource(response.text);
  return source ? {
    url: source,
    type: 'm3u8',
    headers: {
      Origin: 'https://jkanime.net',
      Referer: 'https://jkanime.net/',
      'User-Agent': USER_AGENT,
    },
  } : null;
}

module.exports = { extractJkPlayer, decodeBase64Url, findJkPlayerSource };
