const crypto = require('node:crypto');
const { getText } = require('../lib/http');

const MAIN_URL = 'https://vidrock.net';
const PASSPHRASE = 'x7k9mPqT2rWvY8zA5bC3nF6hJ2lK4mN9';

function encryptVidrockId(value) {
  const key = Buffer.from(PASSPHRASE, 'utf8');
  const iv = key.subarray(0, 16);
  const cipher = crypto.createCipheriv('aes-256-cbc', key, iv);
  return Buffer.concat([cipher.update(String(value), 'utf8'), cipher.final()]).toString('base64')
    .replace(/\+/g, '-').replace(/\//g, '_').replace(/=+$/g, '');
}

async function extractVidrock(url) {
  let parsed;
  try { parsed = new URL(url); } catch { return null; }
  if (parsed.hostname !== 'vidrock.net') return null;
  const apiUrl = new URL(parsed.pathname + parsed.search, MAIN_URL);
  const serverName = decodeURIComponent(parsed.hash.slice(1));
  const response = await getText(apiUrl.href, { Referer: MAIN_URL + '/', Origin: MAIN_URL, Accept: 'application/json' });
  if (!response.ok) return null;
  let json;
  try { json = JSON.parse(response.text); } catch { return null; }
  const entry = serverName
    ? Object.entries(json).find(([name]) => name.toLowerCase() === serverName.toLowerCase())
    : Object.entries(json).find(([, value]) => value?.url);
  if (!entry?.[1]?.url) return null;
  let source = entry[1].url;
  let type = 'm3u8';
  if (entry[0].toLowerCase() === 'atlas') {
    const qualities = await getText(source, { Referer: MAIN_URL + '/', Origin: MAIN_URL });
    if (qualities.ok) {
      try {
        const list = JSON.parse(qualities.text);
        const highest = list.filter(item => item?.url).sort((a, b) => Number(b.resolution || 0) - Number(a.resolution || 0))[0];
        if (highest) { source = highest.url; type = 'mp4'; }
      } catch {}
    }
  }
  return { url: source, type, headers: { Referer: MAIN_URL + '/', Origin: MAIN_URL } };
}
module.exports = { extractVidrock, encryptVidrockId };