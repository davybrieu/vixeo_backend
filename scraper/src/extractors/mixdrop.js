// MixDrop (mixdrop.co and many md*/mxdrop.* mirrors): the file URL is set on
// MDCore.wurl, sometimes inside a packed script.
// Port of ONYX MixDropExtractor.
const { getText } = require('../lib/http');
const { unpack } = require('../lib/packer');
const { originOf, mediaType } = require('../lib/util');

const USER_AGENT =
  'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0.0.0 Safari/537.36';

const WURL_PATTERN = /wurl.*?=.*?"(.*?)";/;

async function extractMixDrop(url) {
  const origin = originOf(url);
  let target = url.replace(/\/f\//, '/e/').replace(/\/emb\//, '/e/').replace('.club/', '.ag/');
  const clean = /^(https?:\/\/[^/]+\/e\/[^/?#]+).*$/.exec(target);
  if (clean) target = clean[1];

  const res = await getText(target, {
    Referer: `${origin}/`,
    'User-Agent': USER_AGENT,
    'X-Requested-With': 'XMLHttpRequest',
  });
  let html = res.text;

  const packed = /(eval\(function\(p,a,c,k,e,d\)(.|\n)*?)<\/script>/.exec(html);
  if (packed) {
    const unpacked = unpack(packed[1]);
    if (unpacked) html = unpacked;
  }

  const m = WURL_PATTERN.exec(html);
  if (m && m[1]) {
    let link = m[1];
    if (link.startsWith('//')) link = `https:${link}`;
    else if (!/^https?:/i.test(link)) link = `https://${link}`;
    return {
      url: link,
      type: mediaType(link) || 'mp4',
      headers: { 'User-Agent': USER_AGENT },
    };
  }

  return null;
}

module.exports = { extractMixDrop };
