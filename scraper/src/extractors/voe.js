function rot13(str) {
  return str.replace(/[a-zA-Z]/g, c => {
    const base = c <= 'Z' ? 65 : 97;
    return String.fromCharCode(((c.charCodeAt(0) - base + 13) % 26) + base);
  });
}

function decryptVoe(encoded) {
  try {
    let s = rot13(encoded);
    const patterns = ['@$', '^^', '~@', '%?', '*~', '!!', '#&'];
    for (const p of patterns) s = s.split(p).join('_');
    s = s.replace(/_/g, '');
    const decoded1 = Buffer.from(s, 'base64').toString('utf8');
    const shifted = [...decoded1].map(c => String.fromCharCode(c.charCodeAt(0) - 3)).join('');
    const reversed = shifted.split('').reverse().join('');
    const final = Buffer.from(reversed, 'base64').toString('utf8');
    return JSON.parse(final);
  } catch (e) {
    return null;
  }
}

async function extractVoe(url) {
  const res = await fetch(url, {
    headers: {
      'User-Agent': 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/130.0.0.0 Safari/537.36',
      'Referer': url,
    },
    redirect: 'follow',
  });
  const html = await res.text();

  const jsonMatch = html.match(/<script\s+type="application\/json">([\s\S]*?)<\/script>/);
  const encoded = jsonMatch?.[1]?.trim();
  if (!encoded) return null;

  const data = decryptVoe(encoded);
  const source = data?.source || data?.file;
  if (source && source.startsWith('http')) {
    return { url: source, type: source.includes('.m3u8') ? 'm3u8' : 'mp4' };
  }
  return null;
}

module.exports = { extractVoe };
