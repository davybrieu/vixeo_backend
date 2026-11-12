const { getText } = require('../lib/http');
const { unpackPackedJs } = require('../lib/packed');

const USER_AGENT = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/121.0.0.0 Safari/537.36';

function decryptFsvidSource(fnCode, encoded, host) {
  try {
    let hostSum = 0;
    for (const char of host) hostSum = (hostSum + char.charCodeAt(0)) & 0xff;
    const extraPx = /calc\(1in\s*\+\s*(\d+)px\)/i.exec(fnCode)?.[1];
    const widthPct = /width:(\d+)%/i.exec(fnCode)?.[1];
    const extra = extraPx ? Number(extraPx) : 56;
    const pct = widthPct ? Number(widthPct) / 100 : 0.5;
    const baseConstant = Math.trunc((96 + extra) * pct);
    const kk = /kk\s*=\s*\(\s*(0x[0-9a-f]+|\d+)\s*\+\s*i\s*\*\s*(\d+)/i.exec(fnCode);
    const baseXor = kk ? (kk[1].startsWith('0x') ? parseInt(kk[1], 16) : Number(kk[1])) : 0x3d;
    const step = kk ? Number(kk[2]) : 89;
    const bytes = Buffer.from(encoded, 'base64').reverse();
    const decoded = Buffer.alloc(bytes.length);
    for (let i = 0; i < bytes.length; i++) decoded[i] = bytes[i] ^ ((baseXor + i * step + hostSum + baseConstant) & 0xff);
    const result = decoded.toString('latin1');
    return result.startsWith('http') ? result : null;
  } catch {
    return null;
  }
}

async function extractFsvid(url) {
  let parsed;
  try { parsed = new URL(url); } catch { return null; }
  if (parsed.hostname !== 'fsvid.lol') return null;
  const response = await getText(url, { 'User-Agent': USER_AGENT, Referer: parsed.origin + '/' });
  if (!response.ok) return null;
  const marker = 'eval(function(p,a,c,k,e,d)';
  const start = response.text.indexOf(marker);
  if (start < 0) return null;
  const script = marker + response.text.slice(start + marker.length).split('</script>')[0];
  const unpacked = unpackPackedJs(script);
  if (!unpacked) return null;
  let source = /src\s*:\s*["'](https?:\/\/[^"']+)["']/i.exec(unpacked)?.[1] || null;
  if (!source) {
    const match = /sources\s*:\s*\[\s*\{\s*src\s*:\s*\((function\(s\)\{[\s\S]*?\})\)\s*\(\s*["']([^"']+)["']/i.exec(unpacked)
      || /\((function\(s\)\{[\s\S]*?\})\)\s*\(\s*["']([A-Za-z0-9+/=]+)["']/i.exec(unpacked);
    if (match) source = decryptFsvidSource(match[1], match[2], parsed.hostname);
  }
  return source ? { url: source, type: 'm3u8', headers: { 'User-Agent': USER_AGENT, Referer: parsed.origin + '/' } } : null;
}
module.exports = { extractFsvid, decryptFsvidSource };