/* Ticketchi — admin app kit: REST client, formatting, notification bell, command palette. */
(function () {
    'use strict';

    var APP = window.TKM_APP || {};
    var FA = '۰۱۲۳۴۵۶۷۸۹';

    function fa(v) { return String(v == null ? '' : v).replace(/\d/g, function (d) { return FA[d]; }); }
    function en(v) { return String(v == null ? '' : v).replace(/[۰-۹]/g, function (d) { return FA.indexOf(d); }).replace(/[٠-٩]/g, function (d) { return '٠١٢٣٤٥٦٧٨٩'.indexOf(d); }); }
    function esc(t) {
        return String(t == null ? '' : t).replace(/[&<>"']/g, function (c) {
            return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
        });
    }
    function num(n) { return fa(Number(n || 0).toLocaleString('en-US').replace(/,/g, '٬')); }

    /* ───────── REST ───────── */
    function api(path, opts) {
        opts = opts || {};
        var url = APP.root + path.replace(/^\//, '');
        if (opts.query) {
            var qs = Object.keys(opts.query).filter(function (k) {
                return opts.query[k] !== undefined && opts.query[k] !== null && opts.query[k] !== '';
            }).map(function (k) { return encodeURIComponent(k) + '=' + encodeURIComponent(opts.query[k]); }).join('&');
            if (qs) url += (url.indexOf('?') === -1 ? '?' : '&') + qs;
        }
        var init = { method: opts.method || 'GET', credentials: 'same-origin', headers: { 'X-WP-Nonce': APP.nonce, 'Accept': 'application/json' } };
        if (opts.form) {
            init.body = opts.form;
        } else if (opts.body !== undefined) {
            init.headers['Content-Type'] = 'application/json';
            init.body = JSON.stringify(opts.body);
        }
        // Some hosts block PATCH/PUT/DELETE; tunnel through POST.
        if (/^(PATCH|PUT|DELETE)$/.test(init.method)) {
            init.headers['X-HTTP-Method-Override'] = init.method;
            init.method = 'POST';
        }
        return fetch(url, init).then(function (res) {
            return res.text().then(function (text) {
                var data = null;
                try { data = text ? JSON.parse(text) : null; } catch (e) { data = null; }
                if (!res.ok || data === null) {
                    var err = new Error((data && data.message) || (res.status === 403 ? 'دسترسی ندارید یا نشست شما منقضی شده است؛ صفحه را تازه کنید.' : 'ارتباط با سرور برقرار نشد.'));
                    err.status = res.status;
                    throw err;
                }
                return data;
            });
        });
    }

    /* ───────── Dates ───────── */
    var fmtCache = {};
    function fmt(opts) {
        var key = JSON.stringify(opts);
        if (!fmtCache[key]) {
            try { fmtCache[key] = new Intl.DateTimeFormat('fa-IR-u-ca-persian-nu-arabext', opts); } catch (e) { fmtCache[key] = new Intl.DateTimeFormat('fa-IR', opts); }
        }
        return fmtCache[key];
    }
    function toDate(iso) { return iso ? new Date(iso) : null; }
    function date(iso, withTime) {
        var d = toDate(iso);
        if (!d || isNaN(d)) return '—';
        var o = { year: 'numeric', month: 'long', day: 'numeric' };
        if (withTime) { o.hour = '2-digit'; o.minute = '2-digit'; o.hour12 = false; }
        return fa(fmt(o).format(d));
    }
    function time(iso) {
        var d = toDate(iso);
        return d ? fa(fmt({ hour: '2-digit', minute: '2-digit', hour12: false }).format(d)) : '';
    }
    function ago(iso) {
        var d = toDate(iso);
        if (!d || isNaN(d)) return '—';
        var s = Math.round((Date.now() - d.getTime()) / 1000);
        if (s < 45) return 'همین حالا';
        var m = Math.round(s / 60);
        if (m < 60) return fa(m) + ' دقیقه پیش';
        var h = Math.round(m / 60);
        if (h < 24) return fa(h) + ' ساعت پیش';
        var dd = Math.round(h / 24);
        if (dd < 7) return dd === 1 ? 'دیروز' : fa(dd) + ' روز پیش';
        return fa(fmt({ month: 'short', day: 'numeric' }).format(d));
    }
    function duration(seconds) {
        seconds = Math.abs(Math.round(seconds || 0));
        if (seconds < 60) return 'کمتر از ۱ دقیقه';
        var m = Math.round(seconds / 60);
        if (m < 60) return fa(m) + ' دقیقه';
        var h = Math.floor(m / 60), rm = m % 60;
        if (h < 24) return fa(h) + ' ساعت' + (rm && h < 10 ? ' و ' + fa(rm) + ' دقیقه' : '');
        var d = Math.floor(h / 24), rh = h % 24;
        return fa(d) + ' روز' + (rh ? ' و ' + fa(rh) + ' ساعت' : '');
    }

    /* ───────── Pieces ───────── */
    function avatar(user, size) {
        if (!user) return '<span class="tkm-avatar tkm-avatar--' + (size || 'sm') + ' tkm-avatar--empty" aria-hidden="true">?</span>';
        return '<span class="tkm-avatar tkm-avatar--' + (size || 'sm') + '" style="--h:' + (user.avatar ? user.avatar.hue : 220) + '" aria-hidden="true">' + esc(user.avatar ? user.avatar.initial : '') + '</span>';
    }
    function status(s) {
        return s ? '<span class="tkm-badge" style="--c:' + esc(s.color) + '">' + esc(s.name) + '</span>' : '';
    }
    var PRIORITY = { high: 'زیاد', medium: 'متوسط', low: 'کم' };
    function priority(p, label) {
        return '<span class="tkm-priority tkm-priority--' + esc(p) + '" title="اولویت ' + esc(PRIORITY[p] || p) + '"><span class="tkm-priority__bars" aria-hidden="true"><i></i><i></i><i></i></span>' + (label === false ? '' : esc(PRIORITY[p] || p)) + '</span>';
    }
    function tag(t, removable) {
        return '<span class="tkm-chip" style="--c:' + esc(t.color || '#6366f1') + '" data-tag-id="' + t.id + '">' + esc(t.name) +
            (removable ? '<button type="button" class="tkm-chip__x" data-tag-remove="' + t.id + '" aria-label="حذف برچسب ' + esc(t.name) + '">×</button>' : '') + '</span>';
    }
    function sla(s) {
        if (!s || s.state === 'none') return '';
        var label = s.kind === 'first' ? 'پاسخ اول' : 'حل';
        if (s.state === 'met') return '<span class="tkm-sla tkm-sla--met" title="SLA رعایت شد">' + icon('check') + 'SLA</span>';
        if (s.state === 'breached') return '<span class="tkm-sla tkm-sla--breached" title="مهلت ' + label + ' گذشته است">' + icon('alert') + fa(duration(s.remaining).replace(/ و .*/, '')) + ' تأخیر</span>';
        return '<span class="tkm-sla tkm-sla--' + s.state + '" title="مهلت ' + label + '">' + icon('clock') + duration(s.remaining).replace(/ و .*/, '') + '</span>';
    }

    var ICONS = {
        check: '<path d="M20 6 9 17l-5-5"/>',
        alert: '<path d="m21.73 18-8-14a2 2 0 0 0-3.48 0l-8 14A2 2 0 0 0 4 21h16a2 2 0 0 0 1.73-3Z"/><path d="M12 9v4M12 17h.01"/>',
        clock: '<circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/>',
        x: '<path d="M18 6 6 18M6 6l12 12"/>',
        ticket: '<path d="M2 9a3 3 0 0 1 0 6v2a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2v-2a3 3 0 0 1 0-6V7a2 2 0 0 0-2-2H4a2 2 0 0 0-2 2Z"/>',
        user: '<path d="M19 21v-2a4 4 0 0 0-4-4H9a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/>',
        inbox: '<path d="M22 12h-6l-2 3h-4l-2-3H2"/><path d="M5.45 5.11 2 12v6a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2v-6l-3.45-6.89A2 2 0 0 0 16.76 4H7.24a2 2 0 0 0-1.79 1.11z"/>',
        paperclip: '<path d="m21.44 11.05-9.19 9.19a6 6 0 0 1-8.49-8.49l8.57-8.57A4 4 0 1 1 18 8.84l-8.59 8.57a2 2 0 0 1-2.83-2.83l8.49-8.48"/>',
        lock: '<rect width="18" height="11" x="3" y="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/>',
        send: '<path d="m22 2-7 20-4-9-9-4Z"/><path d="M22 2 11 13"/>',
        user_check: '<path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="m16 11 2 2 4-4"/>',
        external: '<path d="M15 3h6v6M10 14 21 3M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/>',
        arrow: '<path d="m9 18 6-6-6-6"/>',
        back: '<path d="m15 18-6-6 6-6"/>',
        star: '<path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01z"/>',
        file: '<path d="M14.5 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7.5Z"/><path d="M14 2v6h6"/>',
        bag: '<path d="M6 2 3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4Z"/><path d="M3 6h18"/><path d="M16 10a4 4 0 0 1-8 0"/>',
        mail: '<rect width="20" height="16" x="2" y="4" rx="2"/><path d="m22 7-10 5L2 7"/>',
        phone: '<path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6A19.79 19.79 0 0 1 2.12 4.18 2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72c.13.96.36 1.9.7 2.81a2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45c.91.34 1.85.57 2.81.7A2 2 0 0 1 22 16.92z"/>',
        trash: '<path d="M3 6h18M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6M8 6V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/>',
        refresh: '<path d="M21 12a9 9 0 1 1-3-6.7L21 8"/><path d="M21 3v5h-5"/>',
        bolt: '<path d="M13 2 3 14h9l-1 8 10-12h-9l1-8z"/>',
        note: '<path d="M12 20h9"/><path d="M16.5 3.5a2.12 2.12 0 0 1 3 3L7 19l-4 1 1-4Z"/>',
        sparkles: '<path d="m12 3-1.9 5.8a2 2 0 0 1-1.3 1.3L3 12l5.8 1.9a2 2 0 0 1 1.3 1.3L12 21l1.9-5.8a2 2 0 0 1 1.3-1.3L21 12l-5.8-1.9a2 2 0 0 1-1.3-1.3Z"/>',
        mic: '<path d="M12 2a3 3 0 0 0-3 3v7a3 3 0 0 0 6 0V5a3 3 0 0 0-3-3Z"/><path d="M19 10v2a7 7 0 0 1-14 0v-2M12 19v3"/>',
        plus: '<path d="M12 5v14M5 12h14"/>',
        tag: '<path d="M12 2H2v10l9.29 9.29a1 1 0 0 0 1.41 0l8.59-8.59a1 1 0 0 0 0-1.41Z"/><circle cx="7" cy="7" r="1.5"/>',
        more: '<circle cx="12" cy="12" r="1"/><circle cx="19" cy="12" r="1"/><circle cx="5" cy="12" r="1"/>'
    };
    function icon(name, size) {
        size = size || 14;
        return '<svg class="tkm-icon" width="' + size + '" height="' + size + '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' + (ICONS[name] || '') + '</svg>';
    }

    function toast(type, title, text) {
        if (window.TKM_ADMIN && window.TKM_ADMIN.toast) window.TKM_ADMIN.toast(type, title, text);
    }
    function confirmBox(message, ok) {
        if (window.TKM_ADMIN && window.TKM_ADMIN.confirm) {
            return new Promise(function (resolve, reject) { window.TKM_ADMIN.confirm(message, ok).done(resolve).fail(reject); });
        }
        return window.confirm(message) ? Promise.resolve() : Promise.reject();
    }

    function ticketUrl(id) { return APP.admin + '?page=tkm-tickets#/ticket/' + id; }
    function typing(e) {
        var t = e.target;
        return t && (t.isContentEditable || /^(INPUT|TEXTAREA|SELECT)$/.test(t.tagName));
    }

    function debounce(fn, ms) {
        var t;
        return function () {
            var a = arguments, self = this;
            clearTimeout(t);
            t = setTimeout(function () { fn.apply(self, a); }, ms);
        };
    }

    /* ───────── Notification bell ───────── */
    function bell() {
        var root = document.querySelector('[data-bell]');
        if (!root) return null;
        var btn = root.querySelector('[data-bell-toggle]');
        var panel = root.querySelector('[data-bell-panel]');
        var list = root.querySelector('[data-bell-list]');
        var dot = root.querySelector('[data-bell-count]');

        function setCount(n) {
            dot.hidden = !n;
            dot.textContent = n > 9 ? '۹+' : fa(n);
        }
        function render(data) {
            setCount(data.unread);
            if (!data.items.length) {
                list.innerHTML = '<div class="tkm-bell__empty">' + icon('check', 18) + '<span>اعلان تازه‌ای ندارید.</span></div>';
                return;
            }
            list.innerHTML = data.items.map(function (n) {
                var href = n.ticket_id ? ticketUrl(n.ticket_id) : '#';
                return '<a class="tkm-bell__item' + (n.read ? '' : ' is-unread') + '" href="' + esc(href) + '" data-note-id="' + n.id + '">' +
                    '<span class="tkm-bell__icon tkm-bell__icon--' + esc(n.type) + '">' + icon({ sla: 'alert', assigned: 'user_check', created: 'ticket', reply: 'mail' }[n.type] || 'bolt') + '</span>' +
                    '<span class="tkm-bell__text">' + esc(n.message) + '<time>' + ago(n.date) + '</time></span></a>';
            }).join('');
        }
        function load() {
            list.innerHTML = '<div class="tkm-bell__empty"><span class="tkm-spinner"></span></div>';
            return api('notifications').then(render).catch(function (e) { list.innerHTML = '<div class="tkm-bell__empty">' + esc(e.message) + '</div>'; });
        }
        function close() {
            panel.hidden = true;
            btn.setAttribute('aria-expanded', 'false');
        }
        btn.addEventListener('click', function (e) {
            e.stopPropagation();
            var open = panel.hidden;
            panel.hidden = !open;
            btn.setAttribute('aria-expanded', String(open));
            if (open) load();
        });
        document.addEventListener('click', function (e) { if (!root.contains(e.target)) close(); });
        document.addEventListener('keydown', function (e) { if (e.key === 'Escape') close(); });
        root.querySelector('[data-bell-readall]').addEventListener('click', function () {
            api('notifications/read', { method: 'POST', body: {} }).then(render);
        });
        list.addEventListener('click', function (e) {
            var a = e.target.closest('[data-note-id]');
            if (!a) return;
            api('notifications/read', { method: 'POST', body: { id: +a.getAttribute('data-note-id') } }).then(render).catch(function () {});
            close();
        });

        setCount(+APP.unread || 0);
        return { setCount: setCount };
    }

    /* ───────── Command palette (Ctrl/⌘ + K) ───────── */
    function palette() {
        var el = document.createElement('div');
        el.className = 'tkm-cmdk';
        el.hidden = true;
        el.setAttribute('role', 'dialog');
        el.setAttribute('aria-modal', 'true');
        el.setAttribute('aria-label', 'جستجوی سریع');
        el.innerHTML = '<div class="tkm-cmdk__backdrop" data-cmdk-close></div>' +
            '<div class="tkm-cmdk__box"><div class="tkm-cmdk__input"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/></svg>' +
            '<input type="text" placeholder="شماره تیکت، عنوان، نام یا ایمیل مشتری…" aria-label="جستجو" autocomplete="off"><kbd class="tkm-kbd">Esc</kbd></div>' +
            '<div class="tkm-cmdk__results" role="listbox"></div></div>';
        var shell = document.getElementById('tkm-admin') || document.body;
        shell.appendChild(el);

        var input = el.querySelector('input');
        var results = el.querySelector('.tkm-cmdk__results');
        var items = [];
        var cursor = 0;
        var seq = 0;

        var PAGES = [
            { label: 'صندوق پشتیبانی', hint: 'صفحه', url: APP.admin + '?page=tkm-tickets', icon: 'inbox' },
            { label: 'تیکت‌های من', hint: 'نما', url: APP.admin + '?page=tkm-tickets#/view/mine', icon: 'user_check' },
            { label: 'ارسال تیکت به کاربر', hint: 'صفحه', url: APP.admin + '?page=tkm-new-ticket', icon: 'plus' }
        ];
        if (APP.manage) {
            PAGES.push(
                { label: 'گزارش‌ها', hint: 'صفحه', url: APP.admin + '?page=tkm-reports', icon: 'sparkles' },
                { label: 'اتوماسیون', hint: 'صفحه', url: APP.admin + '?page=tkm-automations', icon: 'bolt' },
                { label: 'تنظیمات', hint: 'صفحه', url: APP.admin + '?page=tkm-settings', icon: 'more' }
            );
        }

        function paint(groups) {
            items = [];
            var html = '';
            groups.forEach(function (g) {
                if (!g.items.length) return;
                html += '<div class="tkm-cmdk__group">' + esc(g.title) + '</div>';
                g.items.forEach(function (it) {
                    html += '<a class="tkm-cmdk__item" role="option" href="' + esc(it.url) + '" data-i="' + items.length + '">' + it.lead + '<span class="tkm-cmdk__label">' + it.label + '</span><span class="tkm-cmdk__hint">' + it.hint + '</span></a>';
                    items.push(it);
                });
            });
            results.innerHTML = html || '<div class="tkm-cmdk__empty">نتیجه‌ای پیدا نشد.</div>';
            move(0);
        }
        function move(i) {
            var nodes = results.querySelectorAll('.tkm-cmdk__item');
            if (!nodes.length) return;
            cursor = (i + nodes.length) % nodes.length;
            nodes.forEach(function (n, k) { n.classList.toggle('is-active', k === cursor); n.setAttribute('aria-selected', String(k === cursor)); });
            nodes[cursor].scrollIntoView({ block: 'nearest' });
        }
        function pages(q) {
            return PAGES.filter(function (p) { return !q || p.label.indexOf(q) !== -1; }).map(function (p) {
                return { url: p.url, label: esc(p.label), hint: p.hint, lead: '<span class="tkm-cmdk__ic">' + icon(p.icon, 16) + '</span>' };
            });
        }
        var search = debounce(function (q) {
            var my = ++seq;
            api('search', { query: { q: q } }).then(function (data) {
                if (my !== seq) return;
                paint([
                    { title: 'تیکت‌ها', items: data.tickets.map(function (t) {
                        return { url: ticketUrl(t.id), label: '<b class="tkm-num">#' + fa(t.id) + '</b> ' + esc(t.title), hint: status(t.status), lead: avatar(t.customer, 'sm') };
                    }) },
                    { title: 'مشتریان', items: data.customers.map(function (c) {
                        return { url: APP.admin + '?page=tkm-tickets#/customer/' + c.id, label: esc(c.name), hint: '<span class="tkm-ltr">' + esc(c.email) + '</span>', lead: avatar(c, 'sm') };
                    }) },
                    { title: 'رفتن به', items: pages(q) }
                ]);
            }).catch(function () {});
        }, 180);

        function open() {
            el.hidden = false;
            input.value = '';
            paint([{ title: 'رفتن به', items: pages('') }]);
            setTimeout(function () { input.focus(); }, 10);
        }
        function close() { el.hidden = true; }

        input.addEventListener('input', function () {
            var q = input.value.trim();
            if (!q) { paint([{ title: 'رفتن به', items: pages('') }]); return; }
            search(en(q));
        });
        input.addEventListener('keydown', function (e) {
            if (e.key === 'ArrowDown') { e.preventDefault(); move(cursor + 1); }
            else if (e.key === 'ArrowUp') { e.preventDefault(); move(cursor - 1); }
            else if (e.key === 'Enter') {
                e.preventDefault();
                var node = results.querySelectorAll('.tkm-cmdk__item')[cursor];
                if (node) { close(); go(node.getAttribute('href')); }
            }
        });
        results.addEventListener('click', function (e) {
            var a = e.target.closest('.tkm-cmdk__item');
            if (a && !e.metaKey && !e.ctrlKey) { e.preventDefault(); close(); go(a.getAttribute('href')); }
        });
        el.addEventListener('click', function (e) { if (e.target.hasAttribute('data-cmdk-close')) close(); });
        document.addEventListener('keydown', function (e) {
            if ((e.ctrlKey || e.metaKey) && (e.key === 'k' || e.key === 'K' || e.code === 'KeyK')) { e.preventDefault(); el.hidden ? open() : close(); }
            else if (e.key === 'Escape' && !el.hidden) { e.preventDefault(); close(); }
        });
        document.querySelectorAll('[data-cmdk-open]').forEach(function (b) { b.addEventListener('click', open); });
        return { open: open, close: close };
    }

    // Same-page hash links (the inbox) should not reload.
    function go(url) {
        var here = location.href.split('#')[0];
        var there = url.split('#');
        if (there[0] === here && there[1] !== undefined) {
            location.hash = there[1];
        } else {
            location.href = url;
        }
    }

    var kit = {
        app: APP, api: api, fa: fa, en: en, esc: esc, num: num,
        date: date, time: time, ago: ago, duration: duration,
        avatar: avatar, status: status, priority: priority, priorityLabel: PRIORITY, tag: tag, sla: sla, icon: icon,
        toast: toast, confirm: confirmBox, ticketUrl: ticketUrl, typing: typing, debounce: debounce, go: go
    };
    window.TKM = kit;

    function init() {
        kit.bell = bell();
        kit.palette = palette();
    }
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init); else init();
})();
