const { mediaType } = require('../lib/util');

async function extractZilla(url) {
  let parsed;
  try { parsed = new URL(url); } catch { return null; }
  if (parsed.hostname !== 'player.zilla-networks.com') return null;
  const id = parsed.pathname.split('/').filter(Boolean).pop();
  if (!id) return null;
  const origin = 'https://player.zilla-networks.com';
  return {
    url: origin + '/m3u8/' + encodeURIComponent(id),
    type: 'm3u8',
    headers: {
      'User-Agent': 'Mozilla/5.0 (X11; Linux x86_64; rv:148.0) Gecko/20100101 Firefox/148.0',
      Accept: '*/*',
      'Accept-Language': 'es-MX,es-ES;q=0.9,es;q=0.8,en-US;q=0.7,en;q=0.6',
      Referer: url,
      Origin: origin,
      Connection: 'keep-alive',
      'Sec-Fetch-Dest': 'empty',
      'Sec-Fetch-Mode': 'cors',
      'Sec-Fetch-Site': 'same-origin',
    },
  };
}
module.exports = { extractZilla };