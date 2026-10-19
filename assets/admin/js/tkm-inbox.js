/* Ticketchi — support inbox (3-pane, REST-driven). */
(function () {
    'use strict';

    var K = window.TKM;
    var root = document.getElementById('tkm-inbox');
    if (!K || !root) return;

    var fa = K.fa, esc = K.esc, icon = K.icon;
    var $ = function (sel, ctx) { return (ctx || root).querySelector(sel); };
    var $$ = function (sel, ctx) { return Array.prototype.slice.call((ctx || root).querySelectorAll(sel)); };

    var VIEWS = [
        { key: 'mine', label: 'تیکت‌های من', icon: 'user_check' },
        { key: 'unassigned', label: 'بدون مسئول', icon: 'user' },
        { key: 'active', label: 'همه تیکت‌های باز', icon: 'inbox' },
        { key: 'waiting', label: 'منتظر مشتری', icon: 'clock' },
        { key: 'sla', label: 'در خطر SLA', icon: 'alert', alert: true },
        { key: 'closed', label: 'بسته‌شده', icon: 'check' },
        { key: 'all', label: 'همه', icon: 'ticket' },
        { key: 'trash', label: 'زباله‌دان', icon: 'trash' }
    ];
    var VIEW_LABEL = {};
    VIEWS.forEach(function (v) { VIEW_LABEL[v.key] = v.label; });

    var state = {
        meta: null,
        scope: { view: 'active' },          // view | department | tag | customer
        filters: { priority: '', sort: 'updated', search: '' },
        items: [], total: 0, page: 1, pages: 1,
        loading: false,
        selected: {},
        current: null,                       // ticket id
        detail: null,
        mode: 'reply',                       // reply | note
        files: [],
        sideTab: 'details'
    };

    var els = {
        views: $('[data-views]'),
        departments: $('[data-departments]'),
        tags: $('[data-tags]'),
        items: $('[data-items]'),
        total: $('[data-total]'),
        title: $('[data-list-title]'),
        search: $('[data-search]'),
        convo: $('[data-convo]'),
        bulk: $('[data-bulk]'),
        checkAll: $('[data-check-all]')
    };
    var placeholderHtml = els.convo.innerHTML;

    /* ═════════════ Meta & sidebar ═════════════ */

    function loadMeta() {
        return K.api('meta').then(function (meta) {
            state.meta = meta;
            renderNav();
            renderBulkControls();
            return meta;
        });
    }

    function renderNav() {
        var m = state.meta, counts = m.counts || {};
        els.views.innerHTML = VIEWS.map(function (v) {
            var c = counts[v.key];
            var active = state.scope.view === v.key && !state.scope.department && !state.scope.tag && !state.scope.customer;
            return '<a href="#/view/' + v.key + '" class="tkm-ib-view' + (active ? ' is-active' : '') + (v.alert && c ? ' is-alert' : '') + '" data-view="' + v.key + '">' +
                icon(v.icon, 16) + '<span>' + esc(v.label) + '</span>' +
                (c ? '<span class="tkm-ib-count tkm-num">' + fa(c) + '</span>' : '') + '</a>';
        }).join('');

        els.departments.innerHTML = m.departments.length ? m.departments.map(function (d) {
            var html = depLink(d, false);
            d.children.forEach(function (c) { html += depLink(c, true); });
            return html;
        }).join('') : '<p class="tkm-ib-none">دپارتمانی تعریف نشده است.</p>';

        els.tags.innerHTML = m.tags.length ? m.tags.map(function (t) {
            return '<a href="#/tag/' + t.id + '" class="tkm-ib-view tkm-ib-view--tag' + (+state.scope.tag === t.id ? ' is-active' : '') + '"><span class="tkm-dot" style="--c:' + esc(t.color) + '"></span><span>' + esc(t.name) + '</span>' + (t.count ? '<span class="tkm-ib-count tkm-ib-count--muted tkm-num">' + fa(t.count) + '</span>' : '') + '</a>';
        }).join('') : '<p class="tkm-ib-none">هنوز برچسبی ساخته نشده؛ از بخش جزئیات تیکت اضافه کنید.</p>';

        // Compact scope picker for narrow screens where the sidebar is hidden.
        var current = '#' + lastScopeHash();
        var opt = function (hash, label) { return '<option value="' + hash + '"' + (hash === current ? ' selected' : '') + '>' + esc(label) + '</option>'; };
        var html = '<optgroup label="نماها">' + VIEWS.map(function (v) {
            return opt('#/view/' + v.key, v.label + (counts[v.key] ? ' (' + fa(counts[v.key]) + ')' : ''));
        }).join('') + '</optgroup>';
        if (m.departments.length) {
            html += '<optgroup label="دپارتمان‌ها">' + m.departments.map(function (d) {
                return opt('#/department/' + d.id, d.name) + d.children.map(function (c) { return opt('#/department/' + c.id, '— ' + c.name); }).join('');
            }).join('') + '</optgroup>';
        }
        if (m.tags.length) {
            html += '<optgroup label="برچسب‌ها">' + m.tags.map(function (t) { return opt('#/tag/' + t.id, t.name); }).join('') + '</optgroup>';
        }
        if (state.scope.customer) html = opt(current, listTitle()) + html;
        $('[data-scope-select]').innerHTML = html;
    }

    function depLink(d, child) {
        return '<a href="#/department/' + d.id + '" class="tkm-ib-view' + (child ? ' tkm-ib-view--child' : '') + (+state.scope.department === d.id ? ' is-active' : '') + '">' +
            (child ? '<span class="tkm-ib-branch" aria-hidden="true"></span>' : icon('inbox', 15)) + '<span>' + esc(d.name) + '</span></a>';
    }

    function findDepartment(id) {
        var found = null;
        (state.meta ? state.meta.departments : []).forEach(function (d) {
            if (d.id === +id) found = d;
            d.children.forEach(function (c) { if (c.id === +id) found = c; });
        });
        return found;
    }
    function findTag(id) {
        return (state.meta ? state.meta.tags : []).filter(function (t) { return t.id === +id; })[0] || null;
    }
    function findStatus(slug) {
        return (state.meta ? state.meta.statuses : []).filter(function (s) { return s.slug === slug; })[0] || null;
    }

    function renderBulkControls() {
        var m = state.meta;
        $('[data-bulk-assign]').innerHTML = '<option value="">سپردن به…</option><option value="0">بدون مسئول</option>' + m.agents.map(function (a) {
            return '<option value="' + a.id + '">' + esc(a.name) + '</option>';
        }).join('');
        $('[data-bulk-status]').innerHTML = '<option value="">تغییر وضعیت…</option>' + m.statuses.map(function (s) {
            return '<option value="' + esc(s.slug) + '">' + esc(s.name) + '</option>';
        }).join('');
    }

    /* ═════════════ List ═════════════ */

    var listSeq = 0;
    function loadList(opts) {
        opts = opts || {};
        var page = opts.append ? state.page + 1 : 1;
        var my = ++listSeq;
        state.loading = true;
        if (!opts.append && !opts.silent) {
            els.items.innerHTML = skeleton(6);
        }
        var q = {
            view: state.scope.view,
            department: state.scope.department,
            tag: state.scope.tag,
            customer: state.scope.customer,
            priority: state.filters.priority,
            sort: state.filters.sort,
            search: K.en(state.filters.search),
            page: page,
            per_page: opts.silent ? Math.max(30, state.items.length) : 30
        };
        return K.api('inbox', { query: q }).then(function (data) {
            if (my !== listSeq) return;
            state.loading = false;
            if (opts.append) {
                state.items = state.items.concat(data.items);
                state.page = data.page;
            } else {
                state.items = data.items;
                state.page = opts.silent ? Math.max(1, Math.ceil(data.items.length / 30)) : 1;
            }
            state.total = data.total;
            state.pages = Math.max(1, Math.ceil(data.total / 30));
            if (state.meta) {
                state.meta.counts = data.counts;
                renderNav();
            }
            renderList();
            if (opts.silent && state.current && state.detail) {
                var row = byId(state.current);
                if (row && row.updated_at !== state.detail.updated_at && !composerDirty()) {
                    openTicket(state.current, { silent: true });
                }
            }
        }).catch(function (e) {
            if (my !== listSeq) return;
            state.loading = false;
            if (!opts.silent) {
                els.items.innerHTML = '<div class="tkm-ib-empty">' + icon('alert', 22) + '<strong>بارگذاری فهرست ناموفق بود</strong><span>' + esc(e.message) + '</span><button type="button" class="tkm-btn tkm-btn--sm" data-retry>تلاش دوباره</button></div>';
            }
        });
    }

    function skeleton(n) {
        var h = '';
        for (var i = 0; i < n; i++) {
            h += '<div class="tkm-ib-item is-skeleton" aria-hidden="true"><span class="tkm-sk tkm-sk--av"></span><div><span class="tkm-sk" style="width:62%"></span><span class="tkm-sk" style="width:88%"></span><span class="tkm-sk" style="width:40%"></span></div></div>';
        }
        return h;
    }

    function byId(id) {
        return state.items.filter(function (t) { return t.id === +id; })[0] || null;
    }

    function listTitle() {
        var s = state.scope;
        if (s.department) { var d = findDepartment(s.department); return d ? 'دپارتمان ' + d.name : 'دپارتمان'; }
        if (s.tag) { var t = findTag(s.tag); return t ? 'برچسب «' + t.name + '»' : 'برچسب'; }
        if (s.customer) { return state.customerName ? 'تیکت‌های ' + state.customerName : 'تیکت‌های مشتری'; }
        return VIEW_LABEL[s.view] || 'تیکت‌ها';
    }

    function renderList() {
        els.title.textContent = listTitle();
        els.total.textContent = fa(state.total);

        if (!state.items.length) {
            var searching = state.filters.search || state.filters.priority;
            els.items.innerHTML = '<div class="tkm-ib-empty">' + icon(searching ? 'inbox' : 'check', 26) +
                '<strong>' + (searching ? 'نتیجه‌ای پیدا نشد' : 'همه‌چیز مرتب است') + '</strong>' +
                '<span>' + (searching ? 'عبارت جستجو یا فیلترها را تغییر دهید.' : 'در این نما تیکتی وجود ندارد.') + '</span>' +
                (searching ? '<button type="button" class="tkm-btn tkm-btn--sm" data-clear-filters>پاک کردن فیلترها</button>' : '') + '</div>';
            syncBulk();
            return;
        }

        var html = state.items.map(rowHtml).join('');
        if (state.page < state.pages) {
            html += '<button type="button" class="tkm-ib-more" data-more>نمایش موارد بیشتر <span class="tkm-num">(' + fa(state.total - state.items.length) + ')</span></button>';
        }
        els.items.innerHTML = html;
        syncBulk();
    }

    function rowHtml(t) {
        var unread = t.last_from === 'customer' && ['closed', 'finish', 'trash'].indexOf(t.status.slug) === -1;
        var cls = 'tkm-ib-item' + (t.id === state.current ? ' is-active' : '') + (state.selected[t.id] ? ' is-selected' : '') + (unread ? ' is-unread' : '');
        return '<div class="' + cls + '" role="option" data-id="' + t.id + '" aria-selected="' + (t.id === state.current) + '" tabindex="-1">' +
            '<label class="tkm-ib-item__check" title="انتخاب"><input type="checkbox" data-select="' + t.id + '"' + (state.selected[t.id] ? ' checked' : '') + ' aria-label="انتخاب تیکت ' + fa(t.id) + '"></label>' +
            '<span class="tkm-ib-item__av">' + K.avatar(t.customer, 'sm') + '</span>' +
            '<div class="tkm-ib-item__body">' +
                '<div class="tkm-ib-item__top"><span class="tkm-ib-item__name">' + esc(t.customer ? t.customer.name : 'کاربر') + '</span>' +
                    (t.outbound ? '<span class="tkm-ib-item__out" title="ارسال‌شده توسط پشتیبانی">' + icon('send', 11) + '</span>' : '') +
                    '<time class="tkm-ib-item__time" datetime="' + esc(t.updated_at) + '" title="' + esc(K.date(t.updated_at, true)) + '">' + K.ago(t.updated_at) + '</time></div>' +
                '<div class="tkm-ib-item__title">' + esc(t.title) + '</div>' +
                '<div class="tkm-ib-item__snippet">' + (t.last_from === 'staff' ? '<span class="tkm-ib-you">پشتیبانی: </span>' : '') + esc(t.snippet) + '</div>' +
                '<div class="tkm-ib-item__meta">' +
                    '<span class="tkm-num tkm-ib-item__id">#' + fa(t.id) + '</span>' +
                    K.status(t.status) +
                    K.priority(t.priority, false) +
                    K.sla(t.sla) +
                    t.tags.slice(0, 2).map(function (g) { return K.tag(g); }).join('') +
                    (t.tags.length > 2 ? '<span class="tkm-chip tkm-chip--more tkm-num">+' + fa(t.tags.length - 2) + '</span>' : '') +
                    '<span class="tkm-grow"></span>' +
                    (t.assignee ? '<span title="مسئول: ' + esc(t.assignee.name) + '">' + K.avatar(t.assignee, 'xs') + '</span>' : '<span class="tkm-ib-item__noassignee" title="بدون مسئول">' + icon('user', 12) + '</span>') +
                '</div>' +
            '</div></div>';
    }

    /* ═════════════ Selection & bulk ═════════════ */

    function selectedIds() {
        return Object.keys(state.selected).filter(function (k) { return state.selected[k]; }).map(Number);
    }

    function syncBulk() {
        var ids = selectedIds();
        els.bulk.hidden = !ids.length;
        root.classList.toggle('has-selection', ids.length > 0);
        $('[data-bulk-count]').textContent = fa(ids.length) + ' انتخاب‌شده';
        var trash = state.scope.view === 'trash';
        $('[data-bulk-action="trash"]').hidden = trash;
        $('[data-bulk-action="restore"]').hidden = !trash;
        $('[data-bulk-action="delete"]').hidden = !trash || !K.app.manage;
        els.checkAll.checked = ids.length > 0 && ids.length === state.items.length;
        els.checkAll.indeterminate = ids.length > 0 && ids.length < state.items.length;
    }

    function bulk(action, value) {
        var ids = selectedIds();
        if (!ids.length) return;
        var run = function () {
            K.api('tickets/bulk', { method: 'POST', body: { ids: ids, action: action, value: value } }).then(function (res) {
                K.toast('success', fa(res.done) + ' تیکت بروزرسانی شد');
                state.selected = {};
                if ((action === 'trash' || action === 'delete') && ids.indexOf(state.current) !== -1) closeTicket();
                loadList();
                if (state.current) openTicket(state.current, { silent: true });
            }).catch(function (e) { K.toast('error', 'عملیات انجام نشد', e.message); });
        };
        if (action === 'delete') {
            K.confirm(fa(ids.length) + ' تیکت و همه پاسخ‌هایش برای همیشه حذف می‌شود.').then(run, function () {});
        } else {
            run();
        }
    }

    /* ═════════════ Conversation ═════════════ */

    var ticketSeq = 0;
    function openTicket(id, opts) {
        opts = opts || {};
        id = +id;
        var my = ++ticketSeq;
        var switching = state.current !== id;
        if (switching) saveDraft();
        state.current = id;
        root.classList.add('is-reading');
        $$('.tkm-ib-item').forEach(function (n) {
            var on = +n.getAttribute('data-id') === id;
            n.classList.toggle('is-active', on);
            n.setAttribute('aria-selected', String(on));
        });
        if (!opts.silent) {
            els.convo.innerHTML = '<div class="tkm-ib-loading"><span class="tkm-spinner"></span></div>';
        }
        return K.api('tickets/' + id).then(function (t) {
            if (my !== ticketSeq) return;
            state.detail = t;
            if (switching) { state.mode = 'reply'; state.files = []; }
            renderConvo(opts.silent && !switching);
            var row = byId(id);
            if (row) {
                Object.assign(row, pick(t));
                patchRow(row);
            }
        }).catch(function (e) {
            if (my !== ticketSeq) return;
            els.convo.innerHTML = '<div class="tkm-ib-placeholder">' + '<div class="tkm-empty__art">' + icon('alert', 28) + '</div><h3>' + (e.status === 404 ? 'تیکت پیدا نشد' : 'باز کردن تیکت ممکن نشد') + '</h3><p>' + esc(e.message) + '</p></div>';
        });
    }

    function pick(t) {
        var keys = ['title', 'status', 'priority', 'department', 'customer', 'assignee', 'tags', 'outbound', 'snippet', 'last_from', 'replies', 'updated_at', 'sla'];
        var o = {};
        keys.forEach(function (k) { o[k] = t[k]; });
        return o;
    }

    function patchRow(row) {
        var node = els.items.querySelector('[data-id="' + row.id + '"]');
        if (!node) return;
        var tmp = document.createElement('div');
        tmp.innerHTML = rowHtml(row);
        node.replaceWith(tmp.firstChild);
    }

    function closeTicket() {
        saveDraft();
        state.current = null;
        state.detail = null;
        root.classList.remove('is-reading');
        els.convo.innerHTML = placeholderHtml;
        $$('.tkm-ib-item.is-active').forEach(function (n) { n.classList.remove('is-active'); });
    }

    function renderConvo(keepComposer) {
        var t = state.detail;
        var draft = keepComposer ? composerValue() : loadDraft(t.id);
        var closed = ['closed', 'finish'].indexOf(t.status.slug) !== -1;
        var scrollEl = $('.tkm-ib-thread', els.convo);
        var atBottom = !scrollEl || scrollEl.scrollHeight - scrollEl.scrollTop - scrollEl.clientHeight < 80;

        els.convo.innerHTML =
            '<header class="tkm-ib-convo__head">' +
                '<button type="button" class="tkm-btn tkm-btn--ghost tkm-btn--icon tkm-btn--sm tkm-ib-back" data-back aria-label="بازگشت به فهرست">' + icon('arrow', 18) + '</button>' +
                '<div class="tkm-ib-convo__title">' +
                    '<h2>' + esc(t.title) + '</h2>' +
                    '<div class="tkm-ib-convo__sub"><span class="tkm-num">#' + fa(t.id) + '</span><span>·</span>' + K.status(t.status) +
                    '<span>·</span><span>' + (t.department ? esc(t.department.name) : 'بدون دپارتمان') + '</span>' +
                    '<span>·</span><time title="' + esc(K.date(t.created_at, true)) + '">ایجاد ' + K.ago(t.created_at) + '</time></div>' +
                '</div>' +
                '<div class="tkm-ib-convo__actions">' +
                    (t.assignee && t.assignee.id === state.meta.me.id ? '' : '<button type="button" class="tkm-btn tkm-btn--sm" data-assign-me title="سپردن به من (A)">' + icon('user_check', 15) + '<span class="tkm-hide-md">سپردن به من</span></button>') +
                    (closed ? '<button type="button" class="tkm-btn tkm-btn--sm" data-set-status="open">' + icon('refresh', 15) + '<span class="tkm-hide-md">بازگشایی</span></button>'
                            : '<button type="button" class="tkm-btn tkm-btn--sm" data-set-status="closed">' + icon('check', 15) + '<span class="tkm-hide-md">بستن تیکت</span></button>') +
                    '<a class="tkm-btn tkm-btn--ghost tkm-btn--icon tkm-btn--sm" href="' + esc(t.customer_url) + '" target="_blank" rel="noopener" title="نمایش از دید مشتری" aria-label="نمایش از دید مشتری">' + icon('external', 16) + '</a>' +
                    '<button type="button" class="tkm-btn tkm-btn--ghost tkm-btn--icon tkm-btn--sm tkm-ib-side-toggle" data-side-toggle title="جزئیات" aria-label="نمایش جزئیات">' + icon('more', 18) + '</button>' +
                '</div>' +
            '</header>' +
            '<div class="tkm-ib-convo__body">' +
                '<div class="tkm-ib-convo__main">' +
                    '<div class="tkm-ib-thread" data-thread>' + threadHtml(t) + '</div>' +
                    composerHtml(t, closed) +
                '</div>' +
                '<aside class="tkm-ib-side" data-side>' + sideHtml(t) + '</aside>' +
            '</div>';

        var ta = $('[data-composer-input]', els.convo);
        if (ta) {
            ta.value = draft;
            autosize(ta);
        }
        renderFiles();
        var thread = $('[data-thread]', els.convo);
        if (!keepComposer || atBottom) thread.scrollTop = thread.scrollHeight;
        else if (scrollEl) thread.scrollTop = scrollEl.scrollTop;
    }

    function threadHtml(t) {
        var html = '';
        var lastDay = '';
        t.messages.forEach(function (m) {
            var day = K.date(m.date);
            if (day !== lastDay) {
                html += '<div class="tkm-ib-day"><span>' + esc(day) + '</span></div>';
                lastDay = day;
            }
            html += messageHtml(m, t);
        });
        if (t.rating) {
            html += '<div class="tkm-ib-event">' + icon('star', 14) + '<span>مشتری به این گفتگو <b>' + fa(t.rating) + ' از ۵</b> امتیاز داد.</span></div>';
        }
        return html;
    }

    function messageHtml(m, t) {
        var who = m.internal ? 'note' : (m.staff ? 'staff' : 'customer');
        var name = m.author ? m.author.name : 'کاربر';
        var role = m.internal ? 'یادداشت داخلی' : (m.staff ? 'پشتیبانی' : 'مشتری');
        var files = m.files.length ? '<div class="tkm-ib-files">' + m.files.map(function (f) {
            return '<a class="tkm-ib-file" href="' + esc(f.url) + '" target="_blank" rel="noopener">' + icon('paperclip', 14) + '<span class="tkm-ltr">' + esc(f.name) + '</span></a>';
        }).join('') + '</div>' : '';
        var voice = m.voice ? '<audio class="tkm-ib-audio" controls preload="none" src="' + esc(m.voice) + '"></audio>' : '';
        return '<article class="tkm-ib-msg tkm-ib-msg--' + who + '">' +
            K.avatar(m.author, 'sm') +
            '<div class="tkm-ib-msg__card">' +
                '<header class="tkm-ib-msg__head"><strong>' + esc(name) + '</strong><span class="tkm-ib-role tkm-ib-role--' + who + '">' + (m.internal ? icon('lock', 11) : '') + role + '</span>' +
                (m.kind === 'opening' ? '<span class="tkm-ib-role">' + (t.outbound ? 'پیام آغازین پشتیبانی' : 'درخواست اولیه') + '</span>' : '') +
                '<time datetime="' + esc(m.date) + '" title="' + esc(K.date(m.date, true)) + '">' + K.time(m.date) + '</time></header>' +
                (m.html ? '<div class="tkm-prose">' + m.html + '</div>' : '') + voice + files +
            '</div></article>';
    }

    function composerHtml(t, closed) {
        var canned = state.meta.canned;
        var up = state.meta.upload;
        var note = state.mode === 'note';
        return '<div class="tkm-ib-composer' + (note ? ' is-note' : '') + '" data-composer>' +
            '<div class="tkm-ib-composer__tabs" role="tablist">' +
                '<button type="button" role="tab" class="' + (note ? '' : 'is-active') + '" aria-selected="' + !note + '" data-mode="reply">' + icon('send', 14) + 'پاسخ به مشتری <kbd class="tkm-kbd">R</kbd></button>' +
                '<button type="button" role="tab" class="' + (note ? 'is-active' : '') + '" aria-selected="' + note + '" data-mode="note">' + icon('lock', 14) + 'یادداشت داخلی <kbd class="tkm-kbd">N</kbd></button>' +
                (closed && !note ? '<span class="tkm-ib-composer__warn">' + icon('alert', 13) + 'این تیکت بسته است؛ پاسخ شما آن را بسته نگه می‌دارد.</span>' : '') +
            '</div>' +
            '<textarea class="tkm-ib-composer__input" data-composer-input rows="3" placeholder="' + (note ? 'یادداشت فقط برای تیم پشتیبانی نمایش داده می‌شود…' : 'پاسخ خود را بنویسید… (Ctrl + Enter برای ارسال)') + '" aria-label="' + (note ? 'یادداشت داخلی' : 'متن پاسخ') + '"></textarea>' +
            '<div class="tkm-ib-composer__files" data-composer-files></div>' +
            '<div class="tkm-ib-composer__bar">' +
                '<label class="tkm-btn tkm-btn--ghost tkm-btn--icon tkm-btn--sm" title="پیوست فایل' + (up.hint ? ' — ' + esc(up.hint) : '') + '">' + icon('paperclip', 17) +
                    '<input type="file" class="tkm-sr" data-composer-file' + (up.multiple ? ' multiple' : '') + (up.accept ? ' accept="' + esc(up.accept) + '"' : '') + '></label>' +
                (canned.length ? '<div class="tkm-ib-canned"><button type="button" class="tkm-btn tkm-btn--ghost tkm-btn--sm" data-canned-toggle aria-haspopup="true">' + icon('sparkles', 15) + '<span class="tkm-ib-canned__label">پاسخ‌های آماده</span></button>' +
                    '<div class="tkm-popover tkm-ib-canned__menu" data-canned-menu hidden>' + canned.map(function (c, i) {
                        return '<button type="button" data-canned="' + i + '"><strong>' + esc(c.title) + '</strong><span>' + esc(c.text.slice(0, 90)) + '</span></button>';
                    }).join('') + '</div></div>' : '') +
                '<span class="tkm-grow"></span>' +
                (note ? '<button type="button" class="tkm-btn tkm-btn--primary tkm-btn--sm tkm-btn--note" data-send>' + icon('lock', 15) + 'ثبت یادداشت</button>'
                      : (closed ? '' : '<button type="button" class="tkm-btn tkm-btn--sm" data-send="close" title="ارسال پاسخ و بستن تیکت">ارسال و بستن</button>') +
                        '<button type="button" class="tkm-btn tkm-btn--primary tkm-btn--sm" data-send>' + icon('send', 15) + 'ارسال پاسخ</button>') +
            '</div></div>';
    }

    function sideHtml(t) {
        var m = state.meta;
        var tabs = '<div class="tkm-ib-tabs" role="tablist">' +
            ['details:جزئیات', 'customer:مشتری', 'activity:تاریخچه'].map(function (p) {
                var k = p.split(':');
                return '<button type="button" role="tab" data-side-tab="' + k[0] + '" class="' + (state.sideTab === k[0] ? 'is-active' : '') + '" aria-selected="' + (state.sideTab === k[0]) + '">' + k[1] + '</button>';
            }).join('') + '</div>';

        var statusOpts = m.statuses.map(function (s) {
            return '<option value="' + esc(s.slug) + '"' + (s.slug === t.status.slug ? ' selected' : '') + '>' + esc(s.name) + '</option>';
        }).join('');
        if (!findStatus(t.status.slug)) statusOpts = '<option selected value="' + esc(t.status.slug) + '">' + esc(t.status.name) + '</option>' + statusOpts;

        var agentOpts = '<option value="0">— بدون مسئول —</option>' + m.agents.map(function (a) {
            return '<option value="' + a.id + '"' + (t.assignee && t.assignee.id === a.id ? ' selected' : '') + '>' + esc(a.name) + (a.id === m.me.id ? ' (من)' : '') + ' · ' + fa(a.open) + ' باز</option>';
        }).join('');
        if (t.assignee && !m.agents.some(function (a) { return a.id === t.assignee.id; })) {
            agentOpts += '<option value="' + t.assignee.id + '" selected>' + esc(t.assignee.name) + '</option>';
        }

        var depOpts = '<option value="0">— بدون دپارتمان —</option>' + m.departments.map(function (d) {
            var o = '<option value="' + d.id + '"' + (t.department && t.department.id === d.id ? ' selected' : '') + '>' + esc(d.name) + '</option>';
            d.children.forEach(function (c) { o += '<option value="' + c.id + '"' + (t.department && t.department.id === c.id ? ' selected' : '') + '>— ' + esc(c.name) + '</option>'; });
            return o;
        }).join('');

        var details = '<div class="tkm-ib-pane" data-pane="details"' + (state.sideTab === 'details' ? '' : ' hidden') + '>' +
            slaCard(t) +
            '<div class="tkm-ib-props">' +
                prop('وضعیت', '<select class="tkm-select tkm-select--sm" data-prop="status">' + statusOpts + '</select>') +
                prop('اولویت', '<div class="tkm-ib-seg" role="radiogroup" aria-label="اولویت">' + ['low', 'medium', 'high'].map(function (p) {
                    return '<button type="button" role="radio" aria-checked="' + (t.priority === p) + '" class="tkm-ib-seg__btn tkm-ib-seg__btn--' + p + (t.priority === p ? ' is-active' : '') + '" data-priority="' + p + '">' + K.priorityLabel[p] + '</button>';
                }).join('') + '</div>') +
                prop('مسئول', '<select class="tkm-select tkm-select--sm" data-prop="assignee_id">' + agentOpts + '</select>') +
                prop('دپارتمان', '<select class="tkm-select tkm-select--sm" data-prop="department_id">' + depOpts + '</select>') +
                prop('برچسب‌ها', tagEditor(t)) +
            '</div>' +
            '<div class="tkm-ib-block"><div class="tkm-ib-block__title">' + icon('note', 14) + 'یادداشت ثابت تیکت</div>' +
                '<textarea class="tkm-textarea tkm-ib-pin" data-pinned-note rows="2" placeholder="مثلاً: مشتری VIP است؛ قبل از بستن هماهنگ شود.">' + esc(t.note) + '</textarea></div>' +
            (t.product ? '<div class="tkm-ib-block"><div class="tkm-ib-block__title">' + icon('bag', 14) + 'محصول مرتبط</div><a class="tkm-ib-link" href="' + esc(t.product.url) + '" target="_blank" rel="noopener">' + esc(t.product.name) + '</a></div>' : '') +
            '<dl class="tkm-ib-facts">' +
                fact('ایجاد', K.date(t.created_at, true)) +
                fact('اولین پاسخ', t.first_response_at ? K.date(t.first_response_at, true) : '—') +
                fact('حل‌شده', t.resolved_at ? K.date(t.resolved_at, true) : '—') +
                fact('پیام‌ها', fa(t.messages.length)) +
            '</dl>' +
            (K.app.manage ? '<button type="button" class="tkm-ib-danger" data-delete-ticket>' + icon('trash', 14) + 'حذف دائمی تیکت</button>' : '') +
        '</div>';

        var customer = '<div class="tkm-ib-pane" data-pane="customer"' + (state.sideTab === 'customer' ? '' : ' hidden') + '>' + customerHtml(t.customer_profile, t.id) + '</div>';
        var activity = '<div class="tkm-ib-pane" data-pane="activity"' + (state.sideTab === 'activity' ? '' : ' hidden') + '>' + activityHtml(t.activity) + '</div>';

        return tabs + details + customer + activity;
    }

    function prop(label, control) {
        return '<div class="tkm-ib-prop"><span class="tkm-ib-prop__label">' + label + '</span><div class="tkm-ib-prop__control">' + control + '</div></div>';
    }
    function fact(k, v) { return '<div><dt>' + k + '</dt><dd>' + v + '</dd></div>'; }

    function slaCard(t) {
        var s = t.sla;
        if (!s || s.state === 'none') return '';
        var label = s.kind === 'first' ? 'مهلت اولین پاسخ' : 'مهلت حل تیکت';
        var text;
        if (s.state === 'met') text = 'تعهدات SLA این تیکت رعایت شد.';
        else if (s.state === 'breached') text = K.duration(s.remaining) + ' از مهلت گذشته است.';
        else text = K.duration(s.remaining) + ' باقی مانده · ' + K.date(s.due, true);
        return '<div class="tkm-ib-sla tkm-ib-sla--' + s.state + '">' + icon(s.state === 'met' ? 'check' : (s.state === 'breached' ? 'alert' : 'clock'), 18) +
            '<div><strong>' + (s.state === 'met' ? 'SLA رعایت شد' : label) + '</strong><span>' + text + '</span></div></div>';
    }

    function tagEditor(t) {
        return '<div class="tkm-ib-tags" data-tag-editor>' +
            t.tags.map(function (g) { return K.tag(g, true); }).join('') +
            '<div class="tkm-ib-tagadd"><input type="text" class="tkm-ib-tagadd__input" data-tag-input placeholder="+ افزودن برچسب" aria-label="افزودن برچسب" list="tkm-taglist" autocomplete="off">' +
            '<datalist id="tkm-taglist">' + state.meta.tags.filter(function (g) {
                return !t.tags.some(function (x) { return x.id === g.id; });
            }).map(function (g) { return '<option value="' + esc(g.name) + '">'; }).join('') + '</datalist></div></div>';
    }

    function customerHtml(p, ticketId) {
        if (!p) return '<p class="tkm-ib-none">اطلاعات مشتری در دسترس نیست.</p>';
        var s = p.tickets || {};
        var html = '<div class="tkm-ib-customer">' +
            '<div class="tkm-ib-customer__head">' + K.avatar({ avatar: p.avatar }, 'lg') +
                '<div><strong>' + (p.profile_url ? '<a href="' + esc(p.profile_url) + '" target="_blank" rel="noopener">' + esc(p.name) + '</a>' : esc(p.name)) + '</strong><span>' + (p.registered ? 'عضو از ' + esc(K.date(p.registered)) : '') + '</span></div></div>' +
            '<ul class="tkm-ib-contact">' +
                (p.email ? '<li>' + icon('mail', 14) + '<a class="tkm-ltr" href="mailto:' + esc(p.email) + '">' + esc(p.email) + '</a></li>' : '') +
                (p.phone ? '<li>' + icon('phone', 14) + '<a class="tkm-ltr" href="tel:' + esc(p.phone) + '">' + esc(fa(p.phone)) + '</a></li>' : '') +
            '</ul>' +
            '<div class="tkm-ib-stats">' +
                stat(fa(s.total || 0), 'تیکت') + stat(fa(s.open || 0), 'باز') +
                stat(p.rating ? fa(p.rating) + '<small>/۵</small>' : '—', 'رضایت') +
            '</div>';
        if (p.woocommerce) {
            var w = p.woocommerce;
            html += '<div class="tkm-ib-block"><div class="tkm-ib-block__title">' + icon('bag', 14) + 'خریدها <span class="tkm-grow"></span><span class="tkm-muted tkm-num">' + fa(w.orders) + ' سفارش · ' + esc(fa(w.total_spent)) + '</span></div>' +
                (w.recent.length ? '<ul class="tkm-ib-orders">' + w.recent.map(function (o) {
                    return '<li><a href="' + esc(o.url) + '" target="_blank" rel="noopener"><span><b class="tkm-num">#' + esc(fa(o.number)) + '</b> ' + esc(o.items) + '</span><span class="tkm-muted">' + esc(o.status) + ' · ' + esc(fa(o.total)) + '</span></a></li>';
                }).join('') + '</ul>' : '<p class="tkm-ib-none">سفارشی ثبت نشده است.</p>') + '</div>';
        }
        var others = (p.recent || []).filter(function (r) { return r.id !== ticketId; });
        html += '<div class="tkm-ib-block"><div class="tkm-ib-block__title">' + icon('ticket', 14) + 'تیکت‌های دیگر' +
            (s.total > 1 ? '<span class="tkm-grow"></span><a class="tkm-ib-link" href="#/customer/' + p.id + '">همه</a>' : '') + '</div>' +
            (others.length ? '<ul class="tkm-ib-history">' + others.map(function (r) {
                return '<li><a href="#/ticket/' + r.id + '"><span class="tkm-truncate">' + esc(r.title) + '</span>' + K.status(r.status) + '</a></li>';
            }).join('') + '</ul>' : '<p class="tkm-ib-none">این اولین تیکت مشتری است.</p>') + '</div>';
        return html + '</div>';
    }
    function stat(v, l) { return '<div><b>' + v + '</b><span>' + l + '</span></div>'; }

    function activityHtml(rows) {
        if (!rows || !rows.length) return '<p class="tkm-ib-none">هنوز رویدادی ثبت نشده است.</p>';
        return '<ol class="tkm-ib-timeline">' + rows.map(function (a) {
            return '<li class="tkm-ib-timeline__item tkm-ib-timeline__item--' + esc(a.type) + '"><span class="tkm-ib-timeline__dot"></span><div><p>' + esc(a.text) + '</p><time title="' + esc(K.date(a.date, true)) + '">' + K.ago(a.date) + (a.user ? ' · ' + esc(a.user.name) : '') + '</time></div></li>';
        }).join('') + '</ol>';
    }

    /* ═════════════ Mutations ═════════════ */

    function patch(changes, okMsg) {
        var id = state.current;
        return K.api('tickets/' + id, { method: 'PATCH', body: changes }).then(function (t) {
            if (state.current !== id) return;
            state.detail = t;
            renderConvo(true);
            var row = byId(id);
            if (row) { Object.assign(row, pick(t)); patchRow(row); }
            if (okMsg) K.toast('success', okMsg);
            refreshCounts();
            return t;
        }).catch(function (e) {
            K.toast('error', 'تغییر ذخیره نشد', e.message);
            if (state.detail) renderConvo(true);
        });
    }

    var refreshCounts = K.debounce(function () {
        K.api('meta').then(function (m) { state.meta = m; renderNav(); }).catch(function () {});
    }, 400);

    function send(close) {
        var ta = $('[data-composer-input]', els.convo);
        if (!ta) return;
        var body = ta.value.trim();
        if (!body && !state.files.length) {
            K.toast('error', state.mode === 'note' ? 'یادداشت خالی است' : 'متن پاسخ خالی است');
            ta.focus();
            return;
        }
        var fd = new FormData();
        fd.append('body', body);
        fd.append('internal', state.mode === 'note' ? '1' : '0');
        if (close) fd.append('close', '1');
        state.files.forEach(function (f) { fd.append(state.meta.upload.multiple ? 'file[]' : 'file', f); });

        var btns = $$('[data-send]', els.convo);
        btns.forEach(function (b) { b.disabled = true; b.classList.add('is-loading'); });
        var id = state.current;
        var wasNote = state.mode === 'note';

        K.api('tickets/' + id + '/replies', { method: 'POST', form: fd }).then(function (t) {
            clearDraft(id);
            state.files = [];
            if (state.current !== id) return;
            state.detail = t;
            ta.value = '';
            renderConvo(false);
            var row = byId(id);
            if (row) { Object.assign(row, pick(t)); patchRow(row); }
            K.toast('success', wasNote ? 'یادداشت ثبت شد' : (close ? 'پاسخ ارسال و تیکت بسته شد' : 'پاسخ ارسال شد'));
            refreshCounts();
        }).catch(function (e) {
            btns.forEach(function (b) { b.disabled = false; b.classList.remove('is-loading'); });
            K.toast('error', 'ارسال نشد', e.message);
        });
    }

    function addTag(name) {
        name = name.trim();
        if (!name) return;
        var t = state.detail;
        var existing = state.meta.tags.filter(function (g) { return g.name === name; })[0];
        var list = t.tags.map(function (g) { return g.id; });
        if (existing && list.indexOf(existing.id) !== -1) return;
        list.push(existing ? existing.id : name);
        patch({ tags: list }).then(function () {
            if (!existing) K.api('tags').then(function (tags) { state.meta.tags = tags; renderNav(); });
            var input = $('[data-tag-input]', els.convo);
            if (input) input.focus();
        });
    }

    /* ═════════════ Composer helpers ═════════════ */

    function composerValue() {
        var ta = $('[data-composer-input]', els.convo);
        return ta ? ta.value : '';
    }
    function composerDirty() {
        var ta = $('[data-composer-input]', els.convo);
        return !!(ta && (ta.value.trim() || document.activeElement === ta)) || state.files.length > 0;
    }
    function draftKey(id) { return 'tkm-draft-' + id; }
    function saveDraft() {
        if (!state.current) return;
        try {
            var v = composerValue();
            if (v.trim()) localStorage.setItem(draftKey(state.current), v); else localStorage.removeItem(draftKey(state.current));
        } catch (e) { /* storage unavailable */ }
    }
    function loadDraft(id) { try { return localStorage.getItem(draftKey(id)) || ''; } catch (e) { return ''; } }
    function clearDraft(id) { try { localStorage.removeItem(draftKey(id)); } catch (e) { /* ignore */ } }

    function autosize(ta) {
        ta.style.height = 'auto';
        ta.style.height = Math.min(ta.scrollHeight + 2, 320) + 'px';
    }

    function renderFiles() {
        var box = $('[data-composer-files]', els.convo);
        if (!box) return;
        box.innerHTML = state.files.map(function (f, i) {
            return '<span class="tkm-chip tkm-chip--file">' + icon('file', 12) + '<span class="tkm-ltr">' + esc(f.name) + '</span><button type="button" class="tkm-chip__x" data-file-remove="' + i + '" aria-label="حذف فایل">×</button></span>';
        }).join('');
    }

    function addFiles(list) {
        var up = state.meta.upload;
        Array.prototype.forEach.call(list, function (f) {
            if (up.max_size && f.size > up.max_size) {
                K.toast('error', 'فایل بزرگ است', f.name + ' — ' + (up.hint || ''));
                return;
            }
            if (!up.multiple) state.files = [];
            if (up.multiple && up.max_files && state.files.length >= up.max_files) {
                K.toast('error', 'حداکثر ' + fa(up.max_files) + ' فایل مجاز است');
                return;
            }
            state.files.push(f);
        });
        renderFiles();
    }

    function setMode(mode) {
        if (state.mode === mode) {
            var ta0 = $('[data-composer-input]', els.convo);
            if (ta0) ta0.focus();
            return;
        }
        var value = composerValue();
        state.mode = mode;
        var box = $('[data-composer]', els.convo);
        if (!box) return;
        var closed = ['closed', 'finish'].indexOf(state.detail.status.slug) !== -1;
        var tmp = document.createElement('div');
        tmp.innerHTML = composerHtml(state.detail, closed);
        box.replaceWith(tmp.firstChild);
        var ta = $('[data-composer-input]', els.convo);
        ta.value = value;
        autosize(ta);
        renderFiles();
        ta.focus();
    }

    /* ═════════════ Routing ═════════════ */

    function route() {
        var h = (location.hash || '').replace(/^#\/?/, '').split('/');
        var kind = h[0], id = h[1];

        if (kind === 'ticket' && id) {
            if (!state.items.length && !state.loading) loadList();
            openTicket(id);
            return;
        }

        var scope = { view: 'active' };
        if (kind === 'view' && VIEW_LABEL[id]) scope = { view: id };
        else if (kind === 'department' && id) scope = { view: 'all', department: +id };
        else if (kind === 'tag' && id) scope = { view: 'all', tag: +id };
        else if (kind === 'customer' && id) {
            scope = { view: 'all', customer: +id };
            state.customerName = '';
            K.api('customers/' + id).then(function (p) { state.customerName = p.name; els.title.textContent = listTitle(); }).catch(function () {});
        }
        state.scope = scope;
        state.selected = {};
        try { localStorage.setItem('tkm-inbox-scope', location.hash); } catch (e) { /* ignore */ }
        if (state.meta) renderNav();
        loadList();
    }

    /* ═════════════ Events ═════════════ */

    root.addEventListener('click', function (e) {
        var t = e.target;
        var el;

        if ((el = t.closest('[data-select]'))) {
            state.selected[+el.getAttribute('data-select')] = el.checked;
            el.closest('.tkm-ib-item').classList.toggle('is-selected', el.checked);
            syncBulk();
            return;
        }
        if (t.closest('.tkm-ib-item__check')) return;

        if ((el = t.closest('.tkm-ib-item[data-id]'))) {
            var id = +el.getAttribute('data-id');
            if (e.shiftKey || e.metaKey || e.ctrlKey) {
                state.selected[id] = !state.selected[id];
                el.classList.toggle('is-selected', state.selected[id]);
                el.querySelector('[data-select]').checked = state.selected[id];
                syncBulk();
                return;
            }
            setHash('/ticket/' + id);
            return;
        }
        if (t.closest('[data-more]')) { t.closest('[data-more]').classList.add('is-loading'); loadList({ append: true }); return; }
        if (t.closest('[data-retry]')) { loadList(); return; }
        if (t.closest('[data-clear-filters]')) {
            state.filters.search = ''; state.filters.priority = '';
            els.search.value = ''; $('[data-filter="priority"]').value = '';
            loadList();
            return;
        }
        if ((el = t.closest('[data-bulk-action]'))) { bulk(el.getAttribute('data-bulk-action')); return; }
        if (t.closest('[data-bulk-clear]')) { state.selected = {}; renderList(); return; }

        if (t.closest('[data-back]')) { setHash(lastScopeHash()); closeTicket(); return; }
        if (t.closest('[data-side-toggle]')) { root.classList.toggle('show-side'); return; }
        if ((el = t.closest('[data-side-tab]'))) {
            state.sideTab = el.getAttribute('data-side-tab');
            $$('[data-side-tab]', els.convo).forEach(function (b) { var on = b === el; b.classList.toggle('is-active', on); b.setAttribute('aria-selected', String(on)); });
            $$('[data-pane]', els.convo).forEach(function (p) { p.hidden = p.getAttribute('data-pane') !== state.sideTab; });
            return;
        }
        if (t.closest('[data-assign-me]')) { patch({ assignee_id: state.meta.me.id }, 'تیکت به شما سپرده شد'); return; }
        if ((el = t.closest('[data-set-status]'))) {
            var st = el.getAttribute('data-set-status');
            patch({ status: st }, st === 'closed' ? 'تیکت بسته شد' : 'تیکت دوباره باز شد');
            return;
        }
        if ((el = t.closest('[data-priority]'))) { patch({ priority: el.getAttribute('data-priority') }); return; }
        if ((el = t.closest('[data-tag-remove]'))) {
            var rid = +el.getAttribute('data-tag-remove');
            patch({ tags: state.detail.tags.filter(function (g) { return g.id !== rid; }).map(function (g) { return g.id; }) });
            return;
        }
        if ((el = t.closest('[data-mode]'))) { setMode(el.getAttribute('data-mode')); return; }
        if ((el = t.closest('[data-send]'))) { send(el.getAttribute('data-send') === 'close'); return; }
        if ((el = t.closest('[data-file-remove]'))) { state.files.splice(+el.getAttribute('data-file-remove'), 1); renderFiles(); return; }
        if (t.closest('[data-canned-toggle]')) {
            var menu = $('[data-canned-menu]', els.convo);
            menu.hidden = !menu.hidden;
            return;
        }
        if ((el = t.closest('[data-canned]'))) {
            var c = state.meta.canned[+el.getAttribute('data-canned')];
            var ta = $('[data-composer-input]', els.convo);
            ta.value = (ta.value.trim() ? ta.value.replace(/\s+$/, '') + '\n\n' : '') + c.text;
            autosize(ta);
            ta.focus();
            $('[data-canned-menu]', els.convo).hidden = true;
            return;
        }
        if (t.closest('[data-delete-ticket]')) {
            K.confirm('تیکت #' + fa(state.current) + ' و همه پیام‌هایش برای همیشه حذف می‌شود.').then(function () {
                var id = state.current;
                K.api('tickets/' + id, { method: 'DELETE' }).then(function () {
                    K.toast('success', 'تیکت حذف شد');
                    state.items = state.items.filter(function (r) { return r.id !== id; });
                    state.total = Math.max(0, state.total - 1);
                    renderList();
                    setHash(lastScopeHash());
                    closeTicket();
                    refreshCounts();
                }).catch(function (err) { K.toast('error', 'حذف نشد', err.message); });
            }, function () {});
            return;
        }
        // click outside the canned menu closes it
        var open = $('[data-canned-menu]:not([hidden])', els.convo);
        if (open && !t.closest('.tkm-ib-canned')) open.hidden = true;
    });

    root.addEventListener('change', function (e) {
        var t = e.target;
        if (t === els.checkAll) {
            state.items.forEach(function (r) { state.selected[r.id] = t.checked; });
            renderList();
            return;
        }
        if (t.matches('[data-filter]')) {
            state.filters[t.getAttribute('data-filter')] = t.value;
            loadList();
            return;
        }
        if (t.matches('[data-scope-select]')) { location.hash = t.value.replace(/^#/, ''); return; }
        if (t.matches('[data-bulk-assign]') && t.value !== '') { bulk('assign', +t.value); t.value = ''; return; }
        if (t.matches('[data-bulk-status]') && t.value) { bulk('status', t.value); t.value = ''; return; }
        if (t.matches('[data-prop]')) {
            var key = t.getAttribute('data-prop');
            var v = key === 'status' ? t.value : +t.value;
            patch((function () { var o = {}; o[key] = v; return o; })(), key === 'assignee_id' ? (v ? 'مسئول تیکت تغییر کرد' : 'مسئول برداشته شد') : null);
            return;
        }
        if (t.matches('[data-composer-file]')) { addFiles(t.files); t.value = ''; return; }
        if (t.matches('[data-pinned-note]')) {
            if (t.value !== state.detail.note) patch({ note: t.value }, 'یادداشت ثابت ذخیره شد');
        }
    });

    root.addEventListener('input', function (e) {
        if (e.target.matches('[data-composer-input]')) { autosize(e.target); saveDraftSoon(); }
        if (e.target === els.search) searchSoon();
    });
    var saveDraftSoon = K.debounce(saveDraft, 600);
    var searchSoon = K.debounce(function () {
        state.filters.search = els.search.value.trim();
        loadList();
    }, 300);

    root.addEventListener('keydown', function (e) {
        var t = e.target;
        if (t.matches('[data-composer-input]') && (e.ctrlKey || e.metaKey) && e.key === 'Enter') {
            e.preventDefault();
            send(false);
        }
        if (t.matches('[data-tag-input]') && (e.key === 'Enter' || e.key === ',' || e.key === '،')) {
            e.preventDefault();
            addTag(t.value);
            t.value = '';
        }
        if (t === els.search && e.key === 'Escape') { els.search.value = ''; searchSoon(); els.search.blur(); }
    });
    root.addEventListener('focusout', function (e) {
        if (e.target.matches('[data-tag-input]') && e.target.value.trim()) { addTag(e.target.value); e.target.value = ''; }
    });

    // Drag & drop files onto the composer.
    root.addEventListener('dragover', function (e) {
        var c = e.target.closest && e.target.closest('[data-composer]');
        if (c && e.dataTransfer && Array.prototype.indexOf.call(e.dataTransfer.types, 'Files') !== -1) { e.preventDefault(); c.classList.add('is-over'); }
    });
    root.addEventListener('dragleave', function (e) {
        var c = e.target.closest && e.target.closest('[data-composer]');
        if (c) c.classList.remove('is-over');
    });
    root.addEventListener('drop', function (e) {
        var c = e.target.closest && e.target.closest('[data-composer]');
        if (c && e.dataTransfer.files.length) { e.preventDefault(); c.classList.remove('is-over'); addFiles(e.dataTransfer.files); }
    });

    // Global keyboard shortcuts.
    var shortcuts = document.querySelector('[data-shortcuts]');
    document.addEventListener('keydown', function (e) {
        if (e.ctrlKey || e.metaKey || e.altKey) return;
        if (e.key === 'Escape') {
            if (shortcuts && !shortcuts.hidden) { shortcuts.hidden = true; return; }
            if (root.classList.contains('show-side')) { root.classList.remove('show-side'); return; }
            if (K.typing(e) && e.target.matches('[data-composer-input]')) { e.target.blur(); }
            return;
        }
        if (K.typing(e) || document.querySelector('.tkm-cmdk:not([hidden]), .tkm-modal:not([hidden]):not([data-shortcuts])')) return;

        var k = e.key.toLowerCase();
        if (k === 'j' || k === 'k' || e.key === 'ArrowDown' && e.target === els.items || e.key === 'ArrowUp' && e.target === els.items) {
            e.preventDefault();
            step(k === 'j' || e.key === 'ArrowDown' ? 1 : -1);
        } else if (k === '/' ) {
            e.preventDefault(); els.search.focus(); els.search.select();
        } else if (k === 'r' && state.detail) {
            e.preventDefault(); setMode('reply');
        } else if (k === 'n' && state.detail) {
            e.preventDefault(); setMode('note');
        } else if (k === 'a' && state.detail && !(state.detail.assignee && state.detail.assignee.id === state.meta.me.id)) {
            e.preventDefault(); patch({ assignee_id: state.meta.me.id }, 'تیکت به شما سپرده شد');
        } else if (k === 'x' && state.current) {
            e.preventDefault();
            state.selected[state.current] = !state.selected[state.current];
            renderList();
        } else if (e.key === '?') {
            e.preventDefault(); if (shortcuts) shortcuts.hidden = false;
        }
    });
    if (shortcuts) {
        shortcuts.addEventListener('click', function (e) { if (e.target.closest('[data-close]')) shortcuts.hidden = true; });
    }

    function step(dir) {
        if (!state.items.length) return;
        var idx = state.items.findIndex(function (r) { return r.id === state.current; });
        var next = idx === -1 ? 0 : Math.min(state.items.length - 1, Math.max(0, idx + dir));
        var row = state.items[next];
        if (row && row.id !== state.current) {
            setHash('/ticket/' + row.id);
            var node = els.items.querySelector('[data-id="' + row.id + '"]');
            if (node) node.scrollIntoView({ block: 'nearest' });
        }
    }

    function lastScopeHash() {
        var s = state.scope;
        if (s.department) return '/department/' + s.department;
        if (s.tag) return '/tag/' + s.tag;
        if (s.customer) return '/customer/' + s.customer;
        return '/view/' + s.view;
    }
    function setHash(h) {
        if (location.hash.replace(/^#/, '') === h) route(); else location.hash = h;
    }

    window.addEventListener('hashchange', route);
    window.addEventListener('beforeunload', saveDraft);

    // Live refresh: poll quietly while the tab is visible.
    setInterval(function () {
        if (document.hidden || state.loading || selectedIds().length) return;
        if (els.items.querySelector('[data-more].is-loading')) return;
        loadList({ silent: true });
    }, 30000);
    document.addEventListener('visibilitychange', function () {
        if (!document.hidden && state.meta) loadList({ silent: true });
    });

    /* ═════════════ Boot ═════════════ */

    loadMeta().then(function () {
        root.setAttribute('data-state', 'ready');
        var h = location.hash;
        if (!h || h === '#' || h === '#/') {
            var saved = '';
            try { saved = localStorage.getItem('tkm-inbox-scope') || ''; } catch (e) { /* ignore */ }
            if (!saved) saved = state.meta.counts.mine ? '#/view/mine' : '#/view/active';
            history.replaceState(null, '', saved);
        }
        route();
    }).catch(function (e) {
        root.setAttribute('data-state', 'error');
        els.items.innerHTML = '<div class="tkm-ib-empty">' + icon('alert', 22) + '<strong>صندوق بارگذاری نشد</strong><span>' + esc(e.message) + '</span><button type="button" class="tkm-btn tkm-btn--sm" onclick="location.reload()">تلاش دوباره</button></div>';
    });
})();
