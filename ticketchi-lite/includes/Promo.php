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
            ['key' => 'team', 'icon' => 'users', 'title' => 'تیم پشتیبانی و تخصیص', 'text' => 'نقش «کارشناس پشتیبانی»، سپردن تیکت به کارشناس، تخصیص خودکار نوبتی یا به کم‌کارترین کارشناس و دسترسی هر کارشناس فقط به دپارتمان‌های خودش.'],
            ['key' => 'sla', 'icon' => 'clock', 'title' => 'SLA و مهلت پاسخ', 'text' => 'مهلت اولین پاسخ و حل بر اساس اولویت و ساعات کاری، تایمر در صندوق و هشدار پیش از عبور از مهلت.'],
            ['key' => 'automation', 'icon' => 'zap', 'title' => 'اتوماسیون «وقتی ← اگر ← آنگاه»', 'text' => 'سازنده بصری قوانین با الگوهای آماده: سپردن، تغییر اولویت و وضعیت، برچسب، پاسخ خودکار و اطلاع‌رسانی.'],
            ['key' => 'reports', 'icon' => 'bar-chart', 'title' => 'داشبورد و گزارش‌ها', 'text' => '۸ شاخص کلیدی با مقایسه دوره قبل، روند روزانه، عملکرد کارشناسان، رضایت مشتری، نقشه حرارتی ساعات شلوغ و خروجی CSV.'],
            ['key' => 'notes', 'icon' => 'lock', 'title' => 'یادداشت داخلی و تاریخچه', 'text' => 'گفتگوی تیمی که مشتری نمی‌بیند، یادداشت ثابت روی تیکت و تاریخچه کامل همه تغییرات.'],
            ['key' => 'canned', 'icon' => 'reply', 'title' => 'پاسخ‌های آماده', 'text' => 'جواب سوال‌های تکراری را با یک کلیک بفرستید؛ صرفه‌جویی جدی در زمان تیم.'],
            ['key' => 'tags', 'icon' => 'tag', 'title' => 'برچسب، عملیات گروهی و نماهای هوشمند', 'text' => 'برچسب‌های رنگی، «تیکت‌های من»، «بدون مسئول»، «در خطر SLA»، عملیات گروهی و میانبرهای صفحه‌کلید.'],
            ['key' => 'customer', 'icon' => 'shopping-bag', 'title' => 'پروفایل ۳۶۰ مشتری', 'text' => 'سفارش‌ها و مبلغ خرید ووکامرس، تیکت‌های قبلی و رضایت مشتری کنار هر گفتگو؛ انتخاب محصول خریداری‌شده در فرم تیکت.'],
            ['key' => 'sms', 'icon' => 'message', 'title' => 'پیامک و اعلان درون پنل', 'text' => 'اطلاع‌رسانی پیامکی (ملی پیامک و کاوه‌نگار) و زنگوله اعلان‌ها برای تیم.'],
            ['key' => 'kb', 'icon' => 'book', 'title' => 'پایگاه دانش', 'text' => 'مرکز راهنما و پیشنهاد خودکار مقاله هنگام نوشتن تیکت تا تیکت‌های تکراری کم شوند.'],
            ['key' => 'customer-ux', 'icon' => 'mic', 'title' => 'پیام صوتی، چند پیوست و امتیازدهی', 'text' => 'ضبط پیام صوتی، پیوست چند فایل هم‌زمان، امتیاز ۱ تا ۵ ستاره و سوالات متداول پیش از فرم.'],
            ['key' => 'more', 'icon' => 'sparkles', 'title' => 'و خیلی بیشتر', 'text' => 'ارسال تیکت از طرف پشتیبانی به مشتری، وضعیت‌های اختصاصی، پاسخ خودکار، بستن خودکار تیکت‌ها و جستجوی سراسری Ctrl+K.'],
        ];
    }

    /** [label, in Lite, in Pro] rows for the comparison table. */
    public static function comparison()
    {
        return [
            ['ثبت و پیگیری تیکت توسط مشتری', true, true],
            ['صندوق پشتیبانی مدرن و دپارتمان‌ها', true, true],
            ['پیوست فایل', 'یک فایل', 'چند فایل + پیام صوتی'],
            ['اعلان ایمیلی برندشده', true, true],
            ['ووکامرس (حساب کاربری › تیکت‌ها)', true, true],
            ['کارشناسان پشتیبانی و تخصیص تیکت', false, true],
            ['SLA و ساعات کاری', false, true],
            ['اتوماسیون', false, true],
            ['داشبورد و گزارش‌ها', false, true],
            ['یادداشت داخلی، برچسب و پاسخ آماده', false, true],
            ['پروفایل ۳۶۰ مشتری', false, true],
            ['پیامک', false, true],
            ['پایگاه دانش', false, true],
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
