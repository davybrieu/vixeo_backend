// Vidoza (vidoza.net, videzz.net): plain page with a <source src="...">.
// Port of ONYX VidozaExtractor.
const { getText } = require('../lib/http');
const { originOf, mediaType } = require('../lib/util');

const USER_AGENT =
  'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36';

async function extractVidoza(url) {
  const origin = originOf(url);
  const res = await getText(url, { Referer: `${origin}/`, 'User-Agent': USER_AGENT });
  const html = res.text;

  if (/File was deleted/i.test(html)) return null;

  const m = /<source[^>]+src=["']([^"']+)["']/.exec(html);
  if (!m) return null;

  return {
    url: m[1],
    type: mediaType(m[1]) || 'mp4',
    headers: { Referer: `${origin}/`, 'User-Agent': USER_AGENT },
  };
}

module.exports = { extractVidoza };
