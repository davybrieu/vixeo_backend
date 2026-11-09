// Vidsonic (vidsonic.net): the player page embeds a hex/base64-encoded blob
// that decodes to a pipe-separated list of source URLs.
// Port of ONYX VidsonicExtractor.
const { getText } = require('../lib/http');
const { originOf, mediaType, normalizeText } = require('../lib/util');

const USER_AGENT =
  'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36';

const BLOB_PATTERNS = [
  /(?:const|var|let)\s+\w+\s*=\s*'([a-fA-F0-9|]{50,})'\s*;/,
  /(?:const|var|let)\s+\w+\s*=\s*"([a-fA-F0-9|]{50,})"\s*;/,
  /['"]([a-fA-F0-9|]{100,})['"]/,
];

const SOURCE_PATTERNS = [
  /sources\s*:\s*\[\s*\{\s*(?:src|file)\s*:\s*['"]([^'"]+)['"]/,
  /file\s*:\s*['"]([^'"]+\.m3u8[^'"]*)['"]/,
  /(https?:\/\/[^"'\s]+\.m3u8[^"'\s]*)/,
  /(https?:\/\/[^"'\s]+\.mp4[^"'\s]*)/,
];

function decodeHexBlob(blob) {
  const clean = blob.replace(/\|/g, '');
  let out = '';
  for (let i = 0; i + 1 < clean.length; i += 2) {
    out += String.fromCharCode(parseInt(clean.slice(i, i + 2), 16));
  }
  return Buffer.from(out, 'utf8').toString('utf8');
}

async function extractVidsonic(url) {
  const origin = originOf(url);
  const res = await getText(url, { 'User-Agent': USER_AGENT, Referer: `${origin}/` });
  const html = normalizeText(res.text);

  for (const pattern of BLOB_PATTERNS) {
    const m = pattern.exec(html);
    if (m && m[1]) {
      try {
        const decoded = decodeHexBlob(m[1]);
        const media = findSource(decoded);
        if (media) return build(media, origin);
      } catch (e) {
        // fall through to plain patterns
      }
    }
  }

  const media = findSource(html);
  return media ? build(media, origin) : null;
}

function findSource(text) {
  for (const pattern of SOURCE_PATTERNS) {
    const m = pattern.exec(text);
    if (m && m[1]) return m[1];
  }
  return null;
}

function build(mediaUrl, origin) {
  const absolute = /^https?:/i.test(mediaUrl) ? mediaUrl : `${origin}${mediaUrl.startsWith('/') ? '' : '/'}${mediaUrl}`;
  return {
    url: absolute,
    type: mediaType(absolute) || 'm3u8',
    headers: { Referer: `${origin}/`, Origin: origin, 'User-Agent': USER_AGENT },
  };
}

module.exports = { extractVidsonic };
