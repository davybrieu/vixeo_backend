// Streamruby (streamruby.com, stmruby.com, rubyvid.com, moflix-stream.fans):
// same packed-script family as VidHide.
// Port of ONYX StreamrubyExtractor.
const { getText } = require('../lib/http');
const { unpack } = require('../lib/packer');
const { originOf, mediaType, absoluteUrl } = require('../lib/util');

const USER_AGENT =
  'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36';

const FILE_PATTERN = /file\s*:\s*["']([^"']+)["']/;

async function extractStreamruby(url) {
  const origin = originOf(url);
  const res = await getText(url, { Referer: `${origin}/`, 'User-Agent': USER_AGENT });
  const html = res.text;

  const packed = /(eval\(function\(p,a,c,k,e,d\).*?)<\/script>/s.exec(html);
  if (!packed) return null;

  const unpacked = unpack(packed[1]);
  if (!unpacked) return null;

  const m = FILE_PATTERN.exec(unpacked);
  if (!m) return null;

  const media = absoluteUrl(origin, m[1]);
  return {
    url: media,
    type: mediaType(media) || 'm3u8',
    headers: { Referer: `${origin}/`, Origin: origin, 'User-Agent': USER_AGENT },
  };
}

module.exports = { extractStreamruby };
