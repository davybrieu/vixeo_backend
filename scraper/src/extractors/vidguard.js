const { getText } = require('../lib/http');
const { unpackPackedJs } = require('../lib/packed');

const DOMAINS = ['vidguard.to', 'vembed.net', 'bembed.cc', 'vgfplay.com', 'listeamed.net'];

function decodeVidGuardSignature(encodedUrl) {
  const match = /[?&]sig=([^&]+)/i.exec(encodedUrl);
  if (!match) return null;
  const encodedSig = match[1];
  if (encodedSig.length % 2 !== 0 || !/^[0-9a-f]+$/i.test(encodedSig)) return null;
  const xorText = encodedSig.match(/.{2}/g).map(pair => String.fromCharCode(parseInt(pair, 16) ^ 2)).join('');
  const padding = '='.repeat((4 - xorText.length % 4) % 4);
  let decoded;
  try { decoded = Buffer.from(xorText + padding, 'base64').toString('utf8'); } catch { return null; }
  decoded = decoded.slice(0, -5).split('').reverse();
  for (let i = 0; i + 1 < decoded.length; i += 2) [decoded[i], decoded[i + 1]] = [decoded[i + 1], decoded[i]];
  const finalSig = decoded.join('').slice(0, -5);
  return encodedUrl.replace(encodedSig, finalSig);
}

async function extractVidGuard(url) {
  let parsed;
  try { parsed = new URL(url); } catch { return null; }
  if (!DOMAINS.includes(parsed.hostname)) return null;
  const response = await getText(url, { Referer: parsed.origin + '/' });
  if (!response.ok) return null;
  const marker = 'eval(function(p,a,c,k,e,d)';
  const start = response.text.indexOf(marker);
  if (start < 0) return null;
  const script = marker + response.text.slice(start + marker.length).split('</script>')[0];
  const unpacked = unpackPackedJs(script);
  if (!unpacked) return null;
  const encoded = /window\.svg\s*=\s*\{\s*["']stream["']\s*:\s*["']([^"']+)["']/i.exec(unpacked)?.[1];
  if (!encoded) return null;
  const source = decodeVidGuardSignature(encoded);
  return source && /^https?:\/\//i.test(source) ? { url: source, headers: { Referer: parsed.origin + '/' } } : null;
}
module.exports = { extractVidGuard, decodeVidGuardSignature };