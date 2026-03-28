const puppeteer = require('puppeteer');
const path = require('path');

const sites = [
  { url: 'https://seagreen-lobster-495209.hostingersite.com/', name: 'projekt-01.jpg' },
  { url: 'https://xn--staubflsterer-dlb.de', name: 'projekt-02.jpg' },
  { url: 'https://vitaraskin.com', name: 'projekt-03.jpg' },
  { url: 'https://el-salam.com', name: 'projekt-04.jpg' },
  { url: 'https://shop.el-salam.com', name: 'projekt-05.jpg' },
  { url: 'https://msdautowelt.com', name: 'projekt-06.jpg' },
  { url: 'https://stage-code.com', name: 'projekt-07.jpg' },
];

const outDir = path.join(__dirname, 'projects');

async function dismissOverlays(page) {
  // Try clicking common cookie/popup dismiss buttons
  const selectors = [
    // Cookie banners
    'button[id*="accept"]', 'button[class*="accept"]',
    'a[id*="accept"]', 'a[class*="accept"]',
    'button[id*="cookie"]', 'button[class*="cookie"]',
    '[data-action="accept"]', '[data-consent="accept"]',
    // Close buttons
    'button[class*="close"]', 'button[aria-label="Close"]',
    '.modal-close', '.popup-close', '.close-btn',
    'button[class*="dismiss"]',
    // German cookie texts
    'button:has-text("Akzeptieren")',
    'button:has-text("Alle akzeptieren")',
    'button:has-text("Accept")',
    'button:has-text("ACCEPT ALL")',
    'button:has-text("Alle Cookies akzeptieren")',
  ];

  for (const sel of selectors) {
    try {
      const els = await page.$$(sel);
      for (const el of els) {
        const visible = await el.boundingBox();
        if (visible) {
          await el.click();
          await new Promise(r => setTimeout(r, 500));
        }
      }
    } catch (e) {}
  }

  // Also try clicking by text content
  try {
    await page.evaluate(() => {
      const texts = ['ACCEPT ALL', 'Accept All', 'Akzeptieren', 'Alle akzeptieren',
                     'Alle Cookies akzeptieren', 'Accept', 'OK', 'Ablehnen'];
      document.querySelectorAll('button, a').forEach(el => {
        const t = el.textContent.trim();
        if (texts.some(txt => t.includes(txt))) {
          el.click();
        }
      });
    });
  } catch (e) {}

  // Remove overlays/modals by CSS
  try {
    await page.evaluate(() => {
      // Remove fixed/sticky overlays
      document.querySelectorAll('[class*="cookie"], [class*="consent"], [class*="modal"], [class*="popup"], [class*="overlay"], [id*="cookie"], [id*="consent"], [id*="modal"], [id*="popup"]').forEach(el => {
        el.remove();
      });
      // Remove backdrop/overlay divs
      document.querySelectorAll('div').forEach(el => {
        const style = window.getComputedStyle(el);
        if (style.position === 'fixed' && parseFloat(style.zIndex) > 100) {
          el.remove();
        }
      });
      // Reset body overflow
      document.body.style.overflow = 'auto';
      document.documentElement.style.overflow = 'auto';
    });
  } catch (e) {}
}

(async () => {
  const browser = await puppeteer.launch({ headless: 'new' });

  for (const site of sites) {
    const page = await browser.newPage();
    await page.setViewport({ width: 1440, height: 900 });
    try {
      console.log(`Screenshotting: ${site.url}`);
      await page.goto(site.url, { waitUntil: 'networkidle2', timeout: 30000 });
      await new Promise(r => setTimeout(r, 2000));

      // Dismiss cookie banners and popups
      await dismissOverlays(page);
      await new Promise(r => setTimeout(r, 1000));
      // Second pass in case dismissing revealed more
      await dismissOverlays(page);
      await new Promise(r => setTimeout(r, 500));

      await page.screenshot({
        path: path.join(outDir, site.name),
        type: 'jpeg',
        quality: 90,
        fullPage: false,
      });
      console.log(`  SAVED: ${site.name}`);
    } catch (err) {
      console.error(`  ERROR (${site.url}): ${err.message}`);
    }
    await page.close();
  }

  await browser.close();
  console.log('Done!');
})();
