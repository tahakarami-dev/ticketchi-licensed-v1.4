<?php
/**
 * Automation rules — rendered by assets/admin/js/tkm-automations.js.
 */
defined('ABSPATH') || exit('NO Access');

include TKM_VIEWS_PATH . 'admin/partials/header.php';
?>
<div class="tkm-head">
    <div class="tkm-head__text">
        <h1 class="tkm-head__title">اتوماسیون</h1>
        <p class="tkm-head__desc">کارهای تکراری را به قانون بسپارید: «وقتی این اتفاق افتاد و این شرط‌ها برقرار بود، این کارها را انجام بده».</p>
    </div>
    <div class="tkm-head__actions">
        <button type="button" class="tkm-btn tkm-btn--primary" data-new-rule><?php echo tkm_icon('plus', 16); ?> قانون جدید</button>
    </div>
</div>

<div class="tkm-auto" id="tkm-automations" data-state="loading">
    <div class="tkm-auto__list" data-rules>
        <div class="tkm-card tkm-ib-loading" style="min-height:220px"><span class="tkm-spinner"></span></div>
    </div>
</div>

<div class="tkm-drawer" data-editor hidden role="dialog" aria-modal="true" aria-labelledby="tkm-rule-title">
    <div class="tkm-drawer__backdrop" data-editor-close></div>
    <form class="tkm-drawer__panel" data-rule-form novalidate>
        <header class="tkm-drawer__head">
            <h2 id="tkm-rule-title" data-editor-title>قانون جدید</h2>
            <button type="button" class="tkm-btn tkm-btn--ghost tkm-btn--icon tkm-btn--sm" data-editor-close aria-label="بستن"><?php echo tkm_icon('x', 18); ?></button>
        </header>
        <div class="tkm-drawer__body" data-editor-body></div>
        <footer class="tkm-drawer__foot">
            <label class="tkm-switch"><input type="checkbox" name="is_active" checked><span class="tkm-switch__track"></span><span>فعال</span></label>
            <span class="tkm-grow"></span>
            <button type="button" class="tkm-btn" data-editor-close>انصراف</button>
            <button type="submit" class="tkm-btn tkm-btn--primary"><span class="tkm-spinner"></span>ذخیره قانون</button>
        </footer>
    </form>
</div>
<?php include TKM_VIEWS_PATH . 'admin/partials/footer.php'; ?>
