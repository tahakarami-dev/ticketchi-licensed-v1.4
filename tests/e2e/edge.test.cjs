// Edge cases: empty states, settings variations, server-side validation, no WooCommerce.
// Run: WP_CLI="php wp-cli.phar --path=/site --allow-root" NODE_PATH=$(npm root -g) node tests/e2e/edge.test.cjs
const { BASE, ok, wp, launch, session, shot, done } = require('./lib.cjs');
const { execFileSync } = require('child_process');

function cli(...args) {
  const [bin, ...rest] = process.env.WP_CLI.split(' ');
  return execFileSync(bin, [...rest, ...args], { encoding: 'utf8', stdio: ['ignore', 'pipe', 'ignore'] }).trim();
}

(async () => {
  wp(`if (!get_user_by('login','newbie')) { wp_insert_user(['user_login'=>'newbie','user_pass'=>'newbie','user_email'=>'newbie@example.com','display_name'=>'کاربر جدید','role'=>'customer']); }`);
  const solo = 'تک‌بخش ' + Date.now();
  wp(`global $wpdb; $wpdb->insert($wpdb->prefix.'tkm_departments', ['name'=>'${solo}','parent'=>0,'position'=>9,'description'=>'بدون زیرمجموعه']);`);
  const settings = wp(`echo wp_json_encode(get_option('tkm_settings'));`);
  wp(`$o = get_option('tkm_settings'); $o['faq_swicher'] = ''; update_option('tkm_settings', $o);`);

  const browser = await launch();
  const { page } = await session(browser, 'newbie');

  console.log('\nEmpty states');
  await page.goto(`${BASE}/my-account/tickets/`);
  ok(await page.locator('.tkm-empty__title', { hasText: 'هنوز تیکتی ثبت نکرده‌اید' }).isVisible(), 'empty list state for a new customer');
  await shot(page, 'edge-empty');
  await page.goto(`${BASE}/my-account/tickets/?action=single&ticket-id=999999`);
  ok(await page.locator('text=تیکت پیدا نشد').isVisible(), 'missing ticket shows not-found state (no PHP notice)');

  console.log('\nSettings variations');
  await page.goto(`${BASE}/my-account/tickets/?action=new`);
  ok(await page.locator('#tkm-submit-ticket').isVisible(), 'form shown directly when FAQ is disabled');
  await page.locator('.tkm-tile', { hasText: solo }).click();
  ok(await page.locator('[data-subdeps]').isHidden(), 'no sub-department step for a department without children');
  ok(await page.locator('[data-dep-note]').isVisible(), 'parent description shown');
  await page.fill('#ticket-content', 'تیکت دپارتمان بدون زیرمجموعه');
  await Promise.all([page.waitForURL(/my-account\/tickets\/?$/), page.click('#tkm-submit-ticket [data-submit]')]);
  const dept = wp(`global $wpdb; echo $wpdb->get_var("SELECT d.name FROM {$wpdb->prefix}tkm_tickets t JOIN {$wpdb->prefix}tkm_departments d ON d.ID=t.department_id WHERE t.creator_id=(SELECT ID FROM {$wpdb->users} WHERE user_login='newbie') ORDER BY t.ID DESC LIMIT 1");`);
  ok(dept === solo, 'ticket stored on the parent department', dept);
  const title = wp(`global $wpdb; echo $wpdb->get_var("SELECT title FROM {$wpdb->prefix}tkm_tickets WHERE creator_id=(SELECT ID FROM {$wpdb->users} WHERE user_login='newbie') ORDER BY ID DESC LIMIT 1");`);
  ok(title === 'بدون عنوان', 'empty title falls back to «بدون عنوان»', title);

  console.log('\nServer-side validation');
  await page.goto(`${BASE}/my-account/tickets/?action=new`);
  const upload = await page.evaluate(async () => {
    const fd = new FormData();
    fd.append('action', 'tkm-submit-ticket');
    fd.append('nonce', TKM_DATA_AJAX.nonce);
    fd.append('child-department', document.querySelector('input[name="tkm-parent-department"]').value);
    fd.append('content', 'x');
    fd.append('file[]', new File(['<?php echo 1;'], 'shell.php', { type: 'application/x-php' }));
    return (await fetch(TKM_DATA_AJAX.ajax_url, { method: 'POST', body: fd })).json();
  });
  ok(upload && upload.success === false && /مجاز نیست/.test(upload.result), 'disallowed file type rejected by the server', upload && upload.result);
  const badDept = await page.evaluate(async () => {
    const fd = new FormData();
    fd.append('action', 'tkm-submit-ticket');
    fd.append('nonce', TKM_DATA_AJAX.nonce);
    fd.append('child-department', '987654');
    fd.append('content', 'x');
    return (await fetch(TKM_DATA_AJAX.ajax_url, { method: 'POST', body: fd })).json();
  });
  ok(badDept && badDept.success === false, 'unknown department rejected');
  const badVoice = await page.evaluate(async () => {
    const fd = new FormData();
    fd.append('action', 'tkm-submit-ticket');
    fd.append('nonce', TKM_DATA_AJAX.nonce);
    fd.append('child-department', document.querySelector('input[name="tkm-parent-department"]').value);
    fd.append('content', 'x');
    fd.append('audioData', 'data:text/html;base64,PHNjcmlwdD4=');
    return (await fetch(TKM_DATA_AJAX.ajax_url, { method: 'POST', body: fd })).json();
  });
  ok(badVoice && badVoice.success === false, 'non-audio voice payload rejected');
  const guest = await browser.newPage();
  await guest.goto(`${BASE}/`);
  const guestRes = await guest.evaluate(async (base) => {
    const fd = new FormData();
    fd.append('action', 'tkm-submit-ticket');
    fd.append('content', 'spam');
    fd.append('child-department', '1');
    return (await fetch(base + '/wp-admin/admin-ajax.php', { method: 'POST', body: fd })).json();
  }, BASE);
  ok(guestRes && guestRes.success === false, 'guest ticket submission rejected');

  console.log('\nSMS notification enabled');
  wp(`$o = get_option('tkm_settings'); $o['user_create_sms'] = '1'; $o['sms-service'] = 'melipayamak'; $o['phone-service-key-user'] = 'billing_phone'; $o['user_create_pattern'] = "{{ticket_id}}\n{{department}}"; update_option('tkm_settings', $o); update_user_meta(get_user_by('login','newbie')->ID, 'billing_phone', '09120000000');`);
  const sms = await page.evaluate(async () => {
    const fd = new FormData();
    fd.append('action', 'tkm-submit-ticket');
    fd.append('nonce', TKM_DATA_AJAX.nonce);
    fd.append('child-department', document.querySelector('input[name="tkm-parent-department"]').value);
    fd.append('content', 'با پیامک فعال');
    const res = await fetch(TKM_DATA_AJAX.ajax_url, { method: 'POST', body: fd });
    const text = await res.text();
    try { return JSON.parse(text); } catch (e) { return { raw: text.slice(0, 200) }; }
  });
  ok(sms && sms.success === true, 'ticket submission still returns clean JSON with SMS enabled', JSON.stringify(sms).slice(0, 200));
  ok(page.jsErrors.length === 0, 'no JS errors', page.jsErrors.join(' | '));

  console.log('\nWithout WooCommerce');
  cli('plugin', 'deactivate', 'woocommerce');
  try {
    const s2 = await session(browser, 'newbie');
    const r = await s2.page.goto(`${BASE}/support/`);
    ok(r.status() === 200 && await s2.page.locator('.tkm-app[data-view="list"]').isVisible(), 'shortcode panel works without WooCommerce');
    await s2.page.click('a:has-text("ثبت تیکت جدید")');
    ok(s2.page.url().includes('/support/') && s2.page.url().includes('action=new'), 'links stay on the shortcode page');
    await s2.page.locator('.tkm-tile', { hasText: solo }).click();
    await s2.page.fill('#ticket-content', 'بدون ووکامرس');
    await Promise.all([s2.page.waitForURL(/\/support\/?$/), s2.page.click('#tkm-submit-ticket [data-submit]')]);
    ok(await s2.page.locator('.tkm-toast--success').isVisible(), 'ticket submitted and redirected back to the shortcode page');

    const admin = await session(browser, 'admin');
    for (const p of ['tkm-analysis', 'tkm-tickets', 'tkm-departments', 'tkm-new-ticket', 'tkm-settings']) {
      const res = await admin.page.goto(`${BASE}/wp-admin/admin.php?page=${p}`);
      ok(res.status() === 200 && await admin.page.locator('.tkm-topbar').isVisible(), `admin ${p} works without WooCommerce`);
    }
  } finally {
    cli('plugin', 'activate', 'woocommerce');
    wp(`update_option('tkm_settings', json_decode(${JSON.stringify(settings)}, true));`);
  }

  await browser.close();
  done();
})().catch((e) => {
  console.error(e);
  process.exit(1); // an open browser would otherwise keep node alive
});
