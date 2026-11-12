const { getText } = require('../lib/http');

async function extractMailRu(url) {
  let parsed;
  try { parsed = new URL(url); } catch { return null; }
  if (parsed.hostname !== 'my.mail.ru' && !parsed.hostname.endsWith('.mail.ru')) return null;
  const id = /\/embed\/(\d+)/.exec(parsed.pathname)?.[1];
  if (!id) return null;
  const endpoint = new URL('/+/video/meta/' + id, 'https://my.mail.ru');
  endpoint.search = new URLSearchParams({ xemail: '', ajax_call: '1', func_name: '', mna: '', mnb: '', ext: '1', _: String(Date.now()) });
  const response = await getText(endpoint.href, { Referer: url, Accept: 'application/json, text/javascript, */*; q=0.01' });
  if (!response.ok) return null;
  let json;
  try { json = JSON.parse(response.text); } catch { return null; }
  const video = (json.videos || []).find(item => typeof item?.url === 'string' && item.url);
  if (!video) return null;
  return { url: video.url.startsWith('//') ? 'https:' + video.url : video.url, headers: { Referer: 'https://my.mail.ru/' } };
}
module.exports = { extractMailRu };