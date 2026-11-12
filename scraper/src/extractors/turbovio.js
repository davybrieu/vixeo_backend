const crypto = require('node:crypto');
const { getText } = require('../lib/http');

const MAIN_URL = 'https://turboembed.com';
const USER_AGENT = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36';
const KEY_CHARS = ['𝕊', '𝔼', '𝕮', '𝚁', '𝔼', '𝚃', '𝓚', '𝓔', '𝓨', '𝟙', '𝟚', '𝟛'];
const SALT_CHARS = ['🔐', '🗝️', '🔑', '🚪', '🛡️', '⚡'];

function generateKey(now = Date.now()) {
  let raw = KEY_CHARS.map(char => char.codePointAt(0).toString(16)).join('');
  raw += Math.floor(now / 86400000).toString(16);
  return raw.length >= 32 ? raw.slice(0, 32) : raw.padEnd(32, '0');
}

function generateIV(domainOrUrl = '') {
  let raw = SALT_CHARS.map(char => char.codePointAt(0).toString(16)).join('');
  const host = String(domainOrUrl).replace(/^https?:\/\//, '').split('/')[0];
  if (host) raw += Buffer.from(host, 'utf8').toString('hex');
  return raw.length > 32 ? raw.slice(0, 32) : raw.padEnd(32, '0');
}

function aesHex(cipherText, keyHex, ivHex, decrypt = true) {
  const key = Buffer.from(keyHex, 'hex');
  const iv = Buffer.from(ivHex, 'hex');
  if (decrypt) {
    const decipher = crypto.createDecipheriv('aes-' + (key.length * 8) + '-cbc', key, iv);
    return Buffer.concat([decipher.update(Buffer.from(cipherText, 'hex')), decipher.final()]).toString('utf8');
  }
  const cipher = crypto.createCipheriv('aes-' + (key.length * 8) + '-cbc', key, iv);
  return Buffer.concat([cipher.update(String(cipherText), 'utf8'), cipher.final()]).toString('hex');
}

function encryptTurbovio(value, domainOrUrl = MAIN_URL, now = Date.now()) {
  return aesHex(value, generateKey(now), generateIV(domainOrUrl), false);
}

function decryptTurbovio(value, domainOrUrl = MAIN_URL, now = Date.now()) {
  return aesHex(value, generateKey(now), generateIV(domainOrUrl), true);
}

async function extractTurbovio(url) {
  let parsed;
  try { parsed = new URL(url); } catch { return null; }
  if (parsed.hostname !== 'turboembed.com') return null;
  const id = parsed.pathname.split('/').filter(Boolean).pop();
  if (!id) return null;
  const referer = MAIN_URL + '/' + id;
  let subtitles = [];
  const metadata = await getText(new URL('/api/stream?s=' + encodeURIComponent(id), MAIN_URL).href, {
    'User-Agent': USER_AGENT, Referer: referer, Accept: 'application/json, text/plain, */*',
  });
  if (metadata.ok) {
    try {
      subtitles = (JSON.parse(metadata.text)?.data?.subtitles || [])
        .filter(item => item?.url)
        .map((item, index) => ({ url: item.url, label: item.lang || 'Subtitle ' + (index + 1) }));
    } catch {}
  }
  const encryptedId = encryptTurbovio(id, MAIN_URL);
  const tracking = await getText(new URL('/api/stream/tracking?c=' + encodeURIComponent(encryptedId), MAIN_URL).href, {
    'User-Agent': USER_AGENT, Referer: referer, Accept: 'application/json, text/plain, */*',
  });
  if (!tracking.ok) return null;
  const encryptedTracking = tracking.headers.get('x-tracking');
  if (!encryptedTracking) return null;
  let trackingData;
  try { trackingData = JSON.parse(decryptTurbovio(encryptedTracking, MAIN_URL)); } catch { return null; }
  const stream = trackingData?.stream;
  const source = stream?.tiktok || stream?.hls || stream?.direct;
  return source ? { url: source, type: 'm3u8', subtitles, headers: { 'User-Agent': USER_AGENT, Referer: MAIN_URL + '/' } } : null;
}

module.exports = { extractTurbovio, generateKey, generateIV, encryptTurbovio, decryptTurbovio };