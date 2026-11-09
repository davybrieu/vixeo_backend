// Supervideo (supervideo.cc): packed script with a `file:` entry.
// Port of ONYX SupervideoExtractor.
const { getText } = require('../lib/http');
const { unpack } = require('../lib/packer');
const { originOf, mediaType, absoluteUrl } = require('../lib/util');

const USER_AGENT =
  'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36';

const FILE_PATTERN = /file\s*:\s*["']([^"']+)["']/g;
const TRACKS_PATTERN = /tracks\s*:\s*\[(.*?)\]/s;
const CAPTION_PATTERN = /file\s*:\s*"(.*?)"\s*,\s*label\s*:\s*"(.*?)"\s*,\s*kind\s*:\s*"captions"/g;

async function extractSupervideo(url) {
  const origin = originOf(url);
  const res = await getText(url, { Referer: `${origin}/`, 'User-Agent': USER_AGENT });
  const html = res.text;

  const start = html.indexOf('eval(function(p,a,c,k,e,d)');
  if (start === -1) return null;
  const packed = html.slice(start, html.indexOf('</script>', start));
  const script = unpack(packed);
  if (!script) return null;

  let media = null;
  let fallback = null;
  let m;
  FILE_PATTERN.lastIndex = 0;
  while ((m = FILE_PATTERN.exec(script)) !== null) {
    if (/\.(m3u8|mp4|mpd)/i.test(m[1])) {
      media = m[1];
      break;
    }
    if (!/\.(vtt|srt|ass)/i.test(m[1])) fallback = m[1];
  }
  media = media || fallback;
  if (!media) return null;

  const subtitles = [];
  const tracks = TRACKS_PATTERN.exec(script);
  if (tracks) {
    let c;
    CAPTION_PATTERN.lastIndex = 0;
    while ((c = CAPTION_PATTERN.exec(tracks[1])) !== null) {
      subtitles.push({ url: absoluteUrl(origin, c[1]), language: c[2] || 'Inconnu' });
    }
  }

  const resolved = absoluteUrl(origin, media);
  return {
    url: resolved,
    type: mediaType(resolved) || 'm3u8',
    headers: { Referer: `${origin}/`, 'User-Agent': USER_AGENT },
    subtitles,
  };
}

module.exports = { extractSupervideo };
