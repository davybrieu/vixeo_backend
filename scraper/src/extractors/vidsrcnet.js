const { getText } = require('../lib/http');

const DOMAINS = ['vidsrc-embed.ru', 'vsembed.ru'];
const XOR_KEY_1 = "pWB9V)[*4I\x60nJpp?ozyB~dbr9yt!_n4u";
const XOR_KEY_2 = "X9a(O;FMV2-7VO5x;Ao\u0005:dN1NoFs?j,";
const XOR_KEY_3 = '3SAY~#%Y(V%>5d/Yg"$G[Lh1rK4a;7ok';

function b64(value) {
  return Buffer.from(String(value).replace(/-/g, '+').replace(/_/g, '/'), 'base64');
}
function hexChars(value) {
  if (value.length % 2) throw new Error('Invalid hex input');
  return value.match(/.{2}/g).map(pair => String.fromCharCode(parseInt(pair, 16))).join('');
}
function decodeVidsrcNet(id, value) {
  switch (id) {
    case 'NdonQLf1Tzyx7bMG':
      return value.match(/.{1,3}/g).reverse().join('');
    case 'sXnL9MQIry': {
      const decoded = hexChars(value);
      let transformed = '';
      for (let i = 0; i < decoded.length; i++) transformed += String.fromCharCode(decoded.charCodeAt(i) ^ XOR_KEY_1.charCodeAt(i % XOR_KEY_1.length));
      return b64([...transformed].map(c => String.fromCharCode(c.charCodeAt(0) - 3)).join('')).toString('utf8');
    }
    case 'IhWrImMIGL': {
      const reversed = [...value].reverse().join('');
      const rot = reversed.replace(/[a-zA-Z]/g, c => String.fromCharCode(c.charCodeAt(0) + (c.toLowerCase() <= 'm' ? 13 : -13)));
      return b64([...rot].reverse().join('')).toString('utf8');
    }
    case 'xTyBxQyGTA': {
      const reversed = [...value].reverse().filter((_, index) => index % 2 === 0).join('');
      return b64(reversed).toString('utf8');
    }
    case 'ux8qjPHC66': {
      const decoded = hexChars([...value].reverse().join(''));
      let out = '';
      for (let i = 0; i < decoded.length; i++) out += String.fromCharCode(decoded.charCodeAt(i) ^ XOR_KEY_2.charCodeAt(i % XOR_KEY_2.length));
      return out;
    }
    case 'eSfH1IRMyL': {
      const shifted = [...value].reverse().map(c => String.fromCharCode(c.charCodeAt(0) - 1)).join('');
      return hexChars(shifted);
    }
    case 'KJHidj7det': {
      const segment = value.slice(10, -16);
      const decoded = b64(segment).toString('latin1');
      const key = XOR_KEY_3.repeat(Math.ceil(decoded.length / XOR_KEY_3.length)).slice(0, decoded.length);
      let out = '';
      for (let i = 0; i < decoded.length; i++) out += String.fromCharCode(decoded.charCodeAt(i) ^ key.charCodeAt(i));
      return out;
    }
    case 'o2VSUnjnZl':
      return value.replace(/[a-zA-Z]/g, c => String.fromCharCode(c.charCodeAt(0) - 3 + (c.toLowerCase() < 'd' ? 26 : 0)));
    case 'Oi3v1dAlaM':
    case 'TsA2KGDGux':
    case 'JoAHUMCLXV': {
      const shift = { Oi3v1dAlaM: 5, TsA2KGDGux: 7, JoAHUMCLXV: 3 }[id];
      const decoded = b64([...value].reverse().join('')).toString('utf8');
      return [...decoded].map(c => String.fromCharCode(c.charCodeAt(0) - shift)).join('');
    }
    default:
      return null;
  }
}

function decodeVidsrcSubtitles(raw, baseUrl) {
  if (!raw) return [];
  return raw.split(',').map(item => {
    const match = /^\[([^\]]+)\](.+)$/.exec(item.trim());
    const subtitlePath = match?.[2]?.trim();
    if (!match || !subtitlePath?.startsWith('/')) return null;
    return { label: match[1].replace(/&amp;/g, '&'), url: new URL(subtitlePath, baseUrl).href };
  }).filter(Boolean);
}

async function extractVidsrcNet(url) {
  let parsed;
  try { parsed = new URL(url); } catch { return null; }
  if (!DOMAINS.includes(parsed.hostname)) return null;
  const response = await getText(url, { Referer: parsed.origin + '/' });
  if (!response.ok) return null;
  const iframe = /<iframe\b[^>]*id=["']player_iframe["'][^>]*src=["']([^"']+)["']/i.exec(response.text)?.[1]
    || /<iframe\b[^>]*src=["']([^"']+)["'][^>]*id=["']player_iframe["']/i.exec(response.text)?.[1];
  if (!iframe) return null;
  const iframeUrl = new URL(iframe.startsWith('//') ? 'https:' + iframe : iframe, response.url).href;
  const iframePage = await getText(iframeUrl, { Referer: url });
  if (!iframePage.ok) return null;
  const prorcpPath = /src:\s*['"](\/prorcp\/[^'"]+)['"]/i.exec(iframePage.text)?.[1];
  if (!prorcpPath) return null;
  const prorcpUrl = new URL(prorcpPath, iframeUrl).href;
  const scriptResponse = await getText(prorcpUrl, { Referer: iframeUrl });
  if (!scriptResponse.ok) return null;
  const script = scriptResponse.text;
  const playerId = /Playerjs[\s\S]*?file:\s*([a-zA-Z0-9]+)\s*,/i.exec(script)?.[1];
  let decoded;
  if (playerId) {
    const encrypted = new RegExp('<div id="' + playerId + '" style="display:none;">\\s*(.*?)\\s*</div>', 's').exec(script)?.[1];
    if (!encrypted) return null;
    decoded = decodeVidsrcNet(playerId, encrypted);
  } else {
    decoded = /Playerjs[\s\S]*?file:\s*"([^"]*?)"\s*,/i.exec(script)?.[1] || null;
  }
  if (!decoded) return null;
  const source = decoded.split(' or ')[0].replace(/\{[a-z]\d+\}/g, 'quibblezoomfable.com');
  if (!/^https?:\/\//i.test(source)) return null;
  const subtitlesRaw = /default_subtitles\s*=\s*["']([^"']+)["']/i.exec(script)?.[1] || '';
  return { url: source, subtitles: decodeVidsrcSubtitles(subtitlesRaw, iframeUrl), headers: { Referer: iframeUrl } };
}

module.exports = { extractVidsrcNet, decodeVidsrcNet, decodeVidsrcSubtitles };