// Streamwish (embedwish.com and many rotating mirrors): the page hides the
// source in a packed `eval(function(p,a,c,k,e,d){...})` script.
// Port of ONYX StreamWishExtractor.
const { getText } = require('../lib/http');
const { unpack } = require('../lib/packer');
const { originOf, mediaType, absoluteUrl } = require('../lib/util');

const USER_AGENT =
  'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36';

const HLS_PATTERN = /(?:["']?hls(\d*)["']?|["']?file["']?)\s*[:=]\s*["']((?:https?:\/\/|\/)[^"']+\.m3u8[^"']*)["']/g;
const TRACKS_PATTERN = /["']?tracks["']?\s*:\s*\[(.*?)\]/s;
const CAPTION_PATTERN = /["']?file["']?\s*:\s*["'](.*?)["'](?:\s*,\s*["']?label["']?\s*:\s*["'](.*?)["'])?\s*,\s*["']?kind["']?\s*:\s*["'](.*?)["']/g;

async function extractStreamWish(url) {
  const origin = originOf(url);
  const embed = url.replace(/^(https?:\/\/[^/]+)\/(?:d|f)\//, '$1/e/');
  const res = await getText(embed, { Referer: `${origin}/`, 'User-Agent': USER_AGENT });
  const html = res.text;

  if (/no longer available|has been deleted|file not found|file was deleted/i.test(html)) return null;

  const packed = /<script .*>(eval.*?)<\/script>/s.exec(html);
  if (!packed) return null;

  const script = unpack(packed[1]);
  if (!script || !script.includes('m3u8')) return null;

  let best = null;
  let bestIndex = -1;
  let m;
  HLS_PATTERN.lastIndex = 0;
  while ((m = HLS_PATTERN.exec(script)) !== null) {
    const index = m[1] ? parseInt(m[1], 10) : 0;
    if (index >= bestIndex) {
      bestIndex = index;
      best = m[2];
    }
  }
  if (!best) return null;

  const media = absoluteUrl(origin, best);
  const tracks = TRACKS_PATTERN.exec(script);
  const subtitles = [];
  if (tracks) {
    let c;
    CAPTION_PATTERN.lastIndex = 0;
    while ((c = CAPTION_PATTERN.exec(tracks[1])) !== null) {
      if (c[3] === 'captions') {
        subtitles.push({ url: absoluteUrl(origin, c[1]), language: c[2] || 'Inconnu' });
      }
    }
  }

  return {
    url: media,
    type: mediaType(media) || 'm3u8',
    headers: { Referer: `${origin}/`, Origin: origin, 'User-Agent': USER_AGENT },
    subtitles,
  };
}

module.exports = { extractStreamWish };
