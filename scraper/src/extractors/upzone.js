const { getText } = require('../lib/http');
const { unpackPackedJs } = require('../lib/packed');
const { withBrowserPage } = require('../lib/browser');
const { normalizeText } = require('../lib/util');

const DOMAINS = ['upzone.cc', 'upzone.to', 'upzone.net', 'upzone.link'];
const USER_AGENT = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36';

async function resolveUpzoneUrl(url) {
  return withBrowserPage(async page => {
    try {
      await page.goto(url, { waitUntil: 'domcontentloaded', timeout: 20000 });
      return page.url() || url;
    } catch {
      return url;
    }
  }, { userAgent: USER_AGENT });
}

function findUpzoneSource(html, baseUrl) {
  const normalized = normalizeText(html);
  const directPatterns = [
    /["']file["']\s*[:=]\s*["']((?:https?:\/\/|\/)[^"']+\.m3u8[^"']*)["']/i,
    /["']src["']\s*[:=]\s*["']((?:https?:\/\/|\/)[^"']+\.m3u8[^"']*)["']/i,
    /["']hls\d*["']\s*[:=]\s*["']((?:https?:\/\/|\/)[^"']+\.m3u8[^"']*)["']/i,
    /source\s*=\s*["']((?:https?:\/\/|\/)[^"']+\.m3u8[^"']*)["']/i,
    /https?:\/\/[^"'\\\s<]+\.m3u8[^"'\\\s<]*/i,
  ];
  for (const pattern of directPatterns) {
    const value = pattern.exec(normalized)?.[1] || pattern.exec(normalized)?.[0];
    if (value) return new URL(value, baseUrl).href;
  }
  const packed = /eval\s*\(\s*function\s*\(\s*p\s*,\s*a\s*,\s*c\s*,\s*k\s*,\s*e\s*,\s*d\s*\)/i.exec(normalized);
  if (packed) {
    const unpacked = unpackPackedJs(normalized.slice(packed.index).split('</script>')[0]);
    if (unpacked) {
      const value = /["']file["']\s*:\s*["']([^"']+)["']/i.exec(unpacked)?.[1]
        || /\bsrc\s*:\s*["']([^"']+)["']/i.exec(unpacked)?.[1];
      if (value) return new URL(value, baseUrl).href;
    }
  }
  return null;
}

async function extractUpzone(url) {
  let parsed;
  try { parsed = new URL(url); } catch { return null; }
  if (!DOMAINS.includes(parsed.hostname)) return null;
  const referer = parsed.searchParams.get('reff') || parsed.origin + '/';
  const pageUrl = await resolveUpzoneUrl(url);
  const response = await getText(pageUrl, { Referer: referer, 'User-Agent': USER_AGENT });
  if (!response.ok) return null;
  const source = findUpzoneSource(response.text, response.url);
  if (source) return { url: source, type: 'm3u8', headers: { Referer: new URL(response.url).origin + '/', Origin: new URL(response.url).origin, 'User-Agent': USER_AGENT, Accept: '*/*' } };
  const iframe = /<iframe\b[^>]*src=["']([^"']+)["']/i.exec(response.text)?.[1]
    || /<a\b[^>]*href=["']([^"']+)["'][^>]*>/i.exec(response.text)?.[1];
  if (!iframe) return null;
  const target = new URL(iframe, response.url).href;
  const { extract } = require('./index');
  const result = await extract(target);
  return result ? { ...result, headers: { ...(result.headers || {}), Referer: response.url } } : null;
}

module.exports = { extractUpzone, resolveUpzoneUrl, findUpzoneSource };