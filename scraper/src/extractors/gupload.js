const { getText } = require('../lib/http');
const { mediaType, normalizeText } = require('../lib/util');

const USER_AGENT = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36';

function findObjectLiteralAfter(text, marker) {
  const markerIndex = text.indexOf(marker);
  if (markerIndex < 0) return null;
  const start = text.indexOf('{', markerIndex);
  if (start < 0) return null;
  let depth = 0;
  let quote = null;
  let escaped = false;
  for (let i = start; i < text.length; i++) {
    const char = text[i];
    if (quote) {
      if (escaped) escaped = false;
      else if (char === '\\') escaped = true;
      else if (char === quote) quote = null;
      continue;
    }
    if (char === '"' || char === "'") quote = char;
    else if (char === '{') depth++;
    else if (char === '}' && --depth === 0) return text.slice(start, i + 1);
  }
  return null;
}

function decodeLegacyConfig(encoded, key) {
  try {
    const payload = encoded.split('~')[1];
    if (!payload || !key) return null;
    const bytes = Buffer.from(payload, 'base64');
    return [...bytes].map((byte, index) => String.fromCharCode(byte ^ key.charCodeAt(index % key.length))).join('');
  } catch {
    return null;
  }
}

function findVideoUrl(html) {
  for (const marker of ['const config', 'var config', 'let config']) {
    const literal = findObjectLiteralAfter(html, marker);
    if (literal) {
      try {
        const value = JSON.parse(literal);
        if (typeof value.videoUrl === 'string' && value.videoUrl) return value.videoUrl;
      } catch {}
    }
  }

  const pContent = /_p\s*=\s*\[([^\]]+)]/.exec(html)?.[1];
  const key = pContent && [...pContent.matchAll(/['"]([^'"]+)['"]/g)].map(match => match[1]).join('');
  const encoded = /_cfg\s*=\s*_(?:dp|xd)\(['"]([^'"]+)['"]\)/.exec(html)?.[1];
  if (!key || !encoded) return null;
  const decoded = decodeLegacyConfig(encoded, key);
  try {
    const value = JSON.parse(decoded);
    return typeof value.videoUrl === 'string' && value.videoUrl ? value.videoUrl : null;
  } catch {
    return null;
  }
}

async function extractGupload(url) {
  let parsed;
  try { parsed = new URL(url); } catch { return null; }
  if (parsed.hostname !== 'gupload.xyz' && !parsed.hostname.endsWith('.gupload.xyz')) return null;
  const response = await getText(url, { 'User-Agent': USER_AGENT });
  if (!response.ok) return null;
  const source = findVideoUrl(normalizeText(response.text));
  if (!source) return null;
  return {
    url: new URL(source, response.url).href,
    type: mediaType(source) || 'mp4',
    headers: { 'User-Agent': USER_AGENT, Referer: new URL(response.url).origin },
  };
}

module.exports = { extractGupload, findObjectLiteralAfter, findVideoUrl };