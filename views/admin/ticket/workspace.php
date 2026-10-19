<?php
/**
 * Ticket workspace: conversation + composer + properties.
 *
 * @var object $ticket
 * @var array  $replies
 */
defined('ABSPATH') || exit('NO Access');

$customer_id  = (int) $ticket->from_admin === 1 && $ticket->user_id ? (int) $ticket->user_id : (int) $ticket->creator_id;
$department   = $department_manager->get_a_department($ticket->department_id);
$rating       = (int) $GLOBALS['wpdb']->get_var($GLOBALS['wpdb']->prepare("SELECT rating FROM {$GLOBALS['wpdb']->prefix}tkm_ratings WHERE ticket_id = %d ORDER BY ID DESC LIMIT 1", $ticket->ID));
$product_info = ($ticket->product) ? get_product_name_and_link_by_id($ticket->product) : null;
$edd_title    = ($ticket->edd_product) ? get_the_title($ticket->edd_product) : '';
$canned       = tkm_settings('save_message');
$canned       = is_array($canned) ? array_filter($canned, function ($c) {
    return !empty($c['message_save']);
}) : [];
$ticket_count = (int) $ticket_manager->ticket_count($customer_id);

$tkm_selected_department = (int) $ticket->department_id;
?>
<form method="post" enctype="multipart/form-data" class="tkm-workspace" id="tkm-ticket-form" novalidate>
    <?php wp_nonce_field('ticket_send', 'ticket_nonce'); ?>
    <input type="hidden" name="publish" value="1">

    <div class="tkm-head tkm-head--ticket">
        <div class="tkm-head__text">
            <a class="tkm-back" href="<?php echo esc_url(tkm_admin_url('tkm-tickets')); ?>"><?php echo tkm_icon('arrow-right', 15); ?> همه تیکت‌ها</a>
            <div class="tkm-head__row">
                <h1 class="tkm-head__title"><?php echo esc_html($ticket->title); ?></h1>
                <?php echo tkm_status_badge($ticket->status); ?>
            </div>
            <div class="tkm-head__meta">
                <span class="tkm-num"><?php echo tkm_icon('hash', 14); ?><?php echo esc_html(tkm_fa($ticket->ID)); ?></span>
                <span><?php echo tkm_icon('building', 14); ?><?php echo esc_html($department ? $department->name : '—'); ?></span>
                <span title="<?php echo esc_attr(tkm_date($ticket->create_date)); ?>"><?php echo tkm_icon('calendar', 14); ?>ایجاد <?php echo esc_html(tkm_time_ago($ticket->create_date)); ?></span>
                <span title="<?php echo esc_attr(tkm_date($ticket->reply_date)); ?>"><?php echo tkm_icon('clock', 14); ?>آخرین فعالیت <?php echo esc_html(tkm_time_ago($ticket->reply_date)); ?></span>
            </div>
        </div>
        <div class="tkm-head__actions">
            <button type="submit" class="tkm-btn" data-save><?php echo tkm_icon('check', 16); ?> ذخیره تغییرات</button>
        </div>
    </div>

    <div class="tkm-ws">
        <div class="tkm-ws__main">
            <section class="tkm-thread">
                <article class="tkm-msg <?php echo (int) $ticket->from_admin === 1 ? 'tkm-msg--staff' : ''; ?>" data-msg>
                    <?php echo tkm_avatar($ticket->creator_id); ?>
                    <div class="tkm-msg__card">
                        <header class="tkm-msg__head">
                            <strong><?php echo esc_html(tkm_user_name($ticket->creator_id)); ?></strong>
                            <?php if ((int) $ticket->from_admin === 1) : ?><span class="tkm-role"><?php echo tkm_icon('check-circle', 12); ?> پشتیبانی</span><?php else : ?><span class="tkm-role tkm-role--customer">مشتری</span><?php endif; ?>
                            <time title="<?php echo esc_attr(tkm_date($ticket->create_date)); ?>"><?php echo esc_html(tkm_time_ago($ticket->create_date)); ?></time>
                            <span class="tkm-msg__tools">
                                <button type="button" class="tkm-btn tkm-btn--ghost tkm-btn--sm" data-toggle-edit><?php echo tkm_icon('edit', 14); ?> ویرایش</button>
                            </span>
                        </header>
                        <div class="tkm-msg__body tkm-prose"><?php echo tkm_content($ticket->body); ?></div>
                        <?php if ($ticket->voice) : ?><?php echo tkm_voice_html($ticket->voice); ?><?php endif; ?>
                        <?php echo tkm_files_html($ticket->file); ?>

                        <div class="tkm-msg__edit" data-edit-panel hidden>
                            <div class="tkm-field">
                                <label class="tkm-label" for="ticket-title">عنوان تیکت <span class="tkm-req">*</span></label>
                                <input class="tkm-input" type="text" id="ticket-title" name="ticket-title" value="<?php echo esc_attr($ticket->title); ?>" required>
                            </div>
                            <div class="tkm-field">
                                <label class="tkm-label" for="tkm-content">متن تیکت <span class="tkm-req">*</span></label>
                                <textarea class="tkm-textarea tkm-code" id="tkm-content" name="tkm-content" rows="6"><?php echo esc_textarea($ticket->body); ?></textarea>
                                <span class="tkm-hint">HTML مجاز است.</span>
                            </div>
                            <?php $ticket_files = tkm_get_files($ticket->file); ?>
                            <?php if ($ticket_files) : ?>
                                <div class="tkm-field">
                                    <span class="tkm-label">فایل‌های پیوست</span>
                                    <div class="tkm-file-manage">
                                        <?php foreach ($ticket_files as $tkm_file) : ?>
                                            <label class="tkm-file tkm-file--removable"><input type="checkbox" name="remove_ticket_file[]" value="<?php echo esc_attr($tkm_file); ?>"><?php echo tkm_icon('paperclip', 14); ?><span><?php echo esc_html(tkm_get_file_name($tkm_file)); ?></span><em>حذف</em></label>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            <?php endif; ?>
                            <?php $tkm_file_name = 'tkm_ticket_files'; $tkm_file_label = 'افزودن فایل به پیام اصلی';
                            include TKM_VIEWS_PATH . 'admin/ticket/partials/file-input.php'; ?>
                        </div>
                    </div>
                </article>

                <?php if ($replies) : ?>
                    <div class="tkm-thread__divider"><span><?php echo esc_html(tkm_fa(count($replies))); ?> پاسخ</span></div>
                <?php endif; ?>

                <?php foreach ($replies as $reply) :
                    $is_staff = (int) $reply->from_admin === 1 || ((int) $reply->creator_id !== $customer_id && tkm_is_staff($reply->creator_id));
                    $reply_files = tkm_get_files($reply->file);
                ?>
                    <article class="tkm-msg <?php echo $is_staff ? 'tkm-msg--staff' : ''; ?>" data-msg id="reply-<?php echo esc_attr($reply->ID); ?>">
                        <?php echo tkm_avatar($reply->creator_id); ?>
                        <div class="tkm-msg__card">
                            <header class="tkm-msg__head">
                                <strong><?php echo esc_html(tkm_user_name($reply->creator_id)); ?></strong>
                                <?php if ($is_staff) : ?><span class="tkm-role"><?php echo tkm_icon('check-circle', 12); ?> پشتیبانی</span><?php else : ?><span class="tkm-role tkm-role--customer">مشتری</span><?php endif; ?>
                                <time title="<?php echo esc_attr(tkm_date($reply->create_date)); ?>"><?php echo esc_html(tkm_time_ago($reply->create_date)); ?></time>
                                <span class="tkm-msg__tools">
                                    <button type="button" class="tkm-btn tkm-btn--ghost tkm-btn--sm" data-toggle-edit><?php echo tkm_icon('edit', 14); ?> ویرایش</button>
                                    <button type="button" class="tkm-btn tkm-btn--ghost tkm-btn--sm tkm-btn--icon tkm-danger-btn" data-delete-reply="<?php echo esc_attr($reply->ID); ?>" aria-label="حذف پیام" title="حذف پیام"><?php echo tkm_icon('trash', 14); ?></button>
                                </span>
                            </header>
                            <?php if (trim(wp_strip_all_tags($reply->body)) !== '') : ?>
                                <div class="tkm-msg__body tkm-prose"><?php echo tkm_content($reply->body); ?></div>
                            <?php endif; ?>
                            <?php echo tkm_voice_html($reply->voice); ?>
                            <?php echo tkm_files_html($reply->file); ?>

                            <div class="tkm-msg__edit" data-edit-panel hidden>
                                <textarea class="tkm-textarea tkm-code" name="tkm-reply-body-<?php echo esc_attr($reply->ID); ?>" id="tkm-reply-body-<?php echo esc_attr($reply->ID); ?>" rows="4" aria-label="متن پیام"><?php echo esc_textarea($reply->body); ?></textarea>
                                <?php if ($reply_files) : ?>
                                    <div class="tkm-file-manage">
                                        <?php foreach ($reply_files as $reply_file) : ?>
                                            <label class="tkm-file tkm-file--removable"><input type="checkbox" name="remove_reply_file_<?php echo esc_attr($reply->ID); ?>[]" value="<?php echo esc_attr($reply_file); ?>"><?php echo tkm_icon('paperclip', 14); ?><span><?php echo esc_html(tkm_get_file_name($reply_file)); ?></span><em>حذف</em></label>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                                <?php $tkm_file_name = 'tkm_reply_files_' . $reply->ID; $tkm_file_label = 'افزودن فایل به این پیام';
                                include TKM_VIEWS_PATH . 'admin/ticket/partials/file-input.php'; ?>
                                <p class="tkm-hint">برای اعمال ویرایش، «ذخیره تغییرات» را بزنید.</p>
                            </div>
                        </div>
                    </article>
                <?php endforeach; ?>
            </section>

            <section class="tkm-card tkm-reply-box" id="tkm-reply">
                <div class="tkm-card__head">
                    <div class="tkm-card__title"><?php echo tkm_icon('reply', 18); ?> پاسخ به <?php echo esc_html(tkm_user_name($customer_id)); ?></div>
                    <?php if ($canned) : ?>
                        <select class="tkm-select tkm-select--sm" id="save_select" aria-label="پاسخ آماده">
                            <option value="">⚡ پاسخ‌های آماده</option>
                            <?php foreach ($canned as $item) : ?>
                                <option value="<?php echo esc_attr($item['message_save']); ?>"><?php echo esc_html($item['save_message_title'] ?: wp_trim_words($item['message_save'], 6)); ?></option>
                            <?php endforeach; ?>
                        </select>
                    <?php endif; ?>
                </div>
                <div class="tkm-card__body tkm-stack">
                    <div class="tkm-editor">
                        <?php wp_editor('', 'reply_content', [
                            'editor_height' => 180,
                            'media_buttons' => false,
                            'teeny'         => true,
                            'quicktags'     => ['buttons' => 'strong,em,link,ul,ol,li,code'],
                            'tinymce'       => ['toolbar1' => 'bold,italic,underline,bullist,numlist,link,unlink,undo,redo', 'directionality' => 'rtl'],
                        ]); ?>
                    </div>
                    <?php $tkm_file_name = 'tkm_reply_files'; $tkm_file_label = 'پیوست فایل به پاسخ';
                    include TKM_VIEWS_PATH . 'admin/ticket/partials/file-input.php'; ?>
                </div>
                <div class="tkm-card__foot tkm-reply-box__foot">
                    <span class="tkm-hint"><?php echo tkm_icon('info', 14); ?> پس از ارسال، وضعیت تیکت به «پاسخ داده» تغییر می‌کند.</span>
                    <div class="tkm-row">
                        <button type="submit" class="tkm-btn" data-reply-close><?php echo tkm_icon('check-circle', 16); ?> ارسال و بستن</button>
                        <button type="submit" class="tkm-btn tkm-btn--primary" data-reply-send><?php echo tkm_icon('send', 16); ?> ارسال پاسخ</button>
                    </div>
                </div>
            </section>
        </div>

        <aside class="tkm-ws__side">
            <section class="tkm-card">
                <div class="tkm-card__head"><div class="tkm-card__title">مشخصات تیکت</div></div>
                <div class="tkm-card__body tkm-stack">
                    <div class="tkm-field">
                        <label class="tkm-label" for="status">وضعیت</label>
                        <select class="tkm-select" name="status" id="status">
                            <?php foreach ($statuses as $_status) : ?>
                                <option value="<?php echo esc_attr($_status['slug']); ?>" <?php selected($ticket->status, $_status['slug']); ?>><?php echo esc_html(trim($_status['name'])); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="tkm-field">
                        <span class="tkm-label">اولویت</span>
                        <div class="tkm-segmented tkm-segmented--full">
                            <?php foreach (['low' => 'کم', 'medium' => 'متوسط', 'high' => 'زیاد'] as $value => $label) : ?>
                                <label><input type="radio" name="priority" value="<?php echo esc_attr($value); ?>" <?php checked($ticket->priority, $value); ?>><span><?php echo tkm_priority_badge($value); ?></span></label>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <div class="tkm-field">
                        <label class="tkm-label" for="tkm-department">دپارتمان</label>
                        <?php include TKM_VIEWS_PATH . 'admin/ticket/partials/department-select.php'; ?>
                    </div>
                    <div class="tkm-field">
                        <label class="tkm-label" for="date_ticket">تاریخ ایجاد</label>
                        <input class="tkm-input tkm-ltr" type="datetime-local" id="date_ticket" name="date_ticket" value="<?php echo esc_attr(wp_date('Y-m-d\TH:i', strtotime($ticket->create_date))); ?>">
                        <span class="tkm-hint"><?php echo esc_html(tkm_date($ticket->create_date)); ?></span>
                    </div>
                </div>
            </section>

            <section class="tkm-card">
                <div class="tkm-card__head"><div class="tkm-card__title">مشتری</div></div>
                <div class="tkm-card__body">
                    <div class="tkm-person">
                        <?php echo tkm_avatar($customer_id, 'lg'); ?>
                        <div class="tkm-grow">
                            <strong><?php echo esc_html(tkm_user_name($customer_id)); ?></strong>
                            <?php $customer = get_userdata($customer_id); ?>
                            <span class="tkm-muted tkm-ltr tkm-truncate"><?php echo esc_html($customer ? $customer->user_email : ''); ?></span>
                        </div>
                    </div>
                    <div class="tkm-person__links">
                        <a class="tkm-btn tkm-btn--sm" href="<?php echo esc_url(tkm_admin_url('tkm-tickets', ['creator_id' => $customer_id])); ?>"><?php echo tkm_icon('inbox', 14); ?> <?php echo esc_html(tkm_fa($ticket_count)); ?> تیکت</a>
                        <?php if ($customer) : ?>
                            <a class="tkm-btn tkm-btn--sm tkm-btn--ghost" target="_blank" href="<?php echo esc_url(get_edit_user_link($customer_id)); ?>"><?php echo tkm_icon('external', 14); ?> پروفایل</a>
                        <?php endif; ?>
                    </div>

                    <details class="tkm-advanced">
                        <summary>تغییر ایجادکننده / گیرنده</summary>
                        <div class="tkm-stack">
                            <div class="tkm-field">
                                <label class="tkm-label" for="ticket-creator">ایجاد کننده</label>
                                <select class="tkm-select tkm-user-search" name="creator-id[]" id="ticket-creator">
                                    <?php if ($ticket->creator_id) : ?><option value="<?php echo esc_attr($ticket->creator_id); ?>" selected><?php echo esc_html(tkm_user_name($ticket->creator_id)); ?></option><?php endif; ?>
                                </select>
                            </div>
                            <div class="tkm-field">
                                <label class="tkm-label" for="ticket-user-id">گیرنده (تیکت‌های ارسالی پشتیبانی)</label>
                                <select class="tkm-select tkm-user-search" name="user-id[]" id="ticket-user-id" data-placeholder="بدون گیرنده">
                                    <option value="">بدون گیرنده</option>
                                    <?php if ($ticket->user_id) : ?><option value="<?php echo esc_attr($ticket->user_id); ?>" selected><?php echo esc_html(tkm_user_name($ticket->user_id)); ?></option><?php endif; ?>
                                </select>
                            </div>
                        </div>
                    </details>
                </div>
            </section>

            <?php if ($rating || $product_info || $edd_title) : ?>
                <section class="tkm-card">
                    <dl class="tkm-kv">
                        <?php if ($rating) : ?>
                            <div><dt>امتیاز کاربر</dt><dd><span class="tkm-stars tkm-stars--static"><?php for ($s = 1; $s <= 5; $s++) : ?><span class="<?php echo $s <= $rating ? 'is-on' : ''; ?>"><?php echo tkm_icon('star', 14); ?></span><?php endfor; ?></span></dd></div>
                        <?php endif; ?>
                        <?php if ($product_info) : ?>
                            <div><dt>محصول</dt><dd><?php if ($product_info['link']) : ?><a href="<?php echo esc_url($product_info['link']); ?>" target="_blank"><?php echo esc_html($product_info['name']); ?></a><?php else : echo esc_html($product_info['name']); endif; ?></dd></div>
                        <?php endif; ?>
                        <?php if ($edd_title) : ?>
                            <div><dt>محصول دیجیتال</dt><dd><a href="<?php echo esc_url(get_permalink($ticket->edd_product)); ?>" target="_blank"><?php echo esc_html($edd_title); ?></a></dd></div>
                        <?php endif; ?>
                    </dl>
                </section>
            <?php endif; ?>

            <section class="tkm-card tkm-note-card">
                <div class="tkm-card__head"><div class="tkm-card__title"><?php echo tkm_icon('note', 16); ?> یادداشت داخلی</div><span class="tkm-card__sub">فقط برای تیم</span></div>
                <div class="tkm-card__body">
                    <textarea class="tkm-textarea" name="note" id="note-ticket" rows="3" placeholder="یادداشتی برای همکاران بنویسید…"><?php echo esc_textarea($ticket->note); ?></textarea>
                </div>
            </section>

            <button type="submit" class="tkm-btn tkm-btn--primary tkm-btn--block tkm-btn--lg" data-save><?php echo tkm_icon('check', 16); ?> ذخیره تغییرات</button>
        </aside>
    </div>
</form>
