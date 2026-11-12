const { getText } = require('../lib/http');

async function extractVideoapp(url) {
  let parsed;
  try { parsed = new URL(url); } catch { return null; }
  if (parsed.hostname !== 'videoapp.zip') return null;
  const response = await getText(url, {
    'User-Agent': 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36',
    'Accept-Language': 'en-US,en;q=0.9',
  });
  if (!response.ok) return null;
  const iframe = /<iframe\b[^>]*\bid=["']frame["'][^>]*\bsrc=["']([^"']+)["']/i.exec(response.text)?.[1]
    || /<iframe\b[^>]*\bsrc=["']([^"']+)["'][^>]*\bid=["']frame["']/i.exec(response.text)?.[1];
  if (!iframe) return null;
  const iframeUrl = new URL(iframe, response.url).href;
  const { extract } = require('./index');
  const result = await extract(iframeUrl);
  return result ? { ...result, headers: { ...(result.headers || {}), Referer: response.url } } : null;
}
module.exports = { extractVideoapp };