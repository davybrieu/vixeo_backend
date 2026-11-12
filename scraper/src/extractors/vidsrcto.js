const { getText } = require('../lib/http');
const { rc4, encodeVidplayId } = require('./vidplay');

const MAIN_URL = 'https://vidsrc.to';
const KEY_URL = 'https://raw.githubusercontent.com/Ciarands/vidsrc-keys/main/keys.json';

function decodeEncryptedEmbed(key, encoded) {
  const bytes = Buffer.from(String(encoded).replace(/_/g, '/').replace(/-/g, '+'), 'base64');
  const decoded = rc4(key, bytes).toString('utf8');
  try { return decodeURIComponent(decoded.replace(/\+/g, '%20')); } catch { return decoded; }
}

async function extractVidsrcTo(url) {
  let parsed;
  try { parsed = new URL(url); } catch { return null; }
  if (parsed.hostname !== 'vidsrc.to') return null;
  const page = await getText(url, { Referer: MAIN_URL + '/' });
  if (!page.ok) return null;
  const mediaId = /<a\b[^>]*data-id=["']([^"']+)["'][^>]*>/i.exec(page.text)?.[1]
    || /data-id=["']([^"']+)["']/i.exec(page.text)?.[1];
  if (!mediaId) return null;
  const keysResponse = await getText(KEY_URL, { Accept: 'application/json' });
  if (!keysResponse.ok) return null;
  let keys;
  try { keys = JSON.parse(keysResponse.text); } catch { return null; }
  if (!keys.encrypt?.[0] || !keys.decrypt?.[0]) return null;
  const sourceUrl = new URL('/ajax/embed/episode/' + encodeURIComponent(mediaId) + '/sources', MAIN_URL);
  sourceUrl.searchParams.set('token', encodeVidplayId(keys.encrypt[0], mediaId));
  const sourcesResponse = await getText(sourceUrl.href, { Referer: url, Accept: 'application/json' });
  if (!sourcesResponse.ok) return null;
  let sourcesJson;
  try { sourcesJson = JSON.parse(sourcesResponse.text); } catch { return null; }
  const sources = sourcesJson?.result;
  if (!Array.isArray(sources) || !sources.length) return null;
  for (const source of sources) {
    if (!source?.id) continue;
    const embedUrl = new URL('/ajax/embed/source/' + encodeURIComponent(source.id), MAIN_URL);
    embedUrl.searchParams.set('token', encodeVidplayId(keys.encrypt[0], source.id));
    const embedResponse = await getText(embedUrl.href, { Referer: url, Accept: 'application/json' });
    if (!embedResponse.ok) continue;
    let embedJson;
    try { embedJson = JSON.parse(embedResponse.text); } catch { continue; }
    const encryptedUrl = embedJson?.result?.url;
    if (!encryptedUrl) continue;
    const finalUrl = decodeEncryptedEmbed(keys.decrypt[0], encryptedUrl);
    if (!finalUrl || finalUrl === encryptedUrl) continue;
    const { extract } = require('./index');
    const resolved = await extract(finalUrl);
    if (!resolved) continue;
    const subtitlesResponse = await getText(new URL('/ajax/embed/episode/' + encodeURIComponent(mediaId) + '/subtitles', MAIN_URL).href, { Referer: url, Accept: 'application/json' });
    let subtitles = [];
    if (subtitlesResponse.ok) {
      try {
        const list = JSON.parse(subtitlesResponse.text);
        subtitles = (Array.isArray(list) ? list : list?.result || []).filter(item => item?.file)
          .map(item => ({ label: item.label || '', url: item.file }));
      } catch {}
    }
    return { ...resolved, subtitles };
  }
  return null;
}

module.exports = { extractVidsrcTo, decodeEncryptedEmbed };