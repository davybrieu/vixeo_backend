const { getText } = require('../lib/http');

const HOST = 'apu.animemovil2.com';

function findPlusPomlaAjaxUrl(html) {
  const value = /\$\.ajax\s*\(\s*\{\s*url\s*:\s*['"]([^'"]+)['"]/i.exec(html)?.[1];
  if (!value) return null;
  try { return new URL(value, 'https://apu.animemovil2.com').href; } catch { return null; }
}

function findPlusPomlaSource(payload) {
  let json;
  try { json = typeof payload === 'string' ? JSON.parse(payload) : payload; } catch { return null; }
  const sources = Array.isArray(json?.sources) ? json.sources : [];
  for (const source of sources) {
    if (typeof source?.file === 'string' && /^https?:\/\//i.test(source.file)) return source.file;
  }
  return null;
}

async function extractPlusPomla(url) {
  let parsed;
  try { parsed = new URL(url); } catch { return null; }
  if (parsed.hostname !== HOST) return null;
  const page = await getText(url, { 'User-Agent': 'Mozilla/5.0', Accept: 'text/html,*/*' });
  if (!page.ok) return null;
  const ajaxUrl = findPlusPomlaAjaxUrl(page.text);
  if (!ajaxUrl) return null;
  const data = await getText(ajaxUrl, {
    Referer: url,
    Accept: 'application/json, text/plain, */*',
    'User-Agent': 'Mozilla/5.0',
  });
  if (!data.ok) return null;
  const source = findPlusPomlaSource(data.text);
  return source ? { url: source, headers: { Referer: url } } : null;
}

module.exports = { extractPlusPomla, findPlusPomlaAjaxUrl, findPlusPomlaSource };
