<?php
defined('ABSPATH') || exit('NO Access');

if (!is_user_logged_in()) {
    include TKM_VIEWS_PATH . 'front/partials/login-required.php';
    return;
}

$user_id = get_current_user_id();

$type     = isset($_GET['type']) ? sanitize_key($_GET['type']) : 'all';
$status   = isset($_GET['status']) ? sanitize_key($_GET['status']) : 'all';
$orderby  = isset($_GET['orderby']) ? sanitize_key($_GET['orderby']) : 'reply-date';
$page_num = isset($_GET['page_num']) ? max(1, absint($_GET['page_num'])) : 1;
$priority = isset($_GET['priority']) ? sanitize_key($_GET['priority']) : 'all-priority';

$type     = in_array($type, ['all', 'send', 'get'], true) ? $type : 'all';
$orderby  = in_array($orderby, ['reply-date', 'create-date'], true) ? $orderby : 'reply-date';
$priority = in_array($priority, ['all-priority', 'low', 'medium', 'high'], true) ? $priority : 'all-priority';

$ticket_manager = new TKM_Ticket_Manager();
$tickets        = $ticket_manager->get_tickets($user_id, $type, $status, $orderby, $page_num, $priority);
$total_count    = (int) $ticket_manager->ticket_count($user_id, $type, $status, $priority);
$all_count      = (int) $ticket_manager->ticket_count($user_id, NULL, NULL);
$per_page       = 10;
$total_pages    = (int) ceil($total_count / $per_page);

$department_manager = new TKM_Front_Department_Manager();
$statuses = tkm_get_status();

// Reply counts for the visible tickets in a single query.
$reply_counts = [];
if ($tickets) {
    global $wpdb;
    $ids = array_map('intval', wp_list_pluck($tickets, 'ID'));
    $rows = $wpdb->get_results("SELECT ticket_id, COUNT(*) AS total FROM {$wpdb->prefix}tkm_replies WHERE ticket_id IN (" . implode(',', $ids) . ") GROUP BY ticket_id");
    foreach ($rows as $row) {
        $reply_counts[(int) $row->ticket_id] = (int) $row->total;
    }
}

$list_url = tkm_url('list');
$filter_url = function ($args) use ($type, $status, $orderby, $priority, $list_url) {
    $query = array_merge(['type' => $type, 'status' => $status, 'orderby' => $orderby, 'priority' => $priority], $args);
    $query = array_filter($query, function ($v, $k) {
        return !in_array($v, ['all', 'all-priority', 'reply-date'], true);
    }, ARRAY_FILTER_USE_BOTH);
    return add_query_arg($query, remove_query_arg(['type', 'status', 'orderby', 'priority', 'page_num'], $list_url));
};

$title = tkm_settings('title_dashboard') ? tkm_settings('title_dashboard') : 'داشبورد تیکت‌ها';
$description = tkm_settings('description_dashboard') ? tkm_settings('description_dashboard') : 'مدیریت تیکت‌های پشتیبانی شما';
$has_filters = $type !== 'all' || $status !== 'all' || $priority !== 'all-priority';
$current_user = wp_get_current_user();
?>
<div class="tkm-ui tkm-app" data-view="list">

    <header class="tkm-hero">
        <div class="tkm-hero__text">
            <span class="tkm-eyebrow"><?php echo tkm_icon('sparkles', 14); ?> سلام <?php echo esc_html($current_user->display_name); ?></span>
            <h2 class="tkm-hero__title"><?php echo esc_html($title); ?></h2>
            <p class="tkm-hero__desc"><?php echo esc_html($description); ?></p>
        </div>
        <a href="<?php echo esc_url(tkm_url('new')); ?>" class="tkm-btn tkm-btn--primary tkm-btn--lg">
            <?php echo tkm_icon('plus', 18); ?> ثبت تیکت جدید
        </a>
    </header>

    <nav class="tkm-stats" aria-label="وضعیت تیکت‌ها">
        <a class="tkm-stat <?php echo $status === 'all' ? 'is-active' : ''; ?>" href="<?php echo esc_url($filter_url(['status' => 'all'])); ?>" style="--c: var(--tkm-primary)">
            <span class="tkm-stat__icon"><?php echo tkm_icon('inbox', 18); ?></span>
            <span class="tkm-stat__label">همه تیکت‌ها</span>
            <strong class="tkm-stat__value"><?php echo esc_html(tkm_fa($all_count)); ?></strong>
        </a>
        <?php foreach ($statuses as $item) : ?>
            <a class="tkm-stat <?php echo $status === $item['slug'] ? 'is-active' : ''; ?>" href="<?php echo esc_url($filter_url(['status' => $item['slug']])); ?>" style="--c: <?php echo esc_attr($item['color'] ?: '#8a94a6'); ?>">
                <span class="tkm-stat__icon"><?php echo tkm_icon('ticket', 18); ?></span>
                <span class="tkm-stat__label"><?php echo esc_html(trim($item['name'])); ?></span>
                <strong class="tkm-stat__value"><?php echo esc_html(tkm_fa($ticket_manager->ticket_count($user_id, NULL, $item['slug']))); ?></strong>
            </a>
        <?php endforeach; ?>
    </nav>

    <section class="tkm-card tkm-list-card">
        <form class="tkm-toolbar" method="get" action="<?php echo esc_url(remove_query_arg(['type', 'status', 'orderby', 'priority', 'page_num'], $list_url)); ?>" data-autosubmit>
            <?php
            // Keep query args that belong to the host page (e.g. ?page_id=12 on plain permalinks).
            $base_query = wp_parse_url($list_url, PHP_URL_QUERY);
            if ($base_query) {
                parse_str($base_query, $base_args);
                foreach ($base_args as $key => $value) {
                    if (is_scalar($value) && !in_array($key, ['type', 'status', 'orderby', 'priority', 'page_num'], true)) {
                        echo '<input type="hidden" name="' . esc_attr($key) . '" value="' . esc_attr($value) . '">';
                    }
                }
            }
            ?>
            <div class="tkm-segmented" role="radiogroup" aria-label="نوع تیکت">
                <label><input type="radio" name="type" value="all" <?php checked($type, 'all'); ?>><span>همه</span></label>
                <label><input type="radio" name="type" value="send" <?php checked($type, 'send'); ?>><span><?php echo tkm_icon('send', 14); ?> ارسالی</span></label>
                <label><input type="radio" name="type" value="get" <?php checked($type, 'get'); ?>><span><?php echo tkm_icon('inbox', 14); ?> دریافتی</span></label>
            </div>

            <div class="tkm-toolbar__filters">
                <select class="tkm-select tkm-select--sm" name="status" aria-label="وضعیت">
                    <option value="all" <?php selected($status, 'all'); ?>>همه وضعیت‌ها</option>
                    <?php foreach ($statuses as $_status) : ?>
                        <option value="<?php echo esc_attr($_status['slug']); ?>" <?php selected($status, $_status['slug']); ?>><?php echo esc_html(trim($_status['name'])); ?></option>
                    <?php endforeach; ?>
                </select>
                <select class="tkm-select tkm-select--sm" name="priority" aria-label="اولویت">
                    <option value="all-priority" <?php selected($priority, 'all-priority'); ?>>همه اولویت‌ها</option>
                    <option value="low" <?php selected($priority, 'low'); ?>>اولویت کم</option>
                    <option value="medium" <?php selected($priority, 'medium'); ?>>اولویت متوسط</option>
                    <option value="high" <?php selected($priority, 'high'); ?>>اولویت زیاد</option>
                </select>
                <select class="tkm-select tkm-select--sm" name="orderby" aria-label="مرتب‌سازی">
                    <option value="reply-date" <?php selected($orderby, 'reply-date'); ?>>آخرین فعالیت</option>
                    <option value="create-date" <?php selected($orderby, 'create-date'); ?>>تاریخ ایجاد</option>
                </select>
                <noscript><button type="submit" class="tkm-btn tkm-btn--sm">اعمال</button></noscript>
                <?php if ($has_filters) : ?>
                    <a class="tkm-btn tkm-btn--ghost tkm-btn--sm" href="<?php echo esc_url($list_url); ?>"><?php echo tkm_icon('x', 14); ?> حذف فیلترها</a>
                <?php endif; ?>
            </div>
        </form>

        <?php if ($tickets) : ?>
            <ul class="tkm-tickets">
                <?php foreach ($tickets as $ticket) :
                    $department = $department_manager->get_department($ticket->department_id);
                    $replies = isset($reply_counts[(int) $ticket->ID]) ? $reply_counts[(int) $ticket->ID] : 0;
                    $incoming = (int) $ticket->from_admin === 1 && (int) $ticket->user_id === $user_id;
                ?>
                    <li>
                        <a class="tkm-ticket" href="<?php echo esc_url(tkm_url('single', $ticket->ID)); ?>">
                            <span class="tkm-ticket__icon" style="--c: <?php echo esc_attr(get_status_color($ticket->status)); ?>">
                                <?php echo tkm_icon($incoming ? 'inbox' : 'message', 18); ?>
                            </span>
                            <span class="tkm-ticket__main">
                                <span class="tkm-ticket__title"><?php echo esc_html($ticket->title); ?></span>
                                <span class="tkm-ticket__meta">
                                    <span class="tkm-num"><?php echo esc_html(tkm_ticket_number($ticket->ID)); ?></span>
                                    <span class="tkm-dot"></span>
                                    <span><?php echo esc_html($department ? $department->name : '—'); ?></span>
                                    <span class="tkm-dot"></span>
                                    <span title="<?php echo esc_attr(tkm_date($ticket->reply_date)); ?>"><?php echo esc_html(tkm_time_ago($ticket->reply_date)); ?></span>
                                    <?php if ($incoming) : ?>
                                        <span class="tkm-badge tkm-badge--plain tkm-badge--incoming">از طرف پشتیبانی</span>
                                    <?php endif; ?>
                                </span>
                            </span>
                            <span class="tkm-ticket__side">
                                <?php echo tkm_status_badge($ticket->status); ?>
                                <span class="tkm-ticket__aux">
                                    <?php echo tkm_priority_badge($ticket->priority); ?>
                                    <span class="tkm-ticket__replies" title="تعداد پاسخ‌ها"><?php echo tkm_icon('messages', 14); ?><?php echo esc_html(tkm_fa($replies)); ?></span>
                                </span>
                            </span>
                            <span class="tkm-ticket__chev"><?php echo tkm_icon('chevron-left', 18); ?></span>
                        </a>
                    </li>
                <?php endforeach; ?>
            </ul>

            <?php if ($total_pages > 1) : ?>
                <nav class="tkm-pagination" aria-label="صفحه‌بندی">
                    <?php
                    echo paginate_links([
                        'base'      => add_query_arg('page_num', '%#%'),
                        'format'    => '',
                        'current'   => $page_num,
                        'total'     => $total_pages,
                        'prev_text' => tkm_icon('chevron-right', 16) . '<span class="tkm-sr">قبلی</span>',
                        'next_text' => tkm_icon('chevron-left', 16) . '<span class="tkm-sr">بعدی</span>',
                        'type'      => 'plain',
                        'before_page_number' => '',
                    ]);
                    ?>
                    <span class="tkm-pagination__info"><?php echo esc_html('صفحه ' . tkm_fa($page_num) . ' از ' . tkm_fa($total_pages)); ?></span>
                </nav>
            <?php endif; ?>
        <?php else : ?>
            <div class="tkm-empty">
                <div class="tkm-empty__art"><?php echo tkm_icon($has_filters ? 'search' : 'inbox', 30); ?></div>
                <h3 class="tkm-empty__title"><?php echo $has_filters ? 'تیکتی با این فیلترها پیدا نشد' : 'هنوز تیکتی ثبت نکرده‌اید'; ?></h3>
                <p class="tkm-empty__text"><?php echo $has_filters ? 'فیلترها را تغییر دهید یا همه تیکت‌ها را ببینید.' : 'هر سوال یا مشکلی دارید، تیم پشتیبانی آماده کمک به شماست.'; ?></p>
                <?php if ($has_filters) : ?>
                    <a class="tkm-btn" href="<?php echo esc_url($list_url); ?>">نمایش همه تیکت‌ها</a>
                <?php else : ?>
                    <a class="tkm-btn tkm-btn--primary" href="<?php echo esc_url(tkm_url('new')); ?>"><?php echo tkm_icon('plus', 16); ?> ثبت اولین تیکت</a>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </section>
</div>
