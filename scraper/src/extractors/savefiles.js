const { getText } = require('../lib/http');
const { mediaType, normalizeText } = require('../lib/util');

const DOMAINS = ['savefiles.com', 'streamhls.to'];

async function extractSavefiles(url) {
  let parsed;
  try { parsed = new URL(url); } catch { return null; }
  if (!DOMAINS.some(host => parsed.hostname === host || parsed.hostname.endsWith('.' + host))) return null;
  const fileCode = parsed.pathname.split('/').filter(Boolean).pop();
  if (!fileCode) return null;
  const endpoint = new URL('/dl', parsed.origin);
  endpoint.search = new URLSearchParams({ op: 'embed', file_code: fileCode, auto: '0', referer: '' });
  const response = await getText(endpoint.href, { Referer: url });
  if (!response.ok) return null;
  const html = normalizeText(response.text);
  for (const script of html.matchAll(/<script\b[^>]*>([\s\S]*?)<\/script>/gi)) {
    if (!/jwplayer/i.test(script[1]) || !/sources/i.test(script[1])) continue;
    const source = /\bfile\s*:\s*(['"])(https?:\/\/[^'"]+|\/[^'"]+)\1/i.exec(script[1])?.[2];
    if (source) return { url: new URL(source, response.url).href, type: mediaType(source) || 'm3u8', headers: { Referer: response.url } };
  }
  return null;
}
module.exports = { extractSavefiles };