const crypto = require('node:crypto');
const { getText } = require('../lib/http');

const KEY_URL = 'https://raw.githubusercontent.com/Rowdy-Avocado/multi-keys/keys/index.html';
const DOMAINS = ['chillx.top', 'player.jeansaispasplus.homes'];

function evpBytesToKey(password, salt, keyLength = 32, ivLength = 16) {
  const required = keyLength + ivLength;
  let material = Buffer.alloc(0);
  let previous = Buffer.alloc(0);
  while (material.length < required) {
    previous = crypto.createHash('md5').update(Buffer.concat([previous, password, salt])).digest();
    material = Buffer.concat([material, previous]);
  }
  return { key: material.subarray(0, keyLength), iv: material.subarray(keyLength, required) };
}

function decryptChillxPayload(raw, password) {
  let data;
  try { data = JSON.parse(raw); } catch { return null; }
  if (!data?.ct || !data?.iv || !data?.s) return null;
  try {
    const salt = Buffer.from(data.s, 'hex');
    const ivLength = data.iv.length / 2;
    const { key, iv } = evpBytesToKey(Buffer.from(password, 'utf8'), salt, 32, ivLength);
    const decipher = crypto.createDecipheriv('aes-256-cbc', key, iv);
    return Buffer.concat([decipher.update(Buffer.from(data.ct, 'base64')), decipher.final()]).toString('utf8');
  } catch {
    return null;
  }
}

async function extractChillx(url) {
  let parsed;
  try { parsed = new URL(url); } catch { return null; }
  if (!DOMAINS.includes(parsed.hostname)) return null;
  const page = await getText(url, { Referer: parsed.origin + '/' });
  if (!page.ok) return null;
  const encrypted = /\s*=\s*['"]([^'"]+)['"]/.exec(page.text)?.[1];
  if (!encrypted) return null;
  const keysResponse = await getText(KEY_URL, { Accept: 'application/json' });
  if (!keysResponse.ok) return null;
  let keys;
  try { keys = JSON.parse(keysResponse.text); } catch { return null; }
  const password = keys?.chillx?.[0];
  if (!password) return null;
  const decrypted = decryptChillxPayload(encrypted, password);
  if (!decrypted) return null;
  let data;
  try { data = JSON.parse(decrypted); } catch { return null; }
  const source = data.file || data.sources?.[0]?.file;
  if (!source) return null;
  const subtitles = (data.tracks || []).filter(track => track?.kind === 'captions' && track.file)
    .map(track => ({ label: track.label || 'Unknown', url: track.file }));
  return { url: source, subtitles, headers: { Referer: parsed.origin + '/' } };
}

module.exports = { extractChillx, evpBytesToKey, decryptChillxPayload };