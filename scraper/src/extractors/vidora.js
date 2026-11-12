const { getText } = require('../lib/http');
const { unpackPackedJs } = require('../lib/packed');

async function extractVidora(url) {
  let parsed;
  try { parsed = new URL(url); } catch { return null; }
  if (parsed.hostname !== 'vidora.stream') return null;
  const response = await getText(url, { Referer: 'https://vidora.stream/', 'User-Agent': 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/129.0.0.0 Safari/537.36' });
  if (!response.ok) return null;
  const marker = /eval\s*\(\s*function\s*\(\s*p\s*,\s*a\s*,\s*c\s*,\s*k\s*,\s*e\s*,\s*d\s*\)/i.exec(response.text);
  if (!marker) return null;
  const unpacked = unpackPackedJs(response.text.slice(marker.index).split('</script>')[0]);
  if (!unpacked || !/jwplayer/i.test(unpacked) || !/sources/i.test(unpacked) || !/file/i.test(unpacked)) return null;
  const source = /\bfile\s*:\s*["']([^"']+)["']/i.exec(unpacked)?.[1];
  return source ? { url: source, headers: { Referer: 'https://vidora.stream/', 'User-Agent': 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/129.0.0.0 Safari/537.36' } } : null;
}
module.exports = { extractVidora };