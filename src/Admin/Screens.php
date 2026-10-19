<?php

namespace Ticketchi\Admin;

use Ticketchi\Notifications\Notifier;
use Ticketchi\Support\Capabilities;
use Ticketchi\Support\Links;

defined('ABSPATH') || exit('NO Access');

/**
 * Glue between wp-admin screens (registered through TKM_MENU / BASE_MENU)
 * and the REST-driven admin apps.
 */
final class Screens
{
    const APPS = ['tkm-tickets' => 'inbox', 'tkm-reports' => 'reports', 'tkm-automations' => 'automations'];

    public static function register()
    {
        add_action('admin_init', [__CLASS__, 'legacy_redirects']);
        add_action('admin_enqueue_scripts', [__CLASS__, 'assets'], 20);
    }

    public static function current()
    {
        return isset($_GET['page']) ? sanitize_key(wp_unslash($_GET['page'])) : '';
    }

    /** Old deep links (emails, bookmarks) keep working. */
    public static function legacy_redirects()
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
            return;
        }
        $page = self::current();
        if ($page === 'tkm-edit-ticket' && !empty($_GET['id'])) {
            wp_safe_redirect(Links::admin_ticket(absint($_GET['id'])));
            exit;
        }
        if ($page === 'tkm-my-departments') {
            wp_safe_redirect(admin_url('admin.php?page=tkm-tickets') . '#/view/mine');
            exit;
        }
    }

    public static function assets()
    {
        if (!\TKM_ASSETS::is_plugin_admin_page()) {
            return;
        }

        // Shared app context (bell, command palette, apps).
        wp_localize_script('tkm-main', 'TKM_APP', [
            'root'     => esc_url_raw(rest_url('ticketchi/v1/')),
            'nonce'    => wp_create_nonce('wp_rest'),
            'admin'    => admin_url('admin.php'),
            'manage'   => Capabilities::can_manage(),
            'unread'   => Notifier::unread_count(get_current_user_id()),
            'csv'      => wp_nonce_url(admin_url('admin-post.php?action=tkm_reports_csv'), 'tkm_reports_csv'),
        ]);

        // Our palette owns Ctrl/⌘+K on Ticketchi screens.
        wp_dequeue_script('wp-core-commands');
        wp_dequeue_style('wp-commands');

        wp_enqueue_style('tkm-app', TKM_ADMIN_ASSETS . 'css/tkm-app.css', ['tkm-admin'], TKM_VER);
        wp_enqueue_script('tkm-kit', TKM_ADMIN_ASSETS . 'js/tkm-kit.js', ['tkm-main'], TKM_VER, true);

        $page = self::current();
        if (isset(self::APPS[$page])) {
            $app = self::APPS[$page];
            wp_enqueue_script('tkm-' . $app, TKM_ADMIN_ASSETS . 'js/tkm-' . $app . '.js', ['tkm-kit'], TKM_VER, true);
        }
    }

    /** Access gate for manager-only screens. */
    public static function deny()
    {
        echo '<div class="tkm-card tkm-empty"><div class="tkm-empty__art">' . tkm_icon('lock', 30) . '</div><h3 class="tkm-empty__title">دسترسی محدود است</h3><p class="tkm-empty__text">این بخش فقط برای مدیران پشتیبانی در دسترس است.</p></div>';
    }
}
