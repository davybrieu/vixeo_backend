const { getText } = require('../lib/http');

const MAIN_URL = 'https://vixsrc.to';
const USER_AGENT = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0.0.0 Safari/537.36';

function preferredLanguageMatch(line, lang) {
  if (line.includes('LANGUAGE="' + lang + '"') || line.includes('NAME="' + lang + '"')) return true;
  if (lang === 'fr') return /French|Français|\bfre\b|\bfra\b/i.test(line);
  if (lang === 'es') return /Spanish|Español|Castellano|\bspa\b/i.test(line);
  if (lang === 'it') return /Italian|\bita\b/i.test(line);
  if (lang === 'en') return /English|\beng\b/i.test(line);
  return false;
}

function patchVixsrcManifest(content, baseUrl, lang = 'en') {
  const lines = String(content).split(/\r?\n/);
  const patched = [];
  for (let line of lines) {
    if (line.startsWith('#')) {
      line = line.replace(/URI=["']([^"']+)["']/g, (full, relative) => {
        if (/^(?:https?:|data:)/i.test(relative)) return full;
        return 'URI="' + new URL(relative, baseUrl).href + '"';
      });
    } else if (line.trim()) {
      line = new URL(line, baseUrl).href;
    }
    if (line.startsWith('#EXT-X-MEDIA:TYPE=AUDIO')) {
      line = line.replace(/DEFAULT=YES/gi, 'DEFAULT=NO').replace(/AUTOSELECT=YES/gi, 'AUTOSELECT=NO');
      if (preferredLanguageMatch(line, lang)) line = line.replace('DEFAULT=NO', 'DEFAULT=YES').replace('AUTOSELECT=NO', 'AUTOSELECT=YES');
    } else if (line.startsWith('#EXT-X-MEDIA:TYPE=SUBTITLES')) {
      const name = /NAME="([^"]+)"/i.exec(line)?.[1] || '';
      const language = /LANGUAGE="([^"]+)"/i.exec(line)?.[1] || '';
      const forced = /forced/i.test(name + ' ' + language) || /FORCED=YES/i.test(line);
      line = line.replace(/DEFAULT=YES/gi, 'DEFAULT=NO').replace(/AUTOSELECT=YES/gi, 'AUTOSELECT=NO');
      if (forced && preferredLanguageMatch('LANGUAGE="' + language + '" NAME="' + name + '"', lang)) {
        line = line.replace('DEFAULT=NO', 'DEFAULT=YES').replace('AUTOSELECT=NO', 'AUTOSELECT=YES');
      }
    }
    patched.push(line);
  }
  return patched.join('\n');
}

async function extractVixSrc(url) {
  let parsed;
  try { parsed = new URL(url); } catch { return null; }
  if (parsed.hostname !== 'vixsrc.to') return null;
  let apiPath = parsed.pathname.replace(/^\/+/, '');
  if (!apiPath.startsWith('api/')) apiPath = 'api/' + apiPath;
  const lang = parsed.searchParams.get('lang') || 'en';
  const apiUrl = new URL(apiPath, MAIN_URL);
  apiUrl.search = new URLSearchParams({ ...Object.fromEntries(parsed.searchParams), lang });
  const apiResponse = await getText(apiUrl.href, {
    'User-Agent': USER_AGENT, Accept: 'application/json, text/plain, */*',
    'X-Requested-With': 'XMLHttpRequest', Referer: MAIN_URL,
  });
  if (!apiResponse.ok) return null;
  let apiJson;
  try { apiJson = JSON.parse(apiResponse.text); } catch { return null; }
  const embedPath = String(apiJson.src || '').replace(/^\/+/, '');
  if (!embedPath) return null;
  const embedUrl = new URL(embedPath, MAIN_URL).href;
  const embed = await getText(embedUrl, {
    'User-Agent': USER_AGENT, Accept: 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
    'X-Requested-With': 'XMLHttpRequest', Referer: MAIN_URL,
  });
  if (!embed.ok) return null;
  const script = [...embed.text.matchAll(/<script\b[^>]*>([\s\S]*?)<\/script>/gi)].map(m => m[1]).find(s => s.includes('window.video = {') && s.includes('window.masterPlaylist')) || embed.text;
  const videoId = /window\.video\s*=\s*\{[\s\S]*?id:\s*['"]([^'"]+)['"]/i.exec(script)?.[1];
  const token = /window\.masterPlaylist[\s\S]*?['"]token['"]\s*:\s*['"]([^'"]+)['"]/i.exec(script)?.[1];
  const expires = /window\.masterPlaylist[\s\S]*?['"]expires['"]\s*:\s*['"]([^'"]+)['"]/i.exec(script)?.[1];
  if (!videoId || !token || !expires) return null;
  const masterBlock = script.split('window.masterPlaylist')[1] || '';
  const hasB = /url\s*:[^,]*b=1/i.test(masterBlock);
  const canPlayFhd = /window\.canPlayFHD\s*=\s*true/i.test(script);
  const playlistUrl = new URL('/playlist/' + encodeURIComponent(videoId), MAIN_URL);
  playlistUrl.search = new URLSearchParams({
    token, expires, ...(hasB ? { b: '1' } : {}), ...(canPlayFhd ? { h: '1' } : {}), lang,
  });
  const playlist = await getText(playlistUrl.href, { 'User-Agent': USER_AGENT, Referer: embedUrl });
  if (!playlist.ok) return { url: playlistUrl.href, type: 'm3u8', headers: { Referer: embedUrl, 'User-Agent': USER_AGENT } };
  const patched = patchVixsrcManifest(playlist.text, playlist.url, lang);
  return {
    url: 'data:application/vnd.apple.mpegurl;base64,' + Buffer.from(patched).toString('base64'),
    type: 'm3u8',
    headers: { Referer: embedUrl, 'User-Agent': USER_AGENT },
  };
}

module.exports = { extractVixSrc, patchVixsrcManifest, preferredLanguageMatch };