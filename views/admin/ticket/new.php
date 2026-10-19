<?php
/**
 * Entry point used by TKM_MENU for both «ارسال تیکت» and «ویرایش تیکت».
 *
 * @var bool        $is_edit
 * @var object|null $ticket   (edit)
 * @var array       $replies  (edit)
 */
defined('ABSPATH') || exit('NO Access');

$statuses           = tkm_get_status();
$department_manager = new TKM_Admin_Department_Manager();
$parent_departments = $department_manager->get_parent_department();
$ticket_manager     = new TKM_Ticket_Manager();

include TKM_VIEWS_PATH . 'admin/partials/header.php';

if ($is_edit) {
    if (empty($ticket)) {
        echo '<div class="tkm-card tkm-empty"><div class="tkm-empty__art">' . tkm_icon('search', 30) . '</div><h3 class="tkm-empty__title">تیکت پیدا نشد</h3><p class="tkm-empty__text">ابتدا یک تیکت را از فهرست تیکت‌ها انتخاب کنید.</p><a class="tkm-btn tkm-btn--primary" href="' . esc_url(tkm_admin_url('tkm-tickets')) . '">' . tkm_icon('inbox', 16) . ' فهرست تیکت‌ها</a></div>';
    } else {
        include TKM_VIEWS_PATH . 'admin/ticket/workspace.php';
    }
} else {
    include TKM_VIEWS_PATH . 'admin/ticket/compose.php';
}

include TKM_VIEWS_PATH . 'admin/partials/footer.php';
