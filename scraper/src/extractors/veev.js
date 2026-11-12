const { getText } = require('../lib/http');

const DOMAINS = ['veev.to', 'kinoger.pw', 'poophq.com', 'doods.to'];
const USER_AGENT = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:109.0) Gecko/20100101 Firefox/115.0';

function veevDecode(encoded) {
  if (!encoded) return '';
  const result = [encoded[0]];
  const dictionary = new Map();
  let nextCode = 256;
  let previous = encoded[0];
  for (const char of encoded.slice(1)) {
    const code = char.charCodeAt(0);
    const current = code < 256 ? char : (dictionary.get(code) || previous + previous[0]);
    result.push(current);
    dictionary.set(nextCode++, previous + current[0]);
    previous = current;
  }
  return result.join('');
}

function buildVeevArray(value) {
  const chars = [...String(value)];
  const number = char => /^\d$/.test(char || '') ? Number(char) : 0;
  const result = [];
  let count = number(chars.shift());
  while (count > 0 && chars.length >= count) {
    const group = [];
    for (let i = 0; i < count; i++) group.unshift(number(chars.shift()));
    result.push(group);
    count = number(chars.shift());
  }
  return result;
}

function decodeVeevUrl(encoded, operations) {
  let value = encoded;
  for (const operation of operations || []) {
    if (operation === 1) value = [...value].reverse().join('');
    if (value.length % 2 !== 0 || !/^[0-9a-f]*$/i.test(value)) return '';
    const bytes = Buffer.from(value, 'hex');
    value = bytes.toString('utf8').replace('dXRmOA==', '');
  }
  return value;
}

async function extractVeev(url) {
  let parsed;
  try { parsed = new URL(url); } catch { return null; }
  if (!DOMAINS.includes(parsed.hostname)) return null;
  const mediaId = /\/(?:e|d)\/([a-z0-9]+)/i.exec(parsed.pathname)?.[1];
  if (!mediaId) return null;
  const origin = parsed.origin;
  const referer = origin + '/';
  const pageUrl = new URL('/e/' + mediaId, origin).href;
  const page = await getText(pageUrl, { 'User-Agent': USER_AGENT, Referer: referer, Origin: origin });
  if (!page.ok) return null;
  const items = [...page.text.matchAll(/[.\s'](?:fc|_vvto\[[^\]]*)(?:['\]]*)?\s*[:=]\s*['"]([^'"]+)['"]/g)].map(m => m[1]);
  if (!items.length) return null;
  for (const item of items.reverse()) {
    const channel = veevDecode(item);
    if (!channel || channel === item) continue;
    const download = new URL('/dl', origin);
    download.search = new URLSearchParams({
      op: 'player_api', cmd: 'gi', file_code: mediaId, r: referer, ch: channel, ie: '1',
    });
    const api = await getText(download.href, { 'User-Agent': USER_AGENT, Referer: pageUrl, Origin: origin });
    if (!api.ok) continue;
    let json;
    try { json = JSON.parse(api.text); } catch { continue; }
    const file = json?.file;
    if (file?.file_status !== 'OK') continue;
    const encodedSource = file?.dv?.[0]?.s;
    if (!encodedSource) continue;
    const decoded = veevDecode(encodedSource);
    const operations = buildVeevArray(channel)[0] || [];
    const source = decodeVeevUrl(decoded, operations);
    if (!/^https?:\/\//i.test(source)) continue;
    return { url: source, headers: { 'User-Agent': USER_AGENT, Referer: referer, Origin: referer } };
  }
  return null;
}

module.exports = { extractVeev, veevDecode, buildVeevArray, decodeVeevUrl };