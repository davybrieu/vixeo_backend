const { getText } = require('../lib/http');
const { mediaType, normalizeText } = require('../lib/util');
const { unpackPackedJs } = require('../lib/packed');

const USER_AGENT = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0.0.0 Safari/537.36';

async function extractFastream(url) {
  let parsed;
  try { parsed = new URL(url); } catch { return null; }
  if (!['fastream.to'].some(host => parsed.hostname === host || parsed.hostname.endsWith('.' + host))) return null;

  const response = await getText(url, { 'User-Agent': USER_AGENT });
  if (!response.ok) return null;
  const html = normalizeText(response.text);
  const packed = /eval\s*\(\s*function\s*\(\s*p\s*,\s*a\s*,\s*c\s*,\s*k\s*,\s*e\s*,\s*d\s*\)/i.exec(html);
  if (!packed) return null;
  const unpacked = unpackPackedJs(html.slice(packed.index).split('</script>')[0]);
  if (!unpacked || !/jwplayer/i.test(unpacked) || !/sources/i.test(unpacked)) return null;

  const source = /sources\s*:\s*\[\s*\{\s*file\s*:\s*['"]([^'"]+)['"]/i.exec(unpacked)?.[1];
  if (!source) return null;
  return {
    url: new URL(source, response.url).href,
    type: mediaType(source) || 'm3u8',
    headers: {
      'User-Agent': USER_AGENT,
      'Sec-Fetch-Dest': 'empty',
      Accept: '*/*',
      'Accept-Language': '',
    },
  };
}

module.exports = { extractFastream };