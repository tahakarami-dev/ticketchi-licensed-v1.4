/* Ticketchi — customer panel */
(function ($) {
    'use strict';

    var CFG = window.TKM_DATA_AJAX || {};
    var UPLOAD = $.extend({ multiple: false, max_files: 5, max_size: 5242880, allowed_ext: [] }, CFG.upload || {});

    var ICONS = {
        success: '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"/></svg>',
        error: '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="3" stroke-linecap="round"><path d="M18 6 6 18M6 6l12 12"/></svg>',
        info: '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="3" stroke-linecap="round"><path d="M12 16v-4M12 8h.01"/></svg>',
        clip: '<svg class="tkm-icon" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="m21.44 11.05-9.19 9.19a6 6 0 0 1-8.49-8.49l8.57-8.57A4 4 0 1 1 18 8.84l-8.59 8.57a2 2 0 0 1-2.83-2.83l8.49-8.48"/></svg>',
        x: '<svg class="tkm-icon" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M18 6 6 18M6 6l12 12"/></svg>'
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

    function errorText(result) {
        if ($.isArray(result)) {
            return result.join(' ');
        }
        if (result && typeof result === 'object') {
            return $.map(result, function (v) { return v; }).join(' ');
        }
        return result || 'خطایی رخ داد. لطفا دوباره تلاش کنید.';
    }

    function setLoading($btn, loading) {
        $btn.prop('disabled', loading).toggleClass('is-loading', loading).attr('aria-busy', loading ? 'true' : null);
    }

    function invalid($el) {
        $el.addClass('is-invalid tkm-shake');
        setTimeout(function () { $el.removeClass('tkm-shake'); }, 450);
        var el = $el.get(0);
        if (el && el.scrollIntoView) {
            el.scrollIntoView({ behavior: 'smooth', block: 'center' });
        }
    }

    // Flash carried across a redirect.
    try {
        var flash = sessionStorage.getItem('tkm_flash');
        if (flash) {
            sessionStorage.removeItem('tkm_flash');
            flash = JSON.parse(flash);
            $(function () { toast(flash.type, flash.title, flash.text); });
        }
    } catch (e) { /* storage unavailable */ }

    /* ───────── Small behaviours ───────── */
    $(document).on('change', '[data-autosubmit] select, [data-autosubmit] input[type="radio"]', function () {
        $(this).closest('form').trigger('submit');
    });

    function autogrow(el) {
        el.style.height = 'auto';
        el.style.height = Math.min(el.scrollHeight + 2, 560) + 'px';
    }
    $(document).on('input', '[data-autogrow]', function () { autogrow(this); });

    $(document).on('input change', '.is-invalid', function () {
        $(this).removeClass('is-invalid');
    });

    $('[data-faq-continue]').on('click', function () {
        var $form = $('#tkm-submit-ticket');
        $(this).closest('[data-faq]').find('.tkm-card__foot').slideUp(160);
        $form.prop('hidden', false);
        $('html, body').animate({ scrollTop: $form.offset().top - 40 }, 320);
    });

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
        var parentId = $(this).val();
        var hasChildren = $(this).data('has-children') == 1;
        $('.tkm-tiles').removeClass('is-invalid');

        $('input[name="child-department"]').prop('checked', false);
        $('[data-subdeps] .tkm-chips').prop('hidden', true).removeClass('is-invalid');
        $('[data-subdeps]').prop('hidden', !hasChildren);
        if (hasChildren) {
            $('[data-subdeps] .tkm-chips[data-parent="' + parentId + '"]').prop('hidden', false);
        }
        showDepartmentNote(hasChildren ? '' : $(this).data('description'));
    });

    $(document).on('change', 'input[name="child-department"]', function () {
        $(this).closest('.tkm-chips').removeClass('is-invalid');
        showDepartmentNote($(this).data('description'));
    });

    function selectedDepartment() {
        var $parent = $('input[name="tkm-parent-department"]:checked');
        if (!$parent.length) {
            return { error: 'parent' };
        }
        if ($parent.data('has-children') == 1) {
            var $child = $('input[name="child-department"]:checked');
            return $child.length ? { id: $child.val() } : { error: 'child', parent: $parent.val() };
        }
        return { id: $parent.val() };
    }

    /* ───────── Attachments ───────── */
    function attachState($attach) {
        var state = $attach.data('tkmFiles');
        if (!state) {
            state = [];
            $attach.data('tkmFiles', state);
        }
        return state;
    }

    function renderFiles($attach) {
        var files = attachState($attach);
        var $list = $attach.find('[data-file-list]').empty();
        $.each(files, function (i, file) {
            $list.append(
                '<span class="tkm-file tkm-file--pending">' + ICONS.clip +
                '<span>' + esc(file.name) + '</span>' +
                '<button type="button" data-file-remove="' + i + '" aria-label="حذف فایل">' + ICONS.x + '</button></span>'
            );
        });
    }

    function addFiles($attach, fileList) {
        var files = attachState($attach);
        var maxFiles = UPLOAD.multiple ? parseInt(UPLOAD.max_files, 10) || 1 : 1;

        $.each(fileList, function (_, file) {
            var ext = (file.name.split('.').pop() || '').toLowerCase();

            if (UPLOAD.allowed_ext && UPLOAD.allowed_ext.length && $.inArray(ext, UPLOAD.allowed_ext) === -1) {
                toast('error', 'پسوند غیرمجاز', 'پسوند «' + ext + '» مجاز نیست.');
                return;
            }
            if (file.size > UPLOAD.max_size) {
                toast('error', 'حجم فایل زیاد است', 'حجم «' + file.name + '» بیشتر از حد مجاز است.');
                return;
            }
            if (!UPLOAD.multiple) {
                files.length = 0;
            } else if (files.length >= maxFiles) {
                toast('error', 'تعداد فایل زیاد است', 'حداکثر ' + fa(maxFiles) + ' فایل می‌توانید پیوست کنید.');
                return false;
            }
            files.push(file);
        });

        renderFiles($attach);
    }

    $(document).on('change', '[data-file-input]', function () {
        var $attach = $(this).closest('[data-attach]');
        addFiles($attach, this.files || []);
        this.value = '';
    });

    $(document).on('click', '[data-file-remove]', function () {
        var $attach = $(this).closest('[data-attach]');
        attachState($attach).splice(parseInt($(this).attr('data-file-remove'), 10), 1);
        renderFiles($attach);
    });

    $(document).on('dragenter dragover', '[data-dropzone]', function () {
        $(this).addClass('is-over');
    }).on('dragleave drop', '[data-dropzone]', function () {
        $(this).removeClass('is-over');
    });

    function appendAttachments($form, formData) {
        var $attach = $form.find('[data-attach]');
        $.each(attachState($attach), function (_, file) {
            formData.append('file[]', file, file.name);
        });
        formData.append('audioData', $attach.find('[data-voice-input]').val() || '');
    }

    function resetAttachments($form) {
        var $attach = $form.find('[data-attach]');
        attachState($attach).length = 0;
        renderFiles($attach);
        clearVoice($attach);
    }

    /* ───────── Voice recorder ───────── */
    var rec = { recorder: null, stream: null, chunks: [], timer: null, seconds: 0, dataUrl: '', $attach: null, url: null };
    var $modal = $('[data-voice-modal]').first();
    // .tkm-app is a size container; keep the fixed modal out of it so it covers the viewport.
    if ($modal.length) {
        $('<div class="tkm-ui tkm-portal"></div>').append($modal).appendTo(document.body);
    }

    function pickMime() {
        if (!window.MediaRecorder || !MediaRecorder.isTypeSupported) {
            return '';
        }
        var types = ['audio/webm;codecs=opus', 'audio/webm', 'audio/mp4', 'audio/ogg;codecs=opus'];
        for (var i = 0; i < types.length; i++) {
            if (MediaRecorder.isTypeSupported(types[i])) {
                return types[i];
            }
        }
        return '';
    }

    function clock(seconds) {
        var m = Math.floor(seconds / 60), s = seconds % 60;
        return (m < 10 ? '0' : '') + m + ':' + (s < 10 ? '0' : '') + s;
    }

    function recUI(state) {
        var $r = $modal.find('[data-recorder]');
        $r.toggleClass('is-recording', state === 'recording');
        $modal.find('[data-rec-icon-start]').prop('hidden', state === 'recording');
        $modal.find('[data-rec-icon-stop]').prop('hidden', state !== 'recording');
        $modal.find('[data-rec-toggle]').prop('hidden', state === 'done').attr('aria-label', state === 'recording' ? 'پایان ضبط' : 'شروع ضبط');
        $modal.find('[data-rec-playback]').prop('hidden', state !== 'done');
        $modal.find('[data-rec-actions]').prop('hidden', state !== 'done');
        $modal.find('[data-rec-status]').text({ idle: 'آماده ضبط', recording: 'در حال ضبط… برای پایان دوباره بزنید', done: 'پیش‌نمایش پیام صوتی' }[state]);
        if (state === 'idle') {
            $modal.find('[data-rec-time]').text('00:00');
        }
    }

    function stopStream() {
        if (rec.stream) {
            rec.stream.getTracks().forEach(function (t) { t.stop(); });
            rec.stream = null;
        }
        clearInterval(rec.timer);
    }

    function resetRecording() {
        if (rec.recorder && rec.recorder.state === 'recording') {
            rec.recorder.onstop = null;
            rec.recorder.stop();
        }
        stopStream();
        if (rec.url) {
            URL.revokeObjectURL(rec.url);
            rec.url = null;
        }
        rec.chunks = [];
        rec.dataUrl = '';
        $modal.find('[data-rec-playback]').removeAttr('src');
        recUI('idle');
    }

    function startRecording() {
        if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia || !window.MediaRecorder) {
            toast('error', 'ضبط صدا پشتیبانی نمی‌شود', 'مرورگر شما یا اتصال غیرامن (http) اجازه ضبط صدا را نمی‌دهد.');
            return;
        }

        navigator.mediaDevices.getUserMedia({ audio: true }).then(function (stream) {
            var mime = pickMime();
            rec.stream = stream;
            rec.chunks = [];
            rec.recorder = mime ? new MediaRecorder(stream, { mimeType: mime }) : new MediaRecorder(stream);
            rec.recorder.ondataavailable = function (e) {
                if (e.data && e.data.size) {
                    rec.chunks.push(e.data);
                }
            };
            rec.recorder.onstop = function () {
                stopStream();
                var blob = new Blob(rec.chunks, { type: rec.recorder.mimeType || 'audio/webm' });
                rec.url = URL.createObjectURL(blob);
                $modal.find('[data-rec-playback]').attr('src', rec.url);
                var reader = new FileReader();
                reader.onloadend = function () { rec.dataUrl = reader.result; };
                reader.readAsDataURL(blob);
                recUI('done');
            };
            rec.recorder.start();
            rec.seconds = 0;
            rec.timer = setInterval(function () {
                rec.seconds++;
                $modal.find('[data-rec-time]').text(clock(rec.seconds));
                if (rec.seconds >= 300) { // 5 minute cap
                    rec.recorder.stop();
                }
            }, 1000);
            recUI('recording');
        }).catch(function () {
            toast('error', 'دسترسی به میکروفون رد شد', 'برای ضبط پیام صوتی، اجازه دسترسی به میکروفون را بدهید.');
        });
    }

    function openVoice($attach) {
        rec.$attach = $attach;
        resetRecording();
        $modal.prop('hidden', false);
        $modal.find('[data-rec-toggle]').trigger('focus');
    }

    function closeVoice() {
        resetRecording();
        $modal.prop('hidden', true);
    }

    function clearVoice($attach) {
        $attach.find('[data-voice-input]').val('');
        $attach.find('[data-voice-chip]').prop('hidden', true);
        $attach.find('[data-voice-open]').prop('hidden', false);
    }

    $(document).on('click', '[data-voice-open]', function () { openVoice($(this).closest('[data-attach]')); });
    $(document).on('click', '[data-voice-close]', closeVoice);
    $(document).on('keydown', function (e) {
        if (e.key === 'Escape' && $modal.length && !$modal.prop('hidden')) {
            closeVoice();
        }
    });
    $(document).on('click', '[data-rec-toggle]', function () {
        if (rec.recorder && rec.recorder.state === 'recording') {
            rec.recorder.stop();
        } else {
            startRecording();
        }
    });
    $(document).on('click', '[data-rec-discard]', resetRecording);
    $(document).on('click', '[data-rec-attach]', function () {
        if (!rec.dataUrl || !rec.$attach) {
            toast('info', 'لحظه‌ای صبر کنید', 'فایل صوتی در حال آماده‌سازی است.');
            return;
        }
        rec.$attach.find('[data-voice-input]').val(rec.dataUrl);
        rec.$attach.find('[data-voice-label]').text('پیام صوتی ' + fa(clock(rec.seconds)));
        rec.$attach.find('[data-voice-chip]').prop('hidden', false);
        rec.$attach.find('[data-voice-open]').prop('hidden', true);
        $modal.prop('hidden', true);
        stopStream();
    });
    $(document).on('click', '[data-voice-remove]', function () { clearVoice($(this).closest('[data-attach]')); });

    /* ───────── New ticket ───────── */
    $('#tkm-submit-ticket').on('submit', function (e) {
        e.preventDefault();

        var $form = $(this);
        var $submit = $form.find('[data-submit]');
        if ($submit.prop('disabled')) {
            return;
        }

        var dep = selectedDepartment();
        if (dep.error === 'parent') {
            invalid($form.find('.tkm-tiles'));
            toast('error', 'دپارتمان را انتخاب کنید', 'مشخص کنید درخواست شما مربوط به کدام بخش است.');
            return;
        }
        if (dep.error === 'child') {
            invalid($form.find('.tkm-chips[data-parent="' + dep.parent + '"]'));
            toast('error', 'نوع تیکت را انتخاب کنید');
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
        data.append('action', 'tkm-submit-ticket');
        data.append('nonce', CFG.nonce);
        data.append('child-department', dep.id);
        data.append('title_ticket', $form.find('#title-ticket').val() || '');
        data.append('priority', $form.find('input[name="priority"]:checked').val() || 'medium');
        data.append('content', $content.val());
        data.append('user_purchased_products', $form.find('#products').val() || '');
        data.append('edd_purchased_products', $form.find('#edd-products').val() || '');
        appendAttachments($form, data);

        setLoading($submit, true);

        $.ajax({ type: 'post', url: CFG.ajax_url, data: data, contentType: false, processData: false })
            .done(function (response) {
                if (response && response.success) {
                    try {
                        sessionStorage.setItem('tkm_flash', JSON.stringify({ type: 'success', title: 'تیکت شما ثبت شد', text: 'به‌زودی پاسخ را در همین صفحه می‌بینید.' }));
                    } catch (err) { /* ignore */ }
                    window.location.href = $form.data('redirect') || response.result;
                    return;
                }
                setLoading($submit, false);
                toast('error', 'ثبت تیکت انجام نشد', errorText(response && response.result));
            })
            .fail(function () {
                setLoading($submit, false);
                toast('error', 'خطا در ارتباط', 'ارسال درخواست ناموفق بود. لطفا دوباره تلاش کنید.');
            });
    });

    /* ───────── Reply ───────── */
    $('#reply-submit').on('submit', function (e) {
        e.preventDefault();

        var $form = $(this);
        var $submit = $form.find('[data-submit]');
        if ($submit.prop('disabled')) {
            return;
        }

        var $body = $form.find('#body');
        var $attach = $form.find('[data-attach]');
        var hasExtras = attachState($attach).length > 0 || !!$attach.find('[data-voice-input]').val();
        if ($.trim($body.val()) === '' && !hasExtras) {
            invalid($body.closest('.tkm-composer'));
            $body.trigger('focus');
            toast('error', 'پاسخ خالی است', 'متن پاسخ را بنویسید یا فایل / پیام صوتی پیوست کنید.');
            return;
        }

        var closing = $form.find('#status').is(':checked');
        var data = new FormData();
        data.append('action', 'tkm-submit-reply');
        data.append('nonce', CFG.nonce);
        data.append('ticket_id', $form.find('#ticket_id').val());
        data.append('status', closing ? 'closed' : '');
        data.append('body', $body.val());
        appendAttachments($form, data);

        setLoading($submit, true);

        $.ajax({ type: 'post', url: CFG.ajax_url, data: data, contentType: false, processData: false })
            .done(function (response) {
                if (response && response.success) {
                    var $replies = $('[data-replies]');
                    $replies.html(response.replies_html);
                    var count = $replies.find('.tkm-msg').not('.tkm-msg--bot').length;
                    $('.tkm-thread__divider span').text(count ? fa(count) + ' پاسخ' : 'پاسخ‌ها');
                    if (response.status_badge) {
                        $('[data-status-badge]').html(response.status_badge);
                    }
                    $form.find('.tkm-composer__note').remove();
                    $body.val('');
                    autogrow($body.get(0));
                    $form.find('#status').prop('checked', false);
                    resetAttachments($form);

                    var last = $replies.find('.tkm-msg').last().get(0);
                    if (last && last.scrollIntoView) {
                        last.scrollIntoView({ behavior: 'smooth', block: 'center' });
                    }
                    toast('success', closing ? 'پاسخ ارسال و تیکت بسته شد' : 'پاسخ شما ارسال شد');
                } else {
                    toast('error', 'ارسال پاسخ انجام نشد', errorText(response && response.result));
                }
            })
            .fail(function () {
                toast('error', 'خطا در ارتباط', 'ارسال درخواست ناموفق بود. لطفا دوباره تلاش کنید.');
            })
            .always(function () {
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

    /* ───────── Rating ───────── */
    $(document).on('submit', '[data-rating-form]', function (e) {
        e.preventDefault();

        var $form = $(this);
        var $submit = $form.find('[data-submit]');
        var rating = parseInt($form.find('input[name="rating"]:checked').val(), 10) || 0;

        if (!rating) {
            toast('info', 'امتیاز را انتخاب کنید', 'روی یکی از ستاره‌ها بزنید.');
            return;
        }

        setLoading($submit, true);

        $.post(CFG.ajax_url, { action: 'tkm_submit_rating', nonce: CFG.nonce, rating: rating, ticket_id: $form.data('ticket') })
            .done(function (response) {
                if (response && response.success) {
                    var $card = $form.closest('[data-rating-card]').addClass('is-done');
                    $card.find('.tkm-rate__title').text('امتیاز شما به این تیکت');
                    $form.find('input').prop('disabled', true);
                    $submit.replaceWith('<p class="tkm-hint">ممنون که به بهبود پشتیبانی کمک کردید.</p>');
                    toast('success', 'امتیاز شما ثبت شد', 'سپاس از همراهی شما');
                } else {
                    setLoading($submit, false);
                    toast('error', 'ثبت امتیاز انجام نشد', response && response.data ? response.data.message : '');
                }
            })
            .fail(function () {
                setLoading($submit, false);
                toast('error', 'خطا در ارتباط');
            });
    });

    window.TKM_UI = { toast: toast };
})(jQuery);
