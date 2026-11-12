const { getText } = require('../lib/http');
const { patchVixsrcManifest } = require('./vixsrc');

const USER_AGENT = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36';

function sanitizeVixcloudObject(value) {
  return String(value || '')
    .replace(/'/g, '"')
    .replace(/\b(id|filename|token|expires|asn)\b\s*:/g, '"$1":')
    .replace(/,\s*}/g, '}')
    .trim();
}

async function extractVixcloud(url) {
  let parsed;
  try { parsed = new URL(url); } catch { return null; }
  if (parsed.hostname !== 'vixcloud.co') return null;
  const referer = parsed.origin + '/';
  const response = await getText(url, {
    Referer: referer,
    'User-Agent': USER_AGENT,
    'Accept-Language': 'it-IT,it;q=0.9,en-US;q=0.8,en;q=0.7',
  });
  if (!response.ok) return null;
  const script = [...response.text.matchAll(/<script\b[^>]*>([\s\S]*?)<\/script>/gi)].map(m => m[1]).find(s => s.includes('window.video') && s.includes('window.masterPlaylist')) || response.text;
  let videoRaw = /window\.video\s*=\s*([\s\S]*?);/i.exec(script)?.[1] || /window\.video\s*=\s*([\s\S]*?)window\.masterPlaylist/i.exec(script)?.[1];
  if (!videoRaw) return null;
  videoRaw = sanitizeVixcloudObject(videoRaw);
  if (!videoRaw.startsWith('{') && videoRaw.includes(':')) videoRaw = '{' + videoRaw;
  if (!videoRaw.endsWith('}') && videoRaw.includes(':')) videoRaw += '}';
  let video;
  try { video = JSON.parse(videoRaw); } catch { return null; }
  const paramsRaw = /window\.masterPlaylist[\s\S]*?params\s*:\s*\{([\s\S]*?)\}/i.exec(script)?.[1] || '';
  const params = {};
  for (const match of paramsRaw.matchAll(/(token|expires)\s*:\s*['"]([^'"]+)['"]/gi)) params[match[1].toLowerCase()] = match[2];
  if (!params.token) params.token = /token\s*:\s*['"]([^'"]+)['"]/i.exec(script)?.[1];
  if (!params.expires) params.expires = /expires\s*:\s*['"]([^'"]+)['"]/i.exec(script)?.[1];
  if (!video?.id || !params.token || !params.expires) return null;
  if (/url\s*:[^,]*b=1/i.test(script)) params.b = '1';
  if (parsed.searchParams.has('canPlayFHD')) params.h = '1';
  const language = parsed.searchParams.get('language') || parsed.searchParams.get('lang');
  if (language) params.language = language;
  const playlistUrl = new URL('/playlist/' + encodeURIComponent(video.id), parsed.origin);
  playlistUrl.search = new URLSearchParams(params);
  const headers = { Referer: referer, 'User-Agent': USER_AGENT };
  if (language) {
    headers['Accept-Language'] = language === 'en' ? 'en-US,en;q=0.9' : 'it-IT,it;q=0.9';
    headers.Cookie = 'language=' + language;
    const playlist = await getText(playlistUrl.href, headers);
    if (playlist.ok) {
      const manifest = patchVixsrcManifest(playlist.text, playlist.url, language);
      return { url: 'data:application/vnd.apple.mpegurl;base64,' + Buffer.from(manifest).toString('base64'), type: 'm3u8', headers };
    }
  }
  return { url: playlistUrl.href, type: 'm3u8', headers };
}

module.exports = { extractVixcloud, sanitizeVixcloudObject };