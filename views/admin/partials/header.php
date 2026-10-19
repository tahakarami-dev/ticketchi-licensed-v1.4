<?php
/**
 * Admin app shell — opening markup. Close with partials/footer.php.
 */
defined('ABSPATH') || exit('NO Access');

$tkm_current = isset($_GET['page']) ? sanitize_key(wp_unslash($_GET['page'])) : '';
$tkm_open_count = (int) $GLOBALS['wpdb']->get_var("SELECT COUNT(*) FROM {$GLOBALS['wpdb']->prefix}tkm_tickets WHERE status = 'open'");

$tkm_nav = [
    'tkm-analysis'       => ['label' => 'داشبورد', 'icon' => 'grid'],
    'tkm-tickets'        => ['label' => 'تیکت‌ها', 'icon' => 'inbox', 'count' => $tkm_open_count, 'match' => ['tkm-edit-ticket']],
    'tkm-my-departments' => ['label' => 'صف من', 'icon' => 'layers'],
    'tkm-departments'    => ['label' => 'دپارتمان‌ها', 'icon' => 'building'],
    'tkm-settings'       => ['label' => 'تنظیمات', 'icon' => 'settings'],
];
?>
<div class="tkm-ui tkm-admin" data-theme="light" id="tkm-admin">
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
                $tkm_nav_active = $tkm_current === $tkm_nav_slug || (!empty($tkm_nav_item['match']) && in_array($tkm_current, $tkm_nav_item['match'], true));
            ?>
                <a class="tkm-nav__item <?php echo $tkm_nav_active ? 'is-active' : ''; ?>" href="<?php echo esc_url(tkm_admin_url($tkm_nav_slug)); ?>" <?php echo $tkm_nav_active ? 'aria-current="page"' : ''; ?>>
                    <?php echo tkm_icon($tkm_nav_item['icon'], 17); ?>
                    <span><?php echo esc_html($tkm_nav_item['label']); ?></span>
                    <?php if (!empty($tkm_nav_item['count'])) : ?>
                        <span class="tkm-nav__count tkm-num"><?php echo esc_html(tkm_fa($tkm_nav_item['count'])); ?></span>
                    <?php endif; ?>
                </a>
            <?php endforeach; ?>
        </nav>

        <div class="tkm-topbar__actions">
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
