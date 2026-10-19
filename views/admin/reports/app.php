<?php
/**
 * Reports — rendered by assets/admin/js/tkm-reports.js from /reports.
 */
defined('ABSPATH') || exit('NO Access');

include TKM_VIEWS_PATH . 'admin/partials/header.php';
?>
<div class="tkm-head">
    <div class="tkm-head__text">
        <h1 class="tkm-head__title">گزارش‌ها</h1>
        <p class="tkm-head__desc" data-range-label>عملکرد تیم پشتیبانی در بازه انتخابی، در مقایسه با بازه قبلی هم‌اندازه.</p>
    </div>
    <div class="tkm-head__actions tkm-rp-filters">
        <div class="tkm-rp-presets" role="group" aria-label="بازه زمانی">
            <button type="button" data-days="7">۷ روز</button>
            <button type="button" data-days="30" class="is-active" aria-pressed="true">۳۰ روز</button>
            <button type="button" data-days="90">۹۰ روز</button>
            <button type="button" data-days="month">این ماه</button>
        </div>
        <div class="tkm-rp-custom">
            <input type="date" class="tkm-input tkm-input--sm" data-from aria-label="از تاریخ">
            <span>تا</span>
            <input type="date" class="tkm-input tkm-input--sm" data-to aria-label="تا تاریخ">
        </div>
        <a class="tkm-btn" data-csv href="#"><?php echo tkm_icon('download', 16); ?> خروجی CSV</a>
    </div>
</div>

<div class="tkm-rp" id="tkm-reports" data-state="loading" aria-live="polite">
    <div class="tkm-card tkm-ib-loading" style="min-height:320px"><span class="tkm-spinner"></span></div>
</div>
<?php include TKM_VIEWS_PATH . 'admin/partials/footer.php'; ?>
