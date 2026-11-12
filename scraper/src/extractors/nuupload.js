const { getText, request } = require('../lib/http');
const { unpackPackedJs } = require('../lib/packed');

const DOMAINS = ['nupload.top', 'nupupload.top', 'ap.nupload.me', 'nupload.me'];
const USER_AGENT = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0.0.0 Safari/537.36';

function absolutize(value, base) {
  try { return new URL(value, base).href; } catch { return ''; }
}

function decodeNuuploadParameter(value) {
  try {
    const parsed = new URL(value);
    const nested = parsed.searchParams.get('url');
    if (!nested) return null;
    const decoded = decodeURIComponent(nested);
    return /^https?:\/\//i.test(decoded) ? decoded : null;
  } catch { return null; }
}

function extractNuuploadObfuscated(html, pageUrl) {
  const offset = /String\.fromCharCode\(parseInt\(atob\(value\)\.replace\(\/\\D\/g,\s*['"]{2}\)\)\s*-\s*(\d+)\)/i.exec(html)?.[1];
  const array = /var\s+\w+\s*=\s*\[((?:"[^"]*"|'[^']*')(?:\s*,\s*(?:"[^"]*"|'[^']*'))*)\]/i.exec(html)?.[1];
  if (!offset || !array) return null;
  const chars = [...array.matchAll(/["']([^"']+)["']/g)].map(match => match[1]);
  let base = '';
  for (const encoded of chars) {
    const decoded = Buffer.from(encoded, 'base64').toString('utf8').replace(/\D/g, '');
    const code = Number(decoded) - Number(offset);
    if (Number.isFinite(code) && code > 0 && code <= 0x10ffff) base += String.fromCodePoint(code);
  }
  if (!base.startsWith('http')) return null;
  const session = /var\s+sesz\s*=\s*["']([^"']+)["']/i.exec(html)?.[1];
  if (session) base += (base.includes('?') ? '&' : '?') + 's=' + encodeURIComponent(session);
  return absolutize(base, pageUrl);
}

function findNuuploadDirect(html, baseUrl) {
  const sourceTag = /<(?:source|video)\b[^>]*src=["']([^"']+)["']/i.exec(html)?.[1];
  if (sourceTag) return absolutize(sourceTag, baseUrl);
  const patterns = [
    /["']file["']\s*[:=]\s*["']((?:https?:\/\/|\/)[^"']+\.(?:m3u8|mp4)[^"']*)["']/i,
    /["']src["']\s*[:=]\s*["']((?:https?:\/\/|\/)[^"']+\.(?:m3u8|mp4)[^"']*)["']/i,
    /["']hls\d*["']\s*[:=]\s*["']((?:https?:\/\/|\/)[^"']+\.m3u8[^"']*)["']/i,
    /sources?\s*:\s*\[\s*\{[^}]*file\s*:\s*["']((?:https?:\/\/|\/)[^"']+)["']/i,
    /https?:\/\/[^"'\\\s<]+\.m3u8[^"'\\\s<]*/i,
    /https?:\/\/[^"'\\\s<]+\.mp4[^"'\\\s<]*/i,
  ];
  for (const pattern of patterns) {
    const match = pattern.exec(html);
    const value = match?.[1] || match?.[0];
    if (value) return absolutize(value, baseUrl);
  }
  return extractNuuploadObfuscated(html, baseUrl);
}

function findNuuploadDelegated(html, baseUrl) {
  const candidates = [
    /<iframe\b[^>]*src=["']([^"']+)["']/i.exec(html)?.[1],
    /<a\b[^>]*href=["']([^"']+)["'][^>]*>/i.exec(html)?.[1],
    /<meta[^>]+http-equiv=["']refresh["'][^>]+content=["'][^"']*url=([^"']+)["']/i.exec(html)?.[1],
    /(?:window\.)?location(?:\.href)?\s*=\s*["']([^"']+)["']/i.exec(html)?.[1],
  ];
  for (let candidate of candidates) {
    if (!candidate) continue;
    candidate = candidate.trim().replace(/^src=/, '').replace(/^["']|["']$/g, '');
    const nested = decodeNuuploadParameter(candidate);
    if (nested) return nested;
    const absolute = absolutize(candidate, baseUrl);
    if (!absolute || absolute === baseUrl) continue;
    const host = new URL(absolute).hostname;
    if (DOMAINS.includes(host)) continue;
    return absolute;
  }
  const encoded = /["'](aHR0[a-zA-Z0-9+/=]{20,})["']/.exec(html)?.[1];
  if (encoded) {
    const decoded = Buffer.from(encoded, 'base64').toString('utf8').trim();
    if (/^https?:\/\//i.test(decoded)) return decoded;
  }
  return null;
}

async function extractNuupload(url) {
  let parsed;
  try { parsed = new URL(url); } catch { return null; }
  if (!DOMAINS.includes(parsed.hostname)) return null;
  const page = await getText(url, { 'User-Agent': USER_AGENT, Referer: 'https://nupload.top/' }, 30000);
  if (!page.ok) return null;
  const candidates = [page.text];
  for (const match of page.text.matchAll(/<script\b[^>]*>([\s\S]*?)<\/script>/gi)) {
    if (match[1]) candidates.push(match[1]);
  }
  const expanded = [];
  for (const html of candidates) {
    expanded.push(html);
    const marker = /eval\s*\(\s*function\s*\(\s*p\s*,\s*a\s*,\s*c\s*,\s*k\s*,\s*e\s*,\s*d\s*\)/i.exec(html);
    if (marker) {
      const unpacked = unpackPackedJs(html.slice(marker.index).split('</script>')[0]);
      if (unpacked) expanded.push(unpacked);
    }
  }
  let source = null;
  for (const html of expanded) {
    source = findNuuploadDirect(html, page.url);
    if (source) break;
  }
  if (!source) {
    for (const html of expanded) {
      const delegated = findNuuploadDelegated(html, page.url);
      if (!delegated) continue;
      const { extract } = require('./index');
      const resolved = await extract(delegated);
      if (resolved) return resolved;
    }
    return null;
  }
  const head = await request(source, { method: 'HEAD', headers: { 'User-Agent': USER_AGENT, Referer: new URL(page.url).origin + '/', Origin: new URL(page.url).origin }, timeout: 30000 });
  if (head.ok && head.url) source = head.url;
  const subtitles = [...page.text.matchAll(/<track\b([^>]*)>/gi)].map(match => {
    const attrs = match[1];
    const file = /\bsrc=["']([^"']+)["']/i.exec(attrs)?.[1];
    if (!file) return null;
    const label = /\blabel=["']([^"']+)["']/i.exec(attrs)?.[1] || /\bsrclang=["']([^"']+)["']/i.exec(attrs)?.[1] || 'Subtitle';
    return { label, url: absolutize(file, page.url), default: /\bdefault\b/i.test(attrs) };
  }).filter(Boolean);
  const lower = source.toLowerCase();
  const type = lower.includes('.m3u8') || lower.includes('?s=') ? 'm3u8' : lower.includes('.mp4') ? 'mp4' : undefined;
  return { url: source, type, subtitles, headers: { Referer: new URL(page.url).origin + '/', Origin: new URL(page.url).origin } };
}

module.exports = { extractNuupload, findNuuploadDirect, findNuuploadDelegated, extractNuuploadObfuscated };