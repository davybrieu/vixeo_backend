// SendVid (sendvid.com): the embed page exposes a direct .mp4 source.
// Port of ONYX SendvidExtractor.
const { getText } = require('../lib/http');
const { originOf, mediaType } = require('../lib/util');

const USER_AGENT =
  'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36';

async function extractSendvid(url) {
  const origin = originOf(url);
  const embed = /\/embed\//.test(url) ? url : `${origin}/embed/${url.split('/').pop()}`;
  const res = await getText(embed, { Referer: `${origin}/`, 'User-Agent': USER_AGENT });
  if (!res.ok) return null;

  const html = res.text;
  const m =
    /<source[^>]+src="([^"]+\.mp4[^"]*)"/.exec(html) ||
    /(https?:\/\/[^"'\s]+sendvid\.com\/[^"'\s]+\.mp4[^"'\s]*)/.exec(html);
  if (!m) return null;

  return {
    url: m[1],
    type: mediaType(m[1]) || 'mp4',
    headers: { Referer: `${origin}/`, 'User-Agent': USER_AGENT },
  };
}

module.exports = { extractSendvid };
