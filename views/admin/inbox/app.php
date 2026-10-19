<?php
/**
 * Support inbox — rendered by assets/admin/js/tkm-inbox.js from the REST API.
 */
defined('ABSPATH') || exit('NO Access');

$tkm_shell_class = 'tkm-admin--app';
include TKM_VIEWS_PATH . 'admin/partials/header.php';
?>
<div class="tkm-inbox" id="tkm-inbox" data-state="loading">
    <aside class="tkm-ib-nav" aria-label="نماها">
        <div class="tkm-ib-nav__head">
            <strong>صندوق پشتیبانی</strong>
            <a class="tkm-btn tkm-btn--ghost tkm-btn--icon tkm-btn--sm" href="<?php echo esc_url(tkm_admin_url('tkm-new-ticket')); ?>" title="ارسال تیکت به کاربر" aria-label="ارسال تیکت به کاربر"><?php echo tkm_icon('edit', 16); ?></a>
        </div>
        <nav class="tkm-ib-views" data-views></nav>
        <div class="tkm-ib-group" data-group="departments">
            <div class="tkm-ib-group__title">دپارتمان‌ها</div>
            <div data-departments></div>
        </div>
        <div class="tkm-ib-group" data-group="tags">
            <div class="tkm-ib-group__title">برچسب‌ها</div>
            <div data-tags></div>
        </div>
    </aside>

    <section class="tkm-ib-list" aria-label="فهرست تیکت‌ها">
        <div class="tkm-ib-list__head">
            <div class="tkm-input-icon tkm-ib-search">
                <?php echo tkm_icon('search', 16); ?>
                <input type="search" class="tkm-input" data-search placeholder="جستجو: عنوان، متن، #شناسه، نام یا ایمیل مشتری…" aria-label="جستجو">
                <kbd class="tkm-kbd">/</kbd>
            </div>
            <div class="tkm-ib-list__tools">
                <label class="tkm-ib-checkall" title="انتخاب همه"><input type="checkbox" data-check-all aria-label="انتخاب همه"></label>
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
                    <option value="priority">اولویت</option>
                    <option value="due">نزدیک‌ترین مهلت SLA</option>
                </select>
            </div>
            <div class="tkm-ib-bulk" data-bulk hidden>
                <span data-bulk-count></span>
                <select class="tkm-select tkm-select--sm" data-bulk-assign aria-label="سپردن به"></select>
                <select class="tkm-select tkm-select--sm" data-bulk-status aria-label="تغییر وضعیت"></select>
                <button type="button" class="tkm-btn tkm-btn--sm" data-bulk-action="trash"><?php echo tkm_icon('trash', 14); ?> زباله‌دان</button>
                <button type="button" class="tkm-btn tkm-btn--sm" data-bulk-action="restore" hidden><?php echo tkm_icon('refresh', 14); ?> بازگردانی</button>
                <button type="button" class="tkm-btn tkm-btn--sm tkm-btn--danger" data-bulk-action="delete" hidden><?php echo tkm_icon('trash', 14); ?> حذف دائمی</button>
                <button type="button" class="tkm-btn tkm-btn--ghost tkm-btn--sm" data-bulk-clear>لغو</button>
            </div>
        </div>
        <div class="tkm-ib-items" data-items role="listbox" aria-label="تیکت‌ها" tabindex="0"></div>
    </section>

    <section class="tkm-ib-convo" data-convo aria-live="polite">
        <div class="tkm-ib-placeholder">
            <div class="tkm-empty__art"><?php echo tkm_icon('messages', 30); ?></div>
            <h3>یک تیکت را انتخاب کنید</h3>
            <p>با کلیدهای <kbd class="tkm-kbd">J</kbd> و <kbd class="tkm-kbd">K</kbd> بین تیکت‌ها حرکت کنید و با <kbd class="tkm-kbd">?</kbd> همه میانبرها را ببینید.</p>
        </div>
    </section>
</div>

<div class="tkm-modal" data-shortcuts hidden role="dialog" aria-modal="true" aria-labelledby="tkm-sc-title">
    <div class="tkm-modal__backdrop" data-close></div>
    <div class="tkm-modal__dialog">
        <button type="button" class="tkm-btn tkm-btn--ghost tkm-btn--icon tkm-btn--sm tkm-modal__close" data-close aria-label="بستن"><?php echo tkm_icon('x', 18); ?></button>
        <h3 class="tkm-modal__title" id="tkm-sc-title">میانبرهای صفحه‌کلید</h3>
        <dl class="tkm-shortcuts">
            <div><dt><kbd class="tkm-kbd">J</kbd> / <kbd class="tkm-kbd">K</kbd></dt><dd>تیکت بعدی / قبلی</dd></div>
            <div><dt><kbd class="tkm-kbd">R</kbd></dt><dd>نوشتن پاسخ</dd></div>
            <div><dt><kbd class="tkm-kbd">N</kbd></dt><dd>یادداشت داخلی</dd></div>
            <div><dt><kbd class="tkm-kbd">A</kbd></dt><dd>سپردن به من</dd></div>
            <div><dt><kbd class="tkm-kbd">/</kbd></dt><dd>جستجو</dd></div>
            <div><dt><kbd class="tkm-kbd">Ctrl</kbd> + <kbd class="tkm-kbd">Enter</kbd></dt><dd>ارسال</dd></div>
            <div><dt><kbd class="tkm-kbd">Ctrl</kbd> + <kbd class="tkm-kbd">K</kbd></dt><dd>جستجوی سریع در همه‌جا</dd></div>
            <div><dt><kbd class="tkm-kbd">Esc</kbd></dt><dd>بستن پنجره‌ها</dd></div>
        </dl>
    </div>
</div>
<?php include TKM_VIEWS_PATH . 'admin/partials/footer.php'; ?>
