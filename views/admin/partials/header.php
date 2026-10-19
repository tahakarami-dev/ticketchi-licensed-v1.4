<?php
/**
 * Admin app shell — opening markup. Close with partials/footer.php.
 */
defined('ABSPATH') || exit('NO Access');

$tkm_current = isset($_GET['page']) ? sanitize_key(wp_unslash($_GET['page'])) : '';
$tkm_can_manage = current_user_can('tkm_manage');
$tkm_shell_class = isset($tkm_shell_class) ? $tkm_shell_class : '';

$tkm_nav = [
    'tkm-analysis'    => ['label' => 'داشبورد', 'icon' => 'grid'],
    'tkm-tickets'     => ['label' => 'صندوق پشتیبانی', 'icon' => 'inbox', 'match' => ['tkm-new-ticket']],
    'tkm-reports'     => ['label' => 'گزارش‌ها', 'icon' => 'bar-chart', 'manage' => true],
    'tkm-automations' => ['label' => 'اتوماسیون', 'icon' => 'zap', 'manage' => true],
    'tkm-departments' => ['label' => 'تیم و دپارتمان‌ها', 'icon' => 'users', 'manage' => true],
    'tkm-settings'    => ['label' => 'تنظیمات', 'icon' => 'settings', 'manage' => true],
];
?>?>
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
        </div>
    </header>

    <main class="tkm-main">
        <div class="tkm-flashes"><?php tkm_flash_render(); ?></div>
