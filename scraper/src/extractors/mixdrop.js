async function extractMixDrop(url) {
  const res = await fetch(url, {
    headers: { 'User-Agent': 'Mozilla/5.0', 'Referer': 'https://mixdrop.co/' },
  });
  const html = await res.text();
  const m = html.match(/MDCore\.wurl\s*=\s*["']([^"']+)["']/);
  if (m) {
    let link = m[1];
    if (link.startsWith('//')) link = 'https:' + link;
    return { url: link, type: 'mp4' };
  }
  return null;
}
module.exports = { extractMixDrop };
