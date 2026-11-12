const { getText } = require('../lib/http');

async function extractPcloud(url) {
  let parsed;
  try { parsed = new URL(url); } catch { return null; }
  if (parsed.hostname !== 'u.pcloud.link') return null;
  const code = parsed.searchParams.get('code');
  if (!code) return null;
  const endpoint = new URL('getpublinkdownload', 'https://api.pcloud.com/');
  endpoint.search = new URLSearchParams({ code });
  const response = await getText(endpoint.href, { Accept: 'application/json' });
  if (!response.ok) return null;
  let json;
  try { json = JSON.parse(response.text); } catch { return null; }
  if (!json.path || !Array.isArray(json.hosts) || !json.hosts[0]) return null;
  return { url: 'https://' + json.hosts[0] + json.path };
}
module.exports = { extractPcloud };