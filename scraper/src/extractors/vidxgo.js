const { getText } = require('../lib/http');

const MAIN_URL = 'https://v.vidxgo.co';
const USER_AGENT = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36';

function decodeVidxGoPayload(encoded, key) {
  try {
    const bytes = Buffer.from(encoded, 'base64');
    const keyBytes = Buffer.from(key, 'utf8');
    const output = Buffer.alloc(bytes.length);
    for (let i = 0; i < bytes.length; i++) output[i] = bytes[i] ^ keyBytes[i % keyBytes.length];
    return output.toString('utf8');
  } catch {
    return null;
  }
}

async function extractVidxGo(url) {
  let parsed;
  try { parsed = new URL(url); } catch { return null; }
  if (parsed.hostname !== 'v.vidxgo.co') return null;
  const referer = parsed.origin + '/';
  const response = await getText(url, {
    Referer: referer,
    'User-Agent': USER_AGENT,
    ...(parsed.pathname.includes('/t/') ? {} : { 'sec-fetch-dest': 'iframe' }),
  });
  if (!response.ok) return null;
  if (parsed.pathname.includes('/t/')) {
    let json;
    try { json = JSON.parse(response.text); } catch { return null; }
    const source = String(json.url || '').replace(/\\\//g, '/');
    return source ? { url: source, type: 'm3u8', headers: { Origin: MAIN_URL, Referer: referer, 'Sec-Fetch-Dest': 'empty', 'Sec-Fetch-Site': 'cross-site', 'User-Agent': USER_AGENT } } : null;
  }
  for (const match of response.text.matchAll(/<script\b[^>]*>[\s\S]*?\(function\(\)\s*\{[\s\S]*?\}\s*\)\(\);[\s\S]*?<\/script>/gi)) {
    const script = match[0];
    const key = /var\s+k\s*=\s*['"]([^'"]+)['"]/i.exec(script)?.[1];
    const encoded = /atob\(['"]([^'"]+)['"]\)/i.exec(script)?.[1];
    if (!key || !encoded) continue;
    const decoded = decodeVidxGoPayload(encoded, key);
    const source = /currentSrc\s*=\s*['"]([^'"]+)['"]/i.exec(decoded || '')?.[1];
    if (!source) continue;
    return { url: source.replace(/\\\//g, '/'), type: 'm3u8', headers: { Origin: MAIN_URL, Referer: referer, 'Sec-Fetch-Dest': 'empty', 'Sec-Fetch-Site': 'cross-site', 'User-Agent': USER_AGENT } };
  }
  return null;
}
module.exports = { extractVidxGo, decodeVidxGoPayload };