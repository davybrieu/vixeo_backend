const { getText } = require('../lib/http');
const { normalizeText } = require('../lib/util');

const DOMAIN = 'watch.gxplayer.xyz';
const USER_AGENT = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36';

async function extractGxPlayer(url) {
  let parsed;
  try { parsed = new URL(url); } catch { return null; }
  if (parsed.hostname !== DOMAIN) return null;
  const response = await getText(url, { 'User-Agent': USER_AGENT, Referer: 'https://' + DOMAIN });
  if (!response.ok) return null;
  const html = normalizeText(response.text);
  const script = [...html.matchAll(/<script\b[^>]*>([\s\S]*?)<\/script>/gi)].map(m => m[1]).find(s => s.includes('var video ='));
  if (!script) return null;
  const read = key => new RegExp('"'+key+'"\\s*:\\s*"([^"]+)"').exec(script)?.[1] || null;
  const id = read('id'), uid = read('uid'), md5 = read('md5'), status = read('status') || '';
  if (!id || !uid || !md5) return null;
  return {
    url: 'https://' + DOMAIN + '/m3u8/' + encodeURIComponent(uid) + '/' + encodeURIComponent(md5) + '/master.txt?s=1&id=' + encodeURIComponent(id) + '&cache=' + encodeURIComponent(status),
    type: 'm3u8',
    headers: { 'User-Agent': USER_AGENT, Referer: 'https://' + DOMAIN },
  };
}
module.exports = { extractGxPlayer };