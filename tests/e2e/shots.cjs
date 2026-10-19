// Screenshots a set of pages for visual review.
// Usage: NODE_PATH=$(npm root -g) node tests/e2e/shots.cjs <outDir> <user> <path> [<path>...]
// Paths prefixed with "m:" are captured at phone width.
const { chromium } = require('playwright');

const [outDir, user, ...paths] = process.argv.slice(2);
const base = process.env.BASE_URL || 'http://localhost:8089';

(async () => {
const browser = await chromium.launch();
const context = await browser.newContext({ viewport: { width: 1440, height: 900 }, locale: 'fa-IR' });
const page = await context.newPage();
const errors = [];
page.on('pageerror', (e) => errors.push(`pageerror: ${e.message}`));
page.on('console', (m) => { if (m.type() === 'error') errors.push(`console: ${m.text()}`); });

await page.goto(`${base}/wp-login.php`);
await page.fill('#user_login', user);
await page.fill('#user_pass', user === 'admin' ? 'admin' : user);
await page.click('#wp-submit');
await page.waitForLoadState('networkidle');

let i = 0;
for (let p of paths) {
  const mobile = p.startsWith('m:');
  if (mobile) p = p.slice(2);
  await page.setViewportSize(mobile ? { width: 390, height: 844 } : { width: 1440, height: 900 });
  await page.goto(base + p, { waitUntil: 'networkidle' });
  const name = `${String(++i).padStart(2, '0')}-${(mobile ? 'm-' : '') + p.replace(/[^a-z0-9]+/gi, '_').slice(0, 60)}.png`;
  await page.screenshot({ path: `${outDir}/${name}`, fullPage: true });
  console.log('shot', name);
}
if (errors.length) console.log('JS errors:\n' + errors.join('\n'));
await browser.close();
})();
