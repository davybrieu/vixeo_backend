// Vidara (vidara.to and mirrors): the player exposes a JSON API at /api/stream.
// Port of ONYX VidaraExtractor.
const { postJson, getText } = require('../lib/http');
const { originOf, mediaType } = require('../lib/util');

const USER_AGENT =
  'Mozilla/5.0 (Linux; Android 14; Pixel 8) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Mobile Safari/537.36';

function filecodeFrom(url) {
  const clean = url.split('#')[0].split('?')[0].replace(/\/+$/, '');
  return clean.split('/').pop();
}

async function callStream(origin, filecode) {
  const res = await postJson(
    `${origin}/api/stream`,
    { filecode, device: 'android' },
    {
      'User-Agent': USER_AGENT,
      Referer: `${origin}/`,
      Origin: origin,
      Accept: 'application/json, text/plain, */*',
      'Accept-Language': 'fr-FR,fr;q=0.9',
      'X-Requested-With': 'XMLHttpRequest',
    },
  );
  if (!res.ok) return null;
  try {
    return JSON.parse(res.text);
  } catch (e) {
    return null;
  }
}

function frenchLabel(language) {
  const lang = language || 'Inconnu';
  if (/french/i.test(lang)) {
    return /forced/i.test(lang) ? 'Français (forcé)' : 'Français';
  }
  if (/english/i.test(lang)) return 'Anglais';
  return lang;
}

async function extractVidara(url) {
  const origin = originOf(url);
  const filecode = filecodeFrom(url);
  if (!origin || !filecode) return null;

  let host = origin;
  let data = await callStream(host, filecode);

  if (!data) {
    // The mirror may have moved: follow the redirect to find the real host.
    const page = await getText(url, { 'User-Agent': USER_AGENT });
    const realOrigin = originOf(page.url);
    if (realOrigin && realOrigin !== origin) {
      host = realOrigin;
      data = await callStream(host, filecode);
    }
  }

  if (!data || data.error) return null;

  const stream = data.streaming_url;
  if (!stream || !/^https?:/i.test(stream)) return null;

  const subtitles = [];
  if (!/\/uploads\/[^/]+\.mp4($|\?)/.test(stream)) {
    for (const sub of data.subtitles || []) {
      if (sub && sub.status === 1 && /^https?:/i.test(sub.file_path || '')) {
        subtitles.push({ url: sub.file_path, language: frenchLabel(sub.language) });
      }
    }
  }

  return {
    url: stream,
    type: mediaType(stream) || 'mp4',
    headers: { Referer: `${host}/`, Origin: host, 'User-Agent': USER_AGENT },
    subtitles,
  };
}

module.exports = { extractVidara };
