const { getText } = require('../lib/http');
const { unpackPackedJs } = require('../lib/packed');

const DOMAINS = ['luluvdo.com', 'luluvdoo.com', 'luluvid.com', 'lulust.com'];
const USER_AGENT = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0.0.0 Safari/537.36';

async function extractLuluVdo(url) {
  let parsed;
  try { parsed = new URL(url); } catch { return null; }
  if (!DOMAINS.includes(parsed.hostname)) return null;
  const response = await getText(url, { 'User-Agent': USER_AGENT, 'Accept-Language': 'en-US,en;q=0.9' });
  if (!response.ok) return null;
  const marker = /eval\s*\(\s*function\s*\(\s*p\s*,\s*a\s*,\s*c\s*,\s*k\s*,\s*e\s*,\s*d\s*\)/i.exec(response.text);
  if (!marker) return null;
  const unpacked = unpackPackedJs(response.text.slice(marker.index).split('</script>')[0]);
  if (!unpacked) return null;
  const block = /sources\s*:\s*\[\s*\{([\s\S]*?)\}\s*\]/i.exec(unpacked)?.[1] || '';
  const source = /file\s*:\s*"([^"]+)"/i.exec(block)?.[1];
  if (!source) return null;
  const tracks = /tracks\s*:\s*\[([\s\S]*?)\]/i.exec(unpacked)?.[1] || '';
  const subtitles = [...tracks.matchAll(/file\s*:\s*"([^"]+)"\s*,\s*label\s*:\s*"([^"]+)"/gi)]
    .filter(m => m[2] !== 'Upload captions')
    .map(m => ({ url: m[1], label: m[2] }));
  return { url: new URL(source, response.url).href, subtitles: subtitles.map(s => ({ ...s, url: new URL(s.url, response.url).href })), headers: { 'User-Agent': USER_AGENT, 'Accept-Language': 'en-US,en;q=0.9' } };
}
module.exports = { extractLuluVdo };