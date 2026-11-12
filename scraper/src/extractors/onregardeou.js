function normalizeUrl(value, baseUrl) {
  if (typeof value !== 'string' || !value.trim()) return '';
  try {
    const url = new URL(value, baseUrl);
    return ['http:', 'https:'].includes(url.protocol) ? url.href : '';
  } catch {
    return '';
  }
}

function parseOnRegardeOuScript(scriptText, baseUrl, suffix = '') {
  const marker = 'const videoData=';
  const start = String(scriptText || '').indexOf(marker);
  if (start < 0) return [];
  const end = String(scriptText).indexOf('};', start);
  if (end < 0) return [];

  let data;
  try {
    data = JSON.parse(String(scriptText).slice(start + marker.length, end + 1));
  } catch {
    return [];
  }
  if (!Array.isArray(data?.servers)) return [];

  return data.servers.map((server, index) => {
    const url = normalizeUrl(server?.url, baseUrl);
    if (!url) return null;
    return {
      name: suffix + String(server.name || 'Server ' + (index + 1)).trim(),
      url,
    };
  }).filter(Boolean);
}

async function expandOnRegardeOu(page, url, referer = url, suffix = '') {
  await page.goto(url, { waitUntil: 'domcontentloaded', timeout: 15000, referer });
  const scriptText = await page.locator('script').evaluateAll((scripts) => {
    const script = scripts.find((item) => (item.textContent || '').includes('videoData'));
    return script?.textContent || '';
  });
  return parseOnRegardeOuScript(scriptText, url, suffix);
}

module.exports = { parseOnRegardeOuScript, expandOnRegardeOu };
