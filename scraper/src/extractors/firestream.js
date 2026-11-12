const { getText, postJson } = require('../lib/http');

const DOMAINS = ['firestream.to', 'firestream.site'];
const USER_AGENT = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0.0.0 Safari/537.36';

async function extractFirestream(url) {
  let parsed;
  try { parsed = new URL(url); } catch { return null; }
  if (!DOMAINS.includes(parsed.hostname)) return null;
  const baseUrl = parsed.origin;
  const slug = parsed.pathname.split('/').filter(Boolean).pop();
  if (!slug) return null;
  const page = await getText(url, { 'User-Agent': USER_AGENT, Accept: 'text/html,application/xhtml+xml', Referer: baseUrl + '/' });
  if (!page.ok) return null;
  const videoDataRaw = /<[^>]+id=["']video-data["'][^>]*>([\s\S]*?)<\/[^>]+>/i.exec(page.text)?.[1]?.trim();
  if (!videoDataRaw) return null;
  let root;
  try { root = JSON.parse(videoDataRaw); } catch { return null; }
  const video = root?.video;
  if (!video) return null;
  let source = video.signedVideoUrl || video.signedVideoSdUrl || '';
  if (!source) {
    const tokenBlob = /<[^>]+id=["']token-blob["'][^>]*>([\s\S]*?)<\/[^>]+>/i.exec(page.text)?.[1]?.trim();
    if (!tokenBlob) return null;
    const resolved = await postJson(new URL('/api/videos/' + encodeURIComponent(slug) + '/resolve', baseUrl).href, { blob: tokenBlob }, {
      'User-Agent': USER_AGENT,
      Accept: 'application/json',
      Origin: baseUrl,
      Referer: url,
      ...(page.cookie ? { Cookie: page.cookie } : {}),
    });
    if (!resolved.ok) return null;
    try {
      const json = JSON.parse(resolved.text);
      source = json.signedVideoUrl || json.signedVideoSdUrl || '';
    } catch { return null; }
  }
  if (!source) return null;
  const subtitles = (video.subtitles || []).filter(item => item?.url).map(item => ({
    url: new URL(item.url, baseUrl + '/').href,
    label: item.label || item.language || 'Subtitle',
    default: Boolean(item.default),
  }));
  return { url: source, subtitles };
}
module.exports = { extractFirestream };