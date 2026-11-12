// VidMoLy: extracts the HLS source from the player configuration.
const { getText } = require('../lib/http');
const { mediaType, normalizeText } = require('../lib/util');

const USER_AGENT = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36';
const PLAYER_ORIGIN = 'https://vidmoly.to';

async function extractVidmoly(url) {
  let target;
  try {
    const parsed = new URL(url);
    if (parsed.hostname === 'vidmoly.me') parsed.hostname = 'vidmoly.to';
    target = parsed.toString();
  } catch (e) {
    return null;
  }

  const response = await getText(target, {
    Referer: PLAYER_ORIGIN + '/',
    'User-Agent': USER_AGENT,
    Accept: 'text/html',
  });
  if (!response.ok) return null;

  const html = normalizeText(response.text);
  const patterns = [
    /sources\\s*:\\s*\\[\\s*\\{\\s*file\\s*:\\s*['"]([^'"]+)['"]/i,
    /\\bfile\\s*:\\s*['"](https?:\\/\\/[^'"]+\\.m3u8[^'"]*)['"]/i,
  ];
  let source = null;
  for (const pattern of patterns) {
    const match = pattern.exec(html);
    if (match) {
      source = new URL(match[1], PLAYER_ORIGIN).toString();
      break;
    }
  }
  if (!source) return null;

  return {
    url: source,
    type: mediaType(source) || 'm3u8',
    headers: { Referer: PLAYER_ORIGIN + '/', 'User-Agent': USER_AGENT },
  };
}

module.exports = { extractVidmoly };
