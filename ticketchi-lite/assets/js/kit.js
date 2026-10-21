/* Ticketchi Lite — admin app kit: REST client, formatting and small UI pieces. */
(function () {
    'use strict';

    var APP = window.TICKETCHI_LITE_APP || {};
    var FA = '۰۱۲۳۴۵۶۷۸۹';

    function fa(v) { return String(v == null ? '' : v).replace(/\d/g, function (d) { return FA[d]; }); }
    function en(v) { return String(v == null ? '' : v).replace(/[۰-۹]/g, function (d) { return FA.indexOf(d); }).replace(/[٠-٩]/g, function (d) { return '٠١٢٣٤٥٦٧٨٩'.indexOf(d); }); }
    function esc(t) {
        return String(t == null ? '' : t).replace(/[&<>"']/g, function (c) {
            return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
        });
    }

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
                    err.fields = (data && data.fields) || [];
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

    /* ───────── Pieces ───────── */
    function avatar(user, size) {
        var cls = 'tkm-avatar tkm-avatar--' + (size || 'sm');
        if (!user) return '<span class="' + cls + ' tkm-avatar--empty" aria-hidden="true">?</span>';
        var a = user.avatar || {};
        if (a.url) return '<img class="' + cls + ' tkm-avatar--img" src="' + esc(a.url) + '" alt="" loading="lazy" decoding="async">';
        return '<span class="' + cls + '" style="--h:' + (a.hue != null ? +a.hue : 220) + '" aria-hidden="true">' + esc(a.initial || '') + '</span>';
    }
    function status(s) {
        return s ? '<span class="tkm-badge" style="--c:' + esc(s.color) + '">' + esc(s.name) + '</span>' : '';
    }
    var PRIORITY = { high: 'زیاد', medium: 'متوسط', low: 'کم' };
    function priority(p, label) {
        return '<span class="tkm-priority tkm-priority--' + esc(p) + '" title="اولویت ' + esc(PRIORITY[p] || p) + '"><span class="tkm-priority__bars" aria-hidden="true"><i></i><i></i><i></i></span>' + (label === false ? '' : esc(PRIORITY[p] || p)) + '</span>';
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
        external: '<path d="M15 3h6v6M10 14 21 3M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/>',
        arrow: '<path d="m9 18 6-6-6-6"/>',
        file: '<path d="M14.5 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7.5Z"/><path d="M14 2v6h6"/>',
        mail: '<rect width="20" height="16" x="2" y="4" rx="2"/><path d="m22 7-10 5L2 7"/>',
        trash: '<path d="M3 6h18M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6M8 6V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/>',
        refresh: '<path d="M21 12a9 9 0 1 1-3-6.7L21 8"/><path d="M21 3v5h-5"/>',
        sparkles: '<path d="m12 3-1.9 5.8a2 2 0 0 1-1.3 1.3L3 12l5.8 1.9a2 2 0 0 1 1.3 1.3L12 21l1.9-5.8a2 2 0 0 1 1.3-1.3L21 12l-5.8-1.9a2 2 0 0 1-1.3-1.3Z"/>',
        crown: '<path d="m2 4 3 12h14l3-12-6 7-4-7-4 7-6-7zM5 20h14"/>',
        plus: '<path d="M12 5v14M5 12h14"/>',
        more: '<circle cx="12" cy="12" r="1"/><circle cx="19" cy="12" r="1"/><circle cx="5" cy="12" r="1"/>'
    };
    function icon(name, size) {
        size = size || 14;
        return '<svg class="tkm-icon" width="' + size + '" height="' + size + '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' + (ICONS[name] || '') + '</svg>';
    }

    function toast(type, title, text) {
        if (window.TICKETCHI_LITE_ADMIN) window.TICKETCHI_LITE_ADMIN.toast(type, title, text);
    }
    function confirmBox(message, ok) {
        if (window.TICKETCHI_LITE_ADMIN) {
            return new Promise(function (resolve, reject) { window.TICKETCHI_LITE_ADMIN.confirm(message, ok).done(resolve).fail(reject); });
        }
        return window.confirm(message) ? Promise.resolve() : Promise.reject();
    }

    function debounce(fn, ms) {
        var t;
        return function () {
            var a = arguments, self = this;
            clearTimeout(t);
            t = setTimeout(function () { fn.apply(self, a); }, ms);
        };
    }

    window.TICKETCHI_LITE = {
        app: APP, api: api, fa: fa, en: en, esc: esc,
        date: date, time: time, ago: ago,
        avatar: avatar, status: status, priority: priority, priorityLabel: PRIORITY, icon: icon,
        toast: toast, confirm: confirmBox, debounce: debounce
    };
})();
