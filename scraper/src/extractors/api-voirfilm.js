function normalizeUrl(value, baseUrl) {
  if (typeof value !== 'string' || !value.trim()) return '';
  try {
    const url = new URL(value, baseUrl);
    return ['http:', 'https:'].includes(url.protocol) ? url.href : '';
  } catch {
    return '';
  }
}

function normalizeApiVoirFilmItems(items, baseUrl, suffix = '') {
  if (!Array.isArray(items)) return [];
  return items.map((item, index) => {
    const url = normalizeUrl(item?.url, baseUrl);
    if (!url) return null;
    return {
      name: `${suffix}${String(item.name || `Server ${index + 1}`).trim()}`,
      url,
    };
  }).filter(Boolean);
}

async function expandApiVoirFilm(page, url, referer = url, suffix = '') {
  await page.goto(url, { waitUntil: 'domcontentloaded', timeout: 15000, referer });
  const items = await page.locator('div.top ul.content > li[data-url]').evaluateAll((nodes) =>
    nodes.map((node, index) => ({
      name: node.innerText?.trim() || `Server ${index + 1}`,
      url: node.getAttribute('data-url') || '',
    }))
  );
  return normalizeApiVoirFilmItems(items, url, suffix);
}

module.exports = { normalizeApiVoirFilmItems, expandApiVoirFilm };
