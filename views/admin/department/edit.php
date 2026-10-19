<?php
/**
 * @var object|null $showdepartment
 * @var int[]       $answerable
 * @var array       $departments
 */
defined('ABSPATH') || exit('NO Access');

include TKM_VIEWS_PATH . 'admin/partials/header.php';

if (!$showdepartment) {
    echo '<div class="tkm-card tkm-empty"><div class="tkm-empty__art">' . tkm_icon('search', 30) . '</div><h3 class="tkm-empty__title">دپارتمان پیدا نشد</h3><a class="tkm-btn" href="' . esc_url(tkm_admin_url('tkm-departments')) . '">بازگشت به دپارتمان‌ها</a></div>';
} else {
    $tkm_head_title = 'ویرایش «' . esc_html($showdepartment->name) . '»';
    $tkm_head_back = tkm_admin_url('tkm-departments');
    include TKM_VIEWS_PATH . 'admin/partials/page-head.php';
    echo '<div class="tkm-narrow">';
    include TKM_VIEWS_PATH . 'admin/department/form.php';
    echo '</div>';
}

include TKM_VIEWS_PATH . 'admin/partials/footer.php';
