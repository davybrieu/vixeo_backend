const { getText, request } = require('../lib/http');
const { unpackPackedJs } = require('../lib/packed');

const DOMAINS = ['nupload.top', 'nupupload.top', 'ap.nupload.me', 'nupload.me'];
const USER_AGENT = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0.0.0 Safari/537.36';

function absolutize(value, base) {
  try { return new URL(value, base).href; } catch { return ''; }
}

function findNuuploadDirect(html, baseUrl) {
  const patterns = [
    /<(?:source|video)\b[^>]*src=["']([^"']+)["']/i,
    /["']file["']\s*[:=]\s*["']((?:https?:\/\/|\/)[^"']+\.(?:m3u8|mp4)[^"']*)["']/i,
    /["']src["']\s*[:=]\s*["']((?:https?:\/\/|\/)[^"']+\.(?:m3u8|mp4)[^"']*)["']/i,
    /https?:\/\/[^"'\\\s<]+\.m3u8[^"'\\\s<]*/i,
    /https?:\/\/[^"'\\\s<]+\.mp4[^"'\\\s<]*/i,
  ];
  for (const pattern of patterns) {
    const match = pattern.exec(html);
    const value = match?.[1] || match?.[0];
    if (value) return absolutize(value, baseUrl);
  }
  const offset = /String\.fromCharCode\(parseInt\(atob\(value\)\.replace\(\/\\D\/g,\s*['"]{2}\)\)\s*-\s*(\d+)\)/i.exec(html)?.[1];
  const array = /var\s+\w+\s*=\s*\[((?:"[^"]*"|'[^']*')(?:\s*,\s*(?:"[^"]*"|'[^']*'))*)\]/i.exec(html)?.[1];
  if (offset && array) {
    const encoded = [...array.matchAll(/["']([^"']+)["']/g)].map(m => m[1]);
    let result = '';
    for (const item of encoded) {
      const digits = Buffer.from(item, 'base64').toString('utf8').replace(/\D/g, '');
      const code = Number(digits) - Number(offset);
      if (code > 0 && code <= 0x10ffff) result += String.fromCodePoint(code);
    }
    if (result.startsWith('http')) {
      const session = /var\s+sesz\s*=\s*["']([^"']+)["']/i.exec(html)?.[1];
      if (session) result += (result.includes('?') ? '&' : '?') + 's=' + encodeURIComponent(session);
      return absolutize(result, baseUrl);
    }
  }
  return null;
}

function findNuuploadDelegated(html, baseUrl) {
  const candidates = [
    /<iframe\b[^>]*src=["']([^"']+)["']/i.exec(html)?.[1],
    /<a\b[^>]*href=["']([^"']+)["'][^>]*>/i.exec(html)?.[1],
    /(?:window\.)?location(?:\.href)?\s*=\s*["']([^"']+)["']/i.exec(html)?.[1],
  ];
  for (const candidate of candidates) {
    if (!candidate) continue;
    const absolute = absolutize(candidate, baseUrl);
    if (!absolute || absolute === baseUrl) continue;
    if (DOMAINS.includes(new URL(absolute).hostname)) continue;
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
  const candidates = [page.text, ...[...page.text.matchAll(/<script\b[^>]*>([\s\S]*?)<\/script>/gi)].map(m => m[1])];
  const expanded = [];
  for (const html of candidates) {
    expanded.push(html);
    const packed = /eval\s*\(\s*function\s*\(\s*p\s*,\s*a\s*,\s*c\s*,\s*k\s*,\s*e\s*,\s*d\s*\)/i.exec(html);
    if (packed) {
      const unpacked = unpackPackedJs(html.slice(packed.index).split('</script>')[0]);
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
  const origin = new URL(page.url).origin;
  const head = await request(source, { method: 'HEAD', headers: { 'User-Agent': USER_AGENT, Referer: origin + '/', Origin: origin }, timeout: 30000 });
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
  return { url: source, type, subtitles, headers: { Referer: origin + '/', Origin: origin } };
}

module.exports = { extractNuupload, findNuuploadDirect, findNuuploadDelegated };