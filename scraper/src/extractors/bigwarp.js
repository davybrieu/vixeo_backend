const { getText } = require('../lib/http');
const { mediaType, normalizeText } = require('../lib/util');

const USER_AGENT = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)';

async function extractBigWarp(url) {
  let parsed;
  try { parsed = new URL(url); } catch { return null; }
  if (!['bigwarp.cc', 'bigwarp.io', 'bigwarp.pro'].some(host => parsed.hostname === host || parsed.hostname.endsWith('.' + host))) return null;

  const origin = parsed.origin;
  const response = await getText(url, {
    'User-Agent': USER_AGENT,
    Referer: origin + '/',
  });
  if (!response.ok) return null;

  const html = normalizeText(response.text);
  const scripts = [...html.matchAll(/<script\b[^>]*>([\s\S]*?)<\/script>/gi)].map(match => match[1]);
  for (const script of scripts) {
    if (!/jwplayer/i.test(script) || !/sources/i.test(script) || !/\bfile\b/i.test(script)) continue;
    const match = /\bfile\s*:\s*(['"])(https?:\/\/[^'"]+|\/[^'"]+)\1/i.exec(script);
    if (!match) continue;
    const source = new URL(match[2], origin).href;
    return {
      url: source,
      type: mediaType(source) || 'mp4',
      headers: { 'User-Agent': USER_AGENT, Referer: origin + '/' },
    };
  }
  return null;
}

module.exports = { extractBigWarp };