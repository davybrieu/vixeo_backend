const { getText } = require('../lib/http');
const { mediaType, normalizeText } = require('../lib/util');

const DOMAINS = ['oneupload.net', 'tipfly.xyz'];

async function extractOneupload(url) {
  let parsed;
  try { parsed = new URL(url); } catch { return null; }
  if (!DOMAINS.some(host => parsed.hostname === host || parsed.hostname.endsWith('.' + host))) return null;
  const response = await getText(url, { Referer: parsed.origin + '/' });
  if (!response.ok) return null;
  const html = normalizeText(response.text);
  for (const script of html.matchAll(/<script\b[^>]*>([\s\S]*?)<\/script>/gi)) {
    if (!/jwplayer/i.test(script[1]) || !/sources/i.test(script[1])) continue;
    const source = /\bfile\s*:\s*(['"])(https?:\/\/[^'"]+|\/[^'"]+)\1/i.exec(script[1])?.[2];
    if (!source) continue;
    return { url: new URL(source, response.url).href, type: mediaType(source) || 'm3u8', headers: { Referer: parsed.origin + '/' } };
  }
  return null;
}
module.exports = { extractOneupload };