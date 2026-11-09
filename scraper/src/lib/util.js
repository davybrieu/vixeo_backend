// Small helpers shared by the extractors, ported from the ONYX extractor base class.

const DEFAULT_USER_AGENT =
  'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36';

function hostOf(url) {
  try {
    return new URL(url).hostname.toLowerCase().replace(/^www\./, '');
  } catch (e) {
    return '';
  }
}

function originOf(url) {
  try {
    const u = new URL(url);
    return `${u.protocol}//${u.host}`;
  } catch (e) {
    return '';
  }
}

function absoluteUrl(base, path) {
  if (!path) return null;
  if (/^https?:\/\//i.test(path)) return path;
  if (path.startsWith('//')) return `https:${path}`;
  try {
    return new URL(path, base).toString();
  } catch (e) {
    return null;
  }
}

// Normalises escaped slashes and entities found in packed player sources.
function normalizeText(text) {
  return String(text)
    .replace(/\\\//g, '/')
    .replace(/\\u0026/gi, '&')
    .replace(/&amp;/g, '&');
}

function firstMatch(text, patterns) {
  for (const pattern of patterns) {
    const re = pattern instanceof RegExp ? pattern : new RegExp(pattern);
    const m = re.exec(text);
    if (m && m[1] !== undefined) return m[1];
  }
  return null;
}

function hexDecode(hex) {
  const clean = String(hex).replace(/[^0-9a-fA-F]/g, '');
  let out = '';
  for (let i = 0; i + 1 < clean.length; i += 2) {
    out += String.fromCharCode(parseInt(clean.slice(i, i + 2), 16));
  }
  return out;
}

function decodeHtmlEntities(text) {
  return String(text)
    .replace(/&amp;/g, '&')
    .replace(/&lt;/g, '<')
    .replace(/&gt;/g, '>')
    .replace(/&quot;/g, '"')
    .replace(/&#0?39;/g, "'")
    .replace(/&apos;/g, "'")
    .replace(/&#(\d+);/g, (_, n) => String.fromCharCode(parseInt(n, 10)));
}

// A movie/TV id from a player link: /e/<id>, /d/<id>, ?id=<id>, or #<id>.
function extractVideoId(link) {
  const hash = link.indexOf('#');
  if (hash !== -1 && hash !== link.length - 1) {
    return link.slice(hash + 1).split('&')[0];
  }
  return firstMatch(link, [
    /\/(?:e|embed|v|d)\/([a-zA-Z0-9_-]+)/,
    /[?&]id=([a-zA-Z0-9_-]+)/,
  ]);
}

const VIDEO_EXT = /\.(m3u8|mp4|mpd)(\?[^"'\s]*)?/i;

function mediaType(url) {
  if (!url) return null;
  if (/\.m3u8/i.test(url)) return 'm3u8';
  if (/\.mpd/i.test(url)) return 'mpd';
  if (/\.mp4/i.test(url)) return 'mp4';
  return null;
}

module.exports = {
  DEFAULT_USER_AGENT,
  hostOf,
  originOf,
  absoluteUrl,
  normalizeText,
  firstMatch,
  hexDecode,
  decodeHtmlEntities,
  extractVideoId,
  mediaType,
  VIDEO_EXT,
};
