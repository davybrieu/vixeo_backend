const { getText } = require('../lib/http');

const USER_AGENT = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0.0.0 Safari/537.36';

async function extractMaxstream(url) {
  let parsed;
  try { parsed = new URL(url); } catch { return null; }
  if (parsed.hostname !== 'maxstream.video') return null;
  const headers = { 'User-Agent': USER_AGENT, Referer: parsed.origin + '/' };
  const response = await getText(url, headers);
  if (!response.ok) return null;
  if (/File is not longer available as it expired or has been deleted/i.test(response.text)) return null;
  let source = /sources\s*:\s*\[\s*\{\s*src\s*:\s*["']([^"']+)["']/i.exec(response.text)?.[1];
  const videoId = parsed.pathname.replace(/\/+$/, '').split('/').pop();
  if (!source && videoId) {
    const castv = await getText(new URL('/castv/' + encodeURIComponent(videoId), parsed.origin).href, headers);
    if (castv.ok) {
      const script = /<script\b[^>]*id=["']m3u8["'][^>]*>([\s\S]*?)<\/script>/i.exec(castv.text)?.[1]?.trim();
      if (script?.startsWith('http')) source = script.split('#')[0].trim();
    }
  }
  return source ? { url: new URL(source, response.url).href } : null;
}
module.exports = { extractMaxstream };