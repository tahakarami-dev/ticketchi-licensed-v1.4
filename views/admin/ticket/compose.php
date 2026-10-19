<?php
/**
 * Send a ticket from staff to one or more users.
 */
defined('ABSPATH') || exit('NO Access');

// Re-populate after a failed validation (no redirect happens in that case).
$old = $_SERVER['REQUEST_METHOD'] === 'POST' ? wp_unslash($_POST) : [];
$old_users = !empty($old['user-id']) ? array_filter(array_map('absint', (array) $old['user-id'])) : [];
$tkm_selected_department = isset($old['department_id']) ? absint($old['department_id']) : 0;
$old_priority = isset($old['priority']) ? $old['priority'] : 'medium';
$old_status = isset($old['status']) ? $old['status'] : 'open';

$tkm_head_title = 'ارسال تیکت به کاربر';
$tkm_head_desc = 'برای یک یا چند کاربر، تیکت پشتیبانی جدید ایجاد کنید. هر گیرنده تیکت جداگانه‌ای دریافت می‌کند.';
$tkm_head_back = tkm_admin_url('tkm-tickets');
include TKM_VIEWS_PATH . 'admin/partials/page-head.php';
?>
<form method="post" enctype="multipart/form-data" class="tkm-ws" id="tkm-compose-form" novalidate>
    <?php wp_nonce_field('ticket_send', 'ticket_nonce'); ?>
    <input type="hidden" name="publish" value="1">

    <div class="tkm-ws__main">
        <section class="tkm-card">
            <div class="tkm-card__body tkm-stack">
                <div class="tkm-field">
                    <label class="tkm-label" for="ticket-user-id">گیرندگان <span class="tkm-req">*</span></label>
                    <select class="tkm-select tkm-user-search" name="user-id[]" id="ticket-user-id" multiple data-placeholder="نام، نام کاربری یا ایمیل را جستجو کنید…">
                        <?php foreach ($old_users as $uid) : ?>
                            <option value="<?php echo esc_attr($uid); ?>" selected><?php echo esc_html(tkm_user_name($uid)); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="tkm-field">
                    <label class="tkm-label" for="ticket-title">عنوان <span class="tkm-req">*</span></label>
                    <input class="tkm-input tkm-input--lg" type="text" id="ticket-title" name="ticket-title" value="<?php echo esc_attr(isset($old['ticket-title']) ? $old['ticket-title'] : ''); ?>" placeholder="مثلا: اطلاعیه به‌روزرسانی سرویس" required>
                </div>
                <div class="tkm-field">
                    <label class="tkm-label" for="tkm-content">متن پیام <span class="tkm-req">*</span></label>
                    <div class="tkm-editor">
                        <?php wp_editor(isset($old['tkm-content']) ? $old['tkm-content'] : '', 'tkm-content', [
                            'editor_height' => 240,
                            'media_buttons' => true,
                            'quicktags'     => true,
                            'tinymce'       => ['directionality' => 'rtl'],
                        ]); ?>
                    </div>
                </div>
                <?php $tkm_file_name = 'tkm_ticket_files'; $tkm_file_label = 'پیوست فایل';
                include TKM_VIEWS_PATH . 'admin/ticket/partials/file-input.php'; ?>
            </div>
        </section>
    </div>

    <aside class="tkm-ws__side">
        <section class="tkm-card">
            <div class="tkm-card__head"><div class="tkm-card__title">تنظیمات ارسال</div></div>
            <div class="tkm-card__body tkm-stack">
                <div class="tkm-field">
                    <label class="tkm-label" for="tkm-department">دپارتمان <span class="tkm-req">*</span></label>
                    <?php include TKM_VIEWS_PATH . 'admin/ticket/partials/department-select.php'; ?>
                </div>
                <div class="tkm-field">
                    <label class="tkm-label" for="status">وضعیت اولیه</label>
                    <select class="tkm-select" name="status" id="status">
                        <?php foreach ($statuses as $_status) :
                            if ($_status['slug'] === 'trash') continue; ?>
                            <option value="<?php echo esc_attr($_status['slug']); ?>" <?php selected($old_status, $_status['slug']); ?>><?php echo esc_html(trim($_status['name'])); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="tkm-field">
                    <span class="tkm-label">اولویت</span>
                    <div class="tkm-segmented tkm-segmented--full">
                        <?php foreach (['low' => 'کم', 'medium' => 'متوسط', 'high' => 'زیاد'] as $value => $label) : ?>
                            <label><input type="radio" name="priority" value="<?php echo esc_attr($value); ?>" <?php checked($old_priority, $value); ?>><span><?php echo tkm_priority_badge($value); ?></span></label>
                        <?php endforeach; ?>
                    </div>
                </div>
                <div class="tkm-field">
                    <label class="tkm-label" for="note-ticket">یادداشت داخلی</label>
                    <textarea class="tkm-textarea" name="note" id="note-ticket" rows="3" placeholder="فقط برای تیم پشتیبانی قابل مشاهده است"><?php echo esc_textarea(isset($old['note']) ? $old['note'] : ''); ?></textarea>
                </div>
                <label class="tkm-switch">
                    <input type="checkbox" name="send-copy" id="send-copy" <?php checked(!empty($old['send-copy'])); ?>>
                    <span class="tkm-switch__track"></span>
                    ارسال پیامک اطلاع‌رسانی
                </label>
            </div>
            <div class="tkm-card__foot">
                <button type="submit" class="tkm-btn tkm-btn--primary tkm-btn--block tkm-btn--lg" id="publish"><?php echo tkm_icon('send', 16); ?> ارسال تیکت</button>
            </div>
        </section>
    </aside>
</form>
