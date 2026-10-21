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
        <div class="tkm-ib-group" data-group="departments">
            <div class="tkm-ib-group__title">دپارتمان‌ها</div>
            <div data-departments></div>
        </div>
        <a class="tkm-ib-pro" href="<?php echo esc_url(ticketchi_lite_admin_url('ticketchi-lite-pro')); ?>">
            <span class="tkm-ib-pro__icon"><?php echo wp_kses(ticketchi_lite_icon('crown', 16), $ticketchi_lite_kses); ?></span>
            <strong>کار تیمی با نسخه حرفه‌ای</strong>
            <span>سپردن تیکت به کارشناس، برچسب، یادداشت داخلی، پاسخ آماده، SLA و گزارش‌ها</span>
        </a>
    </aside>

    <section class="tkm-ib-list" aria-label="فهرست تیکت‌ها">
        <div class="tkm-ib-list__head">
            <div class="tkm-input-icon tkm-ib-search">
                <?php echo wp_kses(ticketchi_lite_icon('search', 16), $ticketchi_lite_kses); ?>
                <input type="search" class="tkm-input" data-search placeholder="جستجو: عنوان، متن، #شناسه، نام یا ایمیل مشتری…" aria-label="جستجو">
            </div>
            <div class="tkm-ib-list__tools">
                <span class="tkm-ib-list__title" data-list-title>تیکت‌ها</span>
                <select class="tkm-select tkm-select--sm tkm-ib-scope" data-scope-select aria-label="نما"></select>
                <span class="tkm-badge tkm-badge--plain tkm-badge--outline tkm-num" data-total>—</span>
                <span class="tkm-grow"></span>
                <select class="tkm-select tkm-select--sm" data-filter="priority" aria-label="اولویت">
                    <option value="">همه اولویت‌ها</option>
                    <option value="high">اولویت زیاد</option>
                    <option value="medium">اولویت متوسط</option>
                    <option value="low">اولویت کم</option>
                </select>
                <select class="tkm-select tkm-select--sm" data-filter="sort" aria-label="مرتب‌سازی">
                    <option value="updated">آخرین فعالیت</option>
                    <option value="oldest">قدیمی‌ترین انتظار</option>
                    <option value="created">تاریخ ایجاد</option>
                </select>
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
