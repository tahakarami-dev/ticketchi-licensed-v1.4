/* Ticketchi — admin app */
jQuery(function ($) {
    'use strict';

    var CFG = window.TKM_DATA || {};
    var $app = $('#tkm-admin');
    var FA = '۰۱۲۳۴۵۶۷۸۹';
    function fa(v) { return String(v).replace(/\d/g, function (d) { return FA[d]; }); }
    function esc(t) { return $('<div>').text(t == null ? '' : String(t)).html(); }

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
            result ? dfd.resolve() : dfd.reject();
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
        try { localStorage.setItem('tkm-theme', dark ? 'dark' : 'light'); } catch (err) { /* ignore */ }
    });

    /* ───────── User search (select2) ───────── */
    if ($.fn.select2) {
        $('.tkm-user-search').each(function () {
            var $el = $(this);
            $el.select2({
                dir: 'rtl',
                width: '100%',
                allowClear: !$el.prop('multiple'),
                placeholder: $el.data('placeholder') || 'جستجوی کاربر…',
                minimumInputLength: 1,
                language: {
                    inputTooShort: function () { return 'حداقل یک حرف وارد کنید'; },
                    searching: function () { return 'در حال جستجو…'; },
                    noResults: function () { return 'کاربری پیدا نشد'; },
                    errorLoading: function () { return 'خطا در دریافت نتایج'; }
                },
                ajax: {
                    url: CFG.ajax_url,
                    dataType: 'json',
                    delay: 250,
                    type: 'post',
                    data: function (params) {
                        return { term: params.term, action: 'tkm_search_user', nonce: CFG.nonce };
                    },
                    processResults: function (data) {
                        return {
                            results: $.map(data || [], function (user) {
                                return { id: user[0], text: user[1] };
                            })
                        };
                    },
                    cache: true
                }
            }).on('select2:open', function () {
                $('.select2-container--open .select2-dropdown').addClass('tkm-select2-dropdown');
            });
        });
    }

    /* ───────── Clickable rows ───────── */
    $(document).on('click', 'tr[data-href]', function (e) {
        if ($(e.target).closest('a, button, input, label, select, .row-actions, .check-column').length) {
            return;
        }
        window.location.href = $(this).data('href');
    });

    /* ───────── Chart tooltip ───────── */
    $('[data-chart]').each(function () {
        var $chart = $(this);
        var $tip = $chart.find('.tkm-chart__tooltip');
        $chart.on('mouseenter', '.tkm-chart__col', function () {
            var hit = this.querySelector('.tkm-chart__hit').getBoundingClientRect();
            var box = $chart[0].getBoundingClientRect();
            $tip.html('<strong>' + esc($(this).data('value')) + '</strong>' + esc($(this).data('tip')))
                .css({ left: hit.left - box.left + hit.width / 2, top: hit.top - box.top + 8 })
                .prop('hidden', false);
            $chart.attr('data-hovering', '');
        }).on('mouseleave', '.tkm-chart__col', function () {
            $tip.prop('hidden', true);
            $chart.removeAttr('data-hovering');
        });
    });

    /* ───────── Dropzones ───────── */
    $(document).on('change', '[data-admin-dropzone] input[type="file"]', function () {
        var $zone = $(this).closest('[data-admin-dropzone]');
        var $title = $zone.find('[data-dropzone-title]');
        if (!$title.data('label')) {
            $title.data('label', $title.text());
        }
        var names = $.map(this.files || [], function (f) { return f.name; });
        $zone.toggleClass('has-files', names.length > 0);
        $title.text(names.length ? fa(names.length) + ' فایل: ' + names.join('، ') : $title.data('label'));
    });
    $(document).on('dragenter dragover', '[data-admin-dropzone]', function () { $(this).addClass('is-over'); })
        .on('dragleave drop', '[data-admin-dropzone]', function () { $(this).removeClass('is-over'); });

    /* ───────── Workspace ───────── */
    function editorContent(id) {
        if (window.tinymce && tinymce.get(id) && !tinymce.get(id).isHidden()) {
            return tinymce.get(id).getContent({ format: 'text' });
        }
        return $('#' + id).val() || '';
    }

    function setEditor(id, text) {
        if (window.tinymce && tinymce.get(id) && !tinymce.get(id).isHidden()) {
            var ed = tinymce.get(id);
            var html = $.map(String(text).split(/\n{2,}/), function (p) { return '<p>' + esc(p).replace(/\n/g, '<br>') + '</p>'; }).join('');
            ed.setContent(ed.getContent() ? ed.getContent() + html : html);
            ed.focus();
        } else {
            var $ta = $('#' + id);
            $ta.val(($ta.val() ? $ta.val() + '\n\n' : '') + text).trigger('focus');
        }
    }

    $(document).on('click', '[data-toggle-edit]', function () {
        var $panel = $(this).closest('[data-msg]').find('[data-edit-panel]');
        $panel.prop('hidden', !$panel.prop('hidden'));
        if (!$panel.prop('hidden')) {
            $panel.find('textarea, input[type="text"]').first().trigger('focus');
        }
    });

    $(document).on('click', '[data-delete-reply]', function () {
        var id = $(this).data('delete-reply');
        var $form = $(this).closest('form');
        var $msg = $(this).closest('[data-msg]');
        confirmDialog('این پیام برای همیشه حذف می‌شود.').done(function () {
            $form.find('[name="tkm-reply-body-' + id + '"]').val('');
            $msg.addClass('is-deleting');
            $form.trigger('submit');
        });
    });

    $('#save_select').on('change', function () {
        var text = $(this).val();
        if (text) {
            setEditor('reply_content', text);
            $(this).val('');
        }
    });

    var $ticketForm = $('#tkm-ticket-form');
    $ticketForm.on('click', '[data-reply-send], [data-reply-close]', function (e) {
        if (window.tinymce) { tinymce.triggerSave(); }
        if ($.trim(editorContent('reply_content')) === '') {
            e.preventDefault();
            toast('error', 'متن پاسخ خالی است', 'ابتدا پاسخ را بنویسید یا یک پاسخ آماده انتخاب کنید.');
            return;
        }
        if ($(this).is('[data-reply-close]')) {
            $ticketForm.find('#status').val('closed');
        }
    });

    $ticketForm.on('submit', function () {
        if ($.trim($('#ticket-title').val()) === '') {
            $('[data-edit-panel]').first().prop('hidden', false);
            $('#ticket-title').trigger('focus');
            toast('error', 'عنوان تیکت نمی‌تواند خالی باشد');
            return false;
        }
        $(this).find('button[type="submit"]').prop('disabled', true).addClass('is-loading');
    });

    $('#tkm-compose-form').on('submit', function () {
        if (window.tinymce) { tinymce.triggerSave(); }
        var errors = [];
        if (!$('#ticket-user-id').val() || !$('#ticket-user-id').val().length) errors.push('حداقل یک گیرنده انتخاب کنید');
        if ($.trim($('#ticket-title').val()) === '') errors.push('عنوان را وارد کنید');
        if (!$('#tkm-department').val()) errors.push('دپارتمان را انتخاب کنید');
        if ($.trim(editorContent('tkm-content')) === '') errors.push('متن پیام را بنویسید');
        if (errors.length) {
            toast('error', 'فرم کامل نیست', errors.join('، '));
            return false;
        }
        $(this).find('button[type="submit"]').prop('disabled', true).addClass('is-loading');
    });

    // Bulk actions: ask before permanent delete.
    $(document).on('click', '.tkm-wptable #doaction, .tkm-wptable #doaction2', function (e) {
        var $btn = $(this);
        var action = $btn.siblings('select').val();
        if (action === 'bulk-delete' && !$btn.data('confirmed')) {
            e.preventDefault();
            confirmDialog('تیکت‌های انتخاب‌شده برای همیشه حذف می‌شوند.').done(function () {
                $btn.data('confirmed', true).trigger('click');
            });
        }
    });

    window.TKM_ADMIN = { toast: toast, confirm: confirmDialog };
});
