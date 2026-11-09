// VOE (voe.sx and a long list of rotating domains): the page carries an
// obfuscated JSON blob (ROT13 + base64 + reverse) under a JSON script tag.
// Port of ONYX VoeExtractor.
const { getText } = require('../lib/http');
const { originOf, mediaType, absoluteUrl } = require('../lib/util');

const USER_AGENT =
  'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36';

function rot13(str) {
  return str.replace(/[a-zA-Z]/g, (c) => {
    const base = c <= 'Z' ? 65 : 97;
    return String.fromCharCode(((c.charCodeAt(0) - base + 13) % 26) + base);
  });
}

function decryptVoe(encoded) {
  try {
    let s = rot13(encoded);
    for (const p of ['@$', '^^', '~@', '%?', '*~', '!!', '#&']) {
      s = s.split(p).join('_');
    }
    s = s.replace(/_/g, '');
    const first = Buffer.from(s, 'base64').toString('utf8');
    const shifted = [...first].map((c) => String.fromCharCode(c.charCodeAt(0) - 3)).join('');
    const reversed = shifted.split('').reverse().join('');
    return JSON.parse(Buffer.from(reversed, 'base64').toString('utf8'));
  } catch (e) {
    return null;
  }
}

function jsonRedirect(html) {
  const patterns = [
    /window\.location\.href\s*=\s*['"]([^'"]+)['"]/,
    /location\.replace\(\s*['"]([^'"]+)['"]/,
  ];
  for (const pattern of patterns) {
    const m = pattern.exec(html);
    if (m && m[1].startsWith('http')) return m[1];
  }
  return null;
}

async function extractVoe(url) {
  let target = url;
  let origin = originOf(url);
  let res = await getText(target, { Referer: target, 'User-Agent': USER_AGENT });
  let html = res.text;

  // Short challenge pages redirect through a small script.
  if (html.length < 3000 && html.includes('location.replace')) {
    const next = jsonRedirect(html);
    if (next) {
      target = next;
      origin = originOf(next);
      res = await getText(target, { Referer: target, 'User-Agent': USER_AGENT });
      html = res.text;
    }
  }

  const json = /<script\s+type="application\/json">([\s\S]*?)<\/script>/.exec(html);
  if (!json) return null;

  let encoded = json[1].trim();
  try {
    const arr = JSON.parse(encoded);
    if (Array.isArray(arr) && typeof arr[0] === 'string') encoded = arr[0];
  } catch (e) {
    // keep the raw string
  }

  const data = decryptVoe(encoded);
  const source = data && (data.source || data.file);
  if (!source || !/^https?:/i.test(source)) return null;

  let base = origin;
  const baseMatch = /var\s+base\s*=\s*['"]([^'"]+)['"]/.exec(html);
  if (baseMatch) base = baseMatch[1];

  const subtitles = (data.captions || [])
    .filter((c) => c && c.file)
    .map((c) => ({
      url: absoluteUrl(base, c.file),
      language: c.label || 'Inconnu',
      default: !!c.default,
    }));

  return {
    url: source,
    type: mediaType(source) || 'mp4',
    headers: { Referer: `${origin}/`, Origin: origin, 'User-Agent': USER_AGENT },
    subtitles,
  };
}

module.exports = { extractVoe };
