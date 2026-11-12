const { getText } = require('../lib/http');

async function extractTwoEmbed(url) {
  let parsed;
  try { parsed = new URL(url); } catch { return null; }
  if (!['2embed.cc', 'www.2embed.cc'].includes(parsed.hostname)) return null;
  const response = await getText(url);
  if (!response.ok) return null;
  const iframe = /<iframe\b[^>]*data-src=["']([^"']+)["']/i.exec(response.text)?.[1]
    || /<iframe\b[^>]*src=["']([^"']+)["']/i.exec(response.text)?.[1];
  if (!iframe) return null;
  const iframeUrl = new URL(iframe, response.url);
  const id = iframeUrl.searchParams.get('id') || iframeUrl.pathname.split('/').filter(Boolean).pop();
  if (!id) return null;
  const target = 'https://uqloads.xyz/e/' + encodeURIComponent(id);
  const { extract } = require('./index');
  const result = await extract(target);
  return result ? { ...result, headers: { ...(result.headers || {}), Referer: iframeUrl.origin } } : null;
}
module.exports = { extractTwoEmbed };