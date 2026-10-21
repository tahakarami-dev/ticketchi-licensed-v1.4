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
        if (defined('TKM_BASE_FILE') || class_exists('TKM_Core', false)) {
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

    public static function pro_active_notice()
    {
        if (!current_user_can('activate_plugins')) {
            return;
        }
        echo '<div class="notice notice-info"><p><strong>تیکت‌چی لایت:</strong> نسخه حرفه‌ای تیکت‌چی فعال است و همه تیکت‌ها را مدیریت می‌کند؛ می‌توانید تیکت‌چی لایت را غیرفعال و حذف کنید. اطلاعات تیکت‌ها حفظ می‌شود.</p></div>';
    }
}
