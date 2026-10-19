<?php defined('ABSPATH') || exit('NO Access'); ?>
<div class="tkm-ui tkm-app">
    <div class="tkm-card tkm-empty tkm-gate">
        <div class="tkm-empty__art"><?php echo tkm_icon('lock', 30); ?></div>
        <h3 class="tkm-empty__title">برای دسترسی به پشتیبانی وارد شوید</h3>
        <p class="tkm-empty__text">برای ثبت و پیگیری تیکت‌های پشتیبانی، ابتدا وارد حساب کاربری خود شوید.</p>
        <a class="tkm-btn tkm-btn--primary" href="<?php echo esc_url(wp_login_url(home_url(add_query_arg([], isset($_SERVER['REQUEST_URI']) ? wp_unslash($_SERVER['REQUEST_URI']) : '')))); ?>">
            <?php echo tkm_icon('user', 16); ?> ورود به حساب کاربری
        </a>
    </div>
</div>
