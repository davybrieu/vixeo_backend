// Rpmvid (rpmvid.com and the flemmix.* / *.upns.* mirrors): the player answers
// an AES-CBC encrypted JSON payload at /api/v1/video.
// Port of ONYX RpmvidExtractor (API path; the Cloudflare-signature fallback that
// needs a WebView is approximated with the t/e params when present).
const crypto = require('crypto');
const { getText } = require('../lib/http');
const { originOf, extractVideoId, mediaType, absoluteUrl } = require('../lib/util');

const KEY = Buffer.from('kiemtienmua911ca', 'utf8');
const IV = Buffer.from('1234567890oiuytr', 'utf8');
const USER_AGENT =
  'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36';

function decryptHexPayload(hex) {
  const clean = String(hex).trim().replace(/[^0-9a-fA-F]/g, '');
  const data = Buffer.from(clean.length % 2 ? '0' + clean : clean, 'hex');
  const decipher = crypto.createDecipheriv('aes-128-cbc', KEY, IV);
  decipher.setAutoPadding(true);
  return Buffer.concat([decipher.update(data), decipher.final()]).toString('utf8');
}

function buildCloudflareUrl(json, cfPath, cfExpire) {
  let t = null;
  let e = null;
  try {
    const config = JSON.parse(json.streamingConfig || '{}');
    const cf = config.adjust && config.adjust.Cloudflare;
    if (cf && !cf.disabled) {
      t = cf.params && cf.params.t;
      e = cf.params && cf.params.e;
    }
  } catch (err) {
    // ignore
  }
  if (t && e) return `${cfPath}?t=${t}&e=${e}`;
  if (cfExpire && cfExpire.includes('::')) {
    const [a, b] = cfExpire.split('::');
    return `${cfPath}?t=${a}&e=${b}`;
  }
  return cfPath;
}

async function extractRpmvid(url) {
  const origin = originOf(url);
  const id = extractVideoId(url);
  if (!origin || !id) return null;

  const api = `${origin}/api/v1/video?id=${encodeURIComponent(id)}&w=1920&h=1080&r=`;
  const res = await getText(api, { Referer: `${origin}/`, 'User-Agent': USER_AGENT });
  if (!res.ok) return null;

  let json;
  try {
    json = JSON.parse(decryptHexPayload(res.text));
  } catch (e) {
    return null;
  }

  const hls = json.hls || json.hlsVideoTiktok;
  let media = hls;
  if (!media && json.cf) {
    const path = absoluteUrl(origin, json.cf);
    media = buildCloudflareUrl(json, path, json.cfExpire);
  }
  if (!media) return null;

  const subtitles = [];
  const subtitleMap = json.subtitle || {};
  const preferred = json.defaultSubtitle;
  for (const [language, subUrl] of Object.entries(subtitleMap)) {
    if (subUrl) subtitles.push({ url: subUrl, language, default: language === preferred });
  }

  return {
    url: media,
    type: mediaType(media) || 'm3u8',
    headers: { Referer: `${origin}/`, Origin: origin, 'User-Agent': USER_AGENT },
    subtitles,
  };
}

module.exports = { extractRpmvid };
