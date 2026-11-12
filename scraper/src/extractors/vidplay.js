const { getText } = require('../lib/http');

const KEY_URL = 'https://raw.githubusercontent.com/Ciarands/vidsrc-keys/main/keys.json';
const DOMAINS = ['vidplay.site', 'mcloud.bz', 'vidplay.online'];

function rc4(key, data) {
  const keyBytes = Buffer.from(String(key), 'utf8');
  const input = Buffer.isBuffer(data) ? data : Buffer.from(String(data), 'utf8');
  if (!keyBytes.length) throw new Error('Empty RC4 key');
  const state = Uint8Array.from({ length: 256 }, (_, i) => i);
  let j = 0;
  for (let i = 0; i < 256; i++) {
    j = (j + state[i] + keyBytes[i % keyBytes.length]) & 0xff;
    [state[i], state[j]] = [state[j], state[i]];
  }
  const output = Buffer.alloc(input.length);
  let i = 0;
  j = 0;
  for (let index = 0; index < input.length; index++) {
    i = (i + 1) & 0xff;
    j = (j + state[i]) & 0xff;
    [state[i], state[j]] = [state[j], state[i]];
    const t = (state[i] + state[j]) & 0xff;
    output[index] = input[index] ^ state[t];
  }
  return output;
}

function encodeVidplayId(key, id) {
  return rc4(key, id).toString('base64').replace(/\//g, '_').replace(/\+/g, '-');
}

function decodeVidplayResult(key, value) {
  const bytes = Buffer.from(String(value).replace(/_/g, '/').replace(/-/g, '+'), 'base64');
  const decoded = rc4(key, bytes).toString('utf8');
  try { return decodeURIComponent(decoded.replace(/\+/g, '%20')); } catch { return decoded; }
}

async function extractVidplay(url) {
  let parsed;
  try { parsed = new URL(url); } catch { return null; }
  if (!DOMAINS.includes(parsed.hostname)) return null;
  const keysResponse = await getText(KEY_URL, { Accept: 'application/json' });
  if (!keysResponse.ok) return null;
  let keys;
  try { keys = JSON.parse(keysResponse.text); } catch { return null; }
  if (!Array.isArray(keys.encrypt) || !Array.isArray(keys.decrypt) || !keys.encrypt[1] || !keys.encrypt[2] || !keys.decrypt[1]) return null;
  const id = parsed.pathname.replace(/\/+$/, '').split('/').pop();
  if (!id) return null;
  const encId = encodeVidplayId(keys.encrypt[1], id);
  const hash = encodeVidplayId(keys.encrypt[2], id);
  const endpoint = new URL('/mediainfo/' + encId, parsed.origin);
  for (const [key, value] of parsed.searchParams) endpoint.searchParams.append(key, value);
  endpoint.searchParams.set('autostart', 'true');
  endpoint.searchParams.set('ads', '0');
  endpoint.searchParams.set('h', hash);
  const response = await getText(endpoint.href, {
    Accept: 'application/json, text/javascript, */*; q=0.01',
    'X-Requested-With': 'XMLHttpRequest',
    Referer: url,
  });
  if (!response.ok) return null;
  let payload;
  try { payload = JSON.parse(response.text); } catch { return null; }
  let result = payload?.result;
  if (typeof result === 'string') {
    try { result = JSON.parse(decodeVidplayResult(keys.decrypt[1], result)); } catch { return null; }
  }
  const source = result?.sources?.find(item => item?.file)?.file;
  if (!source) return null;
  const subtitles = (result.tracks || []).filter(track => track?.kind === 'captions' && track.file)
    .map(track => ({ label: track.label || 'Unknown', url: track.file }));
  return { url: source, subtitles, headers: { Referer: url } };
}

module.exports = { extractVidplay, rc4, encodeVidplayId, decodeVidplayResult };