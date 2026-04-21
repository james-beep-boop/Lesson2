import { chromium } from '/Users/jamesmcclelland/.cache/codex-runtimes/codex-primary-runtime/dependencies/node/node_modules/playwright/index.mjs';
import fs from 'node:fs/promises';

const baseUrl = 'http://127.0.0.1:8000';
const outputDir = '/Users/jamesmcclelland/Documents/GitHub/Lesson2/tmp/pdfs/teacher_guide';

async function saveText(name, value) {
  await fs.writeFile(`${outputDir}/${name}`, value, 'utf8');
}

const browser = await chromium.launch({
  executablePath: '/Applications/Google Chrome.app/Contents/MacOS/Google Chrome',
  headless: true,
});
const page = await browser.newPage({ viewport: { width: 1440, height: 1200 } });

await page.goto(`${baseUrl}/login`, { waitUntil: 'networkidle' });
await page.fill('input[type="email"]', 'david@demo.test');
await page.fill('input[type="password"]', 'password');
await page.click('button[type="submit"]');
await page.waitForURL(url => !url.pathname.endsWith('/login'), { timeout: 15000 });
await page.waitForLoadState('networkidle');

await saveText(
  'post_login_url.txt',
  page.url(),
);

const links = await page.locator('a').evaluateAll(nodes =>
  nodes
    .map(node => ({
      text: (node.textContent || '').trim().replace(/\s+/g, ' '),
      href: node.getAttribute('href'),
    }))
    .filter(item => item.text || item.href)
);

await saveText('links.json', JSON.stringify(links, null, 2));

await page.screenshot({ path: `${outputDir}/home_full.png`, fullPage: true });

await browser.close();
