<?php

namespace TicketchiLite;

defined('ABSPATH') || exit;

/**
 * Everything about Ticketchi Pro: the feature list, upgrade links and the
 * upgrade teasers shown inside Ticketchi screens. Pro features are not part of
 * this plugin; these are only links to the Pro version.
 */
final class Promo
{
    const NOTICE_META = 'ticketchi_lite_promo_dismissed';

    public static function register()
    {
        add_action('admin_notices', [__CLASS__, 'notice']);
        add_action('wp_ajax_ticketchi_lite_dismiss_promo', [__CLASS__, 'dismiss']);
    }

    /** Upgrade link with a campaign tag, so sales can be attributed to a screen. */
    public static function url($place = 'general')
    {
        return add_query_arg(['utm_source' => 'ticketchi-lite', 'utm_medium' => 'plugin', 'utm_campaign' => sanitize_key($place)], TICKETCHI_LITE_PRO_URL);
    }

    /** @return array[] key, icon, title, text */
    public static function features()
    {
        return [
            ['key' => 'team', 'icon' => 'users', 'title' => 'تیم پشتیبانی و تخصیص', 'text' => 'نقش «کارشناس پشتیبانی»، تعریف بی‌نهایت کارمند پاسخگو برای هر دپارتمان، سپردن تیکت به کارشناس، تخصیص خودکار و تغییر اولویت و دپارتمان تیکت.'],
            ['key' => 'customer-ux', 'icon' => 'paperclip', 'title' => 'پیوست فایل و پیام صوتی', 'text' => 'مشتری و پشتیبانی فایل و تصویر پیوست می‌کنند (چند فایل هم‌زمان)، پیام صوتی ضبط می‌کنند و مشتری به پاسخ‌ها امتیاز می‌دهد.'],
            ['key' => 'woocommerce', 'icon' => 'shopping-bag', 'title' => 'ووکامرس کامل', 'text' => 'تب «تیکت‌های پشتیبانی» در حساب کاربری ووکامرس، انتخاب محصول خریداری‌شده در فرم تیکت و سفارش‌های مشتری کنار هر گفتگو.'],
            ['key' => 'tags', 'icon' => 'search', 'title' => 'جستجو، فیلتر و برچسب', 'text' => 'جستجوی فوری با شماره، عنوان، نام و ایمیل؛ فیلتر و مرتب‌سازی؛ برچسب‌های رنگی، عملیات گروهی و جستجوی سراسری Ctrl+K.'],
            ['key' => 'sla', 'icon' => 'clock', 'title' => 'SLA و مهلت پاسخ', 'text' => 'مهلت اولین پاسخ و حل بر اساس اولویت و ساعات کاری، تایمر در صندوق و هشدار پیش از عبور از مهلت.'],
            ['key' => 'automation', 'icon' => 'zap', 'title' => 'اتوماسیون «وقتی ← اگر ← آنگاه»', 'text' => 'سازنده بصری قوانین با الگوهای آماده: سپردن، تغییر اولویت و وضعیت، برچسب، پاسخ خودکار و اطلاع‌رسانی.'],
            ['key' => 'reports', 'icon' => 'bar-chart', 'title' => 'داشبورد و گزارش‌ها', 'text' => '۸ شاخص کلیدی با مقایسه دوره قبل، روند روزانه، عملکرد کارشناسان، رضایت مشتری، نقشه حرارتی ساعات شلوغ و خروجی CSV.'],
            ['key' => 'notes', 'icon' => 'lock', 'title' => 'یادداشت داخلی و تاریخچه', 'text' => 'گفتگوی تیمی که مشتری نمی‌بیند، یادداشت ثابت روی تیکت و تاریخچه کامل همه تغییرات.'],
            ['key' => 'canned', 'icon' => 'reply', 'title' => 'پاسخ‌های آماده و سوالات متداول', 'text' => 'جواب سوال‌های تکراری را با یک کلیک بفرستید و پیش از فرم تیکت، سوالات متداول را به مشتری نشان دهید.'],
            ['key' => 'customer', 'icon' => 'user-check', 'title' => 'پروفایل ۳۶۰ مشتری', 'text' => 'سفارش‌ها و مبلغ خرید، تیکت‌های قبلی و میزان رضایت هر مشتری در یک نگاه.'],
            ['key' => 'sms', 'icon' => 'message', 'title' => 'پیامک و اعلان درون پنل', 'text' => 'اطلاع‌رسانی پیامکی (ملی پیامک و کاوه‌نگار) و زنگوله اعلان‌ها برای تیم.'],
            ['key' => 'kb', 'icon' => 'book', 'title' => 'پایگاه دانش', 'text' => 'مرکز راهنما و پیشنهاد خودکار مقاله هنگام نوشتن تیکت تا تیکت‌های تکراری کم شوند.'],
            ['key' => 'brand', 'icon' => 'sparkles', 'title' => 'برند، ظاهر و تنظیمات کامل', 'text' => 'رنگ برند، حالت تاریک، زیرمجموعه دپارتمان، وضعیت‌های اختصاصی، پاسخ خودکار، بستن خودکار تیکت‌ها و ۱۳ بخش تنظیمات.'],
        ];
    }

