// Admin panel end-to-end test.
// Run: WP_CLI="php wp-cli.phar --path=/site --allow-root" NODE_PATH=$(npm root -g) node tests/e2e/admin.test.cjs
const { BASE, ok, wp, launch, session, shot, noHorizontalOverflow, done } = require('./lib.cjs');

const A = (page, args = '') => `${BASE}/wp-admin/admin.php?page=${page}${args}`;
const q = (sql) => wp(`global $wpdb; echo wp_json_encode($wpdb->get_results(str_replace('{p}', $wpdb->prefix, ${JSON.stringify(sql)})));`);
const rows = (sql) => JSON.parse(q(sql) || '[]');

async function fillEditor(page, id, text) {
  await page.evaluate(([id, text]) => {
    if (window.tinymce && tinymce.get(id) && !tinymce.get(id).isHidden()) tinymce.get(id).setContent('<p>' + text + '</p>');
    else document.getElementById(id).value = text;
  }, [id, text]);
}

(async () => {
  const browser = await launch();
  const { page } = await session(browser, 'admin');
  const stamp = Date.now();

  console.log('\nDashboard');
  await page.goto(A('tkm-analysis'));
  ok(await page.locator('.tkm-topbar').isVisible(), 'app shell renders');
  ok((await page.locator('.tkm-kpi').count()) === 4, '4 KPI tiles');
  ok((await page.locator('.tkm-chart__col').count()) === 30, '30-day column chart');
  await page.locator('.tkm-chart__col').nth(5).hover();
  ok(await page.locator('.tkm-chart__tooltip').isVisible(), 'chart tooltip on hover');
  await page.click('.tkm-table-view summary');
  ok((await page.locator('.tkm-mini-table tbody tr').count()) === 30, 'chart table view available');
  await shot(page, 'admin-dashboard');

  console.log('\nTheme');
  await page.click('[data-theme-toggle]');
  ok((await page.getAttribute('#tkm-admin', 'data-theme')) === 'dark', 'dark mode toggles');
  await page.reload();
  ok((await page.getAttribute('#tkm-admin', 'data-theme')) === 'dark', 'dark mode persists across reloads');
  await shot(page, 'admin-dashboard-dark');
  await page.goto(A('tkm-tickets'));
  await shot(page, 'admin-tickets-dark');
  await page.click('[data-theme-toggle]');

  console.log('\nTickets list');
  await page.goto(A('tkm-tickets'));
  const total = Number(rows("SELECT COUNT(*) AS c FROM {p}tkm_tickets WHERE status != 'trash'")[0].c);
  ok((await page.locator('.wp-list-table tbody tr').count()) === Math.min(20, total), 'list shows first page of tickets', `${total}`);
  await page.fill('input[name="search"]', "x' OR 1=1 -- ");
  await Promise.all([page.waitForNavigation(), page.click('.tkm-filters button[type="submit"]')]);
  ok(await page.locator('.tkm-empty').isVisible(), 'search input is escaped (no SQL injection, empty result)');
  await page.goto(A('tkm-tickets', '&search=%23' + rows("SELECT ID FROM {p}tkm_tickets ORDER BY ID LIMIT 1")[0].ID));
  ok((await page.locator('.wp-list-table tbody tr').count()) === 1, 'search by #id finds the ticket');
  await page.goto(A('tkm-tickets', '&status=open'));
  const openRows = await page.locator('.wp-list-table tbody tr .tkm-badge').evaluateAll((els) => els.map((e) => e.dataset.status));
  ok(openRows.length > 0 && openRows.every((s) => s === 'open'), 'status tab filters');
  await page.goto(A('tkm-tickets', '&orderby=priority&order=desc'));
  ok(await page.locator('.wp-list-table tbody tr').first().locator('.tkm-priority--high').count() === 1, 'sort by priority works');

  // trash via row action, then restore via bulk action
  await page.goto(A('tkm-tickets'));
  const firstId = await page.locator('.wp-list-table tbody input[name="id[]"]').first().getAttribute('value');
  await page.locator('.wp-list-table tbody tr').first().hover();
  await Promise.all([page.waitForNavigation(), page.locator('.wp-list-table tbody tr').first().locator('.row-actions .trash a').click()]);
  ok(page.url().indexOf('action=trash') === -1, 'trash action redirects (PRG)');
  ok(await page.locator('.tkm-flash').isVisible(), 'flash message shown after trash');
  ok(rows(`SELECT status FROM {p}tkm_tickets WHERE ID = ${firstId}`)[0].status === 'trash', 'ticket moved to trash');
  await page.goto(A('tkm-tickets', '&status=trash'));
  await page.check(`.wp-list-table input[name="id[]"][value="${firstId}"]`);
  await page.selectOption('#bulk-action-selector-top', 'bulk-open');
  await Promise.all([page.waitForNavigation(), page.click('#doaction')]);
  ok(rows(`SELECT status FROM {p}tkm_tickets WHERE ID = ${firstId}`)[0].status === 'open', 'bulk status change restores ticket');
  await shot(page, 'admin-tickets');

  console.log('\nCompose');
  await page.goto(A('tkm-new-ticket'));
  await page.click('#publish');
  ok(await page.locator('.tkm-toast--error').isVisible(), 'empty compose blocked client-side');
  for (const login of ['maryam', 'reza']) {
    await page.click('#ticket-user-id + .select2 .select2-selection');
    await page.keyboard.type(login);
    await page.waitForSelector('.select2-results__option--selectable, .select2-results__option[aria-selected]', { timeout: 8000 });
    await page.keyboard.press('Enter');
  }
  ok((await page.locator('#ticket-user-id option:checked').count()) === 2, 'select2 user search picks 2 recipients');
  const title = 'اطلاعیه ' + stamp;
  await page.fill('#ticket-title', title);
  await page.selectOption('#tkm-department', { index: 2 });
  await fillEditor(page, 'tkm-content', 'متن اطلاعیه تستی');
  await page.locator('.tkm-segmented--full label', { has: page.locator('input[value="high"]') }).click();
  await shot(page, 'admin-compose');
  await Promise.all([page.waitForNavigation(), page.click('#publish')]);
  const sent = rows(`SELECT * FROM {p}tkm_tickets WHERE title = '${title}'`);
  ok(sent.length === 2 && sent.every((t) => t.from_admin == 1 && t.priority === 'high'), 'one ticket per recipient, from_admin=1, priority=high');
  ok(await page.locator('.tkm-flash').isVisible(), 'flash after sending');

  console.log('\nWorkspace');
  const ticket = rows("SELECT t.* FROM {p}tkm_tickets t WHERE EXISTS (SELECT 1 FROM {p}tkm_replies r WHERE r.ticket_id = t.ID) AND t.status != 'trash' ORDER BY t.ID LIMIT 1")[0];
  await page.goto(A('tkm-edit-ticket', '&id=' + ticket.ID));
  ok(await page.locator('.tkm-ws').isVisible(), 'workspace renders');
  const replyCount = rows(`SELECT COUNT(*) AS c FROM {p}tkm_replies WHERE ticket_id = ${ticket.ID}`)[0].c;
  ok((await page.locator('.tkm-thread [data-msg]').count()) === Number(replyCount) + 1, 'thread shows opening message + replies');

  await page.click('[data-reply-send]');
  ok(await page.locator('.tkm-toast--error').isVisible(), 'empty reply blocked');
  await page.selectOption('#save_select', { index: 1 });
  const canned = await page.evaluate(() => tinymce.get('reply_content').getContent({ format: 'text' }));
  ok(canned.length > 5, 'canned response inserted into editor');
  await fillEditor(page, 'reply_content', 'پاسخ کارشناس ' + stamp);
  await page.locator('.tkm-segmented--full label', { has: page.locator('input[value="high"]') }).click();
  await page.fill('#note-ticket', 'یادداشت ' + stamp);
  await page.setInputFiles('#tkm_reply_files', { name: 'fix.txt', mimeType: 'text/plain', buffer: Buffer.from('patch') });
  await Promise.all([page.waitForNavigation(), page.click('[data-reply-send]')]);
  let t = rows(`SELECT * FROM {p}tkm_tickets WHERE ID = ${ticket.ID}`)[0];
  const lastReply = rows(`SELECT * FROM {p}tkm_replies WHERE ticket_id = ${ticket.ID} ORDER BY ID DESC LIMIT 1`)[0];
  ok(lastReply.body.includes('پاسخ کارشناس ' + stamp) && lastReply.from_admin == 1, 'staff reply stored with from_admin=1');
  ok(/fix(-\d+)?\.txt/.test(lastReply.file || ''), 'reply attachment uploaded');
  ok(t.status === 'answerd', 'status auto-set to answered after reply', t.status);
  ok(t.priority === 'high' && t.note === 'یادداشت ' + stamp, 'priority + note saved');
  ok(page.url().includes('tkm-edit-ticket') && await page.locator('.tkm-flash').count() > 0, 'redirected back with flash (PRG)');
  await shot(page, 'admin-workspace');

  // edit an existing reply
  const editId = lastReply.ID;
  await page.locator(`#reply-${editId} [data-toggle-edit]`).click();
  await page.fill(`#tkm-reply-body-${editId}`, 'ویرایش‌شده ' + stamp);
  await Promise.all([page.waitForNavigation(), page.locator('.tkm-head [data-save]').click()]);
  ok(rows(`SELECT body FROM {p}tkm_replies WHERE ID = ${editId}`)[0].body === 'ویرایش‌شده ' + stamp, 'reply edited in place');

  // date edit (datetime-local, site timezone)
  await page.fill('#date_ticket', '2026-01-15T10:30');
  await Promise.all([page.waitForNavigation(), page.locator('.tkm-head [data-save]').click()]);
  t = rows(`SELECT create_date FROM {p}tkm_tickets WHERE ID = ${ticket.ID}`)[0];
  ok(/^2026-01-15 \d\d:30:00$/.test(t.create_date), 'creation date saved as a valid datetime', t.create_date);

  // delete reply
  await page.locator(`#reply-${editId} [data-delete-reply]`).click();
  await Promise.all([page.waitForNavigation(), page.locator('.tkm-modal [data-ok]').click()]);
  ok(rows(`SELECT COUNT(*) AS c FROM {p}tkm_replies WHERE ID = ${editId}`)[0].c == 0, 'reply deleted after confirm');

  // reply & close
  await fillEditor(page, 'reply_content', 'حل شد');
  await Promise.all([page.waitForNavigation(), page.click('[data-reply-close]')]);
  ok(rows(`SELECT status FROM {p}tkm_tickets WHERE ID = ${ticket.ID}`)[0].status === 'closed', '"send & close" closes the ticket');
  ok(page.jsErrors.length === 0, 'no JS errors so far', page.jsErrors.join(' | '));

  console.log('\nDepartments');
  await page.goto(A('tkm-departments'));
  const dname = 'دپارتمان ' + stamp;
  await page.fill('#tkm-dept-name', dname);
  await page.selectOption('#tkm-dept-parent', { index: 1 });
  await page.click('#department-answerabel + .select2 .select2-selection');
  await page.keyboard.type('sara');
  await page.waitForSelector('.select2-results__option--selectable', { timeout: 8000 });
  await page.keyboard.press('Enter');
  await page.fill('#tkm-dept-desc', 'توضیح تست');
  await Promise.all([page.waitForNavigation(), page.click('#submit')]);
  const dept = rows(`SELECT * FROM {p}tkm_departments WHERE name = '${dname}'`)[0];
  ok(dept && Number(dept.parent) > 0, 'department created under a parent');
  ok(rows(`SELECT COUNT(*) AS c FROM {p}tkm_users WHERE department_id = ${dept.ID}`)[0].c == 1, 'staff assigned on create');
  ok(await page.locator('.tkm-dept__name', { hasText: dname }).isVisible(), 'department appears in the tree');
  await shot(page, 'admin-departments');

  await page.goto(A('tkm-departments', `&action=edit&id=${dept.ID}`));
  ok((await page.locator('#department-answerabel option:checked').count()) === 1, 'edit form preloads assigned staff');
  await page.click('#department-answerabel + .select2 .select2-selection');
  await page.keyboard.type('admin');
  await page.waitForSelector('.select2-results__option--selectable', { timeout: 8000 });
  await page.keyboard.press('Enter');
  await page.fill('#tkm-dept-name', dname + ' ویرایش');
  await Promise.all([page.waitForNavigation(), page.click('#submit')]);
  ok(rows(`SELECT COUNT(*) AS c FROM {p}tkm_users WHERE department_id = ${dept.ID}`)[0].c == 2, 'staff list updated on edit (was broken before)');
  ok(rows(`SELECT name FROM {p}tkm_departments WHERE ID = ${dept.ID}`)[0].name === dname + ' ویرایش', 'department renamed');

  await page.locator('.tkm-dept', { hasText: dname + ' ویرایش' }).locator('[data-confirm]').click();
  await Promise.all([page.waitForNavigation(), page.locator('.tkm-modal [data-ok]').click()]);
  ok(rows(`SELECT COUNT(*) AS c FROM {p}tkm_departments WHERE ID = ${dept.ID}`)[0].c == 0, 'department deleted after confirm');
  ok(rows(`SELECT COUNT(*) AS c FROM {p}tkm_users WHERE department_id = ${dept.ID}`)[0].c == 0, 'its staff assignments removed');

  console.log('\nMy queue');
  await page.goto(A('tkm-my-departments', '&queue=all'));
  ok(await page.locator('.tkm-table tbody tr, .tkm-empty').first().isVisible(), 'my queue renders');

  console.log('\nSettings');
  await page.goto(A('tkm-settings'));
  ok(await page.locator('.tkm-topbar').count() === 1 && await page.locator('.tkm-kpis').count() === 0, 'settings wrapped in shell, no duplicate dashboard');
  ok(await page.locator('.tkm-settings .csf-nav').isVisible(), 'Codestar navigation visible');
  await page.click('.csf-nav a:has-text("استایل")');
  await page.locator('[data-depend-id="primary_color"]').evaluate((el) => { el.value = '#7c3aed'; el.dispatchEvent(new Event('change', { bubbles: true })); });
  await page.click('.csf-footer .csf-save');
  await page.waitForResponse((r) => r.url().includes('admin-ajax.php'));
  await page.waitForTimeout(500);
  const color = wp(`echo tkm_settings('primary_color');`);
  ok(color.toLowerCase() === '#7c3aed', 'settings save via Codestar', color);
  await shot(page, 'admin-settings');
  const front = await session(browser, 'ali');
  await front.page.goto(`${BASE}/my-account/tickets/`);
  const primary = await front.page.evaluate(() => getComputedStyle(document.querySelector('.tkm-app')).getPropertyValue('--tkm-primary').trim());
  ok(primary.toLowerCase() === '#7c3aed', 'brand color flows to the customer panel', primary);
  wp(`$o = get_option('tkm_settings'); $o['primary_color'] = '#2A48CD'; update_option('tkm_settings', $o);`);

  console.log('\nSecurity');
  const res = await front.page.evaluate(async () => {
    const fd = new FormData();
    fd.append('action', 'tkm_search_user');
    fd.append('term', 'a');
    return (await fetch('/wp-admin/admin-ajax.php', { method: 'POST', body: fd })).status;
  });
  ok(res === 403, 'customers cannot enumerate users via tkm_search_user', String(res));

  console.log('\nResponsive');
  const mobile = await session(browser, 'admin', { viewport: { width: 390, height: 844 } });
  for (const p of ['tkm-analysis', 'tkm-tickets', 'tkm-new-ticket', 'tkm-departments', 'tkm-my-departments', 'tkm-settings']) {
    await mobile.page.goto(A(p));
    ok(await noHorizontalOverflow(mobile.page), `no horizontal overflow at 390px: ${p}`);
  }
  await mobile.page.goto(A('tkm-edit-ticket', '&id=' + ticket.ID));
  ok(await noHorizontalOverflow(mobile.page), 'no horizontal overflow at 390px: tkm-edit-ticket');
  await shot(mobile.page, 'admin-mobile-workspace');
  await mobile.page.goto(A('tkm-analysis'));
  await shot(mobile.page, 'admin-mobile-dashboard');

  ok(page.jsErrors.length === 0, 'no JS errors in admin', page.jsErrors.join(' | '));
  await browser.close();
  done();
})().catch((e) => {
  console.error(e);
  process.exit(1); // an open browser would otherwise keep node alive
});
