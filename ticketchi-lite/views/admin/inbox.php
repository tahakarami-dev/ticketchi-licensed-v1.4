<?php
/**
 * Support inbox — rendered by assets/js/inbox.js from the REST API.
 */
defined('ABSPATH') || exit;

$ticketchi_lite_shell = 'tkm-admin--app';
$ticketchi_lite_kses = ticketchi_lite_allowed_html();
include TICKETCHI_LITE_PATH . 'views/admin/partials/header.php';
?>
<div class="tkm-inbox tkm-inbox--lite" id="tkm-inbox" data-state="loading">
    <aside class="tkm-ib-nav" aria-label="نماها">
        <div class="tkm-ib-nav__head">
            <strong>صندوق پشتیبانی</strong>
        </div>
        <nav class="tkm-ib-views" data-views></nav>
        <div class="tkm-ib-group tkm-ib-group--pro">
            <div class="tkm-ib-group__title"><?php echo wp_kses(ticketchi_lite_icon('crown', 13), $ticketchi_lite_kses); ?> نسخه حرفه‌ای</div>
            <?php foreach (['تیکت‌های من' => ['user-check', 'team'], 'بدون مسئول' => ['user', 'team'], 'در خطر SLA' => ['alert', 'sla'], 'برچسب‌ها' => ['tag', 'tags'], 'دپارتمان‌ها' => ['layers', 'team']] as $ticketchi_lite_label => $ticketchi_lite_view) : ?>
                <a class="tkm-ib-view tkm-ib-view--pro" href="<?php echo esc_url(ticketchi_lite_admin_url('ticketchi-lite-pro') . '#pro-' . $ticketchi_lite_view[1]); ?>">
                    <?php echo wp_kses(ticketchi_lite_icon($ticketchi_lite_view[0], 15), $ticketchi_lite_kses); ?><span><?php echo esc_html($ticketchi_lite_label); ?></span><?php echo wp_kses(ticketchi_lite_icon('lock', 12), $ticketchi_lite_kses); ?>
                </a>
            <?php endforeach; ?>
        </div>
        <a class="tkm-ib-pro" href="<?php echo esc_url(ticketchi_lite_admin_url('ticketchi-lite-pro')); ?>">
            <span class="tkm-ib-pro__icon"><?php echo wp_kses(ticketchi_lite_icon('crown', 16), $ticketchi_lite_kses); ?></span>
            <strong>کار تیمی با نسخه حرفه‌ای</strong>
            <span>سپردن تیکت به کارشناس، برچسب، یادداشت داخلی، پاسخ آماده، SLA و گزارش‌ها</span>
        </a>
    </aside>

    <section class="tkm-ib-list" aria-label="فهرست تیکت‌ها">
        <div class="tkm-ib-list__head">
            <a class="tkm-ib-search tkm-ib-search--pro" href="<?php echo esc_url(ticketchi_lite_admin_url('ticketchi-lite-pro') . '#pro-tags'); ?>">
                <?php echo wp_kses(ticketchi_lite_icon('search', 16), $ticketchi_lite_kses); ?>
                <span>جستجو، فیلتر و مرتب‌سازی تیکت‌ها</span>
                <span class="tkm-pro-tag">PRO</span>
            </a>
            <div class="tkm-ib-list__tools">
                <span class="tkm-ib-list__title" data-list-title>تیکت‌ها</span>
                <select class="tkm-select tkm-select--sm tkm-ib-scope" data-scope-select aria-label="نما"></select>
                <span class="tkm-badge tkm-badge--plain tkm-badge--outline tkm-num" data-total>—</span>
            </div>
        </div>
        <div class="tkm-ib-items" data-items role="listbox" aria-label="تیکت‌ها" tabindex="0"></div>
    </section>

    <section class="tkm-ib-convo" data-convo aria-live="polite">
        <div class="tkm-ib-placeholder">
            <div class="tkm-empty__art"><?php echo wp_kses(ticketchi_lite_icon('messages', 30), $ticketchi_lite_kses); ?></div>
            <h3>یک تیکت را انتخاب کنید</h3>
            <p>گفتگو، پاسخ و تغییر وضعیت تیکت همین‌جا انجام می‌شود.</p>
        </div>
    </section>
</div>
<?php include TICKETCHI_LITE_PATH . 'views/admin/partials/footer.php'; ?>
