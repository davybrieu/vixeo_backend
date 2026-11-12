const { withBrowserPage } = require('../lib/browser');

async function extractVidLink(url) {
  let parsed;
  try { parsed = new URL(url); } catch { return null; }
  if (parsed.hostname !== 'vidlink.pro') return null;
  return withBrowserPage(async page => {
    const responsePromise = page.waitForResponse(response => response.url().includes('/api/b/'), { timeout: 30000 }).catch(() => null);
    try { await page.goto(url, { waitUntil: 'domcontentloaded', timeout: 20000 }); } catch {}
    const response = await responsePromise;
    if (!response || !response.ok()) return null;
    let json;
    try { json = await response.json(); } catch { return null; }
    const stream = json?.stream;
    if (!stream?.playlist) return null;
    const subtitles = (stream.captions || []).filter(caption => caption?.id)
      .map(caption => ({ label: caption.language || '', url: caption.id }));
    return { url: stream.playlist, type: 'm3u8', subtitles, headers: { Referer: 'https://vidlink.pro/' } };
  }, { userAgent: 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36' });
}
module.exports = { extractVidLink };