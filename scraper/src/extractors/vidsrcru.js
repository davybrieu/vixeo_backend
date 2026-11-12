const { withBrowserPage } = require('../lib/browser');

async function extractVidsrcRu(url) {
  let parsed;
  try { parsed = new URL(url); } catch { return null; }
  if (parsed.hostname !== 'vidsrc.ru') return null;
  return withBrowserPage(async page => {
    const requestPromise = page.waitForRequest(request => {
      const target = request.url();
      return target.includes('/file2/') && /\.m3u8(?:$|[?#])/i.test(target);
    }, { timeout: 30000 }).catch(() => null);
    try { await page.goto(url, { waitUntil: 'domcontentloaded', timeout: 20000 }); } catch {}
    const request = await requestPromise;
    if (!request) return null;
    return { url: request.url(), type: 'm3u8', headers: { Referer: parsed.origin + '/' } };
  });
}
module.exports = { extractVidsrcRu };