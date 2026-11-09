// Streamtape (streamtape.com, streamta.site, strtape.cloud, ...): the real URL
// is assembled from a fragment + a `.substring(n)` offset in an inline script.
// Port of ONYX StreamtapeExtractor.
const { getText } = require('../lib/http');
const { originOf, mediaType } = require('../lib/util');

const USER_AGENT = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)';

const LINK_PATTERNS = [
  /document\.getElementById\('norobotlink'\)\.innerHTML\s*=\s*'([^']+)'\s*\+\s*\('([^']+)'\)\.substring\(([0-9]+)\)/,
  /document\.getElementById\('botlink'\)\.innerHTML\s*=\s*'([^']+)'\s*\+\s*\('([^']+)'\)\.substring\(([0-9]+)\)/,
  /document\.getElementById\('[^']*link'\)\.innerHTML\s*=\s*'([^']+)'\s*\+\s*\('([^']+)'\)\.substring\(([0-9]+)\)/,
];

async function extractStreamtape(url) {
  const origin = originOf(url);
  const res = await getText(url, { Referer: `${origin}/`, 'User-Agent': USER_AGENT });
  if (res.status === 404) return null;

  for (const pattern of LINK_PATTERNS) {
    const m = pattern.exec(res.text);
    if (m) {
      const offset = parseInt(m[3], 10);
      let link = m[1] + m[2].substring(offset);
      if (link.startsWith('//')) link = `https:${link}`;
      else if (!/^https?:/i.test(link)) link = `https://${origin.replace(/^https?:\/\//, '')}${link.startsWith('/') ? '' : '/'}${link}`;
      if (!link.includes('stream=')) link += '&stream=1';
      return {
        url: link,
        type: mediaType(link) || 'mp4',
        headers: { Referer: `${origin}/`, 'User-Agent': USER_AGENT },
      };
    }
  }

  return null;
}

module.exports = { extractStreamtape };
