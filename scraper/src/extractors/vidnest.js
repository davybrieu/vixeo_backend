const { getText } = require('../lib/http');
const { normalizeText } = require('../lib/util');

const DOMAINS = ['vidnest.io', 'vidnest.live'];

async function extractVidnest(url) {
  let parsed;
  try { parsed = new URL(url); } catch { return null; }
  if (!DOMAINS.includes(parsed.hostname)) return null;
  const response = await getText(url);
  if (!response.ok) return null;
  const html = normalizeText(response.text);
  const scripts = [...html.matchAll(/<script\b[^>]*type=["']text\/javascript["'][^>]*>([\s\S]*?)<\/script>/gi)].map(m => m[1]);
  for (const script of scripts) {
    if (!/jwplayer/i.test(script) || !/sources/i.test(script)) continue;
    const source = /\bfile\s*:\s*["']([^"']+)["']/i.exec(script)?.[1];
    if (!source) continue;
    const tracks = /tracks\s*:\s*\[([\s\S]*?)\]/i.exec(script)?.[1] || '';
    const subtitles = [...tracks.matchAll(/\{([\s\S]*?)\}/g)].map(m => {
      const obj = m[1];
      const kind = /kind\s*:\s*["']([^"']+)["']/i.exec(obj)?.[1];
      const file = /file\s*:\s*["']([^"']+)["']/i.exec(obj)?.[1];
      const label = /label\s*:\s*["']([^"']+)["']/i.exec(obj)?.[1];
      const isDefault = /["']default["']\s*:\s*true/i.test(obj);
      return kind === 'captions' && file && label ? { url: new URL(file, response.url).href, label, default: isDefault } : null;
    }).filter(Boolean);
    return { url: new URL(source, response.url).href, type: 'm3u8', subtitles };
  }
  return null;
}
module.exports = { extractVidnest };