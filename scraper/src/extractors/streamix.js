const { getText } = require('../lib/http');

const DOMAINS = ['streamix.so', 'stmix.io'];

async function extractStreamix(url) {
  let parsed;
  try { parsed = new URL(url); } catch { return null; }
  if (!DOMAINS.includes(parsed.hostname)) return null;
  const code = parsed.pathname.split('/').filter(Boolean).pop();
  if (!code) return null;
  const endpoint = new URL('/ajax/stream', parsed.origin);
  endpoint.search = new URLSearchParams({ filecode: code });
  const response = await getText(endpoint.href, { Accept: 'application/json', Referer: parsed.origin + '/' });
  if (!response.ok) return null;
  let json;
  try { json = JSON.parse(response.text); } catch { return null; }
  return typeof json.streaming_url === 'string' && json.streaming_url ? { url: json.streaming_url } : null;
}
module.exports = { extractStreamix };