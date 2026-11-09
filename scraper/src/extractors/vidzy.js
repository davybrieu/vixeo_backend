// Vidzy (vidzy.org, vidzy.live, vidzy.cc, vidzy.to): the player page usually
// holds a packed script or a direct u<digit>.vidzy master.m3u8 URL.
// Port of ONYX VidzyExtractor (the WebView capture + canvas-key path is not
// reproduced; the direct/pre-unpacked cases are covered).
const { getText } = require('../lib/http');
const { unpack } = require('../lib/packer');
const { originOf, mediaType, absoluteUrl } = require('../lib/util');

const USER_AGENT =
  'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36';

const MEDIA_PATTERNS = [
  /src\s*:\s*["'](https?:\/\/[^"']+\.m3u8[^"']*)["']/,
  /file\s*:\s*["'](https?:\/\/[^"']+\.m3u8[^"']*)["']/,
  /source\s*:\s*["'](https?:\/\/[^"']+\.m3u8[^"']*)["']/,
  /(https?:\/\/u\d+\.vidzy\.[^"'\s]+master\.m3u8[^"'\s]*)/,
  /(https?:\/\/[^"'\s]+vidzy[^"'\s]+\.m3u8[^"'\s]*)/,
];

function findMedia(text) {
  for (const pattern of MEDIA_PATTERNS) {
    const m = pattern.exec(text);
    if (m && m[1]) return m[1];
  }
  return null;
}

function extractSubtitles(text, baseUrl) {
  const block = /loadTracks\s*\(\s*\[(.*?)]\s*\)|tracks\s*:\s*\[(.*?)]|subtitles\s*:\s*\[(.*?)]|captions\s*:\s*\[(.*?)]/s.exec(text);
  if (!block) return [];
  const body = block[1] || block[2] || block[3] || block[4] || '';
  const subtitles = [];
  const re = /\{([^}]*)\}/g;
  let m;
  while ((m = re.exec(body)) !== null) {
    const file = /(?:file|src)\s*:\s*["']([^"']+)["']/.exec(m[1]);
    const label = /(?:label|language|name)\s*:\s*["']([^"']+)["']/.exec(m[1]);
    if (file && file[1]) {
      subtitles.push({ url: absoluteUrl(baseUrl, file[1]), language: (label && label[1]) || 'Inconnu' });
    }
  }
  return subtitles;
}

async function extractVidzy(url) {
  const origin = originOf(url);
  const res = await getText(url, {
    Referer: `${origin}/`,
    Origin: origin,
    'User-Agent': USER_AGENT,
    'Sec-Fetch-Dest': 'empty',
    'Sec-Fetch-Mode': 'cors',
    'Sec-Fetch-Site': 'same-site',
  });
  const html = res.text;

  const candidates = [html];
  const packed = /(eval\(function\(p,a,c,k,e,d\).*?)<\/script>/s.exec(html);
  if (packed) {
    const unpacked = unpack(packed[1]);
    if (unpacked) candidates.unshift(unpacked);
  }

  for (const text of candidates) {
    const media = findMedia(text);
    if (media) {
      return {
        url: media,
        type: mediaType(media) || 'm3u8',
        headers: { Referer: url, Origin: origin, 'User-Agent': USER_AGENT },
        subtitles: extractSubtitles(text, origin),
      };
    }
  }

  return null;
}

module.exports = { extractVidzy };
