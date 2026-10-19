/* Ticketchi — settings app (schema-driven, saves through /settings). */
(function () {
    'use strict';

    var K = window.TKM;
    var root = document.getElementById('tkm-settings-app');
    if (!K || !root) return;

    var esc = K.esc, fa = K.fa;
    var form = root.querySelector('[data-settings-form]');
    var bar = document.querySelector('[data-savebar]');
    var search = document.querySelector('[data-settings-search]');

    var schema = [];       // sections
    var fields = {};       // id → field
    var saved = {};        // last saved values
    var values = {};       // working copy
    var current = '';      // active section id
    var DAYS = [['6', 'شنبه'], ['0', 'یکشنبه'], ['1', 'دوشنبه'], ['2', 'سه‌شنبه'], ['3', 'چهارشنبه'], ['4', 'پنجشنبه'], ['5', 'جمعه']];

    function clone(v) { return JSON.parse(JSON.stringify(v)); }
    function same(a, b) { return JSON.stringify(a) === JSON.stringify(b); }

    /* ───────── Load ───────── */

    K.api('settings').then(function (data) {
        schema = data.sections;
        schema.forEach(function (s) {
            s.groups.forEach(function (g) {
                g.fields.forEach(function (f) { if (f.id) fields[f.id] = f; });
            });
        });
        saved = data.values;
        values = clone(saved);
        root.setAttribute('data-state', 'ready');
        route();
    }).catch(function (e) {
        form.innerHTML = '<div class="tkm-card tkm-ib-empty">' + K.icon('alert', 22) + '<strong>تنظیمات بارگذاری نشد</strong><span>' + esc(e.message) + '</span></div>';
    });

    /* ───────── Render ───────── */

    function route() {
        var id = (location.hash || '').replace(/^#\/?/, '');
        if (!schema.some(function (s) { return s.id === id; })) id = schema[0].id;
        current = id;
        document.querySelectorAll('[data-nav]').forEach(function (a) {
            var on = a.getAttribute('data-nav') === id;
            a.classList.toggle('is-active', on);
            if (on) a.setAttribute('aria-current', 'page'); else a.removeAttribute('aria-current');
        });
        if (search.value.trim()) { renderSearch(search.value.trim()); return; }
        renderSection(schema.filter(function (s) { return s.id === id; })[0]);
    }

    function renderSection(section) {
        form.innerHTML = '<header class="tkm-st-head"><h2>' + esc(section.title) + '</h2><p>' + esc(section.desc || '') + '</p></header>' +
            (section.preview === 'brand' ? brandPreview() : '') +
            section.groups.map(function (g, gi) { return groupHtml(g, section.id + '-' + gi); }).join('');
        sync();
        form.scrollTop = 0;
    }

    function renderSearch(q) {
        var needle = q.toLowerCase();
        var html = '';
        schema.forEach(function (s) {
            s.groups.forEach(function (g, gi) {
                var hits = g.fields.filter(function (f) {
                    var hay = [f.label, f.desc, g.title, s.title, f.item_label].join(' ').toLowerCase();
                    return f.id && hay.indexOf(needle) !== -1;
                });
                if (hits.length) {
                    html += groupHtml({ title: s.title + (g.title ? ' › ' + g.title : ''), fields: hits, show_if: g.show_if }, s.id + '-' + gi);
                }
            });
        });
        form.innerHTML = '<header class="tkm-st-head"><h2>نتایج «' + esc(q) + '»</h2><p>' + (html ? 'تنظیمات مرتبط از همه بخش‌ها' : '') + '</p></header>' +
            (html || '<div class="tkm-card tkm-ib-empty">' + K.icon('inbox', 24) + '<strong>تنظیمی پیدا نشد</strong><span>عبارت دیگری را امتحان کنید.</span></div>');
        sync();
    }

    function groupHtml(group, key) {
        var cols = group.columns || 1;
        return '<section class="tkm-card tkm-st-group" data-group="' + key + '"' + showIfAttr(group.show_if) + '>' +
            (group.title ? '<header class="tkm-st-group__head"><h3>' + esc(group.title) + '</h3></header>' : '') +
            '<div class="tkm-st-group__body' + (cols > 1 ? ' tkm-st-cols tkm-st-cols--' + cols : '') + '">' +
            group.fields.map(function (f) { return fieldHtml(f, cols > 1); }).join('') + '</div></section>';
    }

    function showIfAttr(cond) {
        return cond ? ' data-show-if="' + esc(cond[0]) + '" data-show-value="' + esc(cond[1]) + '"' : '';
    }

    function fieldHtml(f, compact) {
        if (f.type === 'note') return '<div class="tkm-st-note">' + f.html + '</div>';
        if (f.type === 'repeater') return '<div class="tkm-st-field tkm-st-field--block" data-field="' + f.id + '"' + showIfAttr(f.show_if) + '>' + repeaterHtml(f) + '</div>';

        var inline = f.type === 'switch';
        var control = controlHtml(f, values[f.id], 'f-' + f.id, f.id);
        if (inline) {
            return '<div class="tkm-st-field tkm-st-field--switch" data-field="' + f.id + '"' + showIfAttr(f.show_if) + '>' +
                '<label class="tkm-st-switchrow" for="f-' + f.id + '"><span class="tkm-st-label">' + esc(f.label) + (f.desc ? '<small>' + f.desc + '</small>' : '') + '</span>' + control + '</label></div>';
        }
        return '<div class="tkm-st-field' + (compact ? ' tkm-st-field--stack' : '') + '" data-field="' + f.id + '"' + showIfAttr(f.show_if) + '>' +
            '<label class="tkm-st-label" for="f-' + f.id + '">' + esc(f.label) + (f.desc ? '<small>' + f.desc + '</small>' : '') + '</label>' +
            '<div class="tkm-st-control">' + control + '<p class="tkm-st-error" data-error="' + f.id + '" hidden></p></div></div>';
    }

    /** One control; `path` is the key (or "repeater.index.sub") that data-bind writes to. */
    function controlHtml(f, value, domId, path) {
        var bind = ' data-bind="' + esc(path) + '"';
        var ltr = f.ltr ? ' dir="ltr"' : '';
        var ph = f.placeholder ? ' placeholder="' + esc(f.placeholder) + '"' : '';
        value = value == null ? '' : value;
        switch (f.type) {
            case 'switch':
                return '<span class="tkm-switch"><input type="checkbox" id="' + domId + '"' + bind + (value === '1' ? ' checked' : '') + '><span class="tkm-switch__track"></span></span>';
            case 'textarea':
                return '<textarea class="tkm-textarea" id="' + domId + '" rows="3"' + bind + ph + '>' + esc(value) + '</textarea>';
            case 'number':
                return '<div class="tkm-st-num"><input type="text" inputmode="numeric" class="tkm-input" id="' + domId + '"' + bind + ' value="' + esc(fa(value)) + '"' + (f.min != null ? ' data-min="' + f.min + '"' : '') + (f.max != null ? ' data-max="' + f.max + '"' : '') + '>' + (f.unit ? '<span>' + esc(f.unit) + '</span>' : '') + '</div>';
            case 'color':
                var hex = /^#[0-9a-f]{6}$/i.test(value) ? value : (/^#[0-9a-f]{8}$/i.test(value) ? value.slice(0, 7) : (/^#[0-9a-f]{3}$/i.test(value) ? '#' + value.slice(1).split('').map(function (c) { return c + c; }).join('') : '#ffffff'));
                return '<div class="tkm-st-color' + (value ? '' : ' is-empty') + '"><input type="color" value="' + esc(hex) + '" data-color-for="' + esc(path) + '" aria-label="انتخاب رنگ ' + esc(f.label || '') + '">' +
                    '<input type="text" class="tkm-input" dir="ltr" id="' + domId + '"' + bind + ' value="' + esc(value) + '" placeholder="' + (f.default ? esc(f.default) : 'خودکار') + '" maxlength="9"></div>';
            case 'select':
                return '<select class="tkm-select" id="' + domId + '"' + bind + '>' + Object.keys(f.options).map(function (k) {
                    return '<option value="' + esc(k) + '"' + (String(k) === String(value) ? ' selected' : '') + '>' + esc(f.options[k]) + '</option>';
                }).join('') + '</select>';
            case 'combo':
                var listId = 'dl-' + domId;
                return '<input type="text" class="tkm-input" dir="ltr" id="' + domId + '"' + bind + ' value="' + esc(value) + '" list="' + listId + '">' +
                    '<datalist id="' + listId + '">' + (f.options || []).map(function (o) { return '<option value="' + esc(o) + '">'; }).join('') + '</datalist>';
            case 'days':
                var on = (value || []).map(String);
                return '<div class="tkm-st-days" role="group" aria-label="' + esc(f.label) + '" id="' + domId + '"' + bind + '>' + DAYS.map(function (d) {
                    return '<label class="tkm-check' + (on.indexOf(d[0]) !== -1 ? ' is-on' : '') + '"><input type="checkbox" value="' + d[0] + '"' + (on.indexOf(d[0]) !== -1 ? ' checked' : '') + '><span>' + d[1] + '</span></label>';
                }).join('') + '</div>';
            case 'time':
                return '<input type="time" class="tkm-input tkm-input--short" dir="ltr" id="' + domId + '"' + bind + ' value="' + esc(value) + '">';
            default:
                if (f.secret) {
                    return '<div class="tkm-st-secret"><input type="password" class="tkm-input" dir="ltr" id="' + domId + '"' + bind + ' value="' + esc(value) + '"' + ph + ' autocomplete="new-password">' +
                        '<button type="button" class="tkm-btn tkm-btn--ghost tkm-btn--icon tkm-btn--sm" data-reveal aria-label="نمایش رمز">' + '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M2 12s3-7 10-7 10 7 10 7-3 7-10 7-10-7-10-7Z"/><circle cx="12" cy="12" r="3"/></svg></button></div>';
                }
                return '<input type="text" class="tkm-input" id="' + domId + '"' + bind + ' value="' + esc(value) + '"' + ph + ltr + '>';
        }
    }

    /* ───────── Repeater ───────── */

    function repeaterHtml(f) {
        var rows = values[f.id] || [];
        var items = rows.map(function (row, i) { return repeaterItem(f, row, i); }).join('');
        return '<div class="tkm-rep" data-rep="' + f.id + '">' +
            '<div class="tkm-rep__list" data-rep-list>' + (items || '<div class="tkm-rep__empty">' + esc(f.empty || 'موردی وجود ندارد.') + '</div>') + '</div>' +
            '<button type="button" class="tkm-btn tkm-btn--soft tkm-btn--sm" data-rep-add="' + f.id + '">' + K.icon('plus', 15) + ' افزودن ' + esc(f.item_label || 'مورد') + '</button></div>';
    }

    function repeaterItem(f, row, i) {
        var title = row[f.title_field] || '';
        var swatch = row['color-status'] ? '<span class="tkm-dot" style="--c:' + esc(row['color-status']) + '"></span>' : '';
        var open = row.__open ? ' open' : '';
        return '<details class="tkm-rep__item" data-rep-item="' + i + '" draggable="true"' + open + '>' +
            '<summary><span class="tkm-rep__grip" aria-hidden="true">⋮⋮</span>' + swatch +
            '<span class="tkm-rep__title">' + (title ? esc(title) : '<em>' + esc(f.item_label || 'مورد') + ' بدون عنوان</em>') + '</span>' +
            '<span class="tkm-rep__tools">' +
                '<button type="button" class="tkm-btn tkm-btn--ghost tkm-btn--icon tkm-btn--sm" data-rep-move="-1" aria-label="بالا"' + (i === 0 ? ' disabled' : '') + '>↑</button>' +
                '<button type="button" class="tkm-btn tkm-btn--ghost tkm-btn--icon tkm-btn--sm" data-rep-move="1" aria-label="پایین"' + (i === (values[f.id] || []).length - 1 ? ' disabled' : '') + '>↓</button>' +
                '<button type="button" class="tkm-btn tkm-btn--ghost tkm-btn--icon tkm-btn--sm" data-rep-remove aria-label="حذف">' + K.icon('trash', 15) + '</button>' +
            '</span></summary>' +
            '<div class="tkm-rep__body">' + f.fields.map(function (sub) {
                var path = f.id + '.' + i + '.' + sub.id;
                var v = row[sub.id] != null ? row[sub.id] : (sub.default || '');
                return '<div class="tkm-st-field tkm-st-field--stack"><label class="tkm-st-label" for="r-' + f.id + '-' + i + '-' + sub.id + '">' + esc(sub.label) + (sub.desc ? '<small>' + esc(sub.desc) + '</small>' : '') + '</label>' +
                    '<div class="tkm-st-control">' + controlHtml(sub, v, 'r-' + f.id + '-' + i + '-' + sub.id, path) + '</div></div>';
            }).join('') + '</div></details>';
    }

    function rerenderRepeater(id) {
        var box = form.querySelector('[data-field="' + id + '"]');
        if (box) box.innerHTML = repeaterHtml(fields[id]);
        sync();
    }

    /* ───────── State ───────── */

    function setValue(path, value) {
        var parts = path.split('.');
        if (parts.length === 1) {
            values[path] = value;
        } else {
            var list = values[parts[0]];
            if (list && list[+parts[1]]) list[+parts[1]][parts[2]] = value;
        }
        sync();
    }

    function strip(v) {
        // UI-only keys (open state) never count as changes.
        return JSON.parse(JSON.stringify(v, function (k, val) { return k === '__open' ? undefined : val; }));
    }
    function dirtyIds() {
        return Object.keys(values).filter(function (k) { return !same(strip(values[k]), strip(saved[k])); });
    }

    function sync() {
        // conditional fields / groups
        form.querySelectorAll('[data-show-if]').forEach(function (el) {
            var v = values[el.getAttribute('data-show-if')];
            el.hidden = String(v) !== el.getAttribute('data-show-value');
        });
        var dirty = dirtyIds();
        bar.hidden = !dirty.length;
        document.body.classList.toggle('tkm-has-savebar', dirty.length > 0);
        bar.querySelector('[data-savebar-text]').textContent = dirty.length ? fa(dirty.length) + ' تغییر ذخیره‌نشده' : '';
        var dirtySections = {};
        dirty.forEach(function (id) {
            schema.forEach(function (s) {
                s.groups.forEach(function (g) { g.fields.forEach(function (f) { if (f.id === id) dirtySections[s.id] = true; }); });
            });
        });
        document.querySelectorAll('[data-nav]').forEach(function (a) { a.classList.toggle('is-dirty', !!dirtySections[a.getAttribute('data-nav')]); });
        updatePreview();
    }

    function updatePreview() {
        var p = form.querySelector('[data-brand-preview]');
        if (!p) return;
        var ok = function (c) { return /^#([0-9a-f]{3}|[0-9a-f]{6}|[0-9a-f]{8})$/i.test(c || ''); };
        p.style.setProperty('--tkm-primary', ok(values.primary_color) ? values.primary_color : '#2A48CD');
        p.style.setProperty('--tkm-on-primary', ok(values.text_color) ? values.text_color : '#ffffff');
        if (ok(values.secondary_color) && values.secondary_color.length === 7) p.style.setProperty('--tkm-primary-hover', values.secondary_color);
        else p.style.removeProperty('--tkm-primary-hover');
        p.querySelector('[data-pv-title]').textContent = values.title_dashboard || 'داشبورد تیکت‌ها';
        p.querySelector('[data-pv-desc]').textContent = values.description_dashboard || '';
    }

    function brandPreview() {
        return '<section class="tkm-card tkm-st-preview" data-brand-preview aria-label="پیش‌نمایش">' +
            '<div class="tkm-st-preview__label">پیش‌نمایش زنده پنل مشتری</div>' +
            '<div class="tkm-st-preview__app"><div><strong data-pv-title></strong><span data-pv-desc></span></div>' +
            '<div class="tkm-st-preview__row"><button type="button" class="tkm-btn tkm-btn--primary" tabindex="-1">' + K.icon('plus', 15) + ' ثبت تیکت جدید</button>' +
            '<button type="button" class="tkm-btn tkm-btn--soft" tabindex="-1">همه تیکت‌ها</button>' +
            '<a class="tkm-st-preview__link" tabindex="-1">مشاهده جزئیات</a>' +
            '<span class="tkm-st-preview__pill">۳ پاسخ جدید</span></div></div></section>';
    }

    /* ───────── Save ───────── */

    function save() {
        var dirty = dirtyIds();
        if (!dirty.length) return;
        var payload = {};
        dirty.forEach(function (id) { payload[id] = strip(values[id]); });
        var btn = bar.querySelector('[data-save]');
        btn.disabled = true; btn.classList.add('is-loading');
        form.querySelectorAll('.tkm-st-error').forEach(function (e) { e.hidden = true; });
        form.querySelectorAll('.has-error').forEach(function (e) { e.classList.remove('has-error'); });

        K.api('settings', { method: 'POST', body: { values: payload } }).then(function (res) {
            saved = res.values;
            // keep UI-only state (open repeater items) while adopting sanitized values
            Object.keys(saved).forEach(function (k) {
                if (Array.isArray(saved[k]) && Array.isArray(values[k]) && fields[k] && fields[k].type === 'repeater') {
                    values[k] = saved[k].map(function (row, i) { return Object.assign({}, row, values[k][i] && values[k][i].__open ? { __open: true } : {}); });
                } else {
                    values[k] = clone(saved[k]);
                }
            });
            route();
            K.toast('success', 'تنظیمات ذخیره شد');
        }).catch(function (e) {
            K.toast('error', 'ذخیره نشد', e.message);
            // highlight offending fields
            (e.fields || []).forEach(function (id) {
                var f = form.querySelector('[data-field="' + id + '"]');
                if (f) f.classList.add('has-error');
            });
        }).then(function () { btn.disabled = false; btn.classList.remove('is-loading'); });
    }

    /* ───────── Events ───────── */

    form.addEventListener('input', function (e) {
        var t = e.target;
        if (t.matches('[data-color-for]')) {
            var text = form.querySelector('[data-bind="' + t.getAttribute('data-color-for') + '"]');
            text.value = t.value;
            text.closest('.tkm-st-color').classList.remove('is-empty');
            setValue(t.getAttribute('data-color-for'), t.value);
            refreshRepeaterTitle(t);
            return;
        }
        if (!t.matches('[data-bind]') || t.type === 'checkbox') return;
        var path = t.getAttribute('data-bind');
        var f = fields[path];
        if (f && f.type === 'number') {
            setValue(path, K.en(t.value).replace(/[^\d]/g, ''));
            return;
        }
        setValue(path, t.value);
        if (t.closest('.tkm-st-color')) {
            var picker = t.closest('.tkm-st-color').querySelector('[data-color-for]');
            if (/^#[0-9a-f]{6}$/i.test(t.value)) picker.value = t.value;
            t.closest('.tkm-st-color').classList.toggle('is-empty', !t.value);
        }
        refreshRepeaterTitle(t);
    });

    function refreshRepeaterTitle(t) {
        var item = t.closest('[data-rep-item]');
        if (!item) return;
        var id = item.closest('[data-rep]').getAttribute('data-rep');
        var row = values[id][+item.getAttribute('data-rep-item')];
        var f = fields[id];
        item.querySelector('.tkm-rep__title').innerHTML = row[f.title_field] ? esc(row[f.title_field]) : '<em>' + esc(f.item_label) + ' بدون عنوان</em>';
        var dot = item.querySelector('summary .tkm-dot');
        if (dot && row['color-status']) dot.style.setProperty('--c', row['color-status']);
    }

    form.addEventListener('change', function (e) {
        var t = e.target;
        if (t.matches('input[type="checkbox"][data-bind]')) { setValue(t.getAttribute('data-bind'), t.checked ? '1' : '0'); return; }
        if (t.closest('.tkm-st-days')) {
            var group = t.closest('.tkm-st-days');
            var on = Array.prototype.filter.call(group.querySelectorAll('input'), function (i) { return i.checked; }).map(function (i) { return i.value; });
            t.closest('.tkm-check').classList.toggle('is-on', t.checked);
            setValue(group.getAttribute('data-bind'), on);
            return;
        }
        if (t.matches('select[data-bind]')) { setValue(t.getAttribute('data-bind'), t.value); return; }
        if (t.matches('[data-bind]') && fields[t.getAttribute('data-bind')] && fields[t.getAttribute('data-bind')].type === 'number') {
            var f = fields[t.getAttribute('data-bind')];
            var n = parseInt(K.en(t.value), 10);
            if (isNaN(n)) n = parseInt(f.default, 10) || 0;
            if (f.min != null) n = Math.max(f.min, n);
            if (f.max != null) n = Math.min(f.max, n);
            t.value = fa(n);
            setValue(t.getAttribute('data-bind'), String(n));
        }
    });

    form.addEventListener('click', function (e) {
        var t = e.target, el;
        if ((el = t.closest('[data-rep-add]'))) {
            var id = el.getAttribute('data-rep-add');
            var row = { __open: true };
            fields[id].fields.forEach(function (s) { row[s.id] = s.default || ''; });
            values[id] = (values[id] || []).concat([row]);
            rerenderRepeater(id);
            var items = form.querySelectorAll('[data-rep="' + id + '"] [data-rep-item]');
            var last = items[items.length - 1];
            if (last) { last.scrollIntoView({ block: 'nearest' }); var inp = last.querySelector('input, textarea'); if (inp) inp.focus(); }
            return;
        }
        if ((el = t.closest('[data-rep-remove]'))) {
            e.preventDefault();
            var item = el.closest('[data-rep-item]');
            var rid = item.closest('[data-rep]').getAttribute('data-rep');
            values[rid].splice(+item.getAttribute('data-rep-item'), 1);
            rerenderRepeater(rid);
            return;
        }
        if ((el = t.closest('[data-rep-move]'))) {
            e.preventDefault();
            var it = el.closest('[data-rep-item]');
            var mid = it.closest('[data-rep]').getAttribute('data-rep');
            var from = +it.getAttribute('data-rep-item'), to = from + +el.getAttribute('data-rep-move');
            var list = values[mid];
            if (to < 0 || to >= list.length) return;
            list.splice(to, 0, list.splice(from, 1)[0]);
            rerenderRepeater(mid);
            return;
        }
        if ((el = t.closest('[data-reveal]'))) {
            var input = el.parentNode.querySelector('input');
            input.type = input.type === 'password' ? 'text' : 'password';
            return;
        }
        if ((el = t.closest('[data-copy]'))) {
            navigator.clipboard && navigator.clipboard.writeText(el.getAttribute('data-copy')).then(function () { K.toast('success', 'کپی شد', el.getAttribute('data-copy')); });
        }
    });

    // remember which repeater items are open across re-renders
    form.addEventListener('toggle', function (e) {
        var item = e.target;
        if (!item.matches || !item.matches('[data-rep-item]')) return;
        var id = item.closest('[data-rep]').getAttribute('data-rep');
        var row = values[id] && values[id][+item.getAttribute('data-rep-item')];
        if (row) row.__open = item.open;
    }, true);

    // drag & drop reordering
    var dragFrom = null;
    form.addEventListener('dragstart', function (e) {
        var item = e.target.closest && e.target.closest('[data-rep-item]');
        if (!item) return;
        dragFrom = { id: item.closest('[data-rep]').getAttribute('data-rep'), index: +item.getAttribute('data-rep-item') };
        item.classList.add('is-dragging');
        e.dataTransfer.effectAllowed = 'move';
    });
    form.addEventListener('dragover', function (e) {
        var item = e.target.closest && e.target.closest('[data-rep-item]');
        if (dragFrom && item && item.closest('[data-rep]').getAttribute('data-rep') === dragFrom.id) { e.preventDefault(); item.classList.add('is-over'); }
    });
    form.addEventListener('dragleave', function (e) {
        var item = e.target.closest && e.target.closest('[data-rep-item]');
        if (item) item.classList.remove('is-over');
    });
    form.addEventListener('drop', function (e) {
        var item = e.target.closest && e.target.closest('[data-rep-item]');
        if (!dragFrom || !item) return;
        e.preventDefault();
        var list = values[dragFrom.id];
        list.splice(+item.getAttribute('data-rep-item'), 0, list.splice(dragFrom.index, 1)[0]);
        var id = dragFrom.id;
        dragFrom = null;
        rerenderRepeater(id);
    });
    form.addEventListener('dragend', function () { dragFrom = null; form.querySelectorAll('.is-dragging, .is-over').forEach(function (n) { n.classList.remove('is-dragging', 'is-over'); }); });

    form.addEventListener('submit', function (e) { e.preventDefault(); save(); });
    bar.querySelector('[data-save]').addEventListener('click', save);
    bar.querySelector('[data-discard]').addEventListener('click', function () {
        values = clone(saved);
        route();
    });
    document.addEventListener('keydown', function (e) {
        if ((e.ctrlKey || e.metaKey) && (e.key === 's' || e.key === 'S' || e.code === 'KeyS')) { e.preventDefault(); save(); }
    });
    window.addEventListener('beforeunload', function (e) {
        if (dirtyIds().length) { e.preventDefault(); e.returnValue = ''; }
    });
    window.addEventListener('hashchange', function () { search.value = ''; route(); });
    search.addEventListener('input', K.debounce(function () { route(); }, 150));
})();
