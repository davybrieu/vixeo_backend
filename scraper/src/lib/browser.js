const { chromium } = require('playwright');

async function withBrowserPage(callback, options = {}) {
  const browser = await chromium.launch({
    headless: true,
    args: ['--no-sandbox', '--disable-dev-shm-usage'],
  });
  try {
    const context = await browser.newContext({
      userAgent: options.userAgent || 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36',
      viewport: { width: 1365, height: 768 },
    });
    const page = await context.newPage();
    try {
      return await callback(page);
    } finally {
      await context.close();
    }
  } finally {
    await browser.close();
  }
}

module.exports = { withBrowserPage };