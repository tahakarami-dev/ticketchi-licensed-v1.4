<?php

namespace TicketchiLite;

defined('ABSPATH') || exit;

/**
 * Bootstrap: wires the modules together once all plugins are loaded.
 */
final class Plugin
{
    public static function boot()
    {
        // Ticketchi Pro shares the same tickets; when it is active (loaded before
        // or after Lite) it always wins and Lite stays out of the way.
        if (self::pro_active()) {
            add_action('admin_notices', [__CLASS__, 'pro_active_notice']);
            return;
        }

        add_action('init', [Install::class, 'maybe_upgrade'], 1);

        Notifier::register();
        Front::register();
        Rest::register();
        if (is_admin()) {
            Admin::register();
            Promo::register();
        }
    }

    /** Ticketchi Pro, including the older 1.x releases. */
    public static function pro_active()
    {
        return defined('TKM_BASE_FILE') || defined('TKM_PATH') || class_exists('TKM_Core', false) || class_exists('TKM_Ticket_Manager', false);
    }

    public static function pro_active_notice()
    {
        if (!current_user_can('activate_plugins')) {
            return;
        }
        echo '<div class="notice notice-warning"><p><strong>تیکت‌چی لایت غیرفعال مانده است:</strong> نسخه حرفه‌ای (یا نسخه قدیمی) تیکت‌چی روی این سایت فعال است و همان تیکت‌ها را مدیریت می‌کند، بنابراین منوی تیکت‌چی لایت نمایش داده نمی‌شود. یکی از دو نسخه را از صفحه افزونه‌ها غیرفعال کنید؛ اطلاعات تیکت‌ها حفظ می‌شود.</p></div>';
    }
}
