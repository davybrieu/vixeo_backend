// Filemoon (filemoon.org, filemoon.sx and many rotating mirrors). Recent
// versions answer a JSON API; older ones keep an `eval(function(p,a,c,k,e,d))`
// packed script with a plain m3u8/file entry.
// Port of ONYX FilemoonExtractor (packed-script path; the AES-GCM playback API
// needs a browser fingerprint so it is not attempted here).
const { getText } = require('../lib/http');
const { unpack } = require('../lib/packer');
const { originOf, mediaType, absoluteUrl } = require('../lib/util');

const USER_AGENT =
  'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36';

const MEDIA_PATTERNS = [
  /file\s*:\s*["'](https?:\/\/[^"']+\.m3u8[^"']*)["']/,
  /file\s*:\s*["'](https?:\/\/[^"']+\.mp4[^"']*)["']/,
  /(https?:\/\/[^"'\s\\]+\.m3u8[^"'\s\\<]*)/,
  /(https?:\/\/[^"'\s\\]+\.mp4[^"'\s\\<]*)/,
];

async function extractFilemoon(url) {
  const origin = originOf(url);
  const res = await getText(url, { Referer: `${origin}/`, 'User-Agent': USER_AGENT });
  const html = res.text;

  if (/no longer available|has been deleted|file not found|file was deleted/i.test(html)) return null;

  const candidates = [];
  const packed = /<script .*>(eval.*?)<\/script>/s.exec(html);
  if (packed) {
    const unpacked = unpack(packed[1]);
    if (unpacked) candidates.push(unpacked);
  }
  candidates.push(html);

  for (const text of candidates) {
    for (const pattern of MEDIA_PATTERNS) {
      const m = pattern.exec(text);
      if (m && m[1]) {
        const media = absoluteUrl(origin, m[1]);
        return {
          url: media,
          type: mediaType(media) || 'm3u8',
          headers: { Referer: `${origin}/`, Origin: origin, 'User-Agent': USER_AGENT },
        };
      }
    }
  }

  return null;
}

module.exports = { extractFilemoon };
