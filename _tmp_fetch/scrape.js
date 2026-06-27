const { chromium } = require('playwright');
(async () => {
  const browser = await chromium.launch({ headless: true });
  const page = await browser.newPage();
  await page.goto('https://white-express-glove.fwh.is/', { waitUntil: 'domcontentloaded', timeout: 120000 });
  await page.waitForTimeout(5000);
  const urls = await page.evaluate(() => {
    const all = [];
    document.querySelectorAll('img').forEach(img => all.push(img.src, img.getAttribute('data-src'), img.getAttribute('data-lazy-src')));
    return [...new Set(all.filter(Boolean))].filter(u => /FEDXX|USPSS|UPSS|AMZZ|MSC|partner|thumbs/i.test(u));
  });
  console.log(JSON.stringify(urls, null, 2));
  await browser.close();
})().catch(e => { console.error(e); process.exit(1); });
