const crypto = require('node:crypto');
const { getText } = require('../lib/http');

const USER_AGENT = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/121.0.0.0 Safari/537.36';

function buildDailymotionEndpoint(id, options = {}) {
  if (!/^[a-zA-Z0-9]+$/.test(String(id || ''))) return null;
  const viewId = options.viewId || Array.from({ length: 19 }, () => 'abcdefghijklmnopqrstuvwxyz0123456789'[Math.floor(Math.random() * 36)]).join('');
  const endpoint = new URL('/video/' + encodeURIComponent(id) + '.json', 'https://geo.dailymotion.com');
  Object.entries({
    legacy: 'true', 'player-id': 'xtv3w', is_native_app: '0', app: 'com.dailymotion.neon',
    client_type: 'website', section_type: 'player', component_style: '_', parallelCalls: '1',
    locale: options.locale || 'fr-FR',
    dmV1st: options.visitorId || crypto.randomUUID(),
    dmTs: String(options.timestamp ?? Math.floor(Date.now() / 1000)),
    dmViewId: viewId,
  }).forEach(([key, value]) => endpoint.searchParams.set(key, value));
  return endpoint;
}

async function extractDailymotion(url) {
  let parsed;
  try { parsed = new URL(url); } catch { return null; }
  if (!['dailymotion.com', 'www.dailymotion.com', 'geo.dailymotion.com'].includes(parsed.hostname)) return null;
  const id = parsed.searchParams.get('video') || parsed.pathname.split('/').filter(Boolean).pop();
  if (!id || !/^[a-zA-Z0-9]+$/.test(id)) return null;
  const endpoint = buildDailymotionEndpoint(id);
  const response = await getText(endpoint.href, {
    'User-Agent': USER_AGENT,
    Referer: 'https://geo.dailymotion.com/player/xtv3w.html?',
    Accept: 'application/json, text/plain, */*',
  });
  if (!response.ok) return null;
  let json;
  try { json = JSON.parse(response.text); } catch { return null; }
  const manifest = json?.qualities?.auto?.[0]?.url;
  return typeof manifest === 'string' && manifest ? { url: manifest, type: 'm3u8', headers: { Referer: 'https://geo.dailymotion.com/' } } : null;
}
module.exports = { extractDailymotion, buildDailymotionEndpoint };