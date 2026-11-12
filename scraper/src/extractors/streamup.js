const { getText } = require('../lib/http');

async function extractStreamUp(url) {
  let parsed;
  try { parsed = new URL(url); } catch { return null; }
  if (parsed.hostname !== 'strmup.to') return null;
  const fileCode = parsed.pathname.split('/').filter(Boolean).pop();
  if (!fileCode) return null;
  const endpoint = new URL('/ajax/stream', parsed.origin);
  endpoint.search = new URLSearchParams({ filecode: fileCode });
  const response = await getText(endpoint.href, { Referer: parsed.origin + '/v/' + fileCode, Accept: 'application/json, text/plain, */*' });
  if (!response.ok) return null;
  let json;
  try { json = JSON.parse(response.text); } catch { return null; }
  if (typeof json.streaming_url !== 'string' || !json.streaming_url) return null;
  const defaultLanguage = json.default_sub_lang || '';
  let selected = false;
  const subtitles = (json.subtitles || []).filter(s => s && s.file_path).map(s => {
    const isDefault = !selected && defaultLanguage && String(s.language || '').includes(defaultLanguage);
    if (isDefault) selected = true;
    return { label: s.language || '', url: s.file_path, default: Boolean(isDefault) };
  });
  return { url: json.streaming_url, subtitles };
}
module.exports = { extractStreamUp };