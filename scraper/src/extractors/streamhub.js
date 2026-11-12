const { getText } = require('../lib/http');
const { mediaType, normalizeText } = require('../lib/util');
const { unpackPackedJs } = require('../lib/packed');

async function extractStreamhub(url) {
  let parsed;
  try { parsed = new URL(url); } catch { return null; }
  if (parsed.hostname !== 'streamhub.to') return null;
  parsed.pathname = parsed.pathname.replace(/^\/d\//, '/e/');
  const response = await getText(parsed.href, { Referer: 'https://streamhub.to/' });
  if (!response.ok) return null;
  const html = normalizeText(response.text);
  const packedMatch = /eval\s*\(\s*function\s*\(\s*p\s*,\s*a\s*,\s*c\s*,\s*k\s*,\s*e\s*,\s*d\s*\)/i.exec(html);
  if (!packedMatch) return null;
  const unpacked = unpackPackedJs(html.slice(packedMatch.index).split('</script>')[0]);
  if (!unpacked) return null;
  const block = /\{sources\s*:\s*\[([\s\S]*?)\]/i.exec(unpacked)?.[1] || '';
  const source = /\bsrc\s*:\s*['"]([^'"]+)['"]/i.exec(block)?.[1];
  if (!source) return null;
  const subtitles = [...html.matchAll(/<track\b[^>]*label=["']([^"']+)["'][^>]*src=["']([^"']+)["'][^>]*>/gi)]
    .filter(match => match[1] !== 'Upload SRT')
    .map(match => ({ label: match[1], url: new URL(match[2], response.url).href }));
  return { url: new URL(source, response.url).href, type: mediaType(source) || 'm3u8', headers: { Referer: response.url }, subtitles };
}
module.exports = { extractStreamhub };