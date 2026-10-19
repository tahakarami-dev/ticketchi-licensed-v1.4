/* Ticketchi — team page extras: agent workload + tag manager. */
(function () {
    'use strict';

    var K = window.TKM;
    var agentsBox = document.querySelector('#tkm-team-agents [data-agents]');
    var tagsBox = document.querySelector('#tkm-tag-manager [data-tags]');
    var form = document.querySelector('[data-tag-new]');
    if (!K || !tagsBox) return;

    var fa = K.fa, esc = K.esc;

    function renderAgents(agents) {
        if (!agentsBox) return;
        if (!agents.length) {
            agentsBox.innerHTML = '<p class="tkm-ib-none">هنوز کارشناسی ندارید. در فرم دپارتمان، کاربران را به‌عنوان پاسخ‌دهنده اضافه کنید یا نقش «کارشناس پشتیبانی» را به آن‌ها بدهید.</p>';
            return;
        }
        var max = Math.max.apply(null, agents.map(function (a) { return a.open; })) || 1;
        agentsBox.innerHTML = '<div class="tkm-hbars">' + agents.map(function (a) {
            return '<a class="tkm-hbar" href="' + esc(K.app.admin + '?page=tkm-tickets#/view/active') + '"><span class="tkm-hbar__label">' + K.avatar(a, 'xs') + esc(a.name) + '</span>' +
                '<span class="tkm-hbar__value">' + fa(a.open) + ' <small>باز</small></span>' +
                '<span class="tkm-hbar__track"><span class="tkm-hbar__fill" style="width:' + (a.open / max * 100).toFixed(1) + '%"></span></span></a>';
        }).join('') + '</div>';
    }

    function renderTags(tags) {
        if (!tags.length) {
            tagsBox.innerHTML = '<p class="tkm-ib-none">هنوز برچسبی ندارید.</p>';
            return;
        }
        tagsBox.innerHTML = tags.map(function (t) {
            return '<div class="tkm-tagm__row" data-id="' + t.id + '">' +
                '<input type="color" class="tkm-tagm__color" value="' + esc(t.color) + '" aria-label="رنگ ' + esc(t.name) + '" data-field="color">' +
                '<input type="text" class="tkm-input tkm-input--sm" value="' + esc(t.name) + '" maxlength="64" aria-label="نام برچسب" data-field="name">' +
                '<a class="tkm-tagm__count" href="' + esc(K.app.admin + '?page=tkm-tickets#/tag/' + t.id) + '">' + fa(t.count) + ' تیکت</a>' +
                '<button type="button" class="tkm-btn tkm-btn--ghost tkm-btn--icon tkm-btn--sm" data-tag-delete aria-label="حذف ' + esc(t.name) + '">' + K.icon('trash', 16) + '</button></div>';
        }).join('');
    }

    function load() {
        K.api('meta').then(function (m) {
            renderAgents(m.agents);
            renderTags(m.tags);
        }).catch(function (e) { tagsBox.textContent = e.message; });
    }

    form.addEventListener('submit', function (e) {
        e.preventDefault();
        var name = form.elements.name.value.trim();
        if (!name) return;
        K.api('tags', { method: 'POST', body: { name: name, color: form.elements.color.value } }).then(function (res) {
            renderTags(res.tags);
            form.reset();
            K.toast('success', 'برچسب ساخته شد');
        }).catch(function (err) { K.toast('error', 'ساخته نشد', err.message); });
    });

    tagsBox.addEventListener('change', function (e) {
        var row = e.target.closest('[data-id]');
        if (!row || !e.target.matches('[data-field]')) return;
        var name = row.querySelector('[data-field="name"]').value.trim();
        if (!name) { K.toast('error', 'نام برچسب خالی است'); return; }
        K.api('tags/' + row.getAttribute('data-id'), { method: 'PUT', body: { name: name, color: row.querySelector('[data-field="color"]').value } })
            .then(function (tags) { renderTags(tags); K.toast('success', 'برچسب ذخیره شد'); })
            .catch(function (err) { K.toast('error', 'ذخیره نشد', err.message); });
    });

    tagsBox.addEventListener('click', function (e) {
        var btn = e.target.closest('[data-tag-delete]');
        if (!btn) return;
        var row = btn.closest('[data-id]');
        K.confirm('برچسب از همه تیکت‌ها و قوانین برداشته می‌شود.').then(function () {
            K.api('tags/' + row.getAttribute('data-id'), { method: 'DELETE' }).then(function (tags) { renderTags(tags); K.toast('success', 'برچسب حذف شد'); })
                .catch(function (err) { K.toast('error', 'حذف نشد', err.message); });
        }, function () {});
    });

    load();
})();
