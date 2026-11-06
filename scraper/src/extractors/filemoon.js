async function extractFilemoon(url) {
  const res = await fetch(url, {
    headers: { 'User-Agent': 'Mozilla/5.0', 'Referer': 'https://filemoon.sx/' },
  });
  const html = await res.text();
  const m = html.match(/file:\s*["'](https?:[^"']+\.m3u8[^"']*)["']/);
  if (m) return { url: m[1], type: 'm3u8' };
  const m2 = html.match(/sources:\s*\[\s*\{[^}]*file:\s*["']([^"']+)["']/);
  if (m2) return { url: m2[1], type: m2[1].includes('.m3u8') ? 'm3u8' : 'mp4' };
  return null;
}
module.exports = { extractFilemoon };
