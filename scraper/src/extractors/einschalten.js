const { getText } = require('../lib/http');

async function extractEinschalten(url) {
  let parsed;
  try { parsed = new URL(url); } catch { return null; }
  if (parsed.hostname !== 'einschalten.in') return null;
  const response = await getText(url, { 'User-Agent': 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36', Accept: 'application/json' });
  if (!response.ok) return null;
  let json;
  try { json = JSON.parse(response.text); } catch { return null; }
  if (typeof json.streamUrl !== 'string' || !json.streamUrl) return null;
  const { extract } = require('./index');
  return extract(json.streamUrl);
}
module.exports = { extractEinschalten };