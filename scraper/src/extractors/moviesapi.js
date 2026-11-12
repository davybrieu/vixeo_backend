const { getText } = require('../lib/http');

async function extractMoviesapi(url) {
  let parsed;
  try { parsed = new URL(url); } catch { return null; }
  if (parsed.hostname !== 'moviesapi.club') return null;
  const response = await getText(url, { Referer: 'https://pressplay.top/' });
  if (!response.ok) return null;
  const iframe = /<iframe\b[^>]*src=["']([^"']+)["']/i.exec(response.text)?.[1];
  if (iframe) {
    const target = new URL(iframe, response.url).href;
    const { extract } = require('./index');
    const result = await extract(target);
    if (result) return { ...result, headers: { ...(result.headers || {}), Referer: response.url } };
  }
  // Streamflix also exposes Moviesapi as a Chillx-compatible AES-CBC host.
  const { extractChillx } = require('./chillx');
  return extractChillx(url);
}
module.exports = { extractMoviesapi };