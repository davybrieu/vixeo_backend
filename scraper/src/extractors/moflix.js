const { getText } = require('../lib/http');

const DOMAINS = ['moflix-stream.xyz', 'moflix-stream.link'];

async function extractMoflix(url) {
  let parsed;
  try { parsed = new URL(url); } catch { return null; }
  if (!DOMAINS.includes(parsed.hostname)) return null;
  if (/\.m3u8(?:$|\?)/i.test(url)) return { url, type: 'm3u8' };
  if (!parsed.pathname.includes('/playback')) return null;
  const videoId = parsed.pathname.split('/').filter(Boolean).at(-2);
  const referer = parsed.origin + '/watch/' + videoId;
  const response = await getText(url, {
    Referer: referer,
    Accept: 'application/json',
    'User-Agent': 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:109.0) Gecko/20100101 Firefox/115.0',
  });
  if (!response.ok) return null;
  let json;
  try { json = JSON.parse(response.text); } catch { return null; }
  return typeof json.src === 'string' && json.src ? { url: json.src, type: json.type || 'm3u8', headers: { Referer: referer } } : null;
}
module.exports = { extractMoflix };