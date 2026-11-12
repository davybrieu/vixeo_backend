const { getText } = require('../lib/http');
const { normalizeText } = require('../lib/util');

async function extractRidoo(url) {
  let parsed;
  try { parsed = new URL(url); } catch { return null; }
  if (parsed.hostname !== 'ridoo.net') return null;
  const response = await getText(url, {
    Referer: 'https://ridomovies.tv/',
    'User-Agent': 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:140.0) Gecko/20100101 Firefox/140.0',
  });
  if (!response.ok) return null;
  const source = /file\s*:\s*"([^"]+\.m3u8[^"]*)"/i.exec(normalizeText(response.text))?.[1];
  return source ? { url: new URL(source, response.url).href, type: 'm3u8', headers: { Referer: 'https://ridoo.net/', 'User-Agent': 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:140.0) Gecko/20100101 Firefox/140.0', Accept: '*/*', 'Accept-Language': 'de,en-US;q=0.7,en;q=0.3', Origin: 'https://ridoo.net' } } : null;
}
module.exports = { extractRidoo };