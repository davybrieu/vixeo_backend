const { getText, postForm } = require('../lib/http');
const { normalizeText } = require('../lib/util');

const USER_AGENT = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36';

async function extractDeltaBit(url) {
  let parsed;
  try { parsed = new URL(url); } catch { return null; }
  if (parsed.hostname !== 'deltabit.co') return null;
  const first = await getText(url, { 'User-Agent': USER_AGENT, 'Accept-Language': 'it-IT,it;q=0.9,en-US;q=0.8,en;q=0.7' });
  if (!first.ok) return null;
  const html = normalizeText(first.text);
  const read = name => new RegExp("name=[\\"']" + name + "[\\"']\\s+value=[\\"']([^\\"']+)[\\"']", 'i').exec(html)?.[1] || '';
  const hash = read('hash');
  if (!hash) return null;
  const id = read('id') || new URL(first.url).pathname.replace(/\/$/, '').split('/').pop();
  const fname = read('fname');
  const fileId = hash.split('-')[0];
  await new Promise(resolve => setTimeout(resolve, 5500));
  const endpoint = new URL('/' + encodeURIComponent(id), 'https://deltabit.co');
  const cookie = 'file_id=' + fileId + '; aff=5; ref_url=' + first.url;
  const second = await postForm(endpoint.href, {
    op: 'download1', usr_login: '', id, fname, referer: '', hash, imhuman: '',
  }, {
    'User-Agent': USER_AGENT,
    'Accept-Language': 'it-IT,it;q=0.9,en-US;q=0.8,en;q=0.7',
    Origin: 'https://deltabit.co',
    Referer: first.url,
    Cookie: cookie,
  });
  if (!second.ok) return null;
  const source = /sources\s*:\s*\[\s*["']([^"']+)["']/i.exec(normalizeText(second.text))?.[1];
  return source ? { url: new URL(source, second.url).href, headers: { 'User-Agent': USER_AGENT, Referer: 'https://deltabit.co/' } } : null;
}
module.exports = { extractDeltaBit };