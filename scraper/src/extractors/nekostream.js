const crypto = require('node:crypto');
const net = require('node:net');
const { getText } = require('../lib/http');

const HOSTS = ['vidtube.site', 'megaplay.buzz', 'vidwish.live'];
const UA = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36';
const sessions = new Map();

function decodeAes(value, { key, iv }) {
  try {
    const raw = value.replace(/-/g, '+').replace(/_/g, '/');
    const data = Buffer.from(raw + '='.repeat((4 - raw.length % 4) % 4), 'base64');
    const decipher = crypto.createDecipheriv('aes-256-cbc', key, iv);
    return Buffer.concat([decipher.update(data), decipher.final()]).toString('utf8').trim();
  } catch { return null; }
}

function decryptSource(value, cryptoData) {
  const plain = decodeAes(value, cryptoData);
  if (!plain) return null;
  try {
    const json = JSON.parse(plain);
    return [json.file, json.url].find(v => typeof v === 'string' && /^https?:\/\//i.test(v)) || null;
  } catch { return null; }
}

function safeUrl(value) {
  try {
    const url = new URL(value);
    const host = url.hostname.toLowerCase();
    const ip = net.isIP(host);
    const privateIp = ip === 4 && (/^(10|127|0)\./.test(host) || /^169\.254\./.test(host) || /^192\.168\./.test(host) || /^172\.(1[6-9]|2\d|3[01])\./.test(host));
    if (!['http:', 'https:'].includes(url.protocol) || privateIp || host === 'localhost' || host.endsWith('.local')) return null;
    return url;
  } catch { return null; }
}

async function findCrypto(html, pageUrl) {
  const scripts = [...html.matchAll(/<script[^>]+src=["']([^"']+)["']/gi)].map(m => m[1]);
  for (const src of scripts) {
    try {
      const scriptUrl = new URL(src, pageUrl).href;
      const response = await getText(scriptUrl, { Referer: pageUrl, Accept: '*/*' });
      if (!response.ok || !/trustAesKey/.test(response.text) || !/trustAesIv/.test(response.text) || !/AES-CBC/.test(response.text)) continue;
      const keySeed = /["']trustAesKey["']\s*,\s*["']TRUST_AES_KEY["']\s*\]\s*,\s*["']([^"']+)["']/.exec(response.text)?.[1];
      const ivSeed = /["']trustAesIv["']\s*,\s*["']TRUST_AES_IV["']\s*\]\s*,\s*["']([^"']+)["']/.exec(response.text)?.[1];
      if (!keySeed || !ivSeed) continue;
      const key = Buffer.alloc(32); Buffer.from(keySeed).copy(key, 0, 0, 32);
      const iv = Buffer.alloc(16); Buffer.from(ivSeed).copy(iv, 0, 0, 16);
      return { key, iv };
    } catch {}
  }
  return null;
}

function playlistUrl(token, url) {
  const route = /\.m3u8(?:$|\?)/i.test(url) ? 'playlist' : 'media';
  return '/nekostream/relay/' + token + '/' + route + '?url=' + encodeURIComponent(url);
}

function decryptSegmentUrl(value, cryptoData) {
  const token = /\\/segment\\/([A-Za-z0-9_-]+)/.exec(value)?.[1];
  if (!token) return value;
  const decoded = decodeAes(token, cryptoData);
  return decoded && /^https?:\\/\\//i.test(decoded) ? decoded : value;
}

function rewritePlaylist(body, base, token, cryptoData) {
  return body.split(/\r?\n/).map(line => {
    if (!line.trim()) return line;
    if (line.trim().startsWith('#')) {
      return line.replace(/URI="([^"]+)"/g, (_m, raw) => 'URI="' + playlistUrl(token, decryptSegmentUrl(new URL(raw, base).href, cryptoData)) + '"');
    }
    return playlistUrl(token, decryptSegmentUrl(new URL(line.trim(), base).href, cryptoData));
  }).join('\n');
}

async function extractNekostream(url) {
  let page;
  try { page = new URL(url); } catch { return null; }
  if (!HOSTS.some(host => page.hostname === host || page.hostname.endsWith('.' + host))) return null;
  const response = await getText(url, { Referer: 'https://anikototv.to/', Origin: page.origin });
  if (!response.ok) return null;
  const html = response.text;
  const id = /id=["']megaplay-player["'][^>]*data-id=["']([^"']+)/i.exec(html)?.[1]
    || /data-id=["']([^"']+)["'][^>]*id=["']megaplay-player["']/i.exec(html)?.[1];
  if (!id) return null;
  const params = new URLSearchParams({ id });
  const type = /type:\s*['"]([^'"]+)/i.exec(html)?.[1];
  const season = page.searchParams.get('s');
  if (type) params.set('type', type);
  if (season) params.set('s', season);
  const responses = [];
  for (const endpoint of ['getSourcesNew', 'getSources']) {
    try {
      const result = await getText(page.origin + '/stream/' + endpoint + '?' + params, {
        Referer: url, Origin: page.origin, Accept: 'application/json, text/javascript, */*; q=0.01', 'X-Requested-With': 'XMLHttpRequest',
      });
      if (result.ok) { try { responses.push(JSON.parse(result.text)); } catch {} }
    } catch {}
  }
  let source = responses.map(r => r?.sources?.file).find(v => typeof v === 'string' && v.trim());
  let cryptoData = null;
  if (!source) {
    cryptoData = await findCrypto(html, url);
    const encrypted = responses.map(r => r?.enc).find(v => typeof v === 'string' && v);
    if (cryptoData && encrypted) source = decryptSource(encrypted, cryptoData);
  }
  if (!source || !safeUrl(source)) return null;
  const tracks = responses.find(r => Array.isArray(r?.tracks))?.tracks || [];
  const subtitles = tracks.filter(t => (!t.kind || t.kind === 'captions') && /^https?:\/\//i.test(t.file || ''))
    .map(t => ({ label: t.label || 'Subtitle', url: t.file, default: t.default === true }));
  const relayNeeded = season?.toLowerCase() === 'tcdn' && cryptoData && /\.m3u8(?:$|\?)/i.test(source);
  if (relayNeeded) {
    if (!process.env.SCRAPER_PUBLIC_URL) throw new Error('SCRAPER_PUBLIC_URL must be configured for Nekostream TCDN relay');
    const token = crypto.randomBytes(24).toString('hex');
    sessions.set(token, { source, origin: page.origin, expires: Date.now() + 7200000 });
    return { url: new URL('/nekostream/relay/' + token + '/manifest.m3u8', process.env.SCRAPER_PUBLIC_URL).href, type: 'm3u8', headers: {}, subtitles };
  }
  return {
    url: source, type: /\.m3u8(?:$|\?)/i.test(source) ? 'm3u8' : undefined,
    headers: { 'User-Agent': UA, Referer: page.origin + '/', Origin: page.origin, Accept: '*/*', 'Accept-Language': 'en-US,en;q=0.9' },
    subtitles,
  };
}

async function handleNekostreamRelay(req, res, pathname, search) {
  const match = /^\/nekostream\/relay\/([a-f0-9]{48})\/(manifest\.m3u8|playlist|media)$/.exec(pathname);
  if (!match) return false;
  const session = sessions.get(match[1]);
  if (!session || session.expires < Date.now()) {
    sessions.delete(match[1]); res.writeHead(404); res.end('Relay session expired'); return true;
  }
  const kind = match[2];
  const target = safeUrl(kind === 'manifest.m3u8' ? session.source : search.get('url'));
  if (!target) { res.writeHead(400); res.end('Invalid upstream URL'); return true; }
  try {
    const upstream = await fetch(target, { headers: { 'User-Agent': UA, Referer: session.origin + '/', Origin: session.origin, ...(kind === 'media' && req.headers.range ? { Range: req.headers.range } : {}) }, signal: AbortSignal.timeout(30000) });
    if (!upstream.ok) { res.writeHead(upstream.status); res.end('Upstream error'); return true; }
    if (kind !== 'media') {
      const body = await upstream.text();
      if (!body.includes('#EXTM3U')) { res.writeHead(502); res.end('Invalid playlist'); return true; }
      const rewritten = rewritePlaylist(body, target.href, match[1], session.crypto);
      res.writeHead(200, { 'Content-Type': 'application/vnd.apple.mpegurl', 'Content-Length': Buffer.byteLength(rewritten), 'Access-Control-Allow-Origin': '*', 'Cache-Control': 'no-cache' });
      res.end(rewritten); return true;
    }
    const strip = ['ibyteimg.com', 'tiktokcdn.com', 'ipstatp.com', 'yoot.akirax.buzz'].some(host => target.hostname === host || target.hostname.endsWith('.' + host));
    const headers = { 'Content-Type': strip ? 'video/mp2t' : (upstream.headers.get('content-type') || 'application/octet-stream'), 'Access-Control-Allow-Origin': '*' };
    if (!strip && upstream.headers.get('content-length')) headers['Content-Length'] = upstream.headers.get('content-length');
    if (!strip && upstream.headers.get('content-range')) headers['Content-Range'] = upstream.headers.get('content-range');
    res.writeHead(strip ? 200 : upstream.status, headers);
    if (strip) res.end(Buffer.from(await upstream.arrayBuffer()).subarray(252));
    else {
      const reader = upstream.body.getReader();
      while (true) { const { done, value } = await reader.read(); if (done) break; if (!res.write(Buffer.from(value))) await new Promise(resolve => res.once('drain', resolve)); }
      res.end();
    }
    return true;
  } catch {
    if (!res.headersSent) { res.writeHead(502); res.end('Relay upstream failed'); } else res.destroy();
    return true;
  }
}

module.exports = { extractNekostream, handleNekostreamRelay, decodeAes, decryptSource, decryptSegmentUrl, rewritePlaylist, safeUrl };
