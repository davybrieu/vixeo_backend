// Helpers to pull a playable URL out of a player page, mirroring the shared
// logic of the ONYX extractors (packed JS, file/src/sources, redirects).

const { unpack, findPacked } = require('./packer');
const { absoluteUrl, originOf, normalizeText } = require('./util');

const SOURCE_PATTERNS = [
  /["']?hls\d*["']?\s*[:=]\s*["']((?:https?:\/\/|\/)[^"']+\.m3u8[^"']*)["']/i,
  /["']?file["']?\s*[:=]\s*["']((?:https?:\/\/|\/)[^"']+\.(?:m3u8|mp4)[^"']*)["']/i,
  /["']?src["']?\s*[:=]\s*["']((?:https?:\/\/|\/)[^"']+\.(?:m3u8|mp4)[^"']*)["']/i,
  /["']?source["']?\s*[:=]\s*["']((?:https?:\/\/|\/)[^"']+\.(?:m3u8|mp4)[^"']*)["']/i,
  /sources?\s*:\s*\[\s*\{[^}]*?file\s*:\s*["']([^"']+)["']/i,
  /(https?:\/\/[^"'\s\\]+\.m3u8[^"'\s\\<]*)/i,
  /(https?:\/\/[^"'\s\\]+\.mp4[^"'\s\\<]*)/i,
];

const CAPTION_PATTERN = /file\s*:\s*["']([^"']+)["']\s*,\s*label\s*:\s*["']([^"']+)["']\s*,\s*kind\s*:\s*["']captions["']/gi;

function findMediaUrl(text, baseUrl) {
  const normalized = normalizeText(text);
  for (const pattern of SOURCE_PATTERNS) {
    const m = pattern.exec(normalized);
    if (m && m[1]) {
      const url = absoluteUrl(baseUrl, m[1]);
      if (url) return url;
    }
  }
  return null;
}

function findCaptions(text, baseUrl) {
  const captions = [];
  const normalized = normalizeText(text);
  let m;
  CAPTION_PATTERN.lastIndex = 0;
  while ((m = CAPTION_PATTERN.exec(normalized)) !== null) {
    const url = absoluteUrl(baseUrl, m[1]);
    if (url) captions.push({ url, language: m[2] || 'Inconnu' });
  }
  return captions;
}

// Returns the unpacked player script when the page is packed, else the input.
function unpackIfNeeded(html) {
  const packed = findPacked(html);
  if (!packed) return html;
  const unpacked = unpack(packed);
  return unpacked || html;
}

function mediaTypeOf(url) {
  if (/\.m3u8/i.test(url)) return 'm3u8';
  if (/\.mpd/i.test(url)) return 'mpd';
  return 'mp4';
}

module.exports = { findMediaUrl, findCaptions, unpackIfNeeded, mediaTypeOf, originOf };
