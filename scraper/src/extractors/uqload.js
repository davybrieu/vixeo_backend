// Uqload (uqload.is, uqload.vc, uqload.to, ...): the embed page carries the
// source either directly or inside a packed script.
// Port of ONYX UqloadExtractor.
const { getText } = require('../lib/http');
const { unpack } = require('../lib/packer');
const { originOf, mediaType, absoluteUrl } = require('../lib/util');

const USER_AGENT =
  'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36';

const PATTERNS = [
  /file\s*:\s*["'](https?:\/\/[^"']+\.m3u8[^"']*)["']/,
  /file\s*:\s*["'](https?:\/\/[^"']+\.mp4[^"']*)["']/,
  /file\s*:\s*["'](https?:\/\/[^"']+)["']/,
  /sources:\s*\["([^"]+)"\]/,
  /file\s*:\s*"(https?:\/\/[^"]+\.m3u8[^"]*)"/,
  /file\s*:\s*"(https?:\/\/[^"]+\.mp4[^"]*)"/,
];

async function extractUqload(url) {
  const embed = url.replace(/^(https?:\/\/)(?:www\.)?uqload\.to\//i, '$1uqload.vc/');
  const origin = originOf(embed);
  const res = await getText(embed, { Referer: `${origin}/`, 'User-Agent': USER_AGENT });
  let html = res.text;

  const packed = /(eval\(function\(p,a,c,k,e,d\)(.|\n)*?)<\/script>/.exec(html);
  if (packed) {
    const unpacked = unpack(packed[1]);
    if (unpacked) html = unpacked;
  }

  for (const pattern of PATTERNS) {
    const m = pattern.exec(html);
    if (m && m[1]) {
      const media = absoluteUrl(origin, m[1]);
      if (!media) continue;
      return {
        url: media,
        type: mediaType(media) || 'mp4',
        headers: { Referer: embed, Origin: 'https://uqload.is', 'User-Agent': USER_AGENT },
      };
    }
  }

  if (/no longer available|has been deleted|file not found|file was deleted|locked by administrator/i.test(html)) {
    return null;
  }
  return null;
}

module.exports = { extractUqload };
