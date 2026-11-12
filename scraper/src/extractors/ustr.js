const { getText } = require('../lib/http');
const { unpackPackedJs } = require('../lib/packed');

const DOMAINS = ['ups2up.fun', 'up4stream.com', 'up4fun.top'];

async function extractUstr(url) {
  let parsed;
  try { parsed = new URL(url); } catch { return null; }
  if (!DOMAINS.includes(parsed.hostname)) return null;
  const response = await getText(url, { Referer: parsed.origin + '/' });
  if (!response.ok) return null;
  const marker = /eval\s*\(\s*function\s*\(\s*p\s*,\s*a\s*,\s*c\s*,\s*k\s*,\s*e\s*,\s*d\s*\)/i.exec(response.text);
  if (!marker) return null;
  const unpacked = unpackPackedJs(response.text.slice(marker.index).split('</script>')[0]);
  if (!unpacked) return null;
  const block = /sources\s*:\s*\[([\s\S]*?)\]/i.exec(unpacked)?.[1] || '';
  const source = /file\s*:\s*"([^"]+)"/i.exec(block)?.[1];
  return source ? { url: new URL(source, response.url).href, headers: { Referer: response.url } } : null;
}
module.exports = { extractUstr };