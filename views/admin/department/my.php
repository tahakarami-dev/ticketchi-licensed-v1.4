<?php
/**
 * Tickets of the departments the current user answers for.
 */
defined('ABSPATH') || exit('NO Access');

$tickets = tkm_get_user_tickets_by_department(get_current_user_id());
$filter = isset($_GET['queue']) && $_GET['queue'] === 'all' ? 'all' : 'active';
$active = array_values(array_filter($tickets, function ($t) {
    return !in_array($t['status'], ['closed', 'finish'], true);
}));
$list = $filter === 'all' ? $tickets : $active;
$department_manager = new TKM_Front_Department_Manager();

include TKM_VIEWS_PATH . 'admin/partials/header.php';

$tkm_head_title = 'صف من';
$tkm_head_desc = 'تیکت‌های دپارتمان‌هایی که شما کارشناس پاسخگوی آن‌ها هستید';
include TKM_VIEWS_PATH . 'admin/partials/page-head.php';
?>
<nav class="tkm-tabs">
    <a class="tkm-tab <?php echo $filter === 'active' ? 'is-active' : ''; ?>" href="<?php echo esc_url(tkm_admin_url('tkm-my-departments')); ?>">نیازمند رسیدگی <span class="tkm-tab__count tkm-num"><?php echo esc_html(tkm_fa(count($active))); ?></span></a>
    <a class="tkm-tab <?php echo $filter === 'all' ? 'is-active' : ''; ?>" href="<?php echo esc_url(tkm_admin_url('tkm-my-departments', ['queue' => 'all'])); ?>">همه <span class="tkm-tab__count tkm-num"><?php echo esc_html(tkm_fa(count($tickets))); ?></span></a>
</nav>

<section class="tkm-card">
    <?php if ($list) : ?>
        <div class="tkm-table-wrap">
            <table class="tkm-table tkm-table--rows">
                <thead>
                    <tr><th>تیکت</th><th>کاربر</th><th>دپارتمان</th><th>وضعیت</th><th>اولویت</th><th>آخرین فعالیت</th></tr>
                </thead>
                <tbody>
                    <?php foreach ($list as $ticket) :
                        $url = tkm_admin_url('tkm-edit-ticket', ['id' => $ticket['ID']]);
                        $person = (int) $ticket['from_admin'] === 1 && $ticket['user_id'] ? (int) $ticket['user_id'] : (int) $ticket['creator_id'];
                        $dept = $department_manager->get_department($ticket['department_id']); ?>
                        <tr data-href="<?php echo esc_url($url); ?>">
                            <td><div class="tkm-title-cell"><a class="tkm-title-cell__title" href="<?php echo esc_url($url); ?>"><?php echo esc_html($ticket['title']); ?></a><span class="tkm-title-cell__meta tkm-num"><?php echo esc_html(tkm_ticket_number($ticket['ID'])); ?></span></div></td>
                            <td><span class="tkm-user-cell"><?php echo tkm_avatar($person, 'sm'); ?><span class="tkm-user-cell__name"><?php echo esc_html(tkm_user_name($person)); ?></span></span></td>
                            <td><?php echo esc_html($dept ? $dept->name : '—'); ?></td>
                            <td><?php echo tkm_status_badge($ticket['status']); ?></td>
                            <td><?php echo tkm_priority_badge($ticket['priority']); ?></td>
                            <td><span class="tkm-date-cell" title="<?php echo esc_attr(tkm_date($ticket['reply_date'])); ?>"><?php echo esc_html(tkm_time_ago($ticket['reply_date'])); ?></span></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php else : ?>
        <div class="tkm-empty">
            <div class="tkm-empty__art"><?php echo tkm_icon('check-circle', 30); ?></div>
            <h3 class="tkm-empty__title"><?php echo $tickets ? 'همه تیکت‌ها رسیدگی شده‌اند' : 'صف شما خالی است'; ?></h3>
            <p class="tkm-empty__text"><?php echo $tickets ? 'تیکت فعالی در دپارتمان‌های شما باقی نمانده است.' : 'شما هنوز کارشناس هیچ دپارتمانی نیستید. از بخش دپارتمان‌ها می‌توانید کارشناسان را تعیین کنید.'; ?></p>
        </div>
    <?php endif; ?>
</section>
<?php include TKM_VIEWS_PATH . 'admin/partials/footer.php'; ?>
