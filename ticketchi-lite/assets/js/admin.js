/* Ticketchi Lite — admin shell: toasts, confirm dialog, theme, user menu. */
jQuery(function ($) {
    'use strict';

    var $app = $('#tkm-admin');
    function esc(t) { return $('<div>').text(t == null ? '' : String(t)).html(); }

    if (document.documentElement.classList.contains('tkm-dark')) {
        $app.attr('data-theme', 'dark');
    }

    /* ───────── Toasts ───────── */
    function toast(type, title, text) {
        var $wrap = $('.tkm-toasts');
        if (!$wrap.length) {
            $wrap = $('<div class="tkm-ui tkm-toasts" role="status" aria-live="polite"></div>').appendTo(document.body);
        }
        var icon = type === 'success' ? '<path d="M20 6 9 17l-5-5"/>' : '<path d="M18 6 6 18M6 6l12 12"/>';
        var $t = $('<div class="tkm-toast tkm-toast--' + type + '"><span class="tkm-toast__icon"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="3" stroke-linecap="round" stroke-linejoin="round">' + icon + '</svg></span><div><div class="tkm-toast__title">' + esc(title) + '</div>' + (text ? '<div class="tkm-toast__text">' + esc(text) + '</div>' : '') + '</div></div>').appendTo($wrap);
        setTimeout(function () { $t.addClass('is-leaving'); setTimeout(function () { $t.remove(); }, 240); }, 4500);
    }

    /* ───────── Confirm dialog ───────── */
    function confirmDialog(message, okLabel) {
        var dfd = $.Deferred();
        var $m = $(
            '<div class="tkm-modal" role="dialog" aria-modal="true">' +
                '<div class="tkm-modal__backdrop" data-cancel></div>' +
                '<div class="tkm-modal__dialog">' +
                    '<h3 class="tkm-modal__title">مطمئن هستید؟</h3>' +
                    '<p class="tkm-modal__text">' + esc(message) + '</p>' +
                    '<div class="tkm-row" style="justify-content:flex-start;margin-top:20px;gap:8px">' +
                        '<button type="button" class="tkm-btn tkm-btn--danger" data-ok>' + esc(okLabel || 'بله، حذف شود') + '</button>' +
                        '<button type="button" class="tkm-btn" data-cancel>انصراف</button>' +
                    '</div>' +
                '</div>' +
            '</div>'
        );
        ($app.length ? $app : $('body')).append($m);
        $m.find('[data-ok]').trigger('focus');
        function close(result) {
            $(document).off('keydown.tkmconfirm');
            $m.remove();
            if (result) { dfd.resolve(); } else { dfd.reject(); }
        }
        $m.on('click', '[data-ok]', function () { close(true); });
        $m.on('click', '[data-cancel]', function () { close(false); });
        $(document).on('keydown.tkmconfirm', function (e) { if (e.key === 'Escape') close(false); });
        return dfd.promise();
    }

    $(document).on('click', '[data-confirm]', function (e) {
        e.preventDefault();
        var href = $(this).attr('href');
        confirmDialog($(this).data('confirm')).done(function () { window.location.href = href; });
    });

    /* ───────── Theme ───────── */
    $(document).on('click', '[data-theme-toggle]', function () {
        var dark = $app.attr('data-theme') !== 'dark';
        $app.attr('data-theme', dark ? 'dark' : 'light');
        document.documentElement.classList.toggle('tkm-dark', dark);
        try { localStorage.setItem('tkm-theme', dark ? 'dark' : 'light'); } catch (err) { /* storage unavailable */ }
    });

    /* ───────── User menu ───────── */
    var $menu = $('[data-usermenu]');
    var $panel = $menu.find('[data-usermenu-panel]');
    var $btn = $menu.find('[data-usermenu-toggle]');
    function setMenu(open) { $panel.prop('hidden', !open); $btn.attr('aria-expanded', String(open)); }
    $btn.on('click', function (e) { e.stopPropagation(); setMenu($panel.prop('hidden')); });
    $(document).on('click', function (e) { if (!$menu.is(e.target) && !$menu.has(e.target).length) setMenu(false); });
    $(document).on('keydown', function (e) { if (e.key === 'Escape') setMenu(false); });

    /* ───────── Copy to clipboard ───────── */
    $(document).on('click', '[data-copy]', function () {
        var text = $(this).attr('data-copy');
        if (navigator.clipboard) {
            navigator.clipboard.writeText(text).then(function () { toast('success', 'کپی شد', text); });
        }
    });

    window.TICKETCHI_LITE_ADMIN = { toast: toast, confirm: confirmDialog };
});
