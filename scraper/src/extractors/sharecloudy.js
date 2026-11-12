const { getText } = require('../lib/http');
const { normalizeText } = require('../lib/util');

async function extractShareCloudy(url) {
  let parsed;
  try { parsed = new URL(url); } catch { return null; }
  if (parsed.hostname !== 'sharecloudy.com') return null;
  const response = await getText(url, { Referer: parsed.origin });
  if (!response.ok) return null;
  const source = /file\s*:\s*"([^"]+)"/i.exec(normalizeText(response.text))?.[1];
  return source ? { url: new URL(source, response.url).href, headers: { Referer: parsed.origin } } : null;
}
module.exports = { extractShareCloudy };