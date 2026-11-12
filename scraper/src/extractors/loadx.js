const { getText, request } = require('../lib/http');

const USER_AGENT = 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10.15; rv:139.0) Gecko/20100101 Firefox/139.0';

async function extractLoadX(url) {
  let parsed;
  try { parsed = new URL(url); } catch { return null; }
  if (parsed.hostname !== 'loadx.ws') return null;
  const first = await getText(url, { 'User-Agent': USER_AGENT });
  if (!first.ok) return null;
  const cookie = first.setCookies.map(c => c.split(';')[0]).find(c => c.startsWith('fireplayer_player='));
  if (!cookie) return null;
  const id = parsed.pathname.split('/').filter(Boolean).pop();
  if (!id) return null;
  const endpoint = new URL('/player/index.php', parsed.origin);
  endpoint.search = new URLSearchParams({ data: id, do: 'getVideo' });
  const second = await request(endpoint.href, {
    method: 'POST',
    headers: {
      'User-Agent': USER_AGENT,
      Referer: parsed.origin,
      Origin: parsed.origin,
      Cookie: cookie,
      Accept: '*/*',
      'X-Requested-With': 'XMLHttpRequest',
      'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
      'Accept-Language': 'en-US,en;q=0.5',
    },
    body: '',
  });
  if (!second.ok) return null;
  let json;
  try { json = JSON.parse(second.text); } catch { return null; }
  if (typeof json.videoSource !== 'string' || !json.videoSource) return null;
  return { url: json.videoSource, type: 'm3u8', headers: { 'User-Agent': USER_AGENT, Referer: parsed.origin, Origin: parsed.origin, Accept: '*/*', Cookie: cookie } };
}
module.exports = { extractLoadX };