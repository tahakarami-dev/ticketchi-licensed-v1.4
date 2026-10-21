<?php

namespace TicketchiLite;

defined('ABSPATH') || exit;

/**
 * wp-admin screens. One sidebar entry opens the Ticketchi app, which runs
 * full-screen with its own top navigation.
 */
final class Admin
{
    const PAGES = [
        'ticketchi-lite'             => 'inbox',
        'ticketchi-lite-departments' => 'departments',
        'ticketchi-lite-settings'    => 'settings',
        'ticketchi-lite-pro'         => 'pro',
    ];

    public static function register()
    {
        add_action('admin_menu', [__CLASS__, 'menu']);
        add_action('admin_init', [__CLASS__, 'handle_departments']);
        add_action('admin_enqueue_scripts', [__CLASS__, 'assets']);
        add_filter('admin_body_class', [__CLASS__, 'body_class']);
        add_filter('plugin_action_links_' . plugin_basename(TICKETCHI_LITE_FILE), [__CLASS__, 'action_links']);
    }

    public static function current()
    {
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- routing only.
        $page = isset($_GET['page']) ? sanitize_key(wp_unslash($_GET['page'])) : '';
        return is_admin() && isset(self::PAGES[$page]) ? $page : '';
    }

    /** Administrators, or shop managers on WooCommerce stores. */
    private static function capability()
    {
        return current_user_can('manage_options') || !current_user_can('manage_woocommerce') ? 'manage_options' : 'manage_woocommerce';
    }

    public static function ticket_url($ticket_id)
    {
        return ticketchi_lite_admin_url('ticketchi-lite') . '#/ticket/' . (int) $ticket_id;
    }

    public static function menu()
    {
        $cap = self::capability();
        add_menu_page('تیکت‌چی', 'تیکت‌چی', $cap, 'ticketchi-lite', [__CLASS__, 'page'], self::menu_icon(), 26);
        add_submenu_page('ticketchi-lite', 'صندوق پشتیبانی', 'صندوق پشتیبانی', $cap, 'ticketchi-lite', [__CLASS__, 'page']);
        add_submenu_page('ticketchi-lite', 'دپارتمان‌ها', 'دپارتمان‌ها', $cap, 'ticketchi-lite-departments', [__CLASS__, 'page']);
        add_submenu_page('ticketchi-lite', 'تنظیمات', 'تنظیمات', $cap, 'ticketchi-lite-settings', [__CLASS__, 'page']);
        add_submenu_page('ticketchi-lite', 'نسخه حرفه‌ای', 'نسخه حرفه‌ای', $cap, 'ticketchi-lite-pro', [__CLASS__, 'page']);
    }

    /** Ticket with typing dots; WordPress repaints the SVG to match the admin colour scheme. */
    private static function menu_icon()
    {
        $svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20"><path fill="black" fill-rule="evenodd" d="M3.5 3.5h13a2.5 2.5 0 0 1 2.5 2.5v2.1a1.9 1.9 0 0 0 0 3.8V14a2.5 2.5 0 0 1-2.5 2.5h-13A2.5 2.5 0 0 1 1 14v-2.1a1.9 1.9 0 0 0 0-3.8V6a2.5 2.5 0 0 1 2.5-2.5zM5.7 10a1.3 1.3 0 1 0 2.6 0a1.3 1.3 0 1 0-2.6 0zm3 0a1.3 1.3 0 1 0 2.6 0a1.3 1.3 0 1 0-2.6 0zm3 0a1.3 1.3 0 1 0 2.6 0a1.3 1.3 0 1 0-2.6 0z"/></svg>';
        // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- data URI for the menu icon.
        return 'data:image/svg+xml;base64,' . base64_encode($svg);
    }

    public static function page()
    {
        $page = self::current();
        if (!$page || !ticketchi_lite_is_staff()) {
            wp_die(esc_html('شما اجازه دسترسی به این صفحه را ندارید.'), '', ['response' => 403]);
        }

        $view = self::PAGES[$page];
        if ($view === 'departments') {
            $departments = Departments::all();
            // phpcs:disable WordPress.Security.NonceVerification.Recommended -- read-only.
            $editing = isset($_GET['action'], $_GET['id']) && sanitize_key(wp_unslash($_GET['action'])) === 'edit' ? Departments::find(absint($_GET['id'])) : null;
            // phpcs:enable
        }
        include TICKETCHI_LITE_PATH . 'views/admin/' . $view . '.php';
    }

    public static function body_class($classes)
    {
        return self::current() ? $classes . ' tkm-admin-page ' : $classes;
    }

    public static function action_links($links)
    {
        array_unshift($links, '<a href="' . esc_url(ticketchi_lite_admin_url('ticketchi-lite-settings')) . '">تنظیمات</a>');
        $links[] = '<a href="' . esc_url(ticketchi_lite_admin_url('ticketchi-lite-pro')) . '" style="font-weight:600">نسخه حرفه‌ای</a>';
        return $links;
    }

    /* ───────────── Assets ───────────── */

    public static function assets()
    {
        $page = self::current();
        if (!$page) {
            return;
        }
        $v = TICKETCHI_LITE_VERSION;
        $url = TICKETCHI_LITE_URL . 'assets/';

        wp_enqueue_style('ticketchi-lite-ui', $url . 'css/ui.css', [], $v);
        wp_enqueue_style('ticketchi-lite-admin', $url . 'css/admin.css', ['ticketchi-lite-ui'], $v);
        wp_enqueue_style('ticketchi-lite-app', $url . 'css/app.css', ['ticketchi-lite-admin'], $v);
        wp_add_inline_style('ticketchi-lite-app', Front::brand_css() . self::fullscreen_css());

        // Restore the dark theme before the first paint.
        wp_register_script('ticketchi-lite-theme', false, [], $v, false);
        wp_enqueue_script('ticketchi-lite-theme');
        wp_add_inline_script('ticketchi-lite-theme', "try{if(localStorage.getItem('tkm-theme')==='dark'){document.documentElement.classList.add('tkm-dark');}}catch(e){}");

        wp_enqueue_script('ticketchi-lite-admin', $url . 'js/admin.js', ['jquery'], $v, true);

        wp_enqueue_script('ticketchi-lite-kit', $url . 'js/kit.js', ['ticketchi-lite-admin'], $v, true);
        wp_localize_script('ticketchi-lite-kit', 'TICKETCHI_LITE_APP', [
            'root'  => esc_url_raw(rest_url(Rest::NS . '/')),
            'nonce' => wp_create_nonce('wp_rest'),
            'admin' => admin_url('admin.php'),
        ]);

        $apps = ['ticketchi-lite' => 'inbox', 'ticketchi-lite-settings' => 'settings'];
        if (isset($apps[$page])) {
            wp_enqueue_script('ticketchi-lite-' . $apps[$page], $url . 'js/' . $apps[$page] . '.js', ['ticketchi-lite-kit'], $v, true);
        }

        // The app owns Ctrl/⌘+K and full-screen layout on its screens.
        wp_dequeue_script('wp-core-commands');
        wp_dequeue_style('wp-commands');
    }

    /** Ticketchi screens run as a standalone app: no wp-admin sidebar or toolbar. */
    private static function fullscreen_css()
    {
        return 'html.wp-toolbar{padding-top:0!important}#wpadminbar,#adminmenumain,#screen-meta,#screen-meta-links,#wpfooter{display:none!important}#wpcontent{margin-left:0!important;margin-right:0!important}#wpbody-content{float:none!important}#wpbody{padding-top:0!important}.auto-fold #wpcontent{margin:0!important}';
    }

    /* ───────────── Departments form ───────────── */

    public static function handle_departments()
    {
        if (self::current() !== 'ticketchi-lite-departments' || !ticketchi_lite_is_staff()) {
            return;
        }
        $list = ticketchi_lite_admin_url('ticketchi-lite-departments');

        if (isset($_POST['ticketchi_lite_department_nonce'])) {
            check_admin_referer('ticketchi_lite_department', 'ticketchi_lite_department_nonce');

            $name = isset($_POST['name']) ? sanitize_text_field(wp_unslash($_POST['name'])) : '';
            if ($name === '') {
                ticketchi_lite_flash('عنوان دپارتمان الزامی است.', 'danger');
                wp_safe_redirect(wp_get_referer() ?: $list);
                exit;
            }

            $id = isset($_POST['department_id']) ? absint($_POST['department_id']) : 0;
            $saved = Departments::save([
                'name'        => $name,
                'parent'      => isset($_POST['parent']) ? absint($_POST['parent']) : 0,
                'position'    => isset($_POST['position']) ? sanitize_text_field(wp_unslash($_POST['position'])) : '',
                'description' => isset($_POST['description']) ? sanitize_textarea_field(wp_unslash($_POST['description'])) : '',
            ], $id);

            if ($saved) {
                ticketchi_lite_flash($id ? 'دپارتمان «' . esc_html($name) . '» بروزرسانی شد.' : 'دپارتمان «' . esc_html($name) . '» اضافه شد.');
            } else {
                ticketchi_lite_flash('ذخیره دپارتمان با خطا مواجه شد.', 'danger');
            }
            wp_safe_redirect($list);
            exit;
        }

        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- the nonce is checked right below.
        if (isset($_GET['action'], $_GET['id']) && sanitize_key(wp_unslash($_GET['action'])) === 'delete') {
            check_admin_referer('ticketchi_lite_delete_department');
            Departments::delete(absint($_GET['id']));
            ticketchi_lite_flash('دپارتمان حذف شد.', 'info');
            wp_safe_redirect($list);
            exit;
        }
    }
}
