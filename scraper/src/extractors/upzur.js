const { getText } = require('../lib/http');
const { normalizeText } = require('../lib/util');

function decodeEscapes(value) {
  return value.replace(/\\x([0-9a-f]{2})/gi, (_, h) => String.fromCharCode(parseInt(h, 16)))
    .replace(/\\u([0-9a-f]{4})/gi, (_, h) => String.fromCharCode(parseInt(h, 16)));
}

async function extractUpZur(url) {
  let parsed;
  try { parsed = new URL(url); } catch { return null; }
  if (parsed.hostname !== 'upzur.com') return null;
  const response = await getText(url, { Referer: parsed.origin + '/' });
  if (!response.ok) return null;
  const html = normalizeText(response.text);
  const array = /var\s+uHo4sc\s*=\s*\[([\s\S]*?)\]/i.exec(html)?.[1];
  if (!array) return null;
  const parts = [...array.matchAll(/(["'])([\s\S]*?)\1/g)].map(m => m[2]);
  if (!parts.length) return null;
  const decoded = decodeEscapes(parts.reverse().join(''));
  const source = /\bsrc\s*=\s*(['"])(https?:\/\/[^'"]+|\/[^'"]+)\1/i.exec(decoded)?.[2];
  if (!source) return null;
  return { url: new URL(source, response.url).href, headers: { Referer: response.url } };
}
module.exports = { extractUpZur, decodeEscapes };