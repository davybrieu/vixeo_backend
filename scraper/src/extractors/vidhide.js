// VidHide (dhtpre.com, filelions.to, streamhide.*, morencius.com, ...): packed
// `eval(function(p,a,c,k,e,d){...})` script whose unpacked body holds the HLS
// link under hls2/hls4/hls3/hls/file/src/source.
// Port of ONYX VidHideExtractor.
const { getText } = require('../lib/http');
const { unpack } = require('../lib/packer');
const { originOf, mediaType } = require('../lib/util');

const USER_AGENT =
  'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36';

const KEY_PATTERN = /["'](hls\d*|file|src|source)["']\s*:\s*["']([^"']+)["']/g;
const PREFERRED_KEYS = ['hls2', 'hls4', 'hls3', 'hls', 'file', 'src', 'source'];

async function extractVidHide(url) {
  const origin = originOf(url);
  const res = await getText(url, {
    Referer: `${origin}/`,
    Origin: origin,
    'User-Agent': USER_AGENT,
  });
  const html = res.text;

  const packed = /(eval\(function\(p,a,c,k,e,d\).*?)<\/script>/s.exec(html);
  if (!packed) {
    if (/no longer available|has been deleted|file not found|file was deleted/i.test(html)) return null;
    return null;
  }

  const unpacked = unpack(packed[1]);
  if (!unpacked) return null;

  const map = {};
  let m;
  KEY_PATTERN.lastIndex = 0;
  while ((m = KEY_PATTERN.exec(unpacked)) !== null) {
    map[m[1]] = m[2];
  }

  let media = null;
  for (const key of PREFERRED_KEYS) {
    if (map[key]) {
      media = map[key];
      break;
    }
  }
  if (!media) return null;

  return {
    url: media,
    type: mediaType(media) || 'm3u8',
    headers: { Referer: `${origin}/`, Origin: origin, 'User-Agent': USER_AGENT },
  };
}

module.exports = { extractVidHide };
