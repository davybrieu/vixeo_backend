const { getText } = require('../lib/http');

async function extractMovieMaster(url) {
  let parsed;
  try { parsed = new URL(url); } catch { return null; }
  if (parsed.hostname !== 'api.moviemaster.lat') return null;
  const response = await getText(url, {
    Referer: 'https://api.moviemaster.lat/',
    'User-Agent': 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
    Accept: '*/*',
  });
  if (!response.ok) return null;
  let json;
  try { json = JSON.parse(response.text); } catch { return null; }
  return typeof json.url === 'string' && json.url ? { url: json.url, type: 'm3u8', headers: { Referer: 'https://api.moviemaster.lat/' } } : null;
}
module.exports = { extractMovieMaster };