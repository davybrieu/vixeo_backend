const { getText } = require('../lib/http');

async function extractVidflix(url) {
  let parsed;
  try { parsed = new URL(url); } catch { return null; }
  if (parsed.hostname !== 'vidflix.club') return null;
  const response = await getText(url, { Referer: url.replace('/api/', '/') , Accept: 'application/json' });
  if (!response.ok) return null;
  let json;
  try { json = JSON.parse(response.text); } catch { return null; }
  if (!json.video_url) return null;
  const { extract } = require('./index');
  const result = await extract(json.video_url);
  if (!result) return null;
  return {
    ...result,
    subtitles: (json.subtitles || []).filter(s => s && s.url).map(s => ({ label: s.label || '', url: s.url, default: Boolean(s.default) })),
  };
}
module.exports = { extractVidflix };