const { getText } = require('../lib/http');

const USER_AGENT = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36';

function createSource(base, token) {
  if (!base.endsWith('~')) return base;
  const chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789';
  let random = '';
  for (let i = 0; i < 10; i++) random += chars[Math.floor(Math.random() * chars.length)];
  return base + random + '?token=' + encodeURIComponent(token) + '&expiry=' + Date.now();
}

async function extractVidPly(url) {
  let parsed;
  try { parsed = new URL(url); } catch { return null; }
  if (parsed.hostname !== 'vidply.com') return null;
  const embedUrl = url.replace(/\/d\//i, '/e/');
  const response = await getText(embedUrl, { Accept: 'text/html', 'User-Agent': USER_AGENT, Referer: parsed.origin });
  if (!response.ok) return null;
  const html = response.text;
  const endpoint = /\$\.get\('(/pass_md5\/[^']*)'/i.exec(html)?.[1];
  const token = /return\s*[^?]+\?token=([^&]+)&expiry=/i.exec(html)?.[1];
  if (!endpoint || !token) return null;
  const pass = await getText(new URL(endpoint, 'https://vidply.com').href, { Referer: 'https://vidply.com/' });
  if (!pass.ok || !pass.text.trim()) return null;
  return { url: createSource(pass.text.trim(), token), headers: { Referer: 'https://vidply.com/', 'User-Agent': USER_AGENT } };
}
module.exports = { extractVidPly, createSource };