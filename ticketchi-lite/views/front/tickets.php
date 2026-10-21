<?php
/**
 * Customer panel — ticket list.
 */
defined('ABSPATH') || exit;

use TicketchiLite\Departments;
use TicketchiLite\Settings;
use TicketchiLite\Statuses;
use TicketchiLite\Tickets;

$ticketchi_lite_kses = ticketchi_lite_allowed_html();
$ticketchi_lite_user = wp_get_current_user();

// phpcs:disable WordPress.Security.NonceVerification.Recommended -- read-only filters.
$ticketchi_lite_status = isset($_GET['status']) ? sanitize_key(wp_unslash($_GET['status'])) : 'all';
$ticketchi_lite_priority = isset($_GET['priority']) ? sanitize_key(wp_unslash($_GET['priority'])) : 'all-priority';
$ticketchi_lite_orderby = isset($_GET['orderby']) ? sanitize_key(wp_unslash($_GET['orderby'])) : 'reply-date';
$ticketchi_lite_page = isset($_GET['page_num']) ? max(1, absint($_GET['page_num'])) : 1;
// phpcs:enable

$ticketchi_lite_statuses = Statuses::all();
if ($ticketchi_lite_status !== 'all' && !in_array($ticketchi_lite_status, wp_list_pluck($ticketchi_lite_statuses, 'slug'), true)) {
    $ticketchi_lite_status = 'all';
}
$ticketchi_lite_priority = in_array($ticketchi_lite_priority, ['all-priority', 'low', 'medium', 'high'], true) ? $ticketchi_lite_priority : 'all-priority';
$ticketchi_lite_orderby = in_array($ticketchi_lite_orderby, ['reply-date', 'create-date'], true) ? $ticketchi_lite_orderby : 'reply-date';

$ticketchi_lite_result = Tickets::for_customer($ticketchi_lite_user->ID, [
    'status'   => $ticketchi_lite_status,
    'priority' => $ticketchi_lite_priority,
    'orderby'  => $ticketchi_lite_orderby,
    'page'     => $ticketchi_lite_page,
]);
$ticketchi_lite_counts = Tickets::customer_counts($ticketchi_lite_user->ID);
$ticketchi_lite_replies = Tickets::reply_counts(wp_list_pluck($ticketchi_lite_result['items'], 'ID'));

$ticketchi_lite_list_url = ticketchi_lite_url('list');
$ticketchi_lite_filter_keys = ['status', 'orderby', 'priority', 'page_num'];
$ticketchi_lite_filter_url = function ($args) use ($ticketchi_lite_status, $ticketchi_lite_orderby, $ticketchi_lite_priority, $ticketchi_lite_list_url, $ticketchi_lite_filter_keys) {
    $query = array_merge(['status' => $ticketchi_lite_status, 'orderby' => $ticketchi_lite_orderby, 'priority' => $ticketchi_lite_priority], $args);
    $query = array_filter($query, function ($v) {
        return !in_array($v, ['all', 'all-priority', 'reply-date'], true);
    });
    return add_query_arg($query, remove_query_arg($ticketchi_lite_filter_keys, $ticketchi_lite_list_url));
};
$ticketchi_lite_has_filters = $ticketchi_lite_status !== 'all' || $ticketchi_lite_priority !== 'all-priority';
?>
<div class="tkm-ui tkm-app" data-view="list">

    <header class="tkm-hero">
        <div class="tkm-hero__text">
            <span class="tkm-eyebrow"><?php echo wp_kses(ticketchi_lite_icon('sparkles', 14), $ticketchi_lite_kses); ?> سلام <?php echo esc_html($ticketchi_lite_user->display_name); ?></span>
            <h2 class="tkm-hero__title"><?php echo esc_html(Settings::get('title_dashboard')); ?></h2>
            <p class="tkm-hero__desc"><?php echo esc_html(Settings::get('description_dashboard')); ?></p>
        </div>
        <a href="<?php echo esc_url(ticketchi_lite_url('new')); ?>" class="tkm-btn tkm-btn--primary tkm-btn--lg">
            <?php echo wp_kses(ticketchi_lite_icon('plus', 18), $ticketchi_lite_kses); ?> ثبت تیکت جدید
        </a>
    </header>

    <nav class="tkm-stats" aria-label="وضعیت تیکت‌ها">
        <a class="tkm-stat <?php echo $ticketchi_lite_status === 'all' ? 'is-active' : ''; ?>" href="<?php echo esc_url($ticketchi_lite_filter_url(['status' => 'all'])); ?>" style="--c: var(--tkm-primary)">
            <span class="tkm-stat__icon"><?php echo wp_kses(ticketchi_lite_icon('inbox', 18), $ticketchi_lite_kses); ?></span>
            <span class="tkm-stat__label">همه تیکت‌ها</span>
            <strong class="tkm-stat__value"><?php echo esc_html(ticketchi_lite_fa($ticketchi_lite_counts['all'])); ?></strong>
        </a>
        <?php foreach ($ticketchi_lite_statuses as $ticketchi_lite_item) : ?>
            <a class="tkm-stat <?php echo $ticketchi_lite_status === $ticketchi_lite_item['slug'] ? 'is-active' : ''; ?>" href="<?php echo esc_url($ticketchi_lite_filter_url(['status' => $ticketchi_lite_item['slug']])); ?>" style="--c: <?php echo esc_attr($ticketchi_lite_item['color']); ?>">
                <span class="tkm-stat__icon"><?php echo wp_kses(ticketchi_lite_icon('ticket', 18), $ticketchi_lite_kses); ?></span>
                <span class="tkm-stat__label"><?php echo esc_html($ticketchi_lite_item['name']); ?></span>
                <strong class="tkm-stat__value"><?php echo esc_html(ticketchi_lite_fa(isset($ticketchi_lite_counts[$ticketchi_lite_item['slug']]) ? $ticketchi_lite_counts[$ticketchi_lite_item['slug']] : 0)); ?></strong>
            </a>
        <?php endforeach; ?>
    </nav>

    <section class="tkm-card tkm-list-card">
        <form class="tkm-toolbar" method="get" action="<?php echo esc_url(remove_query_arg($ticketchi_lite_filter_keys, $ticketchi_lite_list_url)); ?>" data-autosubmit>
            <?php
            // Keep query args that belong to the host page (e.g. ?page_id=12 on plain permalinks).
            $ticketchi_lite_base_query = wp_parse_url($ticketchi_lite_list_url, PHP_URL_QUERY);
            if ($ticketchi_lite_base_query) {
                parse_str($ticketchi_lite_base_query, $ticketchi_lite_base_args);
                foreach ($ticketchi_lite_base_args as $ticketchi_lite_key => $ticketchi_lite_value) {
                    if (is_scalar($ticketchi_lite_value) && !in_array($ticketchi_lite_key, $ticketchi_lite_filter_keys, true)) {
                        echo '<input type="hidden" name="' . esc_attr($ticketchi_lite_key) . '" value="' . esc_attr($ticketchi_lite_value) . '">';
                    }
                }
            }
            ?>
            <div class="tkm-toolbar__filters">
                <select class="tkm-select tkm-select--sm" name="status" aria-label="وضعیت">
                    <option value="all" <?php selected($ticketchi_lite_status, 'all'); ?>>همه وضعیت‌ها</option>
                    <?php foreach ($ticketchi_lite_statuses as $ticketchi_lite_item) : ?>
                        <option value="<?php echo esc_attr($ticketchi_lite_item['slug']); ?>" <?php selected($ticketchi_lite_status, $ticketchi_lite_item['slug']); ?>><?php echo esc_html($ticketchi_lite_item['name']); ?></option>
                    <?php endforeach; ?>
                </select>
                <select class="tkm-select tkm-select--sm" name="priority" aria-label="اولویت">
                    <option value="all-priority" <?php selected($ticketchi_lite_priority, 'all-priority'); ?>>همه اولویت‌ها</option>
                    <option value="low" <?php selected($ticketchi_lite_priority, 'low'); ?>>اولویت کم</option>
                    <option value="medium" <?php selected($ticketchi_lite_priority, 'medium'); ?>>اولویت متوسط</option>
                    <option value="high" <?php selected($ticketchi_lite_priority, 'high'); ?>>اولویت زیاد</option>
                </select>
                <select class="tkm-select tkm-select--sm" name="orderby" aria-label="مرتب‌سازی">
                    <option value="reply-date" <?php selected($ticketchi_lite_orderby, 'reply-date'); ?>>آخرین فعالیت</option>
                    <option value="create-date" <?php selected($ticketchi_lite_orderby, 'create-date'); ?>>تاریخ ایجاد</option>
                </select>
                <noscript><button type="submit" class="tkm-btn tkm-btn--sm">اعمال</button></noscript>
                <?php if ($ticketchi_lite_has_filters) : ?>
                    <a class="tkm-btn tkm-btn--ghost tkm-btn--sm" href="<?php echo esc_url($ticketchi_lite_list_url); ?>"><?php echo wp_kses(ticketchi_lite_icon('x', 14), $ticketchi_lite_kses); ?> حذف فیلترها</a>
                <?php endif; ?>
            </div>
        </form>

        <?php if ($ticketchi_lite_result['items']) : ?>
            <ul class="tkm-tickets">
                <?php foreach ($ticketchi_lite_result['items'] as $ticketchi_lite_ticket) :
                    $ticketchi_lite_dep = Departments::find($ticketchi_lite_ticket->department_id);
                    $ticketchi_lite_status_info = Statuses::get($ticketchi_lite_ticket->status);
                ?>
                    <li>
                        <a class="tkm-ticket" href="<?php echo esc_url(ticketchi_lite_url('single', $ticketchi_lite_ticket->ID)); ?>">
                            <span class="tkm-ticket__icon" style="--c: <?php echo esc_attr($ticketchi_lite_status_info['color']); ?>">
                                <?php echo wp_kses(ticketchi_lite_icon('message', 18), $ticketchi_lite_kses); ?>
                            </span>
                            <span class="tkm-ticket__main">
                                <span class="tkm-ticket__title"><?php echo esc_html($ticketchi_lite_ticket->title); ?></span>
                                <span class="tkm-ticket__meta">
                                    <span class="tkm-num"><?php echo esc_html(ticketchi_lite_ticket_number($ticketchi_lite_ticket->ID)); ?></span>
                                    <span class="tkm-dot"></span>
                                    <span><?php echo esc_html($ticketchi_lite_dep ? $ticketchi_lite_dep->name : '—'); ?></span>
                                    <span class="tkm-dot"></span>
                                    <span title="<?php echo esc_attr(ticketchi_lite_date($ticketchi_lite_ticket->reply_date)); ?>"><?php echo esc_html(ticketchi_lite_time_ago($ticketchi_lite_ticket->reply_date)); ?></span>
                                </span>
                            </span>
                            <span class="tkm-ticket__side">
                                <?php echo wp_kses(ticketchi_lite_status_badge($ticketchi_lite_ticket->status), $ticketchi_lite_kses); ?>
                                <span class="tkm-ticket__aux">
                                    <?php echo wp_kses(ticketchi_lite_priority_badge($ticketchi_lite_ticket->priority), $ticketchi_lite_kses); ?>
                                    <span class="tkm-ticket__replies" title="تعداد پاسخ‌ها"><?php echo wp_kses(ticketchi_lite_icon('messages', 14), $ticketchi_lite_kses); ?><?php echo esc_html(ticketchi_lite_fa(isset($ticketchi_lite_replies[(int) $ticketchi_lite_ticket->ID]) ? $ticketchi_lite_replies[(int) $ticketchi_lite_ticket->ID] : 0)); ?></span>
                                </span>
                            </span>
                            <span class="tkm-ticket__chev"><?php echo wp_kses(ticketchi_lite_icon('chevron-left', 18), $ticketchi_lite_kses); ?></span>
                        </a>
                    </li>
                <?php endforeach; ?>
            </ul>

            <?php if ($ticketchi_lite_result['pages'] > 1) : ?>
                <nav class="tkm-pagination" aria-label="صفحه‌بندی">
                    <?php
                    echo wp_kses(paginate_links([
                        'base'      => add_query_arg('page_num', '%#%'),
                        'format'    => '',
                        'current'   => $ticketchi_lite_page,
                        'total'     => $ticketchi_lite_result['pages'],
                        'prev_text' => ticketchi_lite_icon('chevron-right', 16) . '<span class="tkm-sr">قبلی</span>',
                        'next_text' => ticketchi_lite_icon('chevron-left', 16) . '<span class="tkm-sr">بعدی</span>',
                        'type'      => 'plain',
                    ]), $ticketchi_lite_kses);
                    ?>
                    <span class="tkm-pagination__info"><?php echo esc_html('صفحه ' . ticketchi_lite_fa($ticketchi_lite_page) . ' از ' . ticketchi_lite_fa($ticketchi_lite_result['pages'])); ?></span>
                </nav>
            <?php endif; ?>
        <?php else : ?>
            <div class="tkm-empty">
                <div class="tkm-empty__art"><?php echo wp_kses(ticketchi_lite_icon($ticketchi_lite_has_filters ? 'search' : 'inbox', 30), $ticketchi_lite_kses); ?></div>
                <h3 class="tkm-empty__title"><?php echo $ticketchi_lite_has_filters ? 'تیکتی با این فیلترها پیدا نشد' : 'هنوز تیکتی ثبت نکرده‌اید'; ?></h3>
                <p class="tkm-empty__text"><?php echo $ticketchi_lite_has_filters ? 'فیلترها را تغییر دهید یا همه تیکت‌ها را ببینید.' : 'هر سوال یا مشکلی دارید، تیم پشتیبانی آماده کمک به شماست.'; ?></p>
                <?php if ($ticketchi_lite_has_filters) : ?>
                    <a class="tkm-btn" href="<?php echo esc_url($ticketchi_lite_list_url); ?>">نمایش همه تیکت‌ها</a>
                <?php else : ?>
                    <a class="tkm-btn tkm-btn--primary" href="<?php echo esc_url(ticketchi_lite_url('new')); ?>"><?php echo wp_kses(ticketchi_lite_icon('plus', 16), $ticketchi_lite_kses); ?> ثبت اولین تیکت</a>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </section>
</div>
