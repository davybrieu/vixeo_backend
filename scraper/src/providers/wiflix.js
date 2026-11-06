const { chromium } = require('playwright');

async function searchWiflix({ type, title, originalTitle }) {
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
  const sources = [];

  try {
    // Portal to get current base
    let baseUrl = 'https://flemmix.team/';
    try {
      await page.goto('https://ww1.wiflix-adresses.fun/', { waitUntil: 'domcontentloaded', timeout: 15000 });
      const link = await page.locator('a').first().getAttribute('href');
      if (link && link.startsWith('http')) baseUrl = link.endsWith('/') ? link : link + '/';
    } catch (e) {}

    const queries = [title, originalTitle].filter(Boolean);
    const seen = new Set();

    for (const q of queries) {
      const searchUrl = baseUrl + 'index.php?do=search&subaction=search&full_search=1&titleonly=3&story=' + encodeURIComponent(q);
      await page.goto(searchUrl, { waitUntil: 'domcontentloaded', timeout: 20000 });
      await page.waitForTimeout(1200);

      const results = await page.evaluate(() => {
        return [...document.querySelectorAll('div.mov')]
          .filter(el => !el.closest('#no-results-rec'))
          .map(el => {
            const a = el.querySelector('a.mov-t');
            return {
              title: a?.innerText?.trim() || '',
              url: a?.href || '',
            };
          })
          .filter(r => r.url);
      });

      for (const r of results.slice(0, 3)) {
        if (seen.has(r.url)) continue;
        seen.add(r.url);

        await page.goto(r.url, { waitUntil: 'domcontentloaded', timeout: 20000 });

        const servers = await page.evaluate(() => {
          const out = [];
          const selectors = ['div.tabs-sel a', 'div.tabs a'];
          for (const sel of selectors) {
            for (const a of document.querySelectorAll(sel)) {
              const onclick = a.getAttribute('onclick') || '';
              const m = onclick.match(/loadVideo\(['"]([^'"]+)['"]/);
              if (m && m[1]) {
                const name = a.innerText?.trim() || 'Source';
                out.push({ name, url: m[1] });
              }
            }
          }
          return out;
        });

        for (const s of servers) {
          if (s.url && s.url.startsWith('http')) {
            sources.push({
              provider: 'wiflix',
              name: s.name,
              url: s.url,
            });
          }
        }
      }
    }
  } catch (e) {
    console.error('Wiflix error:', e.message);
  } finally {
    await browser.close();
  }

  return sources;
}

module.exports = { searchWiflix };
