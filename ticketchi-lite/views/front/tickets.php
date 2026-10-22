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
$ticketchi_lite_page = isset($_GET['page_num']) ? max(1, absint($_GET['page_num'])) : 1;
// phpcs:enable

$ticketchi_lite_statuses = Statuses::all();
if ($ticketchi_lite_status !== 'all' && !in_array($ticketchi_lite_status, wp_list_pluck($ticketchi_lite_statuses, 'slug'), true)) {
    $ticketchi_lite_status = 'all';
}

$ticketchi_lite_result = Tickets::for_customer($ticketchi_lite_user->ID, [
    'status' => $ticketchi_lite_status,
    'page'   => $ticketchi_lite_page,
]);
$ticketchi_lite_counts = Tickets::customer_counts($ticketchi_lite_user->ID);
$ticketchi_lite_replies = Tickets::reply_counts(wp_list_pluck($ticketchi_lite_result['items'], 'ID'));

$ticketchi_lite_list_url = ticketchi_lite_url('list');
$ticketchi_lite_filter_url = function ($status) use ($ticketchi_lite_list_url) {
    $base = remove_query_arg(['status', 'page_num'], $ticketchi_lite_list_url);
    return $status === 'all' ? $base : add_query_arg('status', $status, $base);
};
$ticketchi_lite_has_filters = $ticketchi_lite_status !== 'all';
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
        <a class="tkm-stat <?php echo $ticketchi_lite_status === 'all' ? 'is-active' : ''; ?>" href="<?php echo esc_url($ticketchi_lite_filter_url('all')); ?>" style="--c: var(--tkm-primary)">
            <span class="tkm-stat__icon"><?php echo wp_kses(ticketchi_lite_icon('inbox', 18), $ticketchi_lite_kses); ?></span>
            <span class="tkm-stat__label">همه تیکت‌ها</span>
            <strong class="tkm-stat__value"><?php echo esc_html(ticketchi_lite_fa($ticketchi_lite_counts['all'])); ?></strong>
        </a>
        <?php foreach ($ticketchi_lite_statuses as $ticketchi_lite_item) : ?>
            <a class="tkm-stat <?php echo $ticketchi_lite_status === $ticketchi_lite_item['slug'] ? 'is-active' : ''; ?>" href="<?php echo esc_url($ticketchi_lite_filter_url($ticketchi_lite_item['slug'])); ?>" style="--c: <?php echo esc_attr($ticketchi_lite_item['color']); ?>">
                <span class="tkm-stat__icon"><?php echo wp_kses(ticketchi_lite_icon('ticket', 18), $ticketchi_lite_kses); ?></span>
                <span class="tkm-stat__label"><?php echo esc_html($ticketchi_lite_item['name']); ?></span>
                <strong class="tkm-stat__value"><?php echo esc_html(ticketchi_lite_fa(isset($ticketchi_lite_counts[$ticketchi_lite_item['slug']]) ? $ticketchi_lite_counts[$ticketchi_lite_item['slug']] : 0)); ?></strong>
            </a>
        <?php endforeach; ?>
    </nav>

    <section class="tkm-card tkm-list-card">
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
                <h3 class="tkm-empty__title"><?php echo $ticketchi_lite_has_filters ? 'تیکتی با این وضعیت ندارید' : 'هنوز تیکتی ثبت نکرده‌اید'; ?></h3>
                <p class="tkm-empty__text"><?php echo $ticketchi_lite_has_filters ? 'وضعیت دیگری را انتخاب کنید یا همه تیکت‌ها را ببینید.' : 'هر سوال یا مشکلی دارید، تیم پشتیبانی آماده کمک به شماست.'; ?></p>
                <?php if ($ticketchi_lite_has_filters) : ?>
                    <a class="tkm-btn" href="<?php echo esc_url($ticketchi_lite_list_url); ?>">نمایش همه تیکت‌ها</a>
                <?php else : ?>
                    <a class="tkm-btn tkm-btn--primary" href="<?php echo esc_url(ticketchi_lite_url('new')); ?>"><?php echo wp_kses(ticketchi_lite_icon('plus', 16), $ticketchi_lite_kses); ?> ثبت اولین تیکت</a>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </section>
</div>
