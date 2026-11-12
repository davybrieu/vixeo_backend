const { getText } = require('../lib/http');
const { mediaType, normalizeText } = require('../lib/util');

const DOMAINS = ['vtbe.to', 'vtube.to'];
const USER_AGENT = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36';

async function extractVtube(url) {
  let parsed;
  try { parsed = new URL(url); } catch { return null; }
  if (!DOMAINS.includes(parsed.hostname)) return null;
  const response = await getText(url, { 'User-Agent': USER_AGENT, Referer: parsed.origin + '/' });
  if (!response.ok) return null;
  const html = normalizeText(response.text);
  const source = /sources\s*:\s*\[\s*\{\s*file\s*:\s*['"]([^'"]+\.m3u8[^'"]*)/i.exec(html)?.[1]
    || /\bfile\s*:\s*['"]([^'"]+\.m3u8[^'"]*)/i.exec(html)?.[1];
  if (!source) return null;
  return { url: new URL(source, response.url).href, type: mediaType(source) || 'm3u8', headers: { Referer: parsed.origin + '/', Origin: parsed.origin, 'User-Agent': USER_AGENT } };
}
module.exports = { extractVtube };