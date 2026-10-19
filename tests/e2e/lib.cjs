// Minimal e2e helpers shared by the test files (no test runner dependency).
const { chromium } = require('playwright');
const { execFileSync } = require('child_process');
const path = require('path');

const BASE = process.env.BASE_URL || 'http://localhost:8089';
const SHOTS = process.env.SHOTS_DIR || '';
const WP_CLI = process.env.WP_CLI; // "php /path/wp-cli.phar --path=/site --allow-root"

let failures = 0;
let passes = 0;

function ok(cond, name, extra) {
  if (cond) {
    passes++;
    console.log('  ✓ ' + name);
  } else {
    failures++;
    console.log('  ✗ ' + name + (extra ? '  →  ' + extra : ''));
  }
}

function wp(php) {
  if (!WP_CLI) throw new Error('WP_CLI env var is required');
  const [bin, ...args] = WP_CLI.split(' ');
  return execFileSync(bin, [...args, 'eval', php], { encoding: 'utf8', stdio: ['ignore', 'pipe', 'ignore'] }).trim();
}

async function launch() {
  return chromium.launch({
    args: ['--use-fake-ui-for-media-stream', '--use-fake-device-for-media-stream'],
  });
}

async function session(browser, user, opts = {}) {
  const context = await browser.newContext({ viewport: opts.viewport || { width: 1440, height: 900 }, locale: 'fa-IR' });
  await context.grantPermissions(['microphone'], { origin: BASE });
  const page = await context.newPage();
  page.jsErrors = [];
  page.on('pageerror', (e) => page.jsErrors.push(e.message));
  page.on('console', (m) => {
    if (m.type() === 'error' && !/ERR_TUNNEL|ERR_CONNECTION|Failed to load resource/.test(m.text())) page.jsErrors.push(m.text());
  });
  await page.goto(`${BASE}/wp-login.php`);
  await page.fill('#user_login', user);
  await page.fill('#user_pass', user === 'admin' ? 'admin' : user);
  await Promise.all([page.waitForNavigation(), page.click('#wp-submit')]);
  return { context, page };
}

async function shot(page, name) {
  if (SHOTS) await page.screenshot({ path: path.join(SHOTS, name + '.png'), fullPage: true });
}

async function noHorizontalOverflow(page) {
  return page.evaluate(() => document.documentElement.scrollWidth <= window.innerWidth + 1);
}

function done() {
  console.log(`\n${passes} passed, ${failures} failed`);
  process.exitCode = failures ? 1 : 0;
}

module.exports = { BASE, ok, wp, launch, session, shot, noHorizontalOverflow, done };
