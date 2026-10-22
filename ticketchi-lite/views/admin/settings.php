<?php
/**
 * Settings app — fields come from TicketchiLite\Settings via the REST API.
 */
defined('ABSPATH') || exit;

use TicketchiLite\Promo;
use TicketchiLite\Settings;

$ticketchi_lite_kses = ticketchi_lite_allowed_html();
include TICKETCHI_LITE_PATH . 'views/admin/partials/header.php';
?>
<div class="tkm-head">
    <div class="tkm-head__text">
        <h1 class="tkm-head__title">تنظیمات</h1>
        <p class="tkm-head__desc">هر تغییری را که بدهید، قبل از خروج با دکمه «ذخیره تغییرات» ثبت کنید.</p>
    </div>
    <div class="tkm-head__actions">
        <div class="tkm-input-icon tkm-st-search">
            <?php echo wp_kses(ticketchi_lite_icon('search', 16), $ticketchi_lite_kses); ?>
            <input type="search" class="tkm-input" data-settings-search placeholder="جستجو در تنظیمات…" aria-label="جستجو در تنظیمات">
        </div>
    </div>
</div>

<div class="tkm-st" id="tkm-settings-app" data-state="loading">
    <nav class="tkm-st-nav" aria-label="بخش‌های تنظیمات">
        <?php foreach (Settings::sections() as $ticketchi_lite_section) : ?>
            <a href="#/<?php echo esc_attr($ticketchi_lite_section['id']); ?>" class="tkm-st-nav__item" data-nav="<?php echo esc_attr($ticketchi_lite_section['id']); ?>">
                <span class="tkm-st-nav__icon"><?php echo wp_kses(ticketchi_lite_icon($ticketchi_lite_section['icon'], 17), $ticketchi_lite_kses); ?></span>
                <span><?php echo esc_html($ticketchi_lite_section['title']); ?></span>
                <span class="tkm-st-nav__dirty" aria-hidden="true"></span>
            </a>
        <?php endforeach; ?>
        <div class="tkm-st-nav__pro">
            <span class="tkm-st-nav__pro-title"><?php echo wp_kses(ticketchi_lite_icon('crown', 14), $ticketchi_lite_kses); ?> در نسخه حرفه‌ای</span>
            <?php foreach (['ظاهر و رنگ برند' => 'brand', 'پیوست فایل' => 'customer-ux', 'ووکامرس' => 'woocommerce', 'تیم و تخصیص خودکار' => 'team', 'SLA و ساعات کاری' => 'sla', 'پاسخ‌های آماده' => 'canned', 'وضعیت‌های اختصاصی' => 'brand', 'پیامک' => 'sms', 'پایگاه دانش' => 'kb'] as $ticketchi_lite_label => $ticketchi_lite_anchor) : ?>
                <a class="tkm-st-nav__item tkm-st-nav__item--pro" href="<?php echo esc_url(ticketchi_lite_admin_url('ticketchi-lite-pro') . '#pro-' . $ticketchi_lite_anchor); ?>">
                    <span class="tkm-st-nav__icon"><?php echo wp_kses(ticketchi_lite_icon('lock', 15), $ticketchi_lite_kses); ?></span>
                    <span><?php echo esc_html($ticketchi_lite_label); ?></span>
                </a>
            <?php endforeach; ?>
        </div>
    </nav>

    <div class="tkm-st-main-wrap">
        <form class="tkm-st-main" data-settings-form novalidate autocomplete="off">
            <div class="tkm-card tkm-ib-loading" style="min-height:360px"><span class="tkm-spinner"></span></div>
        </form>
        <?php echo wp_kses(Promo::teaser('settings', '۱۳ بخش تنظیمات در نسخه حرفه‌ای', 'رنگ برند، پیوست فایل، ووکامرس، پیامک، SLA، پاسخ‌های آماده و وضعیت‌های اختصاصی؛ پشتیبانی را دقیقاً همان‌طور که می‌خواهید بچینید.'), $ticketchi_lite_kses); ?>
    </div>
</div>

<div class="tkm-st-savebar" data-savebar hidden role="region" aria-label="ذخیره تغییرات">
    <span class="tkm-st-savebar__dot" aria-hidden="true"></span>
    <span data-savebar-text>تغییرات ذخیره‌نشده دارید</span>
    <span class="tkm-grow"></span>
    <button type="button" class="tkm-btn tkm-btn--ghost tkm-btn--sm" data-discard>لغو تغییرات</button>
    <button type="button" class="tkm-btn tkm-btn--primary tkm-btn--sm" data-save><span class="tkm-spinner"></span>ذخیره تغییرات <kbd class="tkm-kbd">Ctrl S</kbd></button>
</div>
<?php include TICKETCHI_LITE_PATH . 'views/admin/partials/footer.php'; ?>
