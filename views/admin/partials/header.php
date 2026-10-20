<?php
/**
 * Admin app shell — opening markup. Close with partials/footer.php.
 */
defined('ABSPATH') || exit('NO Access');

$tkm_current = isset($_GET['page']) ? sanitize_key(wp_unslash($_GET['page'])) : '';
$tkm_can_manage = current_user_can('tkm_manage');
$tkm_shell_class = isset($tkm_shell_class) ? $tkm_shell_class : '';
$tkm_me = wp_get_current_user();

$tkm_nav = [
    'tkm-analysis'    => ['label' => 'داشبورد', 'icon' => 'grid'],
    'tkm-tickets'     => ['label' => 'صندوق پشتیبانی', 'icon' => 'inbox', 'match' => ['tkm-new-ticket']],
    'tkm-reports'     => ['label' => 'گزارش‌ها', 'icon' => 'bar-chart', 'manage' => true],
    'tkm-automations' => ['label' => 'اتوماسیون', 'icon' => 'zap', 'manage' => true],
    'tkm-departments' => ['label' => 'تیم و دپارتمان‌ها', 'icon' => 'users', 'manage' => true],
    'tkm-settings'    => ['label' => 'تنظیمات', 'icon' => 'settings', 'manage' => true],
];
?>
<div class="tkm-ui tkm-admin <?php echo esc_attr($tkm_shell_class); ?>" data-theme="light" id="tkm-admin">
    <script>
        try {
            if (localStorage.getItem('tkm-theme') === 'dark') {
                document.getElementById('tkm-admin').setAttribute('data-theme', 'dark');
                document.documentElement.classList.add('tkm-dark');
            }
        } catch (e) {}
    </script>

    <header class="tkm-topbar">
        <a class="tkm-exit" href="<?php echo esc_url(admin_url()); ?>" title="بازگشت به پیشخوان وردپرس" aria-label="بازگشت به پیشخوان وردپرس"><?php echo tkm_icon('arrow-right', 18); ?></a>
        <a class="tkm-brand" href="<?php echo esc_url(tkm_admin_url('tkm-analysis')); ?>">
            <span class="tkm-brand__mark" aria-hidden="true"><?php echo tkm_icon('ticket', 20); ?></span>
            <span class="tkm-brand__text">
                <strong>تیکت‌چی</strong>
                <small>مرکز پشتیبانی</small>
            </span>
        </a>

        <nav class="tkm-nav" aria-label="ناوبری تیکت‌چی">
            <?php foreach ($tkm_nav as $tkm_nav_slug => $tkm_nav_item) :
                if (!empty($tkm_nav_item['manage']) && !$tkm_can_manage) {
                    continue;
                }
                $tkm_nav_active = $tkm_current === $tkm_nav_slug || (!empty($tkm_nav_item['match']) && in_array($tkm_current, $tkm_nav_item['match'], true));
            ?>
                <a class="tkm-nav__item <?php echo $tkm_nav_active ? 'is-active' : ''; ?>" href="<?php echo esc_url(tkm_admin_url($tkm_nav_slug)); ?>" <?php echo $tkm_nav_active ? 'aria-current="page"' : ''; ?>>
                    <?php echo tkm_icon($tkm_nav_item['icon'], 17); ?>
                    <span><?php echo esc_html($tkm_nav_item['label']); ?></span>
                </a>
            <?php endforeach; ?>
        </nav>

        <div class="tkm-topbar__actions">
            <button type="button" class="tkm-cmdk-trigger" data-cmdk-open aria-label="جستجوی سریع">
                <?php echo tkm_icon('search', 16); ?><span class="tkm-hide-sm">جستجو…</span><kbd class="tkm-kbd tkm-hide-sm">Ctrl K</kbd>
            </button>
            <div class="tkm-bell" data-bell>
                <button type="button" class="tkm-btn tkm-btn--ghost tkm-btn--icon" data-bell-toggle aria-label="اعلان‌ها" aria-haspopup="true" aria-expanded="false">
                    <?php echo tkm_icon('bell', 18); ?><span class="tkm-bell__dot tkm-num" data-bell-count hidden></span>
                </button>
                <div class="tkm-popover tkm-bell__panel" data-bell-panel hidden>
                    <div class="tkm-popover__head"><strong>اعلان‌ها</strong><button type="button" class="tkm-link-btn" data-bell-readall>همه خوانده شد</button></div>
                    <div class="tkm-bell__list" data-bell-list></div>
                </div>
            </div>
            <button type="button" class="tkm-btn tkm-btn--ghost tkm-btn--icon" data-theme-toggle aria-label="تغییر حالت روشن / تاریک" title="حالت روشن / تاریک">
                <span class="tkm-theme-dark"><?php echo tkm_icon('moon', 18); ?></span>
                <span class="tkm-theme-light"><?php echo tkm_icon('sun', 18); ?></span>
            </button>
            <a class="tkm-btn tkm-btn--primary" href="<?php echo esc_url(tkm_admin_url('tkm-new-ticket')); ?>">
                <?php echo tkm_icon('plus', 16); ?><span class="tkm-hide-sm">تیکت جدید</span>
            </a>
            <div class="tkm-usermenu" data-usermenu>
                <button type="button" class="tkm-usermenu__btn" data-usermenu-toggle aria-haspopup="true" aria-expanded="false" aria-label="حساب کاربری">
                    <?php echo tkm_avatar($tkm_me->ID, 'sm'); ?>
                </button>
                <div class="tkm-popover tkm-usermenu__panel" data-usermenu-panel hidden>
                    <div class="tkm-usermenu__who">
                        <strong><?php echo esc_html($tkm_me->display_name); ?></strong>
                        <span class="tkm-ltr"><?php echo esc_html($tkm_me->user_email); ?></span>
                    </div>
                    <button type="button" data-avatar-open><?php echo tkm_icon('user', 16); ?> تغییر آواتار</button>
                    <a href="<?php echo esc_url(admin_url()); ?>"><?php echo tkm_icon('home', 16); ?> پیشخوان وردپرس</a>
                    <a href="<?php echo esc_url(home_url('/')); ?>" target="_blank" rel="noopener"><?php echo tkm_icon('external', 16); ?> نمایش سایت</a>
                    <a href="<?php echo esc_url(get_edit_profile_url($tkm_me->ID)); ?>"><?php echo tkm_icon('user', 16); ?> ویرایش پروفایل</a>
                    <a href="<?php echo esc_url(wp_logout_url(admin_url())); ?>" class="is-danger"><?php echo tkm_icon('arrow-left', 16); ?> خروج</a>
                </div>
            </div>
        </div>
    </header>

    <main class="tkm-main">
        <div class="tkm-flashes"><?php tkm_flash_render(); ?></div>
