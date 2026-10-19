// Install / upgrade scenarios, including the "uploaded from GitHub into a second folder" case.
// Run: SITE_DIR=/site WP_CLI="php wp-cli.phar --path=/site --allow-root" OLD_REF=<git ref of the pre-redesign code> \
//      NODE_PATH=$(npm root -g) node tests/e2e/install.test.cjs
const { BASE, ok, launch, session, done } = require('./lib.cjs');
const { execFileSync, execSync } = require('child_process');
const fs = require('fs');
const path = require('path');

const REPO = path.resolve(__dirname, '../..');
const PLUGINS = path.join(process.env.SITE_DIR, 'wp-content/plugins');
const OLD_REF = process.env.OLD_REF || '8f398e1';
const MAIN = 'ticketchi';
const GITHUB = 'ticketchi-licensed-v1.4-main'; // folder name GitHub zips produce

function cli(...args) {
  const [bin, ...rest] = process.env.WP_CLI.split(' ');
  try {
    return execFileSync(bin, [...rest, ...args], { encoding: 'utf8', stdio: ['ignore', 'pipe', 'pipe'] });
  } catch (e) {
    return (e.stdout || '') + (e.stderr || '');
  }
}
const active = () => {
  try { return Object.values(JSON.parse(cli('option', 'get', 'active_plugins', '--format=json').trim().split('\n').pop())); } catch (e) { return []; }
};
const isActive = (slug) => active().includes(`${slug}/core.php`);

function install(folder, ref) {
  const dest = path.join(PLUGINS, folder);
  fs.rmSync(dest, { recursive: true, force: true });
  fs.mkdirSync(dest, { recursive: true });
  if (ref) execSync(`git -C "${REPO}" archive ${ref} | tar -x -C "${dest}"`);
  else execSync(`tar --exclude=.git --exclude=tests -cf - . | tar -x -C "${dest}"`, { cwd: REPO });
  fs.copyFileSync(path.join(REPO, 'tests/stubs/base-menu.php'), path.join(dest, 'inc/admin/abstract/base-menu.php'));
  for (const f of fs.readdirSync(path.join(dest, 'inc/admin/abstract'))) {
    if (f.startsWith('RTL_License_')) fs.rmSync(path.join(dest, 'inc/admin/abstract', f));
  }
}

async function adminWorks(browser, label) {
  const { page, context } = await session(browser, 'admin');
  const res = await page.goto(`${BASE}/wp-admin/admin.php?page=tkm-tickets`);
  const okPage = res.status() === 200 && (await page.locator('.tkm-topbar').count()) === 1;
  await context.close();
  ok(okPage, label);
}

(async () => {
  const tickets = cli('eval', 'global $wpdb; echo $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}tkm_tickets");').trim();
  const browser = await launch();

  console.log('\n1) Only the new version');
  cli('plugin', 'deactivate', MAIN, GITHUB);
  install(MAIN);
  fs.rmSync(path.join(PLUGINS, GITHUB), { recursive: true, force: true });
  let out = cli('plugin', 'activate', MAIN);
  ok(/Success/.test(out) && !/Fatal/.test(out), 'activates without errors', out.trim().slice(0, 160));
  await adminWorks(browser, 'admin screens load after activation');
  ok(isActive(MAIN), 'stays active after admin requests (guard does not misfire)');

  console.log('\n2) Old version active + new version uploaded into another folder');
  cli('plugin', 'deactivate', MAIN);
  install(MAIN, OLD_REF);
  install(GITHUB);
  out = cli('plugin', 'activate', MAIN);
  ok(/Success/.test(out) && isActive(MAIN), 'old version active', out.trim().slice(-300) + ' | active=' + active().join(','));
  out = cli('plugin', 'activate', GITHUB);
  ok(!/Fatal|Cannot declare/.test(out), 'activating the second copy no longer causes a fatal error', out.trim().slice(0, 200));
  {
    const { page, context } = await session(browser, 'admin');
    for (const screen of ['index.php', 'plugins.php']) {
      await page.goto(`${BASE}/wp-admin/${screen}`);
      ok(await page.locator('.notice-error', { hasText: 'نسخه دیگری از افزونه تیکت‌چی' }).count() >= 1, `notice on ${screen} tells the admin to remove the old copy`);
    }
    const res = await page.goto(`${BASE}/wp-admin/admin.php?page=tkm-tickets`);
    ok(res.status() === 200 && (await page.locator('.tkm-topbar').count()) === 0, 'old version keeps serving its own screens (new copy is inert)');
    await context.close();
  }

  console.log('\n3) Correct upgrade: deactivate old, activate new');
  cli('plugin', 'deactivate', MAIN);
  out = cli('plugin', 'activate', GITHUB); // may already be active (it stayed inert next to the old copy)
  ok(isActive(GITHUB) && !/Fatal/.test(out), 'new copy takes over once the old one is off');
  await adminWorks(browser, 'admin screens load after upgrade');
  ok(isActive(GITHUB), 'new copy stays active');
  const after = cli('eval', 'global $wpdb; echo $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}tkm_tickets");').trim();
  ok(after === tickets, 'existing tickets preserved', `${tickets} → ${after}`);

  console.log('\n4) Two copies of the new version');
  install(MAIN);
  out = cli('plugin', 'activate', MAIN);
  ok(!/Fatal|Cannot declare/.test(out), 'no fatal error with two new copies');
  await adminWorks(browser, 'admin still works');
  {
    const { page, context } = await session(browser, 'admin');
    await page.goto(`${BASE}/wp-admin/admin.php?page=tkm-tickets`);
    ok((await page.locator('.notice-error', { hasText: 'نسخه دیگری از افزونه تیکت‌چی' }).count()) === 1, 'exactly one copy runs, the other shows the duplicate notice');
    await context.close();
  }

  // restore: only the main folder, active
  cli('plugin', 'deactivate', MAIN, GITHUB);
  fs.rmSync(path.join(PLUGINS, GITHUB), { recursive: true, force: true });
  install(MAIN);
  cli('plugin', 'activate', MAIN);

  await browser.close();
  done();
})().catch((e) => {
  console.error(e);
  process.exit(1);
});
