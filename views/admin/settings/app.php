<?php
/**
 * Settings app — fields come from Ticketchi\Admin\SettingsSchema via /settings.
 */
defined('ABSPATH') || exit('NO Access');

use Ticketchi\Admin\SettingsSchema;

$tkm_sections = SettingsSchema::sections();
include TKM_VIEWS_PATH . 'admin/partials/header.php';
?>
<div class="tkm-head">
    <div class="tkm-head__text">
        <h1 class="tkm-head__title">تنظیمات</h1>
        <p class="tkm-head__desc">هر تغییری را که بدهید، قبل از خروج با دکمه «ذخیره تغییرات» ثبت کنید.</p>
    </div>
    <div class="tkm-head__actions">
        <div class="tkm-input-icon tkm-st-search">
            <?php echo tkm_icon('search', 16); ?>
            <input type="search" class="tkm-input" data-settings-search placeholder="جستجو در تنظیمات…" aria-label="جستجو در تنظیمات">
        </div>
    </div>
</div>

<div class="tkm-st" id="tkm-settings-app" data-state="loading">
    <nav class="tkm-st-nav" aria-label="بخش‌های تنظیمات">
        <?php foreach ($tkm_sections as $tkm_s) : ?>
            <a href="#/<?php echo esc_attr($tkm_s['id']); ?>" class="tkm-st-nav__item" data-nav="<?php echo esc_attr($tkm_s['id']); ?>">
                <span class="tkm-st-nav__icon"><?php echo tkm_icon($tkm_s['icon'], 17); ?></span>
                <span><?php echo esc_html($tkm_s['title']); ?></span>
                <span class="tkm-st-nav__dirty" aria-hidden="true"></span>
            </a>
        <?php endforeach; ?>
    </nav>

    <form class="tkm-st-main" data-settings-form novalidate autocomplete="off">
        <div class="tkm-card tkm-ib-loading" style="min-height:360px"><span class="tkm-spinner"></span></div>
    </form>
</div>

<div class="tkm-st-savebar" data-savebar hidden role="region" aria-label="ذخیره تغییرات">
    <span class="tkm-st-savebar__dot" aria-hidden="true"></span>
    <span data-savebar-text>تغییرات ذخیره‌نشده دارید</span>
    <span class="tkm-grow"></span>
    <button type="button" class="tkm-btn tkm-btn--ghost tkm-btn--sm" data-discard>لغو تغییرات</button>
    <button type="button" class="tkm-btn tkm-btn--primary tkm-btn--sm" data-save><span class="tkm-spinner"></span>ذخیره تغییرات <kbd class="tkm-kbd">Ctrl S</kbd></button>
</div>
<?php include TKM_VIEWS_PATH . 'admin/partials/footer.php'; ?>