    /** [label, in Lite, in Pro] rows for the comparison table. */
    public static function comparison()
    {
        return [
            ['ثبت و پیگیری تیکت توسط مشتری (شورت‌کد)', true, true],
            ['صندوق پشتیبانی و پاسخ به تیکت', true, true],
            ['دپارتمان‌ها', 'ساده', 'با زیرمجموعه و کارشناس'],
            ['اعلان ایمیلی', 'پایه', 'کامل و قابل تنظیم'],
            ['پیوست فایل و پیام صوتی', false, true],
            ['تب تیکت در حساب کاربری ووکامرس', false, true],
            ['جستجو، فیلتر و مرتب‌سازی', false, true],
            ['اولویت تیکت', false, true],
            ['کارشناسان پشتیبانی و تخصیص تیکت', false, true],
            ['SLA و ساعات کاری', false, true],
            ['اتوماسیون', false, true],
            ['داشبورد و گزارش‌ها', false, true],
            ['یادداشت داخلی، برچسب و پاسخ آماده', false, true],
            ['پروفایل ۳۶۰ مشتری', false, true],
            ['پیامک', false, true],
            ['پایگاه دانش', false, true],
            ['رنگ برند و حالت تاریک', false, true],
            ['پشتیبانی و بروزرسانی ویژه', false, true],
        ];
    }

    /** Small upgrade card used inside Ticketchi screens. */
    public static function teaser($place, $title, $text)
    {
        $kses = ticketchi_lite_allowed_html();
        return '<a class="tkm-promo" href="' . esc_url(self::url($place)) . '" target="_blank" rel="noopener">'
            . '<span class="tkm-promo__icon">' . wp_kses(ticketchi_lite_icon('crown', 18), $kses) . '</span>'
            . '<span class="tkm-promo__text"><strong>' . esc_html($title) . '</strong><span>' . esc_html($text) . '</span></span>'
            . '<span class="tkm-promo__cta">ارتقا به نسخه حرفه‌ای ' . wp_kses(ticketchi_lite_icon('chevron-left', 14), $kses) . '</span></a>';
    }

    /**
     * One dismissible notice, only on the WordPress dashboard and the plugins
     * screen, and only once the site has real tickets.
     */
    public static function notice()
    {
        $screen = function_exists('get_current_screen') ? get_current_screen() : null;
        if (!$screen || !in_array($screen->id, ['dashboard', 'plugins'], true) || !ticketchi_lite_is_staff()) {
            return;
        }
        if (get_user_meta(get_current_user_id(), self::NOTICE_META, true)) {
            return;
        }
        global $wpdb;
        $count = (int) $wpdb->get_var('SELECT COUNT(*) FROM ' . Tickets::table());
        if ($count < 5) {
            return;
        }

        $nonce = wp_create_nonce('ticketchi_lite_dismiss_promo');
        echo '<div class="notice notice-info is-dismissible" data-ticketchi-lite-promo="' . esc_attr($nonce) . '" style="border-right-color:#2A48CD">'
            . '<p><strong>تیکت‌چی:</strong> تا امروز ' . esc_html(ticketchi_lite_fa($count)) . ' تیکت ثبت شده است 🎉 با نسخه حرفه‌ای تیکت‌ها را بین کارشناسان تقسیم کنید، SLA تعریف کنید و با اتوماسیون و گزارش‌ها پشتیبانی را چند برابر سریع‌تر کنید. '
            . '<a href="' . esc_url(self::url('dashboard-notice')) . '" target="_blank" rel="noopener"><strong>مشاهده نسخه حرفه‌ای</strong></a></p></div>';

        wp_register_script('ticketchi-lite-promo', false, ['jquery'], TICKETCHI_LITE_VERSION, true);
        wp_enqueue_script('ticketchi-lite-promo');
        wp_add_inline_script('ticketchi-lite-promo', "jQuery(function($){\$(document).on('click','[data-ticketchi-lite-promo] .notice-dismiss',function(){var n=\$(this).closest('[data-ticketchi-lite-promo]');\$.post(ajaxurl,{action:'ticketchi_lite_dismiss_promo',nonce:n.attr('data-ticketchi-lite-promo')});});});");
    }

    public static function dismiss()
    {
        check_ajax_referer('ticketchi_lite_dismiss_promo', 'nonce');
        update_user_meta(get_current_user_id(), self::NOTICE_META, time());
        wp_send_json_success();
    }
}
