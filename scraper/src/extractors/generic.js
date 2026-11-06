async function extractGeneric(url) {
  try {
    const res = await fetch(url, {
      headers: { 'User-Agent': 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36' },
      redirect: 'follow',
    });
    const html = await res.text();
    const m3u8 = html.match(/https?:\/\/[^"'\s]+\.m3u8[^"'\s]*/);
    if (m3u8) return { url: m3u8[0], type: 'm3u8' };
    const mp4 = html.match(/https?:\/\/[^"'\s]+\.mp4[^"'\s]*/);
    if (mp4) return { url: mp4[0], type: 'mp4' };
  } catch (e) {}
  return null;
}
module.exports = { extractGeneric };
