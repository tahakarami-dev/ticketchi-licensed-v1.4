/* Ticketchi Lite — support inbox (REST-driven). */
(function () {
    'use strict';

    var K = window.TICKETCHI_LITE;
    var root = document.getElementById('tkm-inbox');
    if (!K || !root) return;

    var fa = K.fa, esc = K.esc, icon = K.icon;
    var $ = function (sel, ctx) { return (ctx || root).querySelector(sel); };
    var $$ = function (sel, ctx) { return Array.prototype.slice.call((ctx || root).querySelectorAll(sel)); };
    var PRO = K.app.admin + '?page=ticketchi-lite-pro';

    var VIEWS = [
        { key: 'active', label: 'تیکت‌های باز', icon: 'inbox' },
        { key: 'waiting', label: 'منتظر مشتری', icon: 'clock' },
        { key: 'closed', label: 'بسته‌شده', icon: 'check' },
        { key: 'all', label: 'همه', icon: 'ticket' },
        { key: 'trash', label: 'زباله‌دان', icon: 'trash' }
    ];
    var VIEW_LABEL = {};
    VIEWS.forEach(function (v) { VIEW_LABEL[v.key] = v.label; });

    var state = { meta: null, view: 'active', items: [], total: 0, page: 1, pages: 1, loading: false, current: null, detail: null };

    var els = {
        views: $('[data-views]'),
        items: $('[data-items]'),
        total: $('[data-total]'),
        title: $('[data-list-title]'),
        convo: $('[data-convo]')
    };
    var placeholderHtml = els.convo.innerHTML;

    /* ═════════════ Sidebar ═════════════ */

    function loadMeta() {
        return K.api('meta').then(function (meta) {
            state.meta = meta;
            renderNav();
            return meta;
        });
    }

    function renderNav() {
        var counts = state.meta.counts || {};
        els.views.innerHTML = VIEWS.map(function (v) {
            var c = counts[v.key];
            return '<a href="#/view/' + v.key + '" class="tkm-ib-view' + (state.view === v.key ? ' is-active' : '') + '">' +
                icon(v.icon, 16) + '<span>' + esc(v.label) + '</span>' +
                (c ? '<span class="tkm-ib-count tkm-num">' + fa(c) + '</span>' : '') + '</a>';
        }).join('');
        // Compact view picker for narrow screens where the sidebar is hidden.
        $('[data-scope-select]').innerHTML = VIEWS.map(function (v) {
            return '<option value="#/view/' + v.key + '"' + (state.view === v.key ? ' selected' : '') + '>' + esc(v.label) + (counts[v.key] ? ' (' + fa(counts[v.key]) + ')' : '') + '</option>';
        }).join('');
    }

    /* ═════════════ List ═════════════ */

    var listSeq = 0;
    function loadList(append) {
        var my = ++listSeq;
        state.loading = true;
        if (!append) els.items.innerHTML = skeleton(6);
        return K.api('inbox', { query: { view: state.view, page: append ? state.page + 1 : 1, per_page: 30 } }).then(function (data) {
            if (my !== listSeq) return;
            state.loading = false;
            state.items = append ? state.items.concat(data.items) : data.items;
            state.page = data.page;
            state.total = data.total;
            state.pages = Math.max(1, data.pages);
            state.meta.counts = data.counts;
            renderNav();
            renderList();
        }).catch(function (e) {
            if (my !== listSeq) return;
            state.loading = false;
            els.items.innerHTML = '<div class="tkm-ib-empty">' + icon('alert', 22) + '<strong>بارگذاری فهرست ناموفق بود</strong><span>' + esc(e.message) + '</span><button type="button" class="tkm-btn tkm-btn--sm" data-retry>تلاش دوباره</button></div>';
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

    function renderList() {
        els.title.textContent = VIEW_LABEL[state.view];
        els.total.textContent = fa(state.total);

        if (!state.items.length) {
            els.items.innerHTML = '<div class="tkm-ib-empty">' + icon('check', 26) + '<strong>همه‌چیز مرتب است</strong><span>در این نما تیکتی وجود ندارد.</span></div>';
            return;
        }
        var html = state.items.map(rowHtml).join('');
        if (state.page < state.pages) {
            html += '<button type="button" class="tkm-ib-more" data-more>نمایش موارد بیشتر <span class="tkm-num">(' + fa(state.total - state.items.length) + ')</span></button>';
        }
        els.items.innerHTML = html;
    }

    function rowHtml(t) {
        var unread = t.last_from === 'customer' && ['closed', 'finish', 'trash'].indexOf(t.status.slug) === -1;
        var cls = 'tkm-ib-item tkm-ib-item--lite' + (t.id === state.current ? ' is-active' : '') + (unread ? ' is-unread' : '');
        return '<div class="' + cls + '" role="option" data-id="' + t.id + '" aria-selected="' + (t.id === state.current) + '" tabindex="-1">' +
            '<span class="tkm-ib-item__av">' + K.avatar(t.customer, 'sm') + '</span>' +
            '<div class="tkm-ib-item__body">' +
                '<div class="tkm-ib-item__top"><span class="tkm-ib-item__name">' + esc(t.customer ? t.customer.name : 'کاربر') + '</span>' +
                    '<time class="tkm-ib-item__time" datetime="' + esc(t.updated_at) + '" title="' + esc(K.date(t.updated_at, true)) + '">' + K.ago(t.updated_at) + '</time></div>' +
                '<div class="tkm-ib-item__title">' + esc(t.title) + '</div>' +
                '<div class="tkm-ib-item__snippet">' + (t.last_from === 'staff' ? '<span class="tkm-ib-you">پشتیبانی: </span>' : '') + esc(t.snippet) + '</div>' +
                '<div class="tkm-ib-item__meta">' +
                    '<span class="tkm-num tkm-ib-item__id">#' + fa(t.id) + '</span>' + K.status(t.status) +
                    (t.department ? '<span class="tkm-ib-item__dep">' + esc(t.department.name) + '</span>' : '') +
                '</div>' +
            '</div></div>';
    }

    /* ═════════════ Conversation ═════════════ */

    var ticketSeq = 0;
    function openTicket(id) {
        id = +id;
        var my = ++ticketSeq;
        state.current = id;
        root.classList.add('is-reading');
        $$('.tkm-ib-item').forEach(function (n) {
            var on = +n.getAttribute('data-id') === id;
            n.classList.toggle('is-active', on);
            n.setAttribute('aria-selected', String(on));
        });
        els.convo.innerHTML = '<div class="tkm-ib-loading"><span class="tkm-spinner"></span></div>';
        return K.api('tickets/' + id).then(function (t) {
            if (my === ticketSeq) show(t, false);
        }).catch(function (e) {
            if (my !== ticketSeq) return;
            els.convo.innerHTML = '<div class="tkm-ib-placeholder"><div class="tkm-empty__art">' + icon('alert', 28) + '</div><h3>' + (e.status === 404 ? 'تیکت پیدا نشد' : 'باز کردن تیکت ممکن نشد') + '</h3><p>' + esc(e.message) + '</p></div>';
        });
    }

    /** Render a fresh ticket payload and refresh its row in the list. */
    function show(t, keepComposer) {
        state.detail = t;
        renderConvo(keepComposer);
        var row = byId(t.id);
        if (!row) return;
        ['title', 'status', 'department', 'customer', 'snippet', 'last_from', 'replies', 'updated_at'].forEach(function (k) { row[k] = t[k]; });
        var node = els.items.querySelector('[data-id="' + row.id + '"]');
        if (node) {
            var tmp = document.createElement('div');
            tmp.innerHTML = rowHtml(row);
            node.replaceWith(tmp.firstChild);
        }
    }

    function closeTicket() {
        state.current = null;
        state.detail = null;
        root.classList.remove('is-reading');
        els.convo.innerHTML = placeholderHtml;
        $$('.tkm-ib-item.is-active').forEach(function (n) { n.classList.remove('is-active'); });
    }

    function renderConvo(keepComposer) {
        var t = state.detail;
        var draft = keepComposer ? composerValue() : '';
        var closed = ['closed', 'finish'].indexOf(t.status.slug) !== -1;
        var trashed = t.status.slug === 'trash';

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
                    (trashed || closed ? '<button type="button" class="tkm-btn tkm-btn--sm" data-set-status="open">' + icon('refresh', 15) + '<span class="tkm-hide-md">' + (trashed ? 'بازگردانی' : 'بازگشایی') + '</span></button>'
                                       : '<button type="button" class="tkm-btn tkm-btn--sm" data-set-status="closed">' + icon('check', 15) + '<span class="tkm-hide-md">بستن تیکت</span></button>') +
                    '<a class="tkm-btn tkm-btn--ghost tkm-btn--icon tkm-btn--sm" href="' + esc(t.customer_url) + '" target="_blank" rel="noopener" title="نمایش از دید مشتری" aria-label="نمایش از دید مشتری">' + icon('external', 16) + '</a>' +
                    '<button type="button" class="tkm-btn tkm-btn--ghost tkm-btn--icon tkm-btn--sm tkm-ib-side-toggle" data-side-toggle title="جزئیات" aria-label="نمایش جزئیات">' + icon('more', 18) + '</button>' +
                '</div>' +
            '</header>' +
            '<div class="tkm-ib-convo__body">' +
                '<div class="tkm-ib-convo__main">' +
                    '<div class="tkm-ib-thread" data-thread>' + threadHtml(t) + '</div>' +
                    (trashed ? '' : composerHtml(closed)) +
                '</div>' +
                '<aside class="tkm-ib-side" data-side>' + sideHtml(t) + '</aside>' +
            '</div>';

        var ta = $('[data-composer-input]', els.convo);
        if (ta) { ta.value = draft; autosize(ta); }
        var thread = $('[data-thread]', els.convo);
        thread.scrollTop = thread.scrollHeight;
    }

    function threadHtml(t) {
        var html = '', lastDay = '';
        t.messages.forEach(function (m) {
            var day = K.date(m.date);
            if (day !== lastDay) {
                html += '<div class="tkm-ib-day"><span>' + esc(day) + '</span></div>';
                lastDay = day;
            }
            html += messageHtml(m);
        });
        return html;
    }

    function messageHtml(m) {
        var who = m.staff ? 'staff' : 'customer';
        var files = m.files.length ? '<div class="tkm-ib-files">' + m.files.map(function (f) {
            return '<a class="tkm-ib-file" href="' + esc(f.url) + '" target="_blank" rel="noopener">' + icon('paperclip', 14) + '<span class="tkm-ltr">' + esc(f.name) + '</span></a>';
        }).join('') + '</div>' : '';
        var voice = m.voice ? '<audio class="tkm-ib-audio" controls preload="none" src="' + esc(m.voice) + '"></audio>' : '';
        return '<article class="tkm-ib-msg tkm-ib-msg--' + who + '">' +
            K.avatar(m.author, 'sm') +
            '<div class="tkm-ib-msg__card">' +
                '<header class="tkm-ib-msg__head"><strong>' + esc(m.author ? m.author.name : 'کاربر') + '</strong><span class="tkm-ib-role tkm-ib-role--' + who + '">' + (m.staff ? 'پشتیبانی' : 'مشتری') + '</span>' +
                (m.kind === 'opening' ? '<span class="tkm-ib-role">درخواست اولیه</span>' : '') +
                '<time datetime="' + esc(m.date) + '" title="' + esc(K.date(m.date, true)) + '">' + K.time(m.date) + '</time></header>' +
                // Server-rendered with wp_kses_post().
                (m.html ? '<div class="tkm-prose">' + m.html + '</div>' : '') + voice + files +
            '</div></article>';
    }

    function proLink(anchor, label) {
        return '<a class="tkm-ib-pro-hint" href="' + esc(PRO + '#pro-' + anchor) + '">' + icon('lock', 13) + esc(label) + '</a>';
    }

    function composerHtml(closed) {
        return '<div class="tkm-ib-composer" data-composer>' +
            (closed ? '<div class="tkm-ib-composer__tabs"><span class="tkm-ib-composer__warn">' + icon('alert', 13) + 'این تیکت بسته است؛ پاسخ شما آن را بسته نگه می‌دارد.</span></div>' : '') +
            '<textarea class="tkm-ib-composer__input" data-composer-input rows="3" placeholder="پاسخ خود را بنویسید… (Ctrl + Enter برای ارسال)" aria-label="متن پاسخ"></textarea>' +
            '<div class="tkm-ib-composer__bar">' +
                proLink('customer-ux', 'پیوست فایل') + proLink('canned', 'پاسخ آماده') + proLink('notes', 'یادداشت داخلی') +
                '<span class="tkm-grow"></span>' +
                (closed ? '' : '<button type="button" class="tkm-btn tkm-btn--sm" data-send="close" title="ارسال پاسخ و بستن تیکت">ارسال و بستن</button>') +
                '<button type="button" class="tkm-btn tkm-btn--primary tkm-btn--sm" data-send>' + icon('send', 15) + 'ارسال پاسخ</button>' +
            '</div></div>';
    }

    function sideHtml(t) {
        var statuses = state.meta.statuses;
        var opts = statuses.map(function (s) {
            return '<option value="' + esc(s.slug) + '"' + (s.slug === t.status.slug ? ' selected' : '') + '>' + esc(s.name) + '</option>';
        }).join('');
        if (!statuses.some(function (s) { return s.slug === t.status.slug; })) {
            opts = '<option selected value="' + esc(t.status.slug) + '">' + esc(t.status.name) + '</option>' + opts;
        }
        var c = t.customer;
        return '<div class="tkm-ib-pane">' +
            '<div class="tkm-ib-props">' +
                prop('وضعیت', '<select class="tkm-select tkm-select--sm" data-prop="status">' + opts + '</select>') +
                prop('مسئول', proLink('team', 'سپردن به کارشناس')) +
                prop('اولویت', proLink('team', 'تغییر اولویت و دپارتمان')) +
            '</div>' +
            (c ? '<div class="tkm-ib-block"><div class="tkm-ib-block__title">' + icon('user', 14) + 'مشتری</div>' +
                '<div class="tkm-ib-customer__head">' + K.avatar(c, 'md') + '<div><strong>' + esc(c.name) + '</strong>' +
                (c.email ? '<a class="tkm-ltr tkm-ib-link" href="mailto:' + esc(c.email) + '">' + esc(c.email) + '</a>' : '') + '</div></div>' +
                proLink('customer', 'سفارش‌ها و سابقه مشتری') + '</div>' : '') +
            '<dl class="tkm-ib-facts">' +
                fact('ایجاد', K.date(t.created_at, true)) +
                fact('پیام‌ها', fa(t.messages.length)) +
            '</dl>' +
            (t.status.slug === 'trash'
                ? '<button type="button" class="tkm-ib-danger" data-delete-ticket>' + icon('trash', 14) + 'حذف دائمی تیکت</button>'
                : '<button type="button" class="tkm-ib-danger" data-set-status="trash">' + icon('trash', 14) + 'انتقال به زباله‌دان</button>') +
        '</div>';
    }

    function prop(label, control) {
        return '<div class="tkm-ib-prop"><span class="tkm-ib-prop__label">' + label + '</span><div class="tkm-ib-prop__control">' + control + '</div></div>';
    }
    function fact(k, v) { return '<div><dt>' + k + '</dt><dd>' + v + '</dd></div>'; }

    /* ═════════════ Mutations ═════════════ */

    var STATUS_MSG = { closed: 'تیکت بسته شد', open: 'تیکت دوباره باز شد', trash: 'تیکت به زباله‌دان رفت' };

    function setStatus(status) {
        var id = state.current;
        return K.api('tickets/' + id, { method: 'PATCH', body: { status: status } }).then(function (t) {
            if (state.current !== id) return;
            show(t, true);
            if (STATUS_MSG[status]) K.toast('success', STATUS_MSG[status]);
            refreshCounts();
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
        if (!body) {
            K.toast('error', 'متن پاسخ خالی است');
            ta.focus();
            return;
        }
        var fd = new FormData();
        fd.append('body', body);
        if (close) fd.append('close', '1');

        var btns = $$('[data-send]', els.convo);
        btns.forEach(function (b) { b.disabled = true; b.classList.add('is-loading'); });
        var id = state.current;

        K.api('tickets/' + id + '/replies', { method: 'POST', form: fd }).then(function (t) {
            if (state.current !== id) return;
            ta.value = '';
            show(t, false);
            K.toast('success', close ? 'پاسخ ارسال و تیکت بسته شد' : 'پاسخ ارسال شد');
            refreshCounts();
        }).catch(function (e) {
            btns.forEach(function (b) { b.disabled = false; b.classList.remove('is-loading'); });
            K.toast('error', 'ارسال نشد', e.message);
        });
    }

    function composerValue() {
        var ta = $('[data-composer-input]', els.convo);
        return ta ? ta.value : '';
    }

    function autosize(ta) {
        ta.style.height = 'auto';
        ta.style.height = Math.min(ta.scrollHeight + 2, 320) + 'px';
    }

    /* ═════════════ Routing ═════════════ */

    function route() {
        var h = (location.hash || '').replace(/^#\/?/, '').split('/');
        if (h[0] === 'ticket' && h[1]) {
            if (!state.items.length && !state.loading) loadList();
            openTicket(h[1]);
            return;
        }
        state.view = h[0] === 'view' && VIEW_LABEL[h[1]] ? h[1] : 'active';
        renderNav();
        loadList();
    }

    function setHash(h) {
        if (location.hash.replace(/^#/, '') === h) route(); else location.hash = h;
    }

    /* ═════════════ Events ═════════════ */

    root.addEventListener('click', function (e) {
        var t = e.target, el;
        if ((el = t.closest('.tkm-ib-item[data-id]'))) { setHash('/ticket/' + el.getAttribute('data-id')); return; }
        if (t.closest('[data-more]')) { t.closest('[data-more]').classList.add('is-loading'); loadList(true); return; }
        if (t.closest('[data-retry]')) { loadList(); return; }
        if (t.closest('[data-back]')) { setHash('/view/' + state.view); closeTicket(); return; }
        if (t.closest('[data-side-toggle]')) { root.classList.toggle('show-side'); return; }
        if ((el = t.closest('[data-set-status]'))) { setStatus(el.getAttribute('data-set-status')); return; }
        if ((el = t.closest('[data-send]'))) { send(el.getAttribute('data-send') === 'close'); return; }
        if (t.closest('[data-delete-ticket]')) {
            K.confirm('تیکت #' + fa(state.current) + ' و همه پیام‌هایش برای همیشه حذف می‌شود.').then(function () {
                var id = state.current;
                K.api('tickets/' + id, { method: 'DELETE' }).then(function () {
                    K.toast('success', 'تیکت حذف شد');
                    state.items = state.items.filter(function (r) { return r.id !== id; });
                    state.total = Math.max(0, state.total - 1);
                    renderList();
                    setHash('/view/' + state.view);
                    closeTicket();
                    refreshCounts();
                }).catch(function (err) { K.toast('error', 'حذف نشد', err.message); });
            }, function () {});
        }
    });

    root.addEventListener('change', function (e) {
        var t = e.target;
        if (t.matches('[data-scope-select]')) { location.hash = t.value.replace(/^#/, ''); return; }
        if (t.matches('[data-prop="status"]')) setStatus(t.value);
    });

    root.addEventListener('input', function (e) {
        if (e.target.matches('[data-composer-input]')) autosize(e.target);
    });

    root.addEventListener('keydown', function (e) {
        if (e.target.matches('[data-composer-input]') && (e.ctrlKey || e.metaKey) && e.key === 'Enter') {
            e.preventDefault();
            send(false);
        }
    });

    window.addEventListener('hashchange', route);
    window.addEventListener('beforeunload', function (e) {
        if (composerValue().trim()) { e.preventDefault(); e.returnValue = ''; }
    });

    /* ═════════════ Boot ═════════════ */

    loadMeta().then(function () {
        root.setAttribute('data-state', 'ready');
        if (!location.hash || location.hash === '#' || location.hash === '#/') {
            history.replaceState(null, '', '#/view/active');
        }
        route();
    }).catch(function (e) {
        root.setAttribute('data-state', 'error');
        els.items.innerHTML = '<div class="tkm-ib-empty">' + icon('alert', 22) + '<strong>صندوق بارگذاری نشد</strong><span>' + esc(e.message) + '</span><button type="button" class="tkm-btn tkm-btn--sm" data-reload>تلاش دوباره</button></div>';
        root.addEventListener('click', function (ev) { if (ev.target.closest('[data-reload]')) location.reload(); });
    });
})();
