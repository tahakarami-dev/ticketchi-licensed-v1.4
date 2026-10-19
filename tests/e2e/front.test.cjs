// Customer panel end-to-end test.
// Run: WP_CLI="php wp-cli.phar --path=/site --allow-root" NODE_PATH=$(npm root -g) node tests/e2e/front.test.cjs
const { BASE, ok, wp, launch, session, shot, noHorizontalOverflow, done } = require('./lib.cjs');

const LIST = `${BASE}/my-account/tickets/`;

(async () => {
  const browser = await launch();
  const { page } = await session(browser, 'ali');
  const title = 'تست e2e ' + Date.now();

  console.log('\nList');
  await page.goto(LIST);
  ok(await page.locator('.tkm-app[data-view="list"]').isVisible(), 'dashboard renders in my-account endpoint');
  ok((await page.locator('.tkm-ticket').count()) > 0, 'ticket rows are listed');
  ok(await page.locator('.tkm-stat.is-active').isVisible(), 'active stat card highlighted');
  await page.selectOption('.tkm-toolbar select[name="priority"]', 'low');
  await page.waitForURL(/priority=low/);
  const priorities = await page.locator('.tkm-ticket .tkm-priority').evaluateAll((els) => els.map((e) => e.className));
  ok(priorities.length > 0 && priorities.every((c) => c.includes('tkm-priority--low')), 'priority filter auto-submits and filters', priorities.join(','));
  await page.click('.tkm-toolbar a:has-text("حذف فیلترها")');
  await page.waitForURL((u) => !/priority=/.test(u.toString()));
  ok(true, 'clear filters link resets the list');
  await shot(page, 'front-list');

  console.log('\nNew ticket');
  await page.click('a:has-text("ثبت تیکت جدید")');
  await page.waitForSelector('.tkm-app[data-view="new"]');
  ok(await page.locator('[data-faq]').isVisible(), 'FAQ shown first');
  ok(await page.locator('#tkm-submit-ticket').isHidden(), 'form hidden until FAQ dismissed');
  await page.click('[data-faq-continue]');
  ok(await page.locator('#tkm-submit-ticket').isVisible(), 'form revealed');

  let requests = 0;
  page.on('request', (r) => { if (r.url().includes('admin-ajax.php') && r.method() === 'POST') requests++; });
  await page.click('#tkm-submit-ticket [data-submit]');
  await page.waitForSelector('.tkm-toast--error');
  ok(requests === 0, 'empty form is blocked client-side (no request sent)');
  ok(await page.locator('.tkm-tiles.is-invalid').count() === 1, 'department picker flagged invalid');

  await page.locator('.tkm-tile', { hasText: 'پشتیبانی فنی' }).click();
  ok(await page.locator('[data-subdeps]').isVisible(), 'sub-department chips appear for parent with children');
  await page.locator('.tkm-chip:visible', { hasText: 'نصب و راه‌اندازی' }).click();
  ok(await page.locator('[data-dep-note]').isVisible(), 'department description note shown');
  await page.fill('#title-ticket', title);
  await page.locator('.tkm-segmented--priority label', { has: page.locator('input[value="high"]') }).click();
  await page.fill('#ticket-content', 'متن تست خودکار.\nخط دوم.');
  await page.setInputFiles('#tkm-new-files', { name: 'log.txt', mimeType: 'text/plain', buffer: Buffer.from('hello') });
  ok(await page.locator('.tkm-file--pending').count() === 1, 'attachment chip rendered');
  await page.setInputFiles('#tkm-new-files', { name: 'bad.exe', mimeType: 'application/octet-stream', buffer: Buffer.from('x') });
  ok(await page.locator('.tkm-file--pending').count() === 1, 'disallowed extension rejected client-side');
  await shot(page, 'front-new-filled');

  await Promise.all([page.waitForURL(LIST), page.click('#tkm-submit-ticket [data-submit]')]);
  await page.waitForSelector('.tkm-toast--success');
  ok(true, 'redirected to list with success toast');
  const row = JSON.parse(wp(`global $wpdb; echo wp_json_encode($wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}tkm_tickets WHERE title = %s", ${JSON.stringify(title)})));`));
  ok(row && row.priority === 'high' && row.status === 'open', 'ticket stored with priority=high, status=open');
  ok(row && /log(-\d+)?\.txt/.test(row.file || ''), 'attachment uploaded and stored', row && row.file);
  ok(row && row.department_id > 0 && Number(row.creator_id) > 0, 'department and creator stored');

  console.log('\nConversation');
  await page.goto(`${LIST}?action=single&ticket-id=${row.ID}`);
  ok(await page.locator('.tkm-msg').first().isVisible(), 'opening message rendered');
  ok(await page.locator('.tkm-msg .tkm-file').count() >= 1, 'attachment chip in message');
  await page.click('#reply-submit [data-submit]');
  await page.waitForSelector('.tkm-toast--error');
  ok(true, 'empty reply blocked');

  await page.fill('#body', 'پاسخ تست اول');
  await page.click('#reply-submit [data-submit]');
  await page.waitForSelector('.tkm-toast--success');
  ok(await page.locator('[data-replies] .tkm-msg', { hasText: 'پاسخ تست اول' }).count() === 1, 'reply appended without reload');
  ok((await page.inputValue('#body')) === '', 'composer cleared');

  // Voice note with Chromium's fake microphone.
  await page.click('#reply-submit [data-voice-open]');
  await page.click('[data-rec-toggle]');
  await page.waitForTimeout(1600);
  await page.click('[data-rec-toggle]');
  await page.waitForSelector('[data-rec-actions]:not([hidden])');
  await page.waitForTimeout(300);
  await shot(page, 'front-voice');
  await page.click('[data-rec-attach]');
  ok(await page.locator('#reply-submit [data-voice-chip]').isVisible(), 'voice note attached to composer');
  await page.check('#status', { force: true });
  await page.click('#reply-submit [data-submit]');
  await page.waitForSelector('.tkm-toast--success >> text=بسته شد');
  ok(await page.locator('[data-replies] audio').count() >= 1, 'voice reply rendered with audio player');
  ok(await page.locator('[data-status-badge] [data-status="closed"]').count() >= 1, 'status badge updated to closed');
  const status = wp(`global $wpdb; echo $wpdb->get_var("SELECT status FROM {$wpdb->prefix}tkm_tickets WHERE ID = ${row.ID}");`);
  ok(status === 'closed', 'ticket closed in database', status);

  console.log('\nRating');
  await page.locator('.tkm-stars label[for="tkm-star-4"]').click();
  await page.click('#tkm-submit-rating');
  await page.waitForSelector('.tkm-toast--success >> text=امتیاز');
  const rating = wp(`global $wpdb; echo $wpdb->get_var("SELECT rating FROM {$wpdb->prefix}tkm_ratings WHERE ticket_id = ${row.ID}");`);
  ok(rating === '4', 'rating stored as 4', rating);
  await shot(page, 'front-single');
  ok(page.jsErrors.length === 0, 'no JS errors as customer', page.jsErrors.join(' | '));

  console.log('\nAccess control');
  const other = await session(browser, 'maryam');
  await other.page.goto(`${LIST}?action=single&ticket-id=${row.ID}`);
  ok(await other.page.locator('text=تیکت پیدا نشد').isVisible(), "another customer cannot open the ticket");
  const res = await other.page.evaluate(async (id) => {
    const fd = new FormData();
    fd.append('action', 'tkm-submit-reply');
    fd.append('nonce', window.TKM_DATA_AJAX.nonce);
    fd.append('ticket_id', id);
    fd.append('body', 'hijack');
    return (await fetch(window.TKM_DATA_AJAX.ajax_url, { method: 'POST', body: fd })).json();
  }, row.ID);
  ok(res && res.success === false, 'another customer cannot reply via AJAX');
  const rate = await other.page.evaluate(async (id) => {
    const fd = new FormData();
    fd.append('action', 'tkm_submit_rating');
    fd.append('nonce', window.TKM_DATA_AJAX.nonce);
    fd.append('ticket_id', id);
    fd.append('rating', 99);
    return (await fetch(window.TKM_DATA_AJAX.ajax_url, { method: 'POST', body: fd })).json();
  }, row.ID);
  ok(rate && rate.success === false, 'out-of-range / foreign rating rejected');

  console.log('\nShortcode + mobile');
  const mobile = await session(browser, 'ali', { viewport: { width: 390, height: 844 } });
  for (const url of [LIST, `${LIST}?action=new`, `${LIST}?action=single&ticket-id=${row.ID}`, `${BASE}/support/`]) {
    await mobile.page.goto(url);
    ok(await noHorizontalOverflow(mobile.page), 'no horizontal overflow at 390px: ' + url.replace(BASE, ''));
  }
  await mobile.page.goto(LIST);
  await shot(mobile.page, 'front-mobile-list');
  await mobile.page.goto(`${LIST}?action=single&ticket-id=${row.ID}`);
  await shot(mobile.page, 'front-mobile-single');
  await mobile.page.goto(`${BASE}/support/`);
  ok(await mobile.page.locator('.tkm-app[data-view="list"]').isVisible(), '[tickets] shortcode renders');
  const href = await mobile.page.locator('.tkm-ticket').first().getAttribute('href');
  ok(href && href.includes('/support/') && href.includes('action=single'), 'shortcode links stay on the shortcode page', href);
  await Promise.all([mobile.page.waitForURL(/action=single/), mobile.page.click('.tkm-ticket >> nth=0')]);
  ok(await mobile.page.locator('.tkm-app[data-view="single"]').isVisible(), 'shortcode single view opens');

  const guest = await browser.newPage();
  await guest.goto(`${BASE}/support/`);
  ok(await guest.locator('.tkm-gate').isVisible(), 'guests see the login gate');

  await browser.close();
  done();
})().catch((e) => {
  console.error(e);
  process.exitCode = 1;
});
