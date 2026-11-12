const { withBrowserPage } = require('../lib/browser');

const HOST = 'moflix-stream.day';

function decodeMStreamDaySignature(url) {
  const match = /([?&]sig=)([^&]+)/i.exec(url);
  if (!match) return null;
  const encoded = match[2];
  if (!/^(?:[0-9a-f]{2})+$/i.test(encoded)) return null;
  try {
    const xorText = encoded.match(/.{2}/g).map(pair => String.fromCharCode(parseInt(pair, 16) ^ 2)).join('');
    const padding = '='.repeat((4 - xorText.length % 4) % 4);
    const decoded = Buffer.from(xorText + padding, 'base64').toString('utf8');
    if (decoded.length <= 10) return null;
    const chars = decoded.slice(0, -5).split('').reverse();
    for (let i = 0; i + 1 < chars.length; i += 2) {
      [chars[i], chars[i + 1]] = [chars[i + 1], chars[i]];
    }
    const signature = chars.join('').slice(0, -5);
    return signature ? url.replace(match[0], match[1] + signature) : null;
  } catch {
    return null;
  }
}

async function extractMStreamDay(url) {
  let parsed;
  try { parsed = new URL(url); } catch { return null; }
  if (parsed.hostname !== HOST) return null;

  return withBrowserPage(async page => {
    try {
      await page.goto(url, { waitUntil: 'domcontentloaded', timeout: 30000 });
      await page.waitForFunction(() => typeof window.svg?.stream === 'string' && window.svg.stream.length > 0, { timeout: 15000 });
      const encodedSource = await page.evaluate(() => window.svg.stream);
      const source = decodeMStreamDaySignature(encodedSource);
      return source && /^https?:\/\//i.test(source)
        ? { url: source, type: 'm3u8', headers: { Referer: parsed.origin + '/' } }
        : null;
    } catch {
      return null;
    }
  });
}

module.exports = { extractMStreamDay, decodeMStreamDaySignature };
