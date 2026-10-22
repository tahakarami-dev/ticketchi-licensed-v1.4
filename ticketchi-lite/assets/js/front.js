/* Ticketchi Lite — customer panel */
(function ($) {
    'use strict';

    var CFG = window.TICKETCHI_LITE_FRONT || {};

    var ICONS = {
        success: '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"/></svg>',
        error: '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="3" stroke-linecap="round"><path d="M18 6 6 18M6 6l12 12"/></svg>',
        info: '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="3" stroke-linecap="round"><path d="M12 16v-4M12 8h.01"/></svg>'
    };

    var FA_DIGITS = '۰۱۲۳۴۵۶۷۸۹';
    function fa(value) {
        return String(value).replace(/\d/g, function (d) { return FA_DIGITS[d]; });
    }
    function esc(text) {
        return $('<div>').text(text == null ? '' : String(text)).html();
    }

    /* ───────── Toasts ───────── */
    function toast(type, title, text) {
        var $wrap = $('.tkm-toasts');
        if (!$wrap.length) {
            $wrap = $('<div class="tkm-ui tkm-toasts" role="status" aria-live="polite"></div>').appendTo(document.body);
        }
        var $t = $(
            '<div class="tkm-toast tkm-toast--' + type + '">' +
                '<span class="tkm-toast__icon">' + (ICONS[type] || ICONS.info) + '</span>' +
                '<div><div class="tkm-toast__title">' + esc(title) + '</div>' +
                (text ? '<div class="tkm-toast__text">' + esc(text) + '</div>' : '') + '</div>' +
            '</div>'
        ).appendTo($wrap);
        setTimeout(function () {
            $t.addClass('is-leaving');
            setTimeout(function () { $t.remove(); }, 240);
        }, type === 'error' ? 6000 : 4000);
    }

    function setLoading($btn, loading) {
        $btn.prop('disabled', loading).toggleClass('is-loading', loading).attr('aria-busy', loading ? 'true' : null);
    }

    function invalid($el) {
        $el.addClass('is-invalid tkm-shake');
        setTimeout(function () { $el.removeClass('tkm-shake'); }, 450);
        var el = $el.get(0);
        if (el && el.scrollIntoView) el.scrollIntoView({ behavior: 'smooth', block: 'center' });
    }

    // Flash carried across a redirect.
    try {
        var flash = sessionStorage.getItem('ticketchi_lite_flash');
        if (flash) {
            sessionStorage.removeItem('ticketchi_lite_flash');
            flash = JSON.parse(flash);
            $(function () { toast(flash.type, flash.title, flash.text); });
        }
    } catch (e) { /* storage unavailable */ }

    /* ───────── Small behaviours ───────── */
    function autogrow(el) {
        el.style.height = 'auto';
        el.style.height = Math.min(el.scrollHeight + 2, 560) + 'px';
    }
    $(document).on('input', '[data-autogrow]', function () { autogrow(this); });
    $(document).on('input change', '.is-invalid', function () { $(this).removeClass('is-invalid'); });

    /* ───────── Departments ───────── */
    function showDepartmentNote(description) {
        var $note = $('[data-dep-note]');
        if (description) {
            $('[data-dep-note-text]').text(description);
            $note.prop('hidden', false);
        } else {
            $note.prop('hidden', true);
        }
    }

    $(document).on('change', 'input[name="tkm-parent-department"]', function () {
        $('.tkm-tiles').removeClass('is-invalid');
        showDepartmentNote($(this).data('description'));
    });

    function post(data) {
        return $.ajax({ type: 'post', url: CFG.ajax_url, data: data, contentType: false, processData: false });
    }

    /* ───────── New ticket ───────── */
    $('#tkm-submit-ticket').on('submit', function (e) {
        e.preventDefault();

        var $form = $(this);
        var $submit = $form.find('[data-submit]');
        if ($submit.prop('disabled')) return;

        var $dep = $form.find('input[name="tkm-parent-department"]:checked');
        if (!$dep.length) {
            invalid($form.find('.tkm-tiles'));
            toast('error', 'دپارتمان را انتخاب کنید', 'مشخص کنید درخواست شما مربوط به کدام بخش است.');
            return;
        }

        var $content = $form.find('#ticket-content');
        if ($.trim($content.val()) === '') {
            invalid($content);
            $content.trigger('focus');
            toast('error', 'توضیحات تیکت خالی است', 'لطفا مشکل یا سوال خود را شرح دهید.');
            return;
        }

        var data = new FormData();
        data.append('action', 'ticketchi_lite_submit_ticket');
        data.append('nonce', CFG.nonce);
        data.append('department', $dep.val());
        data.append('title_ticket', $form.find('#title-ticket').val() || '');
        data.append('content', $content.val());

        setLoading($submit, true);
        post(data).done(function (response) {
            if (response && response.success) {
                try {
                    sessionStorage.setItem('ticketchi_lite_flash', JSON.stringify({ type: 'success', title: 'تیکت شما ثبت شد', text: 'به‌زودی پاسخ را در همین صفحه می‌بینید.' }));
                } catch (err) { /* ignore */ }
                window.location.href = $form.data('redirect');
                return;
            }
            setLoading($submit, false);
            toast('error', 'ثبت تیکت انجام نشد', response && response.result);
        }).fail(function () {
            setLoading($submit, false);
            toast('error', 'خطا در ارتباط', 'ارسال درخواست ناموفق بود. لطفا دوباره تلاش کنید.');
        });
    });

    /* ───────── Reply ───────── */
    $('#reply-submit').on('submit', function (e) {
        e.preventDefault();

        var $form = $(this);
        var $submit = $form.find('[data-submit]');
        if ($submit.prop('disabled')) return;

        var $body = $form.find('#body');
        if ($.trim($body.val()) === '') {
            invalid($body.closest('.tkm-composer'));
            $body.trigger('focus');
            toast('error', 'پاسخ خالی است', 'متن پاسخ را بنویسید.');
            return;
        }

        var closing = $form.find('#status').is(':checked');
        var data = new FormData();
        data.append('action', 'ticketchi_lite_submit_reply');
        data.append('nonce', CFG.nonce);
        data.append('ticket_id', $form.find('#ticket_id').val());
        data.append('status', closing ? 'closed' : '');
        data.append('body', $body.val());

        setLoading($submit, true);
        post(data).done(function (response) {
            if (response && response.success) {
                var $replies = $('[data-replies]');
                $replies.html(response.replies_html);
                var count = $replies.find('.tkm-msg').length;
                $('.tkm-thread__divider span').text(count ? fa(count) + ' پاسخ' : 'پاسخ‌ها');
                if (response.status_badge) $('[data-status-badge]').html(response.status_badge);
                $form.find('.tkm-composer__note').remove();
                $body.val('');
                autogrow($body.get(0));
                $form.find('#status').prop('checked', false);

                var last = $replies.find('.tkm-msg').last().get(0);
                if (last && last.scrollIntoView) last.scrollIntoView({ behavior: 'smooth', block: 'center' });
                toast('success', closing ? 'پاسخ ارسال و تیکت بسته شد' : 'پاسخ شما ارسال شد');
            } else {
                toast('error', 'ارسال پاسخ انجام نشد', response && response.result);
            }
        }).fail(function () {
            toast('error', 'خطا در ارتباط', 'ارسال درخواست ناموفق بود. لطفا دوباره تلاش کنید.');
        }).always(function () {
            setLoading($submit, false);
        });
    });

    // Ctrl/Cmd + Enter sends the reply.
    $(document).on('keydown', '#reply-submit #body', function (e) {
        if (e.key === 'Enter' && (e.ctrlKey || e.metaKey)) {
            e.preventDefault();
            $('#reply-submit').trigger('submit');
        }
    });
})(jQuery);
