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

  console.log('\nInbox');
  const api = (path, opts = {}) => page.evaluate(async ([path, opts]) => {
    const res = await fetch(TKM_APP.root + path, { method: opts.method || 'GET', headers: { 'X-WP-Nonce': TKM_APP.nonce, 'Content-Type': 'application/json' }, body: opts.body ? JSON.stringify(opts.body) : undefined });
    return { status: res.status, data: await res.json().catch(() => null) };
  }, [path, opts]);
  await page.goto(A('tkm-tickets') + '#/view/all');
  await page.waitForSelector('.tkm-ib-item[data-id]');
  const total = Number(rows("SELECT COUNT(*) AS c FROM {p}tkm_tickets WHERE status != 'trash'")[0].c);
  ok((await page.locator('.tkm-ib-item[data-id]').count()) === Math.min(30, total), 'inbox lists all tickets in «همه»', `${total}`);
  const firstTicket = rows("SELECT ID FROM {p}tkm_tickets ORDER BY ID LIMIT 1")[0].ID;
  await page.fill('[data-search]', '#' + firstTicket);
  await page.waitForFunction((id) => { const n = document.querySelectorAll('.tkm-ib-item[data-id]'); return n.length === 1 && n[0].dataset.id == id; }, firstTicket, { timeout: 8000 }).catch(() => {});
  ok((await page.locator('.tkm-ib-item[data-id]').count()) === 1, 'search by #id finds the ticket');
  await page.fill('[data-search]', "x' OR 1=1 -- ");
  await page.waitForSelector('.tkm-ib-empty', { timeout: 8000 }).catch(() => {});
  ok(await page.locator('.tkm-ib-empty').isVisible(), 'search input is escaped (no SQL injection, empty result)');
  await page.fill('[data-search]', '');
  await page.waitForTimeout(600);

  await page.click('[data-view="waiting"]');
  await page.waitForTimeout(800);
  const waiting = Number(rows("SELECT COUNT(*) AS c FROM {p}tkm_tickets WHERE status = 'answerd'")[0].c);
  ok((await page.locator('.tkm-ib-item[data-id]').count()) === waiting, 'view «منتظر مشتری» filters by status', `${waiting}`);

  // conversation: reply, internal note, properties
  const ticket = rows("SELECT t.* FROM {p}tkm_tickets t WHERE EXISTS (SELECT 1 FROM {p}tkm_replies r WHERE r.ticket_id = t.ID) AND t.status NOT IN ('trash','closed','finish') ORDER BY t.ID LIMIT 1")[0];
  await page.goto(A('tkm-tickets') + '#/ticket/' + ticket.ID);
  await page.waitForSelector('[data-composer]');
  const replyCount = Number(rows(`SELECT COUNT(*) AS c FROM {p}tkm_replies WHERE ticket_id = ${ticket.ID}`)[0].c);
  ok((await page.locator('.tkm-ib-msg').count()) === replyCount + 1, 'deep link opens the conversation (opening message + replies)');
  await page.click('[data-send]:not([data-send="close"])');
  ok(await page.locator('.tkm-toast--error').isVisible(), 'empty reply blocked');
  await page.click('[data-canned-toggle]');
  await page.click('[data-canned="1"]');
  ok((await page.inputValue('[data-composer-input]')).length > 5, 'canned response inserted');
  await page.fill('[data-composer-input]', 'پاسخ کارشناس ' + stamp);
  await page.setInputFiles('[data-composer-file]', { name: 'fix.txt', mimeType: 'text/plain', buffer: Buffer.from('patch') });
  await page.keyboard.press('Control+Enter');
  await page.waitForSelector(`.tkm-ib-msg :text("پاسخ کارشناس ${stamp}")`, { timeout: 8000 }).catch(() => {});
  let t = rows(`SELECT * FROM {p}tkm_tickets WHERE ID = ${ticket.ID}`)[0];
  const lastReply = rows(`SELECT * FROM {p}tkm_replies WHERE ticket_id = ${ticket.ID} ORDER BY ID DESC LIMIT 1`)[0];
  ok(lastReply.body.includes('پاسخ کارشناس ' + stamp) && lastReply.from_admin == 1 && lastReply.is_internal == 0, 'staff reply stored (Ctrl+Enter)');
  ok(/fix(-\d+)?\.txt/.test(lastReply.file || ''), 'reply attachment uploaded');
  ok(t.status === 'answerd' && t.first_response_at, 'status → answered and first response stamped', t.status);

  await page.keyboard.press('n');
  await page.fill('[data-composer-input]', 'یادداشت داخلی ' + stamp);
  await page.click('[data-send]');
  await page.waitForSelector('.tkm-ib-msg--note', { timeout: 8000 }).catch(() => {});
  const note = rows(`SELECT * FROM {p}tkm_replies WHERE ticket_id = ${ticket.ID} ORDER BY ID DESC LIMIT 1`)[0];
  ok(note.is_internal == 1 && rows(`SELECT status FROM {p}tkm_tickets WHERE ID = ${ticket.ID}`)[0].status === 'answerd', 'internal note stored without changing status');

  await page.click('[data-priority="high"]');
  await page.waitForTimeout(700);
  await page.selectOption('[data-prop="assignee_id"]', '1');
  await page.waitForTimeout(700);
  await page.fill('[data-tag-input]', 'تست ' + stamp);
  await page.keyboard.press('Enter');
  await page.waitForTimeout(900);
  await page.fill('[data-pinned-note]', 'یادداشت ثابت ' + stamp);
  await page.locator('[data-pinned-note]').blur();
  await page.waitForTimeout(900);
  t = rows(`SELECT * FROM {p}tkm_tickets WHERE ID = ${ticket.ID}`)[0];
  ok(t.priority === 'high' && t.assignee_id == 1 && t.note === 'یادداشت ثابت ' + stamp, 'priority, assignee and pinned note saved', JSON.stringify([t.priority, t.assignee_id, t.note]));
  ok(rows(`SELECT COUNT(*) AS c FROM {p}tkm_ticket_tags tt JOIN {p}tkm_tags g ON g.ID = tt.tag_id WHERE tt.ticket_id = ${ticket.ID} AND g.name = 'تست ${stamp}'`)[0].c == 1, 'new tag created and attached');
  await page.click('[data-side-tab="activity"]');
  ok((await page.locator('.tkm-ib-timeline__item').count()) >= 4, 'activity timeline records the changes');
  await page.click('[data-side-tab="customer"]');
  ok(await page.locator('.tkm-ib-customer').isVisible(), 'customer 360 card renders');
  await shot(page, 'admin-inbox');

  await page.click('[data-send="close"]').catch(() => {});
  await page.keyboard.press('r');
  await page.fill('[data-composer-input]', 'حل شد');
  await page.click('[data-send="close"]');
  await page.waitForTimeout(1200);
  ok(['closed'].includes(rows(`SELECT status FROM {p}tkm_tickets WHERE ID = ${ticket.ID}`)[0].status), '"send & close" closes the ticket');

  // bulk: trash then restore
  await page.goto(A('tkm-tickets') + '#/view/all');
  await page.waitForSelector('.tkm-ib-item[data-id]');
  const bulkId = await page.locator('.tkm-ib-item[data-id]').first().getAttribute('data-id');
  await page.locator('.tkm-ib-item[data-id]').first().hover();
  await page.check(`[data-select="${bulkId}"]`);
  ok(await page.locator('[data-bulk]').isVisible(), 'bulk bar appears on selection');
  await page.click('[data-bulk-action="trash"]');
  await page.waitForTimeout(1000);
  ok(rows(`SELECT status FROM {p}tkm_tickets WHERE ID = ${bulkId}`)[0].status === 'trash', 'bulk trash');
  const restore = await api('tickets/bulk', { method: 'POST', body: { ids: [Number(bulkId)], action: 'restore' } });
  ok(restore.status === 200 && rows(`SELECT status FROM {p}tkm_tickets WHERE ID = ${bulkId}`)[0].status === 'open', 'bulk restore via REST');

  // command palette
  await page.keyboard.press('Control+k');
  ok(await page.locator('.tkm-cmdk:not([hidden])').isVisible(), 'Ctrl+K opens the command palette (not wp-admin\'s)');
  ok(await page.locator('.commands-command-menu').count() === 0, 'core command menu does not open on top');
  await page.keyboard.type(String(firstTicket));
  await page.waitForSelector('.tkm-cmdk__item[href*="#/ticket/"]', { timeout: 8000 }).catch(() => {});
  await page.keyboard.press('Enter');
  await page.waitForTimeout(800);
  ok(page.url().includes('#/ticket/'), 'palette jumps to a ticket');

  // legacy deep links
  await page.goto(A('tkm-edit-ticket', '&id=' + ticket.ID));
  ok(page.url().includes('page=tkm-tickets') && page.url().includes('#/ticket/' + ticket.ID), 'old edit-ticket links redirect into the inbox');
  await page.goto(A('tkm-my-departments'));
  ok(page.url().includes('#/view/mine'), 'old «my departments» link redirects to «my tickets»');

  console.log('\nREST API');
  const meta = await api('meta');
  ok(meta.status === 200 && meta.data.me.manage === true && meta.data.statuses.length >= 4, '/meta returns context for the app');
  const bad = await api('tickets/999999');
  ok(bad.status === 404, 'unknown ticket → 404');
  const badPatch = await api('tickets/' + ticket.ID, { method: 'PATCH', body: { priority: 'urgent!!' } });
  ok(badPatch.status === 200 && rows(`SELECT priority FROM {p}tkm_tickets WHERE ID = ${ticket.ID}`)[0].priority === 'high', 'invalid priority ignored');
  const noNonce = await page.evaluate(async () => (await fetch(TKM_APP.root + 'inbox', { credentials: 'same-origin' })).status);
  ok(noNonce === 401 || noNonce === 403, 'REST requires the nonce', String(noNonce));

  console.log('\nAutomations');
  await page.goto(A('tkm-automations'));
  await page.waitForSelector('#tkm-automations[data-state="ready"]');
  const before = await page.locator('.tkm-rule').count();
  await page.click('[data-new-rule]');
  await page.fill('[name="name"]', 'قانون ' + stamp);
  await page.selectOption('[name="trigger"]', 'ticket_created');
  await page.click('[data-add-condition]');
  await page.locator('[data-row="condition"]').last().locator('[data-cond-field]').selectOption('keyword');
  await page.locator('[data-row="condition"]').last().locator('[data-value]').fill('زمان‌بندی' + stamp);
  await page.locator('[data-row="action"]').first().locator('[data-action-type]').selectOption('priority');
  await page.locator('[data-row="action"]').first().locator('[data-value]').selectOption('high');
  await page.click('[data-add-action]');
  await page.locator('[data-row="action"]').last().locator('[data-action-type]').selectOption('note');
  await page.locator('[data-row="action"]').last().locator('[data-value]').fill('یادداشت خودکار');
  await page.click('[data-rule-form] [type="submit"]');
  await page.waitForTimeout(1200);
  ok((await page.locator('.tkm-rule').count()) === before + 1, 'rule saved from the builder');
  await shot(page, 'admin-automations');
  // the rule fires on a real ticket
  const autoId = Number(wp(`echo \\Ticketchi\\Domain\\Tickets::create(['title' => 'زمان‌بندی${stamp}', 'body' => 'تست', 'creator_id' => 2, 'department_id' => (int) $GLOBALS['wpdb']->get_var("SELECT ID FROM {$GLOBALS['wpdb']->prefix}tkm_departments WHERE parent > 0 LIMIT 1"), 'priority' => 'low'], 2, 'customer');`));
  const auto = rows(`SELECT priority FROM {p}tkm_tickets WHERE ID = ${autoId}`)[0];
  ok(auto && auto.priority === 'high', 'automation changed the priority of a matching ticket', JSON.stringify(auto));
  ok(rows(`SELECT COUNT(*) AS c FROM {p}tkm_replies WHERE ticket_id = ${autoId} AND is_internal = 1`)[0].c == 1, 'automation added an internal note');

  console.log('\nReports');
  await page.goto(A('tkm-reports'));
  await page.waitForSelector('#tkm-reports[data-state="ready"]');
  ok((await page.locator('.tkm-rp-kpis .tkm-kpi').count()) === 8, '8 KPI tiles');
  ok(await page.locator('.tkm-rp-trend svg path.tkm-rp-line').count() === 2, 'trend chart has 2 series');
  ok((await page.locator('.tkm-heat__cell').count()) === 7 * 24, '7×24 heatmap');
  const hit = await page.locator('[data-hit]').boundingBox();
  await page.mouse.move(hit.x + hit.width / 2, hit.y + 40);
  ok(await page.locator('[data-tip]').isVisible(), 'trend crosshair tooltip');
  await page.click('[data-days="7"]');
  await page.waitForSelector('#tkm-reports[data-state="ready"]');
  ok((await page.locator('.tkm-mini-table tbody tr').count()) === 7, '7-day range re-renders');
  const csv = await page.request.get(await page.getAttribute('[data-csv]', 'href'));
  ok(csv.status() === 200 && (await csv.text()).includes('شناسه'), 'CSV export downloads');
  await shot(page, 'admin-reports');

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

  console.log('\nTeam & tags');
  await page.goto(A('tkm-departments'));
  await page.waitForSelector('.tkm-tagm__row, #tkm-tag-manager .tkm-ib-none');
  await page.fill('[data-tag-new] [name="name"]', 'برچسب ' + stamp);
  await page.click('[data-tag-new] [type="submit"]');
  await page.waitForTimeout(900);
  ok(rows(`SELECT COUNT(*) AS c FROM {p}tkm_tags WHERE name = 'برچسب ${stamp}'`)[0].c == 1, 'tag created from the team page');

  console.log('\nAgent scope');
  const agent = await session(browser, 'nima');
  await agent.page.goto(A('tkm-tickets') + '#/view/all');
  await agent.page.waitForSelector('.tkm-ib-item[data-id], .tkm-ib-empty');
  const nimaId = wp("echo get_user_by('login','nima')->ID;");
  const visible = Number(rows(`SELECT COUNT(*) AS c FROM {p}tkm_tickets WHERE status != 'trash' AND assignee_id = ${nimaId}`)[0].c);
  ok((await agent.page.locator('.tkm-ib-item[data-id]').count()) === visible, 'agent without a department sees only tickets assigned to them', `${visible}`);
  ok((await agent.page.locator('.tkm-nav__item').count()) === 2, 'agent sees only dashboard + inbox in the nav');
  const foreign = rows(`SELECT ID FROM {p}tkm_tickets WHERE (assignee_id IS NULL OR assignee_id != ${nimaId}) LIMIT 1`)[0].ID;
  const denied = await agent.page.evaluate(async (id) => (await fetch(TKM_APP.root + 'tickets/' + id, { headers: { 'X-WP-Nonce': TKM_APP.nonce } })).status, foreign);
  ok(denied === 403, 'agent cannot open a ticket outside their scope via REST', String(denied));
  await agent.page.goto(A('tkm-reports'));
  ok(await agent.page.locator('.tkm-empty__title', { hasText: 'دسترسی محدود' }).isVisible(), 'reports are manager-only');
  await agent.context.close();

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
  for (const p of ['tkm-analysis', 'tkm-tickets', 'tkm-new-ticket', 'tkm-departments', 'tkm-reports', 'tkm-automations', 'tkm-settings']) {
    await mobile.page.goto(A(p));
    ok(await noHorizontalOverflow(mobile.page), `no horizontal overflow at 390px: ${p}`);
  }
  await mobile.page.goto(A('tkm-tickets') + '#/ticket/' + ticket.ID);
  await mobile.page.waitForSelector('[data-composer]');
  ok(await noHorizontalOverflow(mobile.page) && await mobile.page.locator('[data-composer]').isVisible(), 'mobile inbox shows the conversation full-width');
  await shot(mobile.page, 'admin-mobile-inbox');
  await mobile.page.goto(A('tkm-analysis'));
  await shot(mobile.page, 'admin-mobile-dashboard');

  ok(page.jsErrors.length === 0, 'no JS errors in admin', page.jsErrors.join(' | '));
  await browser.close();
  done();
})().catch((e) => {
  console.error(e);
  process.exit(1); // an open browser would otherwise keep node alive
});
