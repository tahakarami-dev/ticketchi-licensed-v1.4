/* Ticketchi Lite — support inbox (REST-driven). */
(function () {
    'use strict';

    var K = window.TICKETCHI_LITE;
    var root = document.getElementById('tkm-inbox');
    if (!K || !root) return;

    var fa = K.fa, esc = K.esc, icon = K.icon;
    var $ = function (sel, ctx) { return (ctx || root).querySelector(sel); };
    var $$ = function (sel, ctx) { return Array.prototype.slice.call((ctx || root).querySelectorAll(sel)); };

    var VIEWS = [
        { key: 'active', label: 'تیکت‌های باز', icon: 'inbox' },
        { key: 'waiting', label: 'منتظر مشتری', icon: 'clock' },
        { key: 'closed', label: 'بسته‌شده', icon: 'check' },
        { key: 'all', label: 'همه', icon: 'ticket' },
        { key: 'trash', label: 'زباله‌دان', icon: 'trash' }
    ];
    var VIEW_LABEL = {};
    VIEWS.forEach(function (v) { VIEW_LABEL[v.key] = v.label; });

    var state = {
        meta: null,
        scope: { view: 'active' },
        filters: { priority: '', sort: 'updated', search: '' },
        items: [], total: 0, page: 1, pages: 1,
        loading: false,
        current: null,
        detail: null,
        file: null
    };

    var els = {
        views: $('[data-views]'),
        departments: $('[data-departments]'),
        items: $('[data-items]'),
        total: $('[data-total]'),
        title: $('[data-list-title]'),
        search: $('[data-search]'),
        convo: $('[data-convo]')
    };
    var placeholderHtml = els.convo.innerHTML;

    /* ═════════════ Meta & sidebar ═════════════ */

    function loadMeta() {
        return K.api('meta').then(function (meta) {
            state.meta = meta;
            renderNav();
            return meta;
        });
    }

    function renderNav() {
        var m = state.meta, counts = m.counts || {};
        els.views.innerHTML = VIEWS.map(function (v) {
            var c = counts[v.key];
            var active = state.scope.view === v.key && !state.scope.department;
            return '<a href="#/view/' + v.key + '" class="tkm-ib-view' + (active ? ' is-active' : '') + '" data-view="' + v.key + '">' +
                icon(v.icon, 16) + '<span>' + esc(v.label) + '</span>' +
                (c ? '<span class="tkm-ib-count tkm-num">' + fa(c) + '</span>' : '') + '</a>';
        }).join('');

        els.departments.innerHTML = m.departments.length ? m.departments.map(function (d) {
            var html = depLink(d, false);
            d.children.forEach(function (c) { html += depLink(c, true); });
            return html;
        }).join('') : '<p class="tkm-ib-none">دپارتمانی تعریف نشده است.</p>';

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

    /* ═════════════ List ═════════════ */

    var listSeq = 0;
    function loadList(opts) {
        opts = opts || {};
        var page = opts.append ? state.page + 1 : 1;
        var my = ++listSeq;
        state.loading = true;
        if (!opts.append) els.items.innerHTML = skeleton(6);
        var q = {
            view: state.scope.view,
            department: state.scope.department,
            priority: state.filters.priority,
            sort: state.filters.sort,
            search: K.en(state.filters.search),
            page: page,
            per_page: 30
        };
        return K.api('inbox', { query: q }).then(function (data) {
            if (my !== listSeq) return;
            state.loading = false;
            state.items = opts.append ? state.items.concat(data.items) : data.items;
            state.page = data.page;
            state.total = data.total;
            state.pages = Math.max(1, data.pages);
            if (state.meta) {
                state.meta.counts = data.counts;
                renderNav();
            }
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

    function listTitle() {
        if (state.scope.department) {
            var d = findDepartment(state.scope.department);
            return d ? 'دپارتمان ' + d.name : 'دپارتمان';
        }
        return VIEW_LABEL[state.scope.view] || 'تیکت‌ها';
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
                    '<span class="tkm-num tkm-ib-item__id">#' + fa(t.id) + '</span>' +
                    K.status(t.status) +
                    K.priority(t.priority, false) +
                    (t.department ? '<span class="tkm-ib-item__dep">' + esc(t.department.name) + '</span>' : '') +
                '</div>' +
            '</div></div>';
    }

    /* ═════════════ Conversation ═════════════ */

    var ticketSeq = 0;
    function openTicket(id, opts) {
        opts = opts || {};
        id = +id;
        var my = ++ticketSeq;
        var switching = state.current !== id;
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
            if (switching) state.file = null;
            show(t, false);
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
        if (row) {
            ['title', 'status', 'priority', 'department', 'customer', 'snippet', 'last_from', 'replies', 'updated_at'].forEach(function (k) { row[k] = t[k]; });
            var node = els.items.querySelector('[data-id="' + row.id + '"]');
            if (node) {
                var tmp = document.createElement('div');
                tmp.innerHTML = rowHtml(row);
                node.replaceWith(tmp.firstChild);
            }
        }
    }

    function closeTicket() {
        state.current = null;
        state.detail = null;
        root.classList.remove('is-reading');
        els.convo.innerHTML = placeholderHtml;
        $$('.tkm-ib-item.is-active').forEach(function (n) { n.classList.remove('is-active'); });
    }

    function isClosed(t) { return ['closed', 'finish'].indexOf(t.status.slug) !== -1; }

    function renderConvo(keepComposer) {
        var t = state.detail;
        var draft = keepComposer ? composerValue() : '';
        var closed = isClosed(t);
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
                    (trashed ? '<button type="button" class="tkm-btn tkm-btn--sm" data-set-status="open">' + icon('refresh', 15) + '<span class="tkm-hide-md">بازگردانی</span></button>'
                        : (closed ? '<button type="button" class="tkm-btn tkm-btn--sm" data-set-status="open">' + icon('refresh', 15) + '<span class="tkm-hide-md">بازگشایی</span></button>'
                                  : '<button type="button" class="tkm-btn tkm-btn--sm" data-set-status="closed">' + icon('check', 15) + '<span class="tkm-hide-md">بستن تیکت</span></button>')) +
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
        if (ta) {
            ta.value = draft;
            autosize(ta);
        }
        renderFile();
        var thread = $('[data-thread]', els.convo);
        thread.scrollTop = thread.scrollHeight;
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

    function composerHtml(closed) {
        var up = state.meta.upload;
        return '<div class="tkm-ib-composer" data-composer>' +
            (closed ? '<div class="tkm-ib-composer__tabs"><span class="tkm-ib-composer__warn">' + icon('alert', 13) + 'این تیکت بسته است؛ پاسخ شما آن را بسته نگه می‌دارد.</span></div>' : '') +
            '<textarea class="tkm-ib-composer__input" data-composer-input rows="3" placeholder="پاسخ خود را بنویسید… (Ctrl + Enter برای ارسال)" aria-label="متن پاسخ"></textarea>' +
            '<div class="tkm-ib-composer__files" data-composer-files></div>' +
            '<div class="tkm-ib-composer__bar">' +
                '<label class="tkm-btn tkm-btn--ghost tkm-btn--icon tkm-btn--sm" title="پیوست فایل' + (up.hint ? ' — ' + esc(up.hint) : '') + '">' + icon('paperclip', 17) +
                    '<input type="file" class="tkm-sr" data-composer-file' + (up.accept ? ' accept="' + esc(up.accept) + '"' : '') + '></label>' +
                '<a class="tkm-ib-pro-hint" href="' + esc(K.app.admin) + '?page=ticketchi-lite-pro">' + icon('lock', 13) + 'یادداشت داخلی و پاسخ آماده در نسخه حرفه‌ای</a>' +
                '<span class="tkm-grow"></span>' +
                (closed ? '' : '<button type="button" class="tkm-btn tkm-btn--sm" data-send="close" title="ارسال پاسخ و بستن تیکت">ارسال و بستن</button>') +
                '<button type="button" class="tkm-btn tkm-btn--primary tkm-btn--sm" data-send>' + icon('send', 15) + 'ارسال پاسخ</button>' +
            '</div></div>';
    }

    function sideHtml(t) {
        var m = state.meta;
        var statusOpts = m.statuses.map(function (s) {
            return '<option value="' + esc(s.slug) + '"' + (s.slug === t.status.slug ? ' selected' : '') + '>' + esc(s.name) + '</option>';
        }).join('');
        if (!m.statuses.some(function (s) { return s.slug === t.status.slug; })) {
            statusOpts = '<option selected value="' + esc(t.status.slug) + '">' + esc(t.status.name) + '</option>' + statusOpts;
        }

        var depOpts = m.departments.map(function (d) {
            var o = '<option value="' + d.id + '"' + (t.department && t.department.id === d.id ? ' selected' : '') + '>' + esc(d.name) + '</option>';
            d.children.forEach(function (c) { o += '<option value="' + c.id + '"' + (t.department && t.department.id === c.id ? ' selected' : '') + '>— ' + esc(c.name) + '</option>'; });
            return o;
        }).join('');
        if (!t.department) depOpts = '<option value="" selected>— بدون دپارتمان —</option>' + depOpts;

        var c = t.customer;
        return '<div class="tkm-ib-pane">' +
            '<div class="tkm-ib-props">' +
                prop('وضعیت', '<select class="tkm-select tkm-select--sm" data-prop="status">' + statusOpts + '</select>') +
                prop('اولویت', '<div class="tkm-ib-seg" role="radiogroup" aria-label="اولویت">' + ['low', 'medium', 'high'].map(function (p) {
                    return '<button type="button" role="radio" aria-checked="' + (t.priority === p) + '" class="tkm-ib-seg__btn tkm-ib-seg__btn--' + p + (t.priority === p ? ' is-active' : '') + '" data-priority="' + p + '">' + K.priorityLabel[p] + '</button>';
                }).join('') + '</div>') +
                prop('دپارتمان', '<select class="tkm-select tkm-select--sm" data-prop="department_id">' + depOpts + '</select>') +
            '</div>' +
            (c ? '<div class="tkm-ib-block"><div class="tkm-ib-block__title">' + icon('user', 14) + 'مشتری</div>' +
                '<div class="tkm-ib-customer__head">' + K.avatar(c, 'md') + '<div><strong>' + esc(c.name) + '</strong>' +
                (c.email ? '<a class="tkm-ltr tkm-ib-link" href="mailto:' + esc(c.email) + '">' + esc(c.email) + '</a>' : '') + '</div></div></div>' : '') +
            '<dl class="tkm-ib-facts">' +
                fact('ایجاد', K.date(t.created_at, true)) +
                fact('اولین پاسخ', t.first_response_at ? K.date(t.first_response_at, true) : '—') +
                fact('حل‌شده', t.resolved_at ? K.date(t.resolved_at, true) : '—') +
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

    function patch(changes, okMsg) {
        var id = state.current;
        return K.api('tickets/' + id, { method: 'PATCH', body: changes }).then(function (t) {
            if (state.current !== id) return;
            show(t, true);
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
        if (!body && !state.file) {
            K.toast('error', 'متن پاسخ خالی است');
            ta.focus();
            return;
        }
        var fd = new FormData();
        fd.append('body', body);
        if (close) fd.append('close', '1');
        if (state.file) fd.append('file', state.file);

        var btns = $$('[data-send]', els.convo);
        btns.forEach(function (b) { b.disabled = true; b.classList.add('is-loading'); });
        var id = state.current;

        K.api('tickets/' + id + '/replies', { method: 'POST', form: fd }).then(function (t) {
            state.file = null;
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

    /* ═════════════ Composer helpers ═════════════ */

    function composerValue() {
        var ta = $('[data-composer-input]', els.convo);
        return ta ? ta.value : '';
    }

    function autosize(ta) {
        ta.style.height = 'auto';
        ta.style.height = Math.min(ta.scrollHeight + 2, 320) + 'px';
    }

    function renderFile() {
        var box = $('[data-composer-files]', els.convo);
        if (!box) return;
        box.innerHTML = state.file ? '<span class="tkm-chip tkm-chip--file">' + icon('file', 12) + '<span class="tkm-ltr">' + esc(state.file.name) + '</span><button type="button" class="tkm-chip__x" data-file-remove aria-label="حذف فایل">×</button></span>' : '';
    }

    function setFile(f) {
        if (!f) return;
        var up = state.meta.upload;
        if (up.max_size && f.size > up.max_size) {
            K.toast('error', 'فایل بزرگ است', f.name + ' — ' + (up.hint || ''));
            return;
        }
        state.file = f;
        renderFile();
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
        state.scope = scope;
        if (state.meta) renderNav();
        loadList();
    }

    function lastScopeHash() {
        return state.scope.department ? '/department/' + state.scope.department : '/view/' + state.scope.view;
    }
    function setHash(h) {
        if (location.hash.replace(/^#/, '') === h) route(); else location.hash = h;
    }

    /* ═════════════ Events ═════════════ */

    root.addEventListener('click', function (e) {
        var t = e.target;
        var el;

        if ((el = t.closest('.tkm-ib-item[data-id]'))) { setHash('/ticket/' + el.getAttribute('data-id')); return; }
        if (t.closest('[data-more]')) { t.closest('[data-more]').classList.add('is-loading'); loadList({ append: true }); return; }
        if (t.closest('[data-retry]')) { loadList(); return; }
        if (t.closest('[data-clear-filters]')) {
            state.filters.search = ''; state.filters.priority = '';
            els.search.value = ''; $('[data-filter="priority"]').value = '';
            loadList();
            return;
        }
        if (t.closest('[data-back]')) { setHash(lastScopeHash()); closeTicket(); return; }
        if (t.closest('[data-side-toggle]')) { root.classList.toggle('show-side'); return; }
        if ((el = t.closest('[data-set-status]'))) {
            var st = el.getAttribute('data-set-status');
            patch({ status: st }, STATUS_MSG[st]);
            return;
        }
        if ((el = t.closest('[data-priority]'))) { patch({ priority: el.getAttribute('data-priority') }); return; }
        if ((el = t.closest('[data-send]'))) { send(el.getAttribute('data-send') === 'close'); return; }
        if (t.closest('[data-file-remove]')) { state.file = null; renderFile(); return; }
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
        }
    });

    root.addEventListener('change', function (e) {
        var t = e.target;
        if (t.matches('[data-filter]')) {
            state.filters[t.getAttribute('data-filter')] = t.value;
            loadList();
            return;
        }
        if (t.matches('[data-scope-select]')) { location.hash = t.value.replace(/^#/, ''); return; }
        if (t.matches('[data-prop]')) {
            var key = t.getAttribute('data-prop');
            var change = {};
            change[key] = t.value;
            patch(change, key === 'department_id' ? 'دپارتمان تغییر کرد' : null);
            return;
        }
        if (t.matches('[data-composer-file]')) { setFile(t.files[0]); t.value = ''; }
    });

    root.addEventListener('input', function (e) {
        if (e.target.matches('[data-composer-input]')) autosize(e.target);
        if (e.target === els.search) searchSoon();
    });
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
        if (t === els.search && e.key === 'Escape') { els.search.value = ''; searchSoon(); els.search.blur(); }
    });

    // Drag & drop a file onto the composer.
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
        if (c && e.dataTransfer.files.length) { e.preventDefault(); c.classList.remove('is-over'); setFile(e.dataTransfer.files[0]); }
    });

    window.addEventListener('hashchange', route);
    window.addEventListener('beforeunload', function (e) {
        if (composerValue().trim() || state.file) { e.preventDefault(); e.returnValue = ''; }
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
