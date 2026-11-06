async function extractDood(url) {
  const res = await fetch(url, {
    headers: { 'User-Agent': 'Mozilla/5.0', 'Referer': new URL(url).origin + '/' },
  });
  const html = await res.text();
  const passMd5 = html.match(/\/pass_md5\/([^"']+)/);
  if (!passMd5) return null;
  const tokenRes = await fetch(new URL(url).origin + passMd5[0], {
    headers: { 'User-Agent': 'Mozilla/5.0', 'Referer': url },
  });
  const token = (await tokenRes.text()).trim();
  if (!token.startsWith('http')) return null;
  const random = Math.random().toString(36).slice(2, 12);
  return { url: token + random + '?token=' + (html.match(/token=([a-z0-9]+)/)?.[1] || '') + '&expiry=' + Date.now(), type: 'mp4' };
}
module.exports = { extractDood };
