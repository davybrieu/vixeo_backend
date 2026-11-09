// Netu / HQQ (waaw1.tv, netu.tv, hqq.tv, waaw.to, ...): the page holds a hash
// and an `iss` token; posting them to /player/index.php?do=getVideo returns the
// secured stream link.
// Port of ONYX NetuExtractor.
const { getText, postForm } = require('../lib/http');
const { originOf, mediaType } = require('../lib/util');

const USER_AGENT =
  'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36';

function normalizeEmbed(url) {
  return url.replace('/watch/', '/e/').replace('/v/', '/e/').replace('/f/', '/e/');
}

async function extractNetu(url, depth = 0) {
  if (depth > 3) return null;
  const target = normalizeEmbed(url);
  const origin = originOf(target);

  const res = await getText(target, { Referer: `${origin}/`, 'User-Agent': USER_AGENT });
  if (res.status === 404 || res.status === 410) return null;

  const html = res.text;

  const media =
    /(?:olplayer|player)\.src\(\s*\{\s*src\s*:\s*['"]([^'"]+)['"]/.exec(html) ||
    /sources\s*:\s*\[\s*\{\s*(?:src|file)\s*:\s*['"]([^'"]+)['"]/.exec(html) ||
    /(https?:\/\/[^"'\s]+\.m3u8[^"'\s]*)/.exec(html) ||
    /(https?:\/\/[^"'\s]+\.mp4[^"'\s]*)/.exec(html);
  if (media && media[1]) return build(media[1], target, origin);

  const iframe = /<iframe[^>]+src\s*=\s*['"]([^'"]+)['"]/.exec(html);
  if (iframe && iframe[1]) {
    const next = iframe[1].startsWith('//') ? `https:${iframe[1]}` : iframe[1];
    if (next !== target) return extractNetu(next, depth + 1);
  }

  const hash =
    /(?:var|let|const)\s+hash\s*=\s*['"]([^'"]+)['"]/.exec(html) ||
    /data-hash\s*=\s*['"]([^'"]+)['"]/.exec(html) ||
    /hash\s*:\s*['"]([^'"]+)['"]/.exec(html);
  if (!hash || !hash[1]) {
    return null;
  }

  const iss = /(?:var|let|const)\s+iss\s*=\s*['"]([^'"]+)['"]/.exec(html);
  const form = { hash: hash[1], r: target };
  if (iss && iss[1]) form.iss = iss[1];

  const api = await postForm(`${origin}/player/index.php?data=${hash[1]}&do=getVideo`, form, {
    Referer: target,
    'User-Agent': USER_AGENT,
    'X-Requested-With': 'XMLHttpRequest',
  });
  if (!api.ok) return null;

  try {
    const json = JSON.parse(api.text);
    const link = json.securedLink || json.videoSource || json.file || json.url;
    if (link) return build(link, target, origin);
  } catch (e) {
    const fallback = /(https?:\/\/[^"'\s]+\.(?:m3u8|mp4)[^"'\s]*)/.exec(api.text);
    if (fallback) return build(fallback[1], target, origin);
  }

  return null;
}

function build(mediaUrl, referer, origin) {
  return {
    url: mediaUrl,
    type: mediaType(mediaUrl) || 'm3u8',
    headers: { Referer: referer, Origin: origin, 'User-Agent': USER_AGENT },
  };
}

module.exports = { extractNetu };
