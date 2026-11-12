const { getText } = require('../lib/http');
const { mediaType, normalizeText } = require('../lib/util');
const { unpackPackedJs } = require('../lib/packed');

const DOMAINS = ['lamovie.link', 'vimeos.net'];
const USER_AGENT = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36';

async function extractLamovie(url) {
  let parsed;
  try { parsed = new URL(url); } catch { return null; }
  if (!DOMAINS.includes(parsed.hostname)) return null;
  const response = await getText(url, { 'User-Agent': USER_AGENT, 'Accept-Language': 'en-US,en;q=0.9' });
  if (!response.ok) return null;
  const html = normalizeText(response.text);
  const packedMatch = /eval\\s*\\(\\s*function\\s*\\(\\s*p\\s*,\\s*a\\s*,\\s*c\\s*,\\s*k\\s*,\\s*e\\s*,\\s*d\\s*\\)/i.exec(html);
  let source = null;
  let subtitles = [];
  if (packedMatch) {
    const unpacked = unpackPackedJs(html.slice(packedMatch.index).split('</script>')[0]);
    if (unpacked) {
      source = /\\bfile\\s*:\\s*['"]([^'"]+)['"]/i.exec(unpacked)?.[1] || null;
      const tracks = /tracks\\s*:\\s*\\[([\\s\\S]*?)\\]/i.exec(unpacked)?.[1] || '';
      subtitles = [...tracks.matchAll(/file\\s*:\\s*['"]([^'"]+)['"]\\s*,\\s*label\\s*:\\s*['"]([^'"]+)['"]/gi)]
        .filter(m => m[2] !== 'Upload captions')
        .map(m => ({ url: m[1], label: m[2] }));
    }
  } else {
    source = /stream_urls["']?\\s*:\\s*\\[\\s*['"]([^'"]+)['"]/i.exec(html)?.[1] || null;
    const rawSubs = /default_subs["']?\\s*:\\s*(\\[[\\s\\S]*?\\])/i.exec(html)?.[1];
    if (rawSubs) {
      try {
        subtitles = JSON.parse(rawSubs).filter(s => s?.url).map(s => ({ url: s.url, label: s.lang || s.code || '' }));
      } catch {}
    }
  }
  if (!source) return null;
  return {
    url: new URL(source, response.url).href,
    type: mediaType(source) || 'm3u8',
    headers: { 'User-Agent': USER_AGENT, 'Accept-Language': 'en-US,en;q=0.9', 'Sec-Fetch-Dest': 'empty', 'Sec-Fetch-Mode': 'cors' },
    subtitles: subtitles.map(s => ({ ...s, url: new URL(s.url, response.url).href })),
  };
}
module.exports = { extractLamovie };