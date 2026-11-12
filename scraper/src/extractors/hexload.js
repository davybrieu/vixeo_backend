const { postForm } = require('../lib/http');
const { decodeHtmlEntities } = require('../lib/util');

async function extractHexload(url) {
  let parsed;
  try { parsed = new URL(url); } catch { return null; }
  if (parsed.hostname !== 'hexload.com' && !parsed.hostname.endsWith('.hexload.com')) return null;
  const segments = parsed.pathname.split('/').filter(Boolean);
  const segment = segments.find(s => s.startsWith('embed-')) || segments.find(s => !['e','d','embed'].includes(s));
  const id = segment?.replace(/^embed-/, '').split('.')[0];
  if (!id) return null;
  const response = await postForm(new URL('/download', parsed.origin).href, {
    op: 'download3', id, ajax: '1', method_free: '1', dataType: 'json',
  }, { 'X-Requested-With': 'XMLHttpRequest', Referer: url, Accept: 'application/json, text/javascript, */*; q=0.01' });
  if (!response.ok) return null;
  let json;
  try { json = JSON.parse(response.text); } catch { return null; }
  const source = json?.result?.url;
  if (typeof source !== 'string' || !/^https?:\/\//i.test(decodeHtmlEntities(source))) return null;
  return { url: decodeHtmlEntities(source), headers: { Referer: url } };
}
module.exports = { extractHexload };