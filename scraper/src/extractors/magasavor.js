const { getText } = require('../lib/http');

function rot13(value) {
  return value.replace(/[a-z]/gi, char => String.fromCharCode(char.charCodeAt(0) + (char.toLowerCase() <= 'm' ? 13 : -13)));
}

function decryptMagaSavor(encoded) {
  try {
    const rotated = rot13(String(encoded || ''));
    const replaced = rotated.replace(/@\$|\^\^|~@|%\?|\*~|!!|#&/g, '').replace(/_/g, '');
    const first = Buffer.from(replaced, 'base64').toString('utf8');
    const shifted = [...first].map(char => String.fromCharCode(char.charCodeAt(0) - 3)).join('');
    const second = Buffer.from([...shifted].reverse().join(''), 'base64').toString('utf8');
    return JSON.parse(second);
  } catch {
    return null;
  }
}

async function extractMagaSavor(url) {
  let parsed;
  try { parsed = new URL(url); } catch { return null; }
  if (parsed.hostname !== 'magasavor.net') return null;
  const response = await getText(url, { Referer: parsed.origin + '/', Accept: 'text/html' });
  if (!response.ok) return null;
  const encoded = /<script\s+type=["']application\/json["']>([\s\S]*?)<\/script>/i.exec(response.text)?.[1]?.trim();
  if (!encoded) return null;
  const json = decryptMagaSavor(encoded);
  const source = json?.source;
  return typeof source === 'string' && source ? { url: source, type: 'm3u8', headers: { Referer: parsed.origin + '/' } } : null;
}
module.exports = { extractMagaSavor, decryptMagaSavor };