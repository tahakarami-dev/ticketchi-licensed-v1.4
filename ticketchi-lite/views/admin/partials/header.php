<?php
/**
 * Admin app shell — opening markup. Close with partials/footer.php.
 *
 * @var string $ticketchi_lite_shell optional extra class on the shell
 */
defined('ABSPATH') || exit;

$ticketchi_lite_current = \TicketchiLite\Admin::current();
$ticketchi_lite_me = wp_get_current_user();
$ticketchi_lite_nav = [
    'ticketchi-lite'             => ['label' => 'صندوق پشتیبانی', 'icon' => 'inbox'],
    'ticketchi-lite-departments' => ['label' => 'دپارتمان‌ها', 'icon' => 'layers'],
    'ticketchi-lite-settings'    => ['label' => 'تنظیمات', 'icon' => 'settings'],
];
// Screens that only exist in Ticketchi Pro: shown as links to the upgrade page.
$ticketchi_lite_pro_nav = [
    'reports'     => ['label' => 'گزارش‌ها', 'icon' => 'bar-chart'],
    'automation'  => ['label' => 'اتوماسیون', 'icon' => 'zap'],
];
$ticketchi_lite_kses = ticketchi_lite_allowed_html();
?>
<div class="tkm-ui tkm-admin <?php echo esc_attr(isset($ticketchi_lite_shell) ? $ticketchi_lite_shell : ''); ?>" data-theme="light" id="tkm-admin">
    <header class="tkm-topbar">
        <a class="tkm-exit" href="<?php echo esc_url(admin_url()); ?>" title="بازگشت به پیشخوان وردپرس" aria-label="بازگشت به پیشخوان وردپرس"><?php echo wp_kses(ticketchi_lite_icon('arrow-right', 18), $ticketchi_lite_kses); ?></a>
        <a class="tkm-brand" href="<?php echo esc_url(ticketchi_lite_admin_url('ticketchi-lite')); ?>">
            <span class="tkm-brand__mark" aria-hidden="true"><?php echo wp_kses(ticketchi_lite_icon('ticket', 20), $ticketchi_lite_kses); ?></span>
            <span class="tkm-brand__text">
                <strong>تیکت‌چی</strong>
                <small>نسخه لایت</small>
            </span>
        </a>

        <nav class="tkm-nav" aria-label="ناوبری تیکت‌چی">
            <?php foreach ($ticketchi_lite_nav as $ticketchi_lite_slug => $ticketchi_lite_item) :
                $ticketchi_lite_active = $ticketchi_lite_current === $ticketchi_lite_slug; ?>
                <a class="tkm-nav__item <?php echo $ticketchi_lite_active ? 'is-active' : ''; ?>" href="<?php echo esc_url(ticketchi_lite_admin_url($ticketchi_lite_slug)); ?>" <?php echo $ticketchi_lite_active ? 'aria-current="page"' : ''; ?>>
                    <?php echo wp_kses(ticketchi_lite_icon($ticketchi_lite_item['icon'], 17), $ticketchi_lite_kses); ?>
                    <span><?php echo esc_html($ticketchi_lite_item['label']); ?></span>
                </a>
            <?php endforeach; ?>
            <?php foreach ($ticketchi_lite_pro_nav as $ticketchi_lite_anchor => $ticketchi_lite_item) : ?>
                <a class="tkm-nav__item tkm-nav__item--pro" href="<?php echo esc_url(ticketchi_lite_admin_url('ticketchi-lite-pro') . '#pro-' . $ticketchi_lite_anchor); ?>" title="در نسخه حرفه‌ای">
                    <?php echo wp_kses(ticketchi_lite_icon($ticketchi_lite_item['icon'], 17), $ticketchi_lite_kses); ?>
                    <span><?php echo esc_html($ticketchi_lite_item['label']); ?></span>
                    <span class="tkm-pro-tag">PRO</span>
                </a>
            <?php endforeach; ?>
        </nav>

        <div class="tkm-topbar__actions">
            <a class="tkm-btn tkm-btn--soft tkm-pro-cta <?php echo $ticketchi_lite_current === 'ticketchi-lite-pro' ? 'is-active' : ''; ?>" href="<?php echo esc_url(ticketchi_lite_admin_url('ticketchi-lite-pro')); ?>">
                <?php echo wp_kses(ticketchi_lite_icon('crown', 16), $ticketchi_lite_kses); ?><span class="tkm-hide-sm">نسخه حرفه‌ای</span>
            </a>
            <div class="tkm-usermenu" data-usermenu>
                <button type="button" class="tkm-usermenu__btn" data-usermenu-toggle aria-haspopup="true" aria-expanded="false" aria-label="حساب کاربری">
                    <?php echo wp_kses(ticketchi_lite_avatar($ticketchi_lite_me->ID, 'sm'), $ticketchi_lite_kses); ?>
                </button>
                <div class="tkm-popover tkm-usermenu__panel" data-usermenu-panel hidden>
                    <div class="tkm-usermenu__who">
                        <strong><?php echo esc_html($ticketchi_lite_me->display_name); ?></strong>
                        <span class="tkm-ltr"><?php echo esc_html($ticketchi_lite_me->user_email); ?></span>
                    </div>
                    <a href="<?php echo esc_url(admin_url()); ?>"><?php echo wp_kses(ticketchi_lite_icon('home', 16), $ticketchi_lite_kses); ?> پیشخوان وردپرس</a>
                    <a href="<?php echo esc_url(home_url('/')); ?>" target="_blank" rel="noopener"><?php echo wp_kses(ticketchi_lite_icon('external', 16), $ticketchi_lite_kses); ?> نمایش سایت</a>
                    <a href="<?php echo esc_url(get_edit_profile_url($ticketchi_lite_me->ID)); ?>"><?php echo wp_kses(ticketchi_lite_icon('user', 16), $ticketchi_lite_kses); ?> ویرایش پروفایل</a>
                    <a href="<?php echo esc_url(wp_logout_url(admin_url())); ?>" class="is-danger"><?php echo wp_kses(ticketchi_lite_icon('arrow-left', 16), $ticketchi_lite_kses); ?> خروج</a>
                </div>
            </div>
        </div>
    </header>

    <main class="tkm-main">
        <div class="tkm-flashes"><?php ticketchi_lite_flash_render(); ?></div>
