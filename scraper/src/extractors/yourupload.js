const { getText } = require('../lib/http');
const { mediaType, normalizeText } = require('../lib/util');

async function extractYourUpload(url) {
  let parsed;
  try { parsed = new URL(url); } catch { return null; }
  if (!['yourupload.com', 'yucache.net'].some(host => parsed.hostname === host || parsed.hostname.endsWith('.' + host))) return null;
  const userAgent = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/116.0.0.0 Safari/537.36';
  const response = await getText(url, { Referer: 'https://www.yourupload.com/', 'User-Agent': userAgent });
  if (!response.ok) return null;
  const html = normalizeText(response.text);
  const script = [...html.matchAll(/<script\b[^>]*>([\s\S]*?)<\/script>/gi)].map(m => m[1]).find(s => /jwplayerOptions/i.test(s)) || html;
  const source = /\bfile\s*:\s*['"]([^'"]+\.(?:m3u8|mp4)(?:\?[^'"]*)?)['"]/i.exec(script)?.[1];
  if (!source) return null;
  return { url: new URL(source, response.url).href, type: mediaType(source) || 'mp4', headers: { Referer: 'https://www.yourupload.com/', 'User-Agent': userAgent } };
}
module.exports = { extractYourUpload };