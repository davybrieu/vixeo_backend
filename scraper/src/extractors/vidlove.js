const { getText } = require('../lib/http');

async function extractVidlove(url) {
  let parsed;
  try { parsed = new URL(url); } catch { return null; }
  if (parsed.hostname !== 'api.vidlove.cc') return null;
  const response = await getText(url, { Origin: 'https://player.vidlove.cc', Referer: 'https://player.vidlove.cc/', Accept: 'application/json' });
  if (!response.ok) return null;
  let json;
  try { json = JSON.parse(response.text); } catch { return null; }
  const source = json?.source?.url;
  if (typeof source !== 'string' || !source) return null;
  const subtitles = (json.subtitles || []).filter(item => item?.file)
    .map(item => ({ label: item.label || 'Unknown', url: item.file }));
  return { url: source, type: 'm3u8', subtitles, headers: { Referer: 'https://player.vidlove.cc/' } };
}
module.exports = { extractVidlove };