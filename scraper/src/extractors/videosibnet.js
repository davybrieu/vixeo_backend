const { getText } = require('../lib/http');

const USER_AGENT = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36';

async function extractVideoSibNet(url) {
  let parsed;
  try { parsed = new URL(url); } catch { return null; }
  if (parsed.hostname !== 'video.sibnet.ru') return null;
  const response = await getText(url, { Referer: 'https://video.sibnet.ru/', 'User-Agent': USER_AGENT, Accept: 'text/html' });
  if (!response.ok) return null;
  const scripts = [...response.text.matchAll(/<script\b[^>]*>([\s\S]*?)<\/script>/gi)].map(m => m[1]);
  for (const script of scripts) {
    if (!script.includes('player.src')) continue;
    const source = /src\s*:\s*"([^"]+)"/i.exec(script)?.[1];
    if (source) return { url: new URL(source, response.url).href, headers: { Referer: 'https://video.sibnet.ru/', 'User-Agent': USER_AGENT } };
  }
  return null;
}
module.exports = { extractVideoSibNet };