const { getText } = require('../lib/http');

const MAIN_URL = 'https://videm.xyz';
const USER_AGENT = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36';

async function extractVidem(url) {
  let parsed;
  try { parsed = new URL(url); } catch { return null; }
  if (parsed.hostname !== 'videm.xyz') return null;
  const response = await getText(url, { Referer: MAIN_URL + '/', 'User-Agent': USER_AGENT, Accept: 'application/json' });
  if (!response.ok) return null;
  let json;
  try { json = JSON.parse(response.text); } catch { return null; }
  const path = String(json.url || '').trim();
  if (!path) return null;
  return { url: new URL(path, MAIN_URL).href, type: 'm3u8', headers: { Referer: MAIN_URL + '/', 'User-Agent': USER_AGENT } };
}
module.exports = { extractVidem };