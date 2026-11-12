const { getText } = require('../lib/http');

const MAIN_URL = 'https://primesrc.me';

async function extractPrimeSrc(url) {
  let parsed;
  try { parsed = new URL(url); } catch { return null; }
  if (parsed.hostname !== 'primesrc.me') return null;
  const response = await getText(url, { Referer: MAIN_URL + '/', Accept: 'application/json' });
  if (!response.ok) return null;
  let json;
  try { json = JSON.parse(response.text); } catch { return null; }
  if (typeof json.link !== 'string' || !json.link) return null;
  const { extract } = require('./index');
  const result = await extract(json.link);
  return result ? { ...result, headers: { ...(result.headers || {}), Referer: MAIN_URL + '/' } } : null;
}
module.exports = { extractPrimeSrc };