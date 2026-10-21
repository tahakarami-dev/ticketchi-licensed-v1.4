<?php defined('ABSPATH') || exit; ?>
<div class="tkm-ui tkm-app">
    <div class="tkm-card tkm-empty tkm-gate">
        <div class="tkm-empty__art"><?php echo wp_kses(ticketchi_lite_icon('lock', 30), ticketchi_lite_allowed_html()); ?></div>
        <h3 class="tkm-empty__title">برای دسترسی به پشتیبانی وارد شوید</h3>
        <p class="tkm-empty__text">برای ثبت و پیگیری تیکت‌های پشتیبانی، ابتدا وارد حساب کاربری خود شوید.</p>
        <a class="tkm-btn tkm-btn--primary" href="<?php echo esc_url(wp_login_url(get_permalink() ?: home_url('/'))); ?>">
            <?php echo wp_kses(ticketchi_lite_icon('user', 16), ticketchi_lite_allowed_html()); ?> ورود به حساب کاربری
        </a>
    </div>
</div>
