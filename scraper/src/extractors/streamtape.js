async function extractStreamtape(url) {
  const res = await fetch(url, {
    headers: { 'User-Agent': 'Mozilla/5.0', 'Referer': 'https://streamtape.com/' },
  });
  const html = await res.text();
  const m = html.match(/document\.getElementById\(['"]robotlink['"]\)\.innerHTML\s*=\s*['"]([^'"]+)['"]/);
  if (m) {
    let link = m[1];
    if (link.startsWith('//')) link = 'https:' + link;
    else if (!link.startsWith('http')) link = 'https://streamtape.com' + link;
    return { url: link, type: 'mp4' };
  }
  const m2 = html.match(/https?:\/\/[^"'\s]+\.mp4[^"'\s]*/);
  if (m2) return { url: m2[0], type: 'mp4' };
  return null;
}
module.exports = { extractStreamtape };
