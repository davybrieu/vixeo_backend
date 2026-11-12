const { getText } = require('../lib/http');
const { mediaType, normalizeText } = require('../lib/util');
const { unpackPackedJs } = require('../lib/packed');

const DOMAINS = ['hxfile.co', 'xshotcok.com'];
const USER_AGENT = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/122.0.0.0 Safari/537.36';

function decodeXorPayload(payload, key) {
  try {
    const bytes = Buffer.from(payload, 'base64');
    return [...bytes].map((byte, index) => String.fromCharCode(byte ^ key.charCodeAt(index % key.length))).join('');
  } catch {
    return null;
  }
}

async function extractHxfile(url) {
  let parsed;
  try { parsed = new URL(url); } catch { return null; }
  if (!DOMAINS.some(host => parsed.hostname === host || parsed.hostname.endsWith('.' + host))) return null;

  const fileCode = parsed.pathname.split('/').filter(Boolean).pop()?.replace(/\.html?$/i, '');
  if (!fileCode) return null;
  const embedUrl = /\/embed-/i.test(parsed.pathname) ? url : new URL('/embed-' + fileCode + '.html', parsed.origin).href;
  const response = await getText(embedUrl, { 'User-Agent': USER_AGENT, Referer: url });
  if (!response.ok) return null;

  const html = normalizeText(response.text);
  const packedMatch = /eval\s*\(\s*function\s*\(\s*p\s*,\s*a\s*,\s*c\s*,\s*k\s*,\s*e\s*,\s*d\s*\)/i.exec(html);
  if (!packedMatch) return null;
  let unpacked = unpackPackedJs(html.slice(packedMatch.index).split('</script>')[0]);
  if (!unpacked) return null;

  const xorMatch = /var\s+(_[0-9a-f]{6})\s*=\s*"([^"]+)".*?var\s+(_0x[0-9a-f]{6})\s*=\s*_[0-9a-z]{6}\(\)/is.exec(unpacked);
  if (xorMatch) unpacked = decodeXorPayload(xorMatch[2], xorMatch[3]) || unpacked;

  const source = /sources[\s\S]*?['"]?file['"]?\s*[:=]\s*['"]([^'"]+)['"]/i.exec(unpacked)?.[1];
  if (!source) return null;
  return {
    url: new URL(source, response.url).href,
    type: mediaType(source) || 'm3u8',
    headers: { 'User-Agent': USER_AGENT, Referer: response.url },
  };
}

module.exports = { extractHxfile, decodeXorPayload };