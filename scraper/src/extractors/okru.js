const { getText } = require('../lib/http');

async function extractOkru(url) {
  let parsed;
  try { parsed = new URL(url); } catch { return null; }
  if (parsed.hostname !== 'ok.ru') return null;
  const response = await getText(url, { Referer: 'https://ok.ru/' });
  if (!response.ok) return null;
  const raw = /<[^>]+data-options=["']([^"']+)["']/i.exec(response.text)?.[1]
    || /data-options\\?["']?\s*[:=]\s*["']([^"']+)/i.exec(response.text)?.[1];
  if (!raw) return null;
  let options;
  try { options = JSON.parse(raw.replace(/&quot;/g, '"').replace(/&amp;/g, '&')); } catch { return null; }
  const videos = options?.flashvars?.metadata?.videos;
  if (!Array.isArray(videos)) return null;
  const video = [...videos].reverse().find(v => typeof v?.url === 'string' && /^https:\/\//i.test(v.url));
  return video ? { url: video.url, headers: { Referer: 'https://ok.ru/', 'User-Agent': 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/115.0.0.0 Safari/537.36' } } : null;
}
module.exports = { extractOkru };