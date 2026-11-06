async function extractStreamWish(url) {
  const res = await fetch(url, {
    headers: { 'User-Agent': 'Mozilla/5.0', 'Referer': new URL(url).origin + '/' },
  });
  const html = await res.text();
  const m = html.match(/sources:\s*\[\s*\{[^}]*file:\s*["']([^"']+)["']/);
  if (m) return { url: m[1], type: m[1].includes('.m3u8') ? 'm3u8' : 'mp4' };
  const m2 = html.match(/file:\s*["'](https?:[^"']+\.m3u8[^"']*)["']/);
  if (m2) return { url: m2[1], type: 'm3u8' };
  return null;
}
module.exports = { extractStreamWish };
