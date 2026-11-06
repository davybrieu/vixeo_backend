async function extractUqload(url) {
  const res = await fetch(url, {
    headers: { 'User-Agent': 'Mozilla/5.0', 'Referer': 'https://uqload.com/' },
  });
  const html = await res.text();
  const m = html.match(/sources:\s*\[\s*["'](https?:[^"']+)["']/);
  if (m) return { url: m[1], type: m[1].includes('.m3u8') ? 'm3u8' : 'mp4' };
  return null;
}
module.exports = { extractUqload };
