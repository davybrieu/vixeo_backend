// DoodStream (dood.la, doodstream.com, doods.pro, playmogo.com, ...): the embed
// page exposes a /pass_md5/<path> token endpoint; the returned prefix plus a
// random suffix and a token/expiry query is the real MP4 URL.
// Port of ONYX DoodLaExtractor.
const { getText } = require('../lib/http');
const { originOf } = require('../lib/util');

const USER_AGENT =
  'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36';
const ALPHABET = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789';

function randomString(length) {
  let out = '';
  for (let i = 0; i < length; i++) {
    out += ALPHABET[Math.floor(Math.random() * ALPHABET.length)];
  }
  return out;
}

async function extractDood(url) {
  const embed = url.replace('dood.work', 'doodstream.com').replace('d000d.org', 'doodstream.com').replace('/d/', '/e/');
  const origin = originOf(embed);
  const page = await getText(embed, { Referer: `${origin}/`, 'User-Agent': USER_AGENT });
  if (!page.ok) return null;

  const pass = /\/pass_md5\/[^"'\s]+/.exec(page.text);
  if (!pass) return null;

  const token = await getText(`${origin}${pass[0]}`, { Referer: embed, 'User-Agent': USER_AGENT });
  const prefix = token.text.trim();
  if (!/^https?:/i.test(prefix)) return null;

  const separator = prefix.includes('?') ? '&' : '?';
  const file = `${prefix}${randomString(10)}${separator}token=${pass[0].split('/').pop()}&expiry=${Date.now()}`;

  return {
    url: file,
    type: 'mp4',
    headers: { Referer: embed, 'User-Agent': USER_AGENT },
  };
}

module.exports = { extractDood };
