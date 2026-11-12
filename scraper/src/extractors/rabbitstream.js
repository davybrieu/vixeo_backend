const crypto = require('node:crypto');
const { getText } = require('../lib/http');

const RABBIT_DOMAINS = ['rabbitstream.net', 'dokicloud.one', 'premiumembeding.cloud'];
const MEGACLOUD_DOMAINS = ['megacloud.blog', 'videostr.net'];

function md5(value) {
  return crypto.createHash('md5').update(value).digest();
}

function generateRabbitstreamKey(salt, secret) {
  let output = md5(Buffer.concat([secret, salt]));
  let current = output;
  while (current.length < 48) {
    output = md5(Buffer.concat([output, secret, salt]));
    current = Buffer.concat([current, output]);
  }
  return current.subarray(0, 48);
}

function decryptRabbitstreamSource(encoded, secret) {
  try {
    const cipherData = Buffer.from(encoded, 'base64');
    if (cipherData.length <= 16) return null;
    const salt = cipherData.subarray(8, 16);
    const keyIv = generateRabbitstreamKey(salt, Buffer.from(secret, 'utf8'));
    const decipher = crypto.createDecipheriv('aes-256-cbc', keyIv.subarray(0, 32), keyIv.subarray(32, 48));
    const decrypted = Buffer.concat([decipher.update(cipherData.subarray(16)), decipher.final()]).toString('utf8');
    return JSON.parse(decrypted);
  } catch {
    return null;
  }
}

function getRabbitstreamToken(html) {
  const object = /\w+\s*=\s*\{[^}]*?(\w+):\s*"([^"]+)",\s*(\w+):\s*"([^"]+)"(?:,\s*(\w+):\s*"([^"]+)")?/i.exec(html);
  if (object) return object[2] + object[4] + (object[6] || '');
  const sequence = /"([A-Za-z0-9+/=]{10,})",\s*"([A-Za-z0-9+/=]{10,})"(?:,\s*"([A-Za-z0-9+/=]{10,})")?/i.exec(html);
  if (sequence) return sequence[1] + sequence[2] + (sequence[3] || '');
  return [...html.matchAll(/[A-Za-z0-9+/=]{30,}/g)].map(m => m[0]).sort((a, b) => b.length - a.length)[0] || null;
}

function extractMegacloudKeyIndexes(script) {
  const matchingKey = name => {
    const value = new RegExp(',' + name + '=((?:0x)?([0-9a-fA-F]+))').exec(script)?.[1];
    return value ? parseInt(value.replace(/^0x/i, ''), 16) : null;
  };
  return [...script.matchAll(/case\s*0x[0-9a-f]+:(?![^;]*=partKey)\s*\w+\s*=\s*(\w+)\s*,\s*\w+\s*=\s*(\w+);/g)]
    .map(match => [matchingKey(match[1]), matchingKey(match[2])])
    .filter(pair => pair.every(Number.isFinite));
}

function extractMegacloudRealKey(encryptedSources, indexes) {
  const chars = [...encryptedSources];
  let extracted = '';
  let currentIndex = 0;
  for (const [startOffset, length] of indexes) {
    const start = startOffset + currentIndex;
    const end = start + length;
    for (let i = start; i < end && i < chars.length; i++) {
      extracted += chars[i];
      chars[i] = ' ';
    }
    currentIndex += length;
  }
  return { key: extracted, sources: chars.join('').replace(/ /g, '') };
}

async function fetchRabbitstreamKeys() {
  const response = await getText('https://keys4.fun', { Accept: 'application/json' });
  if (!response.ok) return null;
  try { return JSON.parse(response.text)?.rabbitstream?.keys?.key || null; } catch { return null; }
}

async function extractRabbitstream(url) {
  let parsed;
  try { parsed = new URL(url); } catch { return null; }
  const host = parsed.hostname;
  if (!RABBIT_DOMAINS.includes(host) && !MEGACLOUD_DOMAINS.includes(host)) return null;
  const origin = parsed.origin;
  const sourceId = parsed.pathname.split('/').filter(Boolean).pop();
  if (!sourceId) return null;
  const isMega = MEGACLOUD_DOMAINS.includes(host);
  const referer = origin + '/';
  let sourcesUrl;
  let token = null;

  if (isMega) {
    const embedPath = '/' + parsed.pathname.split('/').filter(Boolean).slice(0, -1).join('/');
    const embed = await getText(url, { Referer: referer });
    if (!embed.ok) return null;
    token = getRabbitstreamToken(embed.text);
    sourcesUrl = new URL(embedPath.replace(/\/$/, '') + '/getSources', origin);
  } else {
    sourcesUrl = new URL('/api/v2/embed-4/getSources', origin);
  }
  sourcesUrl.searchParams.set('id', sourceId);
  if (token) sourcesUrl.searchParams.set('_k', token);
  const response = await getText(sourcesUrl.href, {
    Referer: url, 'X-Requested-With': 'XMLHttpRequest',
    Accept: '*/*', 'Accept-Language': 'en-US,en;q=0.5',
  });
  if (!response.ok) return null;
  let payload;
  try { payload = JSON.parse(response.text); } catch { return null; }
  let sources = payload.sources;
  if (!Array.isArray(sources)) {
    if (typeof sources !== 'string') return null;
    if (isMega) {
      const scriptUrl = new URL('/js/player/a/prod/e1-player.min.js', origin);
      scriptUrl.searchParams.set('v', String(Math.floor(Date.now() / 1000)));
      const scriptResponse = await getText(scriptUrl.href, { Referer: origin + '/' });
      if (!scriptResponse.ok) return null;
      const indexes = extractMegacloudKeyIndexes(scriptResponse.text);
      if (!indexes.length) return null;
      const extracted = extractMegacloudRealKey(sources, indexes);
      const decoded = decryptRabbitstreamSource(extracted.sources, extracted.key);
      sources = decoded;
    } else {
      const key = await fetchRabbitstreamKeys();
      if (!key) return null;
      sources = decryptRabbitstreamSource(sources, key);
    }
  }
  if (!Array.isArray(sources) || !sources.length) return null;
  const source = sources.find(item => item?.file)?.file;
  if (!source) return null;
  const subtitles = (payload.tracks || []).filter(track => track?.kind === 'captions' && track.file)
    .map(track => ({ label: track.label || '', url: track.file }));
  return { url: source, subtitles, headers: { Referer: origin + '/' } };
}

module.exports = {
  extractRabbitstream,
  decryptRabbitstreamSource,
  generateRabbitstreamKey,
  getRabbitstreamToken,
  extractMegacloudKeyIndexes,
  extractMegacloudRealKey,
};