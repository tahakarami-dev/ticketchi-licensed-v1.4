/* Ticketchi — automation rules builder. */
(function () {
    'use strict';

    var K = window.TKM;
    var root = document.getElementById('tkm-automations');
    if (!K || !root) return;

    var fa = K.fa, esc = K.esc, icon = K.icon;
    var list = root.querySelector('[data-rules]');
    var drawer = document.querySelector('[data-editor]');
    var form = drawer.querySelector('[data-rule-form]');
    var body = drawer.querySelector('[data-editor-body]');

    var meta = null;
    var rules = [];
    var editing = null; // rule being edited (null = new)

    var TRIGGERS = {
        ticket_created: { label: 'تیکت جدید ثبت شد', icon: 'ticket' },
        customer_replied: { label: 'مشتری پاسخ داد', icon: 'mail' },
        staff_replied: { label: 'پشتیبانی پاسخ داد', icon: 'send' },
        status_changed: { label: 'وضعیت تیکت تغییر کرد', icon: 'refresh' },
        waiting_staff: { label: 'تیکت منتظر پاسخ پشتیبانی ماند', icon: 'clock', timed: true },
        waiting_customer: { label: 'تیکت منتظر پاسخ مشتری ماند', icon: 'clock', timed: true }
    };
    var CONDITIONS = {
        department: 'دپارتمان یکی از',
        priority: 'اولویت یکی از',
        status: 'وضعیت یکی از',
        tag: 'برچسب دارد',
        keyword: 'عنوان یا متن شامل',
        assignee: 'مسئول',
        has_order: 'مشتری خرید دارد',
        hours: 'مدت انتظار (ساعت)'
    };
    var ACTIONS = {
        assign: 'سپردن به',
        priority: 'تغییر اولویت به',
        status: 'تغییر وضعیت به',
        tag: 'افزودن برچسب',
        note: 'افزودن یادداشت داخلی',
        reply: 'ارسال پاسخ خودکار به مشتری',
        notify: 'اطلاع‌رسانی به کارشناس'
    };

    var TEMPLATES = [
        {
            name: 'تقسیم خودکار تیکت‌های جدید', desc: 'هر تیکت جدید به‌نوبت به کارشناسان همان دپارتمان سپرده می‌شود.', icon: 'user_check',
            rule: { trigger: 'ticket_created', conditions: [{ field: 'assignee', value: 'none' }], actions: [{ type: 'assign', value: 'round_robin' }] }
        },
        {
            name: 'تیکت‌های فوری', desc: 'اگر در متن «فوری» یا «اورژانسی» آمد، اولویت زیاد و برچسب «فوری» بگیرد.', icon: 'alert',
            rule: { trigger: 'ticket_created', conditions: [{ field: 'keyword', value: 'فوری، اورژانسی، urgent' }], actions: [{ type: 'priority', value: 'high' }, { type: 'tag', value: 'فوری' }] }
        },
        {
            name: 'بستن تیکت‌های رهاشده', desc: 'اگر مشتری ۷۲ ساعت پاسخ نداد، پیام یادآوری برود و تیکت بسته شود.', icon: 'clock',
            rule: { trigger: 'waiting_customer', conditions: [{ field: 'hours', value: 72 }], actions: [{ type: 'reply', value: '{customer} عزیز، چون پاسخی از شما دریافت نکردیم این تیکت بسته شد. هر زمان نیاز داشتید کافی است دوباره پیام بدهید.' }, { type: 'status', value: 'closed' }] }
        },
        {
            name: 'هشدار تیکت بی‌پاسخ', desc: 'اگر تیکتی ۴ ساعت بی‌پاسخ ماند، اولویتش بالا برود و یادداشت داخلی ثبت شود.', icon: 'bolt',
            rule: { trigger: 'waiting_staff', conditions: [{ field: 'hours', value: 4 }], actions: [{ type: 'priority', value: 'high' }, { type: 'note', value: 'این تیکت بیش از ۴ ساعت بی‌پاسخ مانده است.' }] }
        }
    ];

    /* ───────── Data ───────── */

    function load() {
        return Promise.all([K.api('meta'), K.api('automations')]).then(function (r) {
            meta = r[0];
            rules = r[1];
            render();
            root.setAttribute('data-state', 'ready');
        }).catch(function (e) {
            list.innerHTML = '<div class="tkm-card tkm-ib-empty">' + icon('alert', 22) + '<strong>بارگذاری نشد</strong><span>' + esc(e.message) + '</span></div>';
        });
    }

    function agentName(id) {
        var a = meta.agents.filter(function (x) { return x.id === +id; })[0];
        return a ? a.name : 'کاربر #' + fa(id);
    }
    function depName(id) {
        var n = 'دپارتمان #' + fa(id);
        meta.departments.forEach(function (d) {
            if (d.id === +id) n = d.name;
            d.children.forEach(function (c) { if (c.id === +id) n = c.name; });
        });
        return n;
    }
    function tagName(id) {
        var t = meta.tags.filter(function (x) { return x.id === +id; })[0];
        return t ? t.name : 'برچسب #' + fa(id);
    }
    function statusName(slug) {
        var s = meta.statuses.filter(function (x) { return x.slug === slug; })[0];
        return s ? s.name : slug;
    }

    /* ───────── Rule cards ───────── */

    function describeCondition(c) {
        var v = c.value;
        switch (c.field) {
            case 'department': return CONDITIONS.department + ' ' + [].concat(v).map(depName).join('، ');
            case 'priority': return CONDITIONS.priority + ' ' + [].concat(v).map(function (p) { return K.priorityLabel[p]; }).join('، ');
            case 'status': return CONDITIONS.status + ' ' + [].concat(v).map(statusName).join('، ');
            case 'tag': return CONDITIONS.tag + ' ' + [].concat(v).map(tagName).join('، ');
            case 'keyword': return CONDITIONS.keyword + ' «' + v + '»';
            case 'assignee': return v === 'none' ? 'تیکت مسئول ندارد' : 'تیکت مسئول دارد';
            case 'has_order': return v === 'yes' ? 'مشتری قبلاً خرید کرده' : 'مشتری خریدی ندارد';
            case 'hours': return 'بیش از ' + fa(v) + ' ساعت';
        }
        return '';
    }
    function describeAction(a) {
        var v = a.value;
        switch (a.type) {
            case 'assign': return 'سپردن به ' + (v === 'round_robin' ? 'کارشناسان به‌نوبت' : v === 'least_loaded' ? 'کم‌کارترین کارشناس' : agentName(v));
            case 'priority': return 'اولویت ' + K.priorityLabel[v];
            case 'status': return 'وضعیت «' + statusName(v) + '»';
            case 'tag': return 'برچسب «' + tagName(v) + '»';
            case 'note': return 'یادداشت داخلی';
            case 'reply': return 'پاسخ خودکار';
            case 'notify': return 'اطلاع به ' + agentName(v);
        }
        return '';
    }

    function render() {
        if (!rules.length) {
            list.innerHTML = '<div class="tkm-card tkm-auto__empty">' +
                '<div class="tkm-empty__art">' + icon('bolt', 28) + '</div>' +
                '<h3>اولین قانون خودکار را بسازید</h3><p>از یکی از الگوهای آماده شروع کنید یا قانون خودتان را از صفر بنویسید.</p>' +
                templatesHtml() + '</div>';
            return;
        }
        list.innerHTML = rules.map(function (r) {
            var t = TRIGGERS[r.trigger] || { label: r.trigger, icon: 'bolt' };
            return '<article class="tkm-card tkm-rule' + (r.is_active ? '' : ' is-off') + '" data-rule="' + r.id + '">' +
                '<div class="tkm-rule__icon">' + icon(t.icon, 18) + '</div>' +
                '<div class="tkm-rule__main">' +
                    '<div class="tkm-rule__top"><h3>' + esc(r.name) + '</h3>' +
                        '<span class="tkm-rule__runs tkm-num" title="تعداد اجرا">' + icon('bolt', 12) + fa(r.runs) + ' اجرا</span></div>' +
                    '<div class="tkm-rule__flow">' +
                        '<span class="tkm-rule__step tkm-rule__step--when"><b>وقتی</b>' + esc(t.label) + '</span>' +
                        (r.conditions.length ? '<span class="tkm-rule__arrow">' + icon('back', 14) + '</span><span class="tkm-rule__step"><b>اگر</b>' + r.conditions.map(function (c) { return esc(describeCondition(c)); }).join(' <i>و</i> ') + '</span>' : '') +
                        '<span class="tkm-rule__arrow">' + icon('back', 14) + '</span><span class="tkm-rule__step tkm-rule__step--then"><b>آنگاه</b>' + r.actions.map(function (a) { return esc(describeAction(a)); }).join('، ') + '</span>' +
                    '</div>' +
                '</div>' +
                '<div class="tkm-rule__tools">' +
                    '<label class="tkm-switch" title="فعال / غیرفعال"><input type="checkbox" data-toggle="' + r.id + '"' + (r.is_active ? ' checked' : '') + ' aria-label="فعال بودن قانون ' + esc(r.name) + '"><span class="tkm-switch__track"></span></label>' +
                    '<button type="button" class="tkm-btn tkm-btn--ghost tkm-btn--icon tkm-btn--sm" data-edit="' + r.id + '" aria-label="ویرایش">' + icon('note', 16) + '</button>' +
                    '<button type="button" class="tkm-btn tkm-btn--ghost tkm-btn--icon tkm-btn--sm" data-delete="' + r.id + '" aria-label="حذف">' + icon('trash', 16) + '</button>' +
                '</div></article>';
        }).join('') + '<div class="tkm-auto__more"><h4>الگوهای آماده</h4>' + templatesHtml() + '</div>';
    }

    function templatesHtml() {
        return '<div class="tkm-templates">' + TEMPLATES.map(function (t, i) {
            return '<button type="button" class="tkm-template" data-template="' + i + '"><span class="tkm-template__icon">' + icon(t.icon, 16) + '</span><strong>' + esc(t.name) + '</strong><span>' + esc(t.desc) + '</span></button>';
        }).join('') + '</div>';
    }

    /* ───────── Editor ───────── */

    function open(rule) {
        editing = rule && rule.id ? rule : null;
        rule = rule || { name: '', trigger: 'ticket_created', conditions: [], actions: [{ type: 'assign', value: 'round_robin' }], is_active: true };
        drawer.querySelector('[data-editor-title]').textContent = editing ? 'ویرایش قانون' : 'قانون جدید';
        form.elements.is_active.checked = rule.is_active !== false && rule.is_active !== 0;

        body.innerHTML =
            '<div class="tkm-field"><label class="tkm-label" for="tkm-rule-name">نام قانون <span class="tkm-req">*</span></label>' +
                '<input id="tkm-rule-name" class="tkm-input" name="name" required maxlength="128" value="' + esc(rule.name) + '" placeholder="مثلاً: تقسیم تیکت‌های فروش"></div>' +
            '<section class="tkm-builder"><div class="tkm-builder__label"><span class="tkm-builder__badge">وقتی</span>این اتفاق افتاد</div>' +
                '<select class="tkm-select" name="trigger">' + Object.keys(TRIGGERS).map(function (k) {
                    return '<option value="' + k + '"' + (k === rule.trigger ? ' selected' : '') + '>' + TRIGGERS[k].label + '</option>';
                }).join('') + '</select><p class="tkm-hint" data-trigger-hint></p></section>' +
            '<section class="tkm-builder"><div class="tkm-builder__label"><span class="tkm-builder__badge tkm-builder__badge--if">اگر</span>همه این شرط‌ها برقرار بود <small>(اختیاری)</small></div>' +
                '<div class="tkm-builder__rows" data-conditions></div>' +
                '<button type="button" class="tkm-btn tkm-btn--ghost tkm-btn--sm" data-add-condition>' + icon('plus', 14) + 'افزودن شرط</button></section>' +
            '<section class="tkm-builder"><div class="tkm-builder__label"><span class="tkm-builder__badge tkm-builder__badge--then">آنگاه</span>این کارها انجام شود</div>' +
                '<div class="tkm-builder__rows" data-actions></div>' +
                '<button type="button" class="tkm-btn tkm-btn--ghost tkm-btn--sm" data-add-action>' + icon('plus', 14) + 'افزودن کار</button>' +
                '<p class="tkm-hint">در متن پاسخ خودکار می‌توانید از {customer}، {ticket_id}، {title} و {site} استفاده کنید.</p></section>';

        rule.conditions.forEach(function (c) { addCondition(c); });
        rule.actions.forEach(function (a) { addAction(a); });
        syncTrigger();
        drawer.hidden = false;
        document.documentElement.classList.add('tkm-lock');
        setTimeout(function () { form.elements.name.focus(); }, 30);
    }

    function close() {
        drawer.hidden = true;
        document.documentElement.classList.remove('tkm-lock');
    }

    function syncTrigger() {
        var t = TRIGGERS[form.elements.trigger.value];
        var hint = body.querySelector('[data-trigger-hint]');
        hint.textContent = t.timed ? 'هر ساعت بررسی می‌شود و برای هر تیکت فقط یک‌بار اجرا می‌شود. مدت انتظار را در شرط «مدت انتظار» تعیین کنید (پیش‌فرض ۲۴ ساعت).' : 'بلافاصله پس از رویداد اجرا می‌شود.';
        if (t.timed && !body.querySelector('[data-cond-field] option:checked[value="hours"]')) {
            var has = Array.prototype.some.call(body.querySelectorAll('[data-cond-field]'), function (s) { return s.value === 'hours'; });
            if (!has) addCondition({ field: 'hours', value: 24 });
        }
    }

    function multi(name, options, selected) {
        selected = [].concat(selected || []).map(String);
        return '<div class="tkm-checks" data-value="' + name + '">' + options.map(function (o) {
            return '<label class="tkm-check"><input type="checkbox" value="' + esc(o[0]) + '"' + (selected.indexOf(String(o[0])) !== -1 ? ' checked' : '') + '><span>' + esc(o[1]) + '</span></label>';
        }).join('') + '</div>';
    }
    function select(options, value) {
        return '<select class="tkm-select tkm-select--sm" data-value>' + options.map(function (o) {
            return '<option value="' + esc(o[0]) + '"' + (String(o[0]) === String(value) ? ' selected' : '') + '>' + esc(o[1]) + '</option>';
        }).join('') + '</select>';
    }
    function depOptions() {
        var out = [];
        meta.departments.forEach(function (d) {
            out.push([d.id, d.name]);
            d.children.forEach(function (c) { out.push([c.id, '— ' + c.name]); });
        });
        return out;
    }
    function priorities() { return [['low', 'کم'], ['medium', 'متوسط'], ['high', 'زیاد']]; }
    function statuses() { return meta.statuses.map(function (s) { return [s.slug, s.name]; }); }
    function agents() { return meta.agents.map(function (a) { return [a.id, a.name]; }); }

    function conditionControl(field, value) {
        switch (field) {
            case 'department': return meta.departments.length ? multi('department', depOptions(), value) : '<span class="tkm-hint">دپارتمانی تعریف نشده است.</span>';
            case 'priority': return multi('priority', priorities(), value);
            case 'status': return multi('status', statuses(), value);
            case 'tag': return meta.tags.length ? multi('tag', meta.tags.map(function (t) { return [t.id, t.name]; }), value) : '<span class="tkm-hint">هنوز برچسبی ساخته نشده است.</span>';
            case 'keyword': return '<input class="tkm-input tkm-input--sm" data-value value="' + esc(value || '') + '" placeholder="کلمات را با ، جدا کنید">';
            case 'assignee': return select([['none', 'ندارد'], ['any', 'دارد']], value || 'none');
            case 'has_order': return select([['yes', 'بله'], ['no', 'خیر']], value || 'yes');
            case 'hours': return '<input class="tkm-input tkm-input--sm tkm-input--short" type="number" min="1" max="720" data-value value="' + esc(value || 24) + '">';
        }
        return '';
    }

    function actionControl(type, value) {
        switch (type) {
            case 'assign': return select([['round_robin', 'کارشناسان دپارتمان به‌نوبت'], ['least_loaded', 'کم‌کارترین کارشناس']].concat(agents()), value || 'round_robin');
            case 'priority': return select(priorities(), value || 'high');
            case 'status': return select(statuses(), value || 'closed');
            case 'tag': {
                var name = value && !isNaN(value) ? tagName(value) : (value || '');
                return '<input class="tkm-input tkm-input--sm" data-value list="tkm-auto-tags" value="' + esc(name) + '" placeholder="نام برچسب (موجود یا جدید)">' +
                    '<datalist id="tkm-auto-tags">' + meta.tags.map(function (t) { return '<option value="' + esc(t.name) + '">'; }).join('') + '</datalist>';
            }
            case 'note':
            case 'reply': return '<textarea class="tkm-textarea" rows="3" data-value placeholder="' + (type === 'reply' ? 'سلام {customer} عزیز…' : 'یادداشت برای تیم') + '">' + esc(value || '') + '</textarea>';
            case 'notify': return select(agents(), value);
        }
        return '';
    }

    function rowHtml(kind, map, key, control) {
        return '<div class="tkm-builder__row" data-row="' + kind + '">' +
            '<select class="tkm-select tkm-select--sm" data-' + (kind === 'condition' ? 'cond-field' : 'action-type') + '>' + Object.keys(map).map(function (k) {
                return '<option value="' + k + '"' + (k === key ? ' selected' : '') + '>' + map[k] + '</option>';
            }).join('') + '</select>' +
            '<div class="tkm-builder__control">' + control + '</div>' +
            '<button type="button" class="tkm-btn tkm-btn--ghost tkm-btn--icon tkm-btn--sm" data-remove-row aria-label="حذف">' + icon('x', 16) + '</button></div>';
    }
    function addCondition(c) {
        c = c || { field: 'priority', value: [] };
        body.querySelector('[data-conditions]').insertAdjacentHTML('beforeend', rowHtml('condition', CONDITIONS, c.field, conditionControl(c.field, c.value)));
    }
    function addAction(a) {
        a = a || { type: 'tag', value: '' };
        body.querySelector('[data-actions]').insertAdjacentHTML('beforeend', rowHtml('action', ACTIONS, a.type, actionControl(a.type, a.value)));
    }

    function readValue(row) {
        var checks = row.querySelector('[data-value].tkm-checks, .tkm-checks');
        if (checks) {
            return Array.prototype.filter.call(checks.querySelectorAll('input'), function (i) { return i.checked; }).map(function (i) { return i.value; });
        }
        var el = row.querySelector('[data-value]');
        return el ? el.value : '';
    }

    function collect() {
        var data = {
            name: form.elements.name.value.trim(),
            trigger: form.elements.trigger.value,
            is_active: form.elements.is_active.checked,
            conditions: [],
            actions: []
        };
        body.querySelectorAll('[data-row="condition"]').forEach(function (row) {
            data.conditions.push({ field: row.querySelector('[data-cond-field]').value, value: readValue(row) });
        });
        var pendingTags = [];
        body.querySelectorAll('[data-row="action"]').forEach(function (row) {
            var type = row.querySelector('[data-action-type]').value;
            var value = readValue(row);
            if (type === 'tag') {
                var existing = meta.tags.filter(function (t) { return t.name === value.trim(); })[0];
                var a = { type: 'tag', value: existing ? existing.id : value.trim() };
                if (!existing && value.trim()) pendingTags.push(a);
                data.actions.push(a);
                return;
            }
            data.actions.push({ type: type, value: value });
        });
        return { data: data, pendingTags: pendingTags };
    }

    function save(e) {
        e.preventDefault();
        var c = collect();
        if (!c.data.name) { K.toast('error', 'نام قانون را وارد کنید'); form.elements.name.focus(); return; }
        if (!c.data.actions.length) { K.toast('error', 'حداقل یک کار تعریف کنید'); return; }

        var btn = form.querySelector('[type="submit"]');
        btn.disabled = true; btn.classList.add('is-loading');

        // New tag names are created first so the rule stores tag ids.
        Promise.all(c.pendingTags.map(function (a) {
            return K.api('tags', { method: 'POST', body: { name: a.value } }).then(function (res) { a.value = res.id; meta.tags = res.tags; });
        })).then(function () {
            return K.api(editing ? 'automations/' + editing.id : 'automations', { method: editing ? 'PUT' : 'POST', body: c.data });
        }).then(function (res) {
            rules = res;
            render();
            close();
            K.toast('success', editing ? 'قانون بروزرسانی شد' : 'قانون ساخته شد');
        }).catch(function (err) {
            K.toast('error', 'ذخیره نشد', err.message);
        }).then(function () { btn.disabled = false; btn.classList.remove('is-loading'); });
    }

    /* ───────── Events ───────── */

    document.addEventListener('click', function (e) {
        var t = e.target, el;
        if (t.closest('[data-new-rule]')) { open(null); return; }
        if ((el = t.closest('[data-template]'))) {
            var tpl = TEMPLATES[+el.getAttribute('data-template')];
            open(JSON.parse(JSON.stringify(Object.assign({ name: tpl.name, is_active: true }, tpl.rule))));
            return;
        }
        if ((el = t.closest('[data-edit]'))) {
            open(JSON.parse(JSON.stringify(rules.filter(function (r) { return r.id === +el.getAttribute('data-edit'); })[0])));
            return;
        }
        if ((el = t.closest('[data-delete]'))) {
            var id = +el.getAttribute('data-delete');
            K.confirm('این قانون حذف می‌شود و دیگر اجرا نخواهد شد.').then(function () {
                K.api('automations/' + id, { method: 'DELETE' }).then(function (res) { rules = res; render(); K.toast('success', 'قانون حذف شد'); })
                    .catch(function (err) { K.toast('error', 'حذف نشد', err.message); });
            }, function () {});
            return;
        }
        if (!drawer.contains(t)) return;
        if (t.closest('[data-editor-close]')) { close(); return; }
        if (t.closest('[data-add-condition]')) { addCondition(); return; }
        if (t.closest('[data-add-action]')) { addAction(); return; }
        if ((el = t.closest('[data-remove-row]'))) { el.closest('[data-row]').remove(); }
    });

    document.addEventListener('change', function (e) {
        var t = e.target;
        if (t.matches('[data-toggle]')) {
            var rule = rules.filter(function (r) { return r.id === +t.getAttribute('data-toggle'); })[0];
            var payload = Object.assign({}, rule, { is_active: t.checked });
            K.api('automations/' + rule.id, { method: 'PUT', body: payload }).then(function (res) {
                rules = res; render();
                K.toast('success', t.checked ? 'قانون فعال شد' : 'قانون غیرفعال شد');
            }).catch(function (err) { t.checked = !t.checked; K.toast('error', 'تغییر ذخیره نشد', err.message); });
            return;
        }
        if (!drawer.contains(t)) return;
        if (t.name === 'trigger') { syncTrigger(); return; }
        if (t.matches('[data-cond-field]')) {
            t.closest('[data-row]').querySelector('.tkm-builder__control').innerHTML = conditionControl(t.value);
            return;
        }
        if (t.matches('[data-action-type]')) {
            t.closest('[data-row]').querySelector('.tkm-builder__control').innerHTML = actionControl(t.value);
        }
    });

    form.addEventListener('submit', save);
    document.addEventListener('keydown', function (e) { if (e.key === 'Escape' && !drawer.hidden) close(); });

    load();
})();
