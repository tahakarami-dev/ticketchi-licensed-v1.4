/* Ticketchi — reports. Charts are hand-built SVG/HTML (no chart library). */
(function () {
    'use strict';

    var K = window.TKM;
    var root = document.getElementById('tkm-reports');
    if (!K || !root) return;

    var fa = K.fa, esc = K.esc, num = K.num;
    var state = { from: '', to: '', days: 30, data: null };
    var DAYS = ['شنبه', 'یکشنبه', 'دوشنبه', 'سه‌شنبه', 'چهارشنبه', 'پنجشنبه', 'جمعه'];

    /* ───────── Range ───────── */

    function iso(d) {
        return d.getFullYear() + '-' + String(d.getMonth() + 1).padStart(2, '0') + '-' + String(d.getDate()).padStart(2, '0');
    }
    function preset(days) {
        var to = new Date();
        var from = new Date();
        if (days === 'month') {
            // Start of the current Persian month.
            var parts = new Intl.DateTimeFormat('en-US-u-ca-persian', { day: 'numeric' }).formatToParts(to);
            var day = +parts.filter(function (p) { return p.type === 'day'; })[0].value;
            from.setDate(to.getDate() - (day - 1));
        } else {
            from.setDate(to.getDate() - (days - 1));
        }
        state.days = days;
        state.from = iso(from);
        state.to = iso(to);
        syncControls();
        load();
    }

    function syncControls() {
        document.querySelectorAll('[data-days]').forEach(function (b) {
            var on = String(b.getAttribute('data-days')) === String(state.days);
            b.classList.toggle('is-active', on);
            b.setAttribute('aria-pressed', String(on));
        });
        document.querySelector('[data-from]').value = state.from;
        document.querySelector('[data-to]').value = state.to;
        var csv = document.querySelector('[data-csv]');
        csv.href = K.app.csv + '&from=' + state.from + '&to=' + state.to;
    }

    var seq = 0;
    function load() {
        var my = ++seq;
        root.setAttribute('data-state', 'loading');
        root.classList.add('is-loading');
        K.api('reports', { query: { from: state.from, to: state.to } }).then(function (data) {
            if (my !== seq) return;
            state.data = data;
            render(data);
            root.setAttribute('data-state', 'ready');
            root.classList.remove('is-loading');
        }).catch(function (e) {
            if (my !== seq) return;
            root.classList.remove('is-loading');
            root.innerHTML = '<div class="tkm-card tkm-ib-empty">' + K.icon('alert', 22) + '<strong>گزارش بارگذاری نشد</strong><span>' + esc(e.message) + '</span></div>';
        });
    }

    /* ───────── Formatting ───────── */

    function dur(sec) { return sec == null ? '—' : K.duration(sec); }

    // lowerIsBetter: for durations, a drop is an improvement.
    function delta(cur, prev, lowerIsBetter, unit) {
        if (cur == null || prev == null) return '<span class="tkm-delta" title="داده دوره قبل موجود نیست">—</span>';
        if (prev === 0 && cur === 0) return '<span class="tkm-delta">بدون تغییر</span>';
        var diff, label;
        if (unit === 'pt') {
            diff = cur - prev;
            label = fa(Math.abs(Math.round(diff))) + ' واحد';
        } else if (prev === 0) {
            return '<span class="tkm-delta is-up">جدید</span>';
        } else {
            diff = (cur - prev) / prev * 100;
            label = fa(Math.abs(Math.round(diff))) + '٪';
        }
        if (Math.round(diff) === 0) return '<span class="tkm-delta">بدون تغییر</span>';
        var up = diff > 0;
        var good = lowerIsBetter ? !up : up;
        return '<span class="tkm-delta ' + (good ? 'is-good' : 'is-bad') + '" title="نسبت به دوره قبل">' + (up ? '▲' : '▼') + ' ' + label + '</span>';
    }

    function kpi(label, value, foot, icon, color) {
        return '<div class="tkm-kpi"><div class="tkm-kpi__top"><span class="tkm-kpi__label">' + label + '</span><span class="tkm-kpi__icon" style="--c:' + color + '">' + K.icon(icon, 18) + '</span></div>' +
            '<div class="tkm-kpi__value' + (String(value).length > 9 ? ' tkm-kpi__value--text' : '') + '">' + value + '</div><div class="tkm-kpi__foot">' + foot + '</div></div>';
    }

    /* ───────── Render ───────── */

    function render(d) {
        var k = d.kpis, p = d.previous;
        document.querySelector('[data-range-label]').textContent = K.date(d.range.from) + ' تا ' + K.date(d.range.to) + ' · ' + fa(d.range.days) + ' روز، در مقایسه با ' + fa(d.range.days) + ' روز قبل از آن.';

        var empty = !k.created && !k.resolved && !k.replies;

        root.innerHTML =
            '<div class="tkm-kpis tkm-rp-kpis">' +
                kpi('تیکت‌های جدید', num(k.created), delta(k.created, p.created, false) + '<span>دوره قبل ' + num(p.created) + '</span>', 'ticket', 'var(--tkm-primary)') +
                kpi('حل‌شده', num(k.resolved), delta(k.resolved, p.resolved, false) + '<span>صف باز فعلی ' + num(k.backlog) + '</span>', 'check', 'var(--tkm-success)') +
                kpi('اولین پاسخ (میانه)', dur(k.first_response_median), delta(k.first_response_median, p.first_response_median, true) + '<span>میانگین ' + dur(k.first_response) + '</span>', 'clock', 'var(--tkm-info)') +
                kpi('میانگین زمان حل', dur(k.resolution), delta(k.resolution, p.resolution, true), 'refresh', '#8b5cf6') +
                kpi('رعایت SLA', k.sla_compliance == null ? '—' : fa(k.sla_compliance) + '<small>٪</small>', delta(k.sla_compliance, p.sla_compliance, false, 'pt'), 'alert', 'var(--tkm-warning)') +
                kpi('رضایت مشتری', k.csat == null ? '—' : fa(k.csat) + '<small>/۵</small>', delta(k.csat, p.csat, false) + '<span>' + num(k.csat_count) + ' امتیاز</span>', 'star', '#f5a524') +
                kpi('پاسخ‌های ارسالی', num(k.replies), delta(k.replies, p.replies, false), 'send', 'var(--tkm-primary)') +
                kpi('پاسخ به ازای تیکت', k.created ? fa((k.replies / k.created).toFixed(1)) : '—', '<span>میانگین رفت‌وبرگشت</span>', 'mail', 'var(--tkm-text-3)') +
            '</div>' +
            (empty ? '<div class="tkm-card tkm-ib-empty">' + K.icon('inbox', 24) + '<strong>در این بازه فعالیتی ثبت نشده است</strong><span>بازه زمانی بزرگ‌تری انتخاب کنید.</span></div>' : '') +
            '<div class="tkm-rp-grid">' +
                card('روند تیکت‌ها', 'ثبت‌شده و حل‌شده در هر روز', trend(d.series), 'tkm-rp-span-2') +
                card('رضایت مشتری', 'توزیع امتیازها', csat(d.csat)) +
                card('عملکرد کارشناسان', 'مرتب‌شده بر اساس تیکت‌های حل‌شده', agents(d.agents), 'tkm-rp-span-2') +
                card('اولویت تیکت‌های جدید', '', bars(d.priorities.map(function (r) { return { label: K.priorityLabel[r.priority], value: r.count }; }))) +
                card('ساعات پرترافیک', 'تعداد تیکت‌های ثبت‌شده در هر ساعت از هفته (به وقت سایت)', heatmap(d.heatmap), 'tkm-rp-span-2') +
                card('دپارتمان‌ها', 'تیکت‌های ثبت‌شده در بازه', bars(d.departments.map(function (r) { return { label: r.name, value: r.total, extra: num(r.resolved) + ' حل‌شده' }; }))) +
            '</div>';

        bindTrend();
        bindHeat();
    }

    function card(title, sub, body, cls) {
        return '<section class="tkm-card tkm-rp-card ' + (cls || '') + '"><header class="tkm-card__head"><div><h3 class="tkm-card__title">' + title + '</h3>' + (sub ? '<p class="tkm-card__sub">' + sub + '</p>' : '') + '</div></header><div class="tkm-card__body">' + body + '</div></section>';
    }

    /* line chart: created vs resolved */
    function trend(series) {
        if (!series.length) return '<p class="tkm-ib-none">داده‌ای نیست.</p>';
        var W = 720, H = 240, L = 36, R = 12, T = 14, B = 28;
        var max = Math.max(1, Math.max.apply(null, series.map(function (s) { return Math.max(s.created, s.resolved); })));
        var step = niceStep(max);
        var top = Math.ceil(max / step) * step;
        var x = function (i) { return series.length === 1 ? (L + W - R) / 2 : W - R - i * (W - L - R) / (series.length - 1); }; // RTL: day 1 on the right
        var y = function (v) { return T + (H - T - B) * (1 - v / top); };
        var path = function (key) {
            return series.map(function (s, i) { return (i ? 'L' : 'M') + x(i).toFixed(1) + ' ' + y(s[key]).toFixed(1); }).join(' ');
        };
        var grid = '';
        for (var v = 0; v <= top; v += step) {
            grid += '<line class="tkm-chart__grid" x1="' + L + '" x2="' + (W - R) + '" y1="' + y(v) + '" y2="' + y(v) + '"/>' +
                '<text class="tkm-chart__tick" x="' + (L - 8) + '" y="' + (y(v) + 4) + '" text-anchor="end">' + fa(v) + '</text>';
        }
        var every = Math.max(1, Math.ceil(series.length / 8));
        var ticks = series.map(function (s, i) {
            return i % every === 0 || i === series.length - 1 ? '<text class="tkm-chart__tick" x="' + x(i) + '" y="' + (H - 8) + '" text-anchor="middle">' + esc(s.label) + '</text>' : '';
        }).join('');
        var dots = series.length <= 31 ? series.map(function (s, i) {
            return '<circle class="tkm-rp-dot tkm-rp-dot--a" cx="' + x(i) + '" cy="' + y(s.created) + '" r="3"/><circle class="tkm-rp-dot tkm-rp-dot--b" cx="' + x(i) + '" cy="' + y(s.resolved) + '" r="3"/>';
        }).join('') : '';

        var table = '<details class="tkm-table-view"><summary>' + K.icon('file', 12) + 'نمایش جدول داده‌ها</summary><table class="tkm-mini-table"><thead><tr><th>روز</th><th>ثبت‌شده</th><th>حل‌شده</th></tr></thead><tbody>' +
            series.map(function (s) { return '<tr><td>' + esc(s.label) + '</td><td>' + fa(s.created) + '</td><td>' + fa(s.resolved) + '</td></tr>'; }).join('') + '</tbody></table></details>';

        return '<div class="tkm-legend tkm-rp-legend"><span><i class="tkm-rp-key tkm-rp-key--a"></i>ثبت‌شده</span><span><i class="tkm-rp-key tkm-rp-key--b"></i>حل‌شده</span></div>' +
            '<figure class="tkm-chart tkm-rp-trend" data-trend data-l="' + L + '" data-r="' + R + '" data-w="' + W + '">' +
            '<svg viewBox="0 0 ' + W + ' ' + H + '" role="img" aria-label="نمودار روند تیکت‌های ثبت‌شده و حل‌شده">' + grid +
                '<line class="tkm-chart__axis" x1="' + L + '" x2="' + (W - R) + '" y1="' + y(0) + '" y2="' + y(0) + '"/>' + ticks +
                '<path class="tkm-rp-line tkm-rp-line--a" d="' + path('created') + '"/><path class="tkm-rp-line tkm-rp-line--b" d="' + path('resolved') + '"/>' + dots +
                '<line class="tkm-rp-cross" data-cross x1="0" x2="0" y1="' + T + '" y2="' + y(0) + '" hidden/>' +
                '<rect class="tkm-rp-hit" x="' + L + '" y="0" width="' + (W - L - R) + '" height="' + H + '" data-hit/>' +
            '</svg><div class="tkm-chart__tooltip" data-tip hidden></div></figure>' + table;
    }

    function niceStep(max) {
        var raw = max / 4;
        var pow = Math.pow(10, Math.floor(Math.log10(raw)));
        var n = raw / pow;
        return Math.max(1, (n <= 1 ? 1 : n <= 2 ? 2 : n <= 5 ? 5 : 10) * pow);
    }

    function bindTrend() {
        var fig = root.querySelector('[data-trend]');
        if (!fig) return;
        var svg = fig.querySelector('svg');
        var hit = fig.querySelector('[data-hit]');
        var cross = fig.querySelector('[data-cross]');
        var tip = fig.querySelector('[data-tip]');
        var series = state.data.series;
        var W = +fig.getAttribute('data-w'), L = +fig.getAttribute('data-l'), R = +fig.getAttribute('data-r');

        function show(clientX) {
            var box = svg.getBoundingClientRect();
            var sx = (clientX - box.left) / box.width * W;
            var i = series.length === 1 ? 0 : Math.round((W - R - sx) / ((W - L - R) / (series.length - 1)));
            i = Math.max(0, Math.min(series.length - 1, i));
            var px = series.length === 1 ? (L + W - R) / 2 : W - R - i * (W - L - R) / (series.length - 1);
            cross.setAttribute('x1', px); cross.setAttribute('x2', px); cross.removeAttribute('hidden');
            var s = series[i];
            tip.textContent = '';
            var strong = document.createElement('strong'); strong.textContent = s.label; tip.appendChild(strong);
            [['ثبت‌شده', s.created, 'a'], ['حل‌شده', s.resolved, 'b']].forEach(function (r) {
                var line = document.createElement('span');
                line.className = 'tkm-rp-tipline';
                var key = document.createElement('i'); key.className = 'tkm-rp-key tkm-rp-key--' + r[2];
                var val = document.createElement('b'); val.textContent = fa(r[1]);
                line.appendChild(key); line.appendChild(val); line.appendChild(document.createTextNode(' ' + r[0]));
                tip.appendChild(line);
            });
            var figBox = fig.getBoundingClientRect();
            tip.style.left = (px / W * box.width + box.left - figBox.left) + 'px';
            tip.style.top = '24px';
            tip.hidden = false;
        }
        hit.addEventListener('pointermove', function (e) { show(e.clientX); });
        hit.addEventListener('pointerleave', function () { tip.hidden = true; cross.setAttribute('hidden', ''); });
    }

    /* horizontal bars — single hue */
    function bars(rows) {
        if (!rows.length || !rows.some(function (r) { return r.value; })) return '<p class="tkm-ib-none">در این بازه داده‌ای نیست.</p>';
        var max = Math.max.apply(null, rows.map(function (r) { return r.value; })) || 1;
        return '<div class="tkm-hbars">' + rows.map(function (r) {
            return '<div class="tkm-hbar" title="' + esc(r.label) + ': ' + fa(r.value) + '"><span class="tkm-hbar__label">' + esc(r.label) + '</span><span class="tkm-hbar__value">' + num(r.value) + (r.extra ? ' <small>(' + esc(r.extra) + ')</small>' : '') + '</span>' +
                '<span class="tkm-hbar__track"><span class="tkm-hbar__fill tkm-rp-fill" style="width:' + (r.value / max * 100).toFixed(1) + '%"></span></span></div>';
        }).join('') + '</div>';
    }

    function csat(rows) {
        var total = rows.reduce(function (a, r) { return a + r.count; }, 0);
        if (!total) return '<p class="tkm-ib-none">هنوز امتیازی در این بازه ثبت نشده است.</p>';
        var avg = rows.reduce(function (a, r) { return a + r.count * r.rating; }, 0) / total;
        var happy = rows.filter(function (r) { return r.rating >= 4; }).reduce(function (a, r) { return a + r.count; }, 0);
        return '<div class="tkm-rp-csat"><b>' + fa(avg.toFixed(1)) + '</b><span>از ۵ · ' + fa(Math.round(happy / total * 100)) + '٪ راضی (۴ و ۵ ستاره)</span></div>' +
            bars(rows.map(function (r) { return { label: fa(r.rating) + ' ستاره', value: r.count }; }));
    }

    function agents(rows) {
        if (!rows.length) return '<p class="tkm-ib-none">کارشناسی تعریف نشده است. کاربران را در «تیم و دپارتمان‌ها» به دپارتمان‌ها اضافه کنید.</p>';
        return '<div class="tkm-table-wrap"><table class="tkm-rp-table"><thead><tr><th>کارشناس</th><th>سپرده‌شده</th><th>حل‌شده</th><th>پاسخ‌ها</th><th>باز فعلی</th><th>اولین پاسخ</th><th>رضایت</th></tr></thead><tbody>' +
            rows.map(function (r) {
                return '<tr><td><span class="tkm-rp-agent">' + K.avatar(r.user, 'sm') + esc(r.user ? r.user.name : '—') + '</span></td>' +
                    '<td>' + num(r.assigned) + '</td><td><b>' + num(r.resolved) + '</b></td><td>' + num(r.replies) + '</td><td>' + num(r.open) + '</td>' +
                    '<td>' + dur(r.first_response) + '</td><td>' + (r.csat == null ? '—' : fa(r.csat) + ' ★') + '</td></tr>';
            }).join('') + '</tbody></table></div>';
    }

    /* 7×24 heatmap — sequential blue ramp */
    var RAMP = ['var(--tkm-rp-seq-0)', '#cde2fb', '#9ec5f4', '#6da7ec', '#3987e5', '#256abf', '#184f95'];
    function heatmap(grid) {
        var max = 0;
        grid.forEach(function (row) { row.forEach(function (v) { if (v > max) max = v; }); });
        if (!max) return '<p class="tkm-ib-none">در این بازه تیکتی ثبت نشده است.</p>';
        var level = function (v) { return v === 0 ? 0 : Math.min(6, 1 + Math.floor((v / max) * 5.999)); };
        var head = '<div class="tkm-heat__row tkm-heat__row--head"><span></span>' + Array.apply(null, Array(24)).map(function (_, h) {
            return '<span>' + (h % 3 === 0 ? fa(h) : '') + '</span>';
        }).join('') + '</div>';
        var body = grid.map(function (row, d) {
            return '<div class="tkm-heat__row"><span class="tkm-heat__day">' + DAYS[d] + '</span>' + row.map(function (v, h) {
                return '<span class="tkm-heat__cell" tabindex="0" style="--c:' + RAMP[level(v)] + '" data-heat="' + DAYS[d] + ' · ساعت ' + fa(h) + ' تا ' + fa(h + 1) + '|' + v + '" aria-label="' + DAYS[d] + ' ساعت ' + fa(h) + ': ' + fa(v) + ' تیکت"></span>';
            }).join('') + '</div>';
        }).join('');
        var legend = '<div class="tkm-heat__legend"><span>کم</span>' + RAMP.slice(1).map(function (c) { return '<i style="--c:' + c + '"></i>'; }).join('') + '<span>زیاد (' + fa(max) + ')</span></div>';
        return '<div class="tkm-heat" data-heatmap>' + head + body + '<div class="tkm-chart__tooltip" data-heat-tip hidden></div></div>' + legend;
    }

    function bindHeat() {
        var box = root.querySelector('[data-heatmap]');
        if (!box) return;
        var tip = box.querySelector('[data-heat-tip]');
        function show(cell) {
            var parts = cell.getAttribute('data-heat').split('|');
            tip.textContent = '';
            var s = document.createElement('strong'); s.textContent = fa(parts[1]) + ' تیکت';
            tip.appendChild(s); tip.appendChild(document.createTextNode(parts[0]));
            var a = cell.getBoundingClientRect(), b = box.getBoundingClientRect();
            tip.style.left = (a.left - b.left + a.width / 2) + 'px';
            tip.style.top = (a.top - b.top) + 'px';
            tip.hidden = false;
        }
        box.addEventListener('pointerover', function (e) { var c = e.target.closest('[data-heat]'); if (c) show(c); });
        box.addEventListener('focusin', function (e) { var c = e.target.closest('[data-heat]'); if (c) show(c); });
        box.addEventListener('pointerleave', function () { tip.hidden = true; });
        box.addEventListener('focusout', function () { tip.hidden = true; });
    }

    /* ───────── Events ───────── */

    document.querySelectorAll('[data-days]').forEach(function (b) {
        b.addEventListener('click', function () {
            var v = b.getAttribute('data-days');
            preset(v === 'month' ? 'month' : +v);
        });
    });
    ['[data-from]', '[data-to]'].forEach(function (sel) {
        document.querySelector(sel).addEventListener('change', function () {
            var f = document.querySelector('[data-from]').value, t = document.querySelector('[data-to]').value;
            if (!f || !t) return;
            state.from = f; state.to = t; state.days = 'custom';
            syncControls();
            load();
        });
    });

    preset(30);
})();
