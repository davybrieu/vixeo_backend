const crypto = require('node:crypto');
const { getText } = require('../lib/http');

const MAIN_URL = 'https://player.vidzee.wtf';
const CORE_API = 'https://core.vidzee.wtf';
const STATIC_PASS = '4f2a9c7d1e8b3a6f0d5c2e9a7b1f4d8c';
const USER_AGENT = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36';

function decryptVidzeeMasterKey(encoded) {
  try {
    const data = Buffer.from(encoded, 'base64');
    const iv = data.subarray(0, 12);
    const tag = data.subarray(12, 28);
    const ciphertext = data.subarray(28);
    const key = crypto.createHash('sha256').update(STATIC_PASS).digest();
    const decipher = crypto.createDecipheriv('aes-256-gcm', key, iv);
    decipher.setAuthTag(tag);
    return Buffer.concat([decipher.update(ciphertext), decipher.final()]).toString('utf8');
  } catch {
    return null;
  }
}

function decryptVidzeeLink(encoded, masterKey) {
  try {
    const raw = Buffer.from(encoded, 'base64').toString('utf8');
    const [ivEncoded, ciphertextEncoded] = raw.split(':');
    if (!ivEncoded || !ciphertextEncoded) return null;
    const iv = Buffer.from(ivEncoded, 'base64');
    const ciphertext = Buffer.from(ciphertextEncoded, 'base64');
    const key = Buffer.alloc(32);
    Buffer.from(masterKey, 'utf8').copy(key, 0, 0, 32);
    const decipher = crypto.createDecipheriv('aes-256-cbc', key, iv);
    return Buffer.concat([decipher.update(ciphertext), decipher.final()]).toString('utf8');
  } catch {
    return null;
  }
}

async function extractVidzee(url) {
  let parsed;
  try { parsed = new URL(url); } catch { return null; }
  if (parsed.hostname !== 'player.vidzee.wtf') return null;
  const tmdbId = parsed.searchParams.get('id');
  if (!tmdbId) return null;
  const season = parsed.searchParams.get('ss');
  const episode = parsed.searchParams.get('ep');
  const isEpisode = Boolean(season && episode);
  const servers = isEpisode ? ['ipcloud', 'dcloud', 'tik'] : ['dcloud', 'tik', 'ipcloud'];

  for (const server of servers) {
    const path = isEpisode
      ? '/streams/tv/' + encodeURIComponent(tmdbId) + '/' + encodeURIComponent(season) + '/' + encodeURIComponent(episode)
      : '/streams/movie/' + encodeURIComponent(tmdbId);
    const endpoint = new URL(path, CORE_API);
    endpoint.search = new URLSearchParams({ s: server, e: '0' });
    const response = await getText(endpoint.href, {
      'User-Agent': USER_AGENT, Accept: 'application/json,text/plain,*/*',
      Origin: MAIN_URL, Referer: MAIN_URL + '/',
    });
    if (!response.ok) continue;
    let json;
    try { json = JSON.parse(response.text); } catch { continue; }
    const source = String(json.url || '').trim();
    if (!source) continue;
    const headers = { Referer: MAIN_URL + '/', Origin: MAIN_URL, 'User-Agent': USER_AGENT, ...(json.headers || {}) };
    return { url: source, type: /\.m3u8(?:$|\?)/i.test(source) ? 'm3u8' : 'mp4', headers };
  }

  const keyResponse = await getText(new URL('/api-key', CORE_API).href, {
    'User-Agent': USER_AGENT, Origin: MAIN_URL, Referer: MAIN_URL + '/',
  });
  if (!keyResponse.ok) return null;
  const masterKey = decryptVidzeeMasterKey(keyResponse.text);
  if (!masterKey) return null;
  const legacy = await getText(url, {
    'User-Agent': 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/129.0.0.0 Safari/537.36',
    Origin: MAIN_URL, Referer: MAIN_URL + '/',
  });
  if (!legacy.ok) return null;
  let json;
  try { json = JSON.parse(legacy.text); } catch { return null; }
  const encryptedLink = json?.url?.[0]?.link;
  if (!encryptedLink) return null;
  const source = decryptVidzeeLink(encryptedLink, masterKey);
  if (!source) return null;
  const subtitles = (json.tracks || []).filter(track => track?.url)
    .map(track => ({ label: track.lang || 'Unknown', url: track.url }));
  const isMp4 = parsed.searchParams.get('sr') === '1';
  return { url: source, type: isMp4 ? 'mp4' : 'm3u8', subtitles, headers: { Referer: MAIN_URL, Origin: MAIN_URL, 'User-Agent': USER_AGENT } };
}

module.exports = { extractVidzee, decryptVidzeeMasterKey, decryptVidzeeLink };