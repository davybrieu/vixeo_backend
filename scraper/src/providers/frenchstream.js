const { chromium } = require('playwright');

async function searchFrenchStream({ type, title, originalTitle, year }) {
  const browser = await chromium.launch({
    headless: true,
    args: ['--no-sandbox', '--disable-blink-features=AutomationControlled'],
  });

  const context = await browser.newContext({
    userAgent: 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/130.0.0.0 Safari/537.36',
    locale: 'fr-FR',
  });

  await context.addInitScript(() => {
    Object.defineProperty(navigator, 'webdriver', { get: () => undefined });
  });

  const page = await context.newPage();

  try {
    await page.goto('https://fstream.info/', { waitUntil: 'domcontentloaded', timeout: 20000 });
    let baseUrl = 'https://fs26.lol/';
    try {
      const link = await page.locator('div.url-card a').first().getAttribute('href');
      if (link) baseUrl = link.endsWith('/') ? link : link + '/';
    } catch (e) {}

    const queries = [title, originalTitle].filter(Boolean);
    const seen = new Set();
    const sources = [];

    for (const q of queries) {
      const searchUrl = baseUrl + 'index.php?do=search&subaction=search&full_search=1&titleonly=3&story=' + encodeURIComponent(q);
      await page.goto(searchUrl, { waitUntil: 'domcontentloaded', timeout: 20000 });
      await page.waitForTimeout(1500);

      const results = await page.evaluate(() => {
        return [...document.querySelectorAll('div.short')].map(el => {
          const a = el.querySelector('a.short-poster');
          const titleEl = el.querySelector('div.short-title');
          return {
            title: titleEl?.innerText?.trim() || '',
            url: a?.href || '',
          };
        }).filter(r => r.url);
      });

      for (const r of results.slice(0, 3)) {
        if (seen.has(r.url)) continue;
        seen.add(r.url);

        await page.goto(r.url, { waitUntil: 'domcontentloaded', timeout: 20000 });
        const content = await page.content();
        const newsIdMatch = content.match(/newsid=(\d+)/) || r.url.match(/newsid=(\d+)/);
        const newsId = newsIdMatch?.[1];
        if (!newsId) continue;

        const apiUrl = baseUrl + 'engine/ajax/film_api.php?id=' + newsId;
        const apiData = await page.evaluate(async (url) => {
          try {
            const res = await fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
            return await res.json();
          } catch (e) {
            return null;
          }
        }, apiUrl);

        if (apiData?.players) {
          for (const [provider, langs] of Object.entries(apiData.players)) {
            for (const [lang, url] of Object.entries(langs)) {
              if (typeof url === 'string' && url.startsWith('http')) {
                sources.push({
                  provider: 'frenchstream',
                  name: provider.charAt(0).toUpperCase() + provider.slice(1) + ' (' + lang + ')',
                  url,
                });
              }
            }
          }
        }
      }
    }

    return sources;
  } finally {
    await browser.close();
  }
}

module.exports = { searchFrenchStream };
