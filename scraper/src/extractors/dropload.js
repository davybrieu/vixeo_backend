const { getText } = require('../lib/http');
const { mediaType, normalizeText } = require('../lib/util');
const { unpackPackedJs } = require('../lib/packed');

const DOMAINS = ['dropload.tv', 'dropload.io', 'dropload.pro', 'dr0pstream.com'];

async function extractDropload(url) {
  let parsed;
  try { parsed = new URL(url); } catch { return null; }
  if (!DOMAINS.some(host => parsed.hostname === host || parsed.hostname.endsWith('.' + host))) return null;

  const response = await getText(url);
  if (!response.ok) return null;
  const html = normalizeText(response.text);
  const packed = /eval\s*\(\s*function\s*\(\s*p\s*,\s*a\s*,\s*c\s*,\s*k\s*,\s*e\s*,\s*d\s*\)/i.exec(html);
  if (!packed) return null;
  const script = html.slice(packed.index).split('</script>')[0];
  const unpacked = unpackPackedJs(script);
  if (!unpacked) return null;

  const source = /file\s*:\s*['"]([^'"]+)['"]/i.exec(unpacked)?.[1];
  if (!source) return null;
  const tracks = /tracks\s*:\s*\[([\s\S]*?)\]/i.exec(unpacked)?.[1] || '';
  const subtitles = [...tracks.matchAll(/file\s*:\s*['"]([^'"]+)['"]\s*,\s*label\s*:\s*['"]([^'"]+)['"]\s*,\s*kind\s*:\s*['"]captions['"]/gi)]
    .map(match => ({ url: match[1], label: match[2] }));

  return {
    url: new URL(source, response.url).href,
    type: mediaType(source) || 'm3u8',
    headers: { Referer: new URL(response.url).origin },
    subtitles,
  };
}

module.exports = { extractDropload };