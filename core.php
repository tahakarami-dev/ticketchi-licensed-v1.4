<?php
/*
Plugin Name:  تیکت‌چی
Description: با تیکت‌چی، مدیریت تیکت‌های پشتیبانی در وردپرس را متحول کنید. این افزونه قدرتمند، با ظاهری جذاب و مدرن و امکاناتی که واقعاً به کارتان می‌آید، تجربه‌ای حرفه‌ای و بی‌دردسر را برای شما و کاربرانتان رقم می‌زند. پشتیبانی را از حالت تکراری خارج کنید!
Version: 2.1.0
Author: طاها کرمی
Author URI: https://www.rtl-theme.com/author/taha-karami
*/

defined('ABSPATH') || exit('NO Access');

/*
 * Guard against two copies of Ticketchi being active at once, e.g. the old
 * version is still active and this one was uploaded from a GitHub zip into a
 * differently named folder. WordPress loads plugins alphabetically, so the
 * other copy may load before or after this one:
 *  - before: TKM_BASE_FILE is already defined → this copy steps aside;
 *  - after, and it is the old version (which has no guard and would fatal on
 *    the duplicate functions/classes) → this copy steps aside so it can load;
 *  - after, and it is this new version → it will see TKM_BASE_FILE and step aside.
 * A closure (not a named function) keeps this file free of global symbols.
 */
$tkm_legacy_copy_active = call_user_func(function ($self) {
    $self = wp_normalize_path($self);
    $plugins = (array) get_option('active_plugins', []);
    if (is_multisite()) {
        $plugins = array_merge($plugins, array_keys((array) get_site_option('active_sitewide_plugins', [])));
    }

    foreach ($plugins as $plugin) {
        if (basename($plugin) !== 'core.php') {
            continue;
        }
        $file = wp_normalize_path(WP_PLUGIN_DIR . '/' . $plugin);
        if ($file === $self || !is_readable($file)) {
            continue;
        }
        $head = (string) file_get_contents($file, false, null, 0, 1024);
        if (strpos($head, 'Plugin Name:') !== false && strpos($head, 'تیکت') !== false && !file_exists(dirname($file) . '/inc/tkm-ui.php')) {
            return true;
        }
    }

    return false;
}, __FILE__);

if (defined('TKM_BASE_FILE') || class_exists('TKM_Core', false) || $tkm_legacy_copy_active) {
    // Stay inert (the other copy keeps the site working) and say so on every
    // admin screen until the duplicate is removed; then this copy takes over.
    $tkm_duplicate_folder = basename(__DIR__);

    add_action('admin_notices', function () use ($tkm_duplicate_folder) {
        if (!current_user_can('activate_plugins')) {
            return;
        }
        echo '<div class="notice notice-error"><p><strong>تیکت‌چی:</strong> نسخه دیگری از افزونه تیکت‌چی هم‌زمان فعال است، بنابراین نسخه موجود در پوشه <code>' . esc_html($tkm_duplicate_folder) . '</code> اجرا نمی‌شود. از صفحه افزونه‌ها نسخه قبلی را غیرفعال و حذف کنید؛ اطلاعات تیکت‌ها در پایگاه داده باقی می‌ماند.</p></div>';
    });

    return;
}

// Declared inside a condition so it is bound at runtime, after the duplicate
// check above (an unconditional class is declared at compile time).
if (!class_exists('TKM_Core', false)) {
    class TKM_Core
    {

        private static $_instance = null;

        const MINIUM_PHP_VERSION = '7.4';

        public static function instance()
        {

            if (is_null(self::$_instance)) {

                self::$_instance = new self();
            }

            return  self::$_instance;
        }

        public function __construct()
        {

            if (version_compare(PHP_VERSION, self::MINIUM_PHP_VERSION, '<')) {
                add_action('admin_notices', [$this, 'admin_php_notice']);
                return;
            }

            $this->constant();
            $this->init();
        }
    
        public function constant()
        {

            if (!function_exists('get_plugin_data')) {
                require_once(ABSPATH . 'wp-admin/includes/plugin.php');
            }

            define('TKM_BASE_FILE', __FILE__);
            define('TKM_PATH', trailingslashit(plugin_dir_path(TKM_BASE_FILE)));
            define('TKM_URL', trailingslashit(plugin_dir_url(TKM_BASE_FILE)));
            define('TKM_ADMIN_ASSETS', trailingslashit(TKM_URL . 'assets/admin'));
            define('TKM_FRONT_ASSETS', trailingslashit(TKM_URL . 'assets/front'));
            define('TKM_INC_PATH', trailingslashit(TKM_PATH . 'inc'));
            define('TKM_VIEWS_PATH', trailingslashit(TKM_PATH . 'views'));
            $tkm_plugin_data =  get_plugin_data(TKM_BASE_FILE, '<');
            define('TKM_VER',  $tkm_plugin_data['Version']);
        }

        public function init()
        {
            add_filter('plugin_action_links_' . plugin_basename(__FILE__), function ($links) {
                $links[] = '<a href="' . admin_url('admin.php?page=tkm-settings') . '">تنظیمات</a>';
                return $links;
            });
            add_action('tkm_auto_close', [$this, 'tkm_auto_close_events']);


            require_once TKM_PATH . 'vendor/autoload.php';
            require_once TKM_INC_PATH . 'admin/codestar/codestar-framework.php';
            require_once TKM_INC_PATH . 'admin/tkm-settings.php';
            require_once  TKM_INC_PATH . 'functions.php';
            require_once  TKM_INC_PATH . 'tkm-ui.php';
            require_once  TKM_INC_PATH . 'admin/abstract/base-menu.php';
            require_once  TKM_PATH . 'src/autoload.php';

            register_activation_hook(TKM_BASE_FILE, [$this, 'active']);
            register_deactivation_hook(TKM_BASE_FILE, [$this, 'deactive']);

            tkm_settings();
            \Ticketchi\Plugin::boot();

            if (is_admin()) {

                new TKM_MENU();
                new TKM_Admin_Ajax();
                new TKM_Analysis();
            } else {
                new TKM_Shortcode_Router();
                new TKM_Shortcode_Url();
            }
            // Front tickets arrive through admin-ajax.php (is_admin() === true), so the
            // SMS listener must be registered in every context.
            new TKM_SMS_Notification();
            new TKM_Front_AJAX();
            new TKM_ASSETS();
            new TKM_WC_Dashboard();
        }

        public function active()
        {
            TKM_DB::create_table();
            \Ticketchi\Plugin::activate();
            delete_option('tkm_rewrite_version');

            if (! wp_next_scheduled('tkm_auto_close')) {
                wp_schedule_event(time(), 'daily', 'tkm_auto_close');
            }
        }

        public function tkm_auto_close_events()
        {
            $active = tkm_settings('close_auto_ticket');

            if (! $active) {
                return;
            }

            global $wpdb;
            $ticket_table = $wpdb->prefix . 'tkm_tickets';

            $date = date("Y-m-d H:i:s", strtotime("-7 days"));

            $tickets = $wpdb->get_col(
                $wpdb->prepare(
                    "SELECT ID FROM {$ticket_table} WHERE status != 'closed' AND reply_date < %s",
                    $date
                )
            );

            if (!empty($tickets) && class_exists('TKM_Ticket_Manager')) {
                $ticket_manager = new TKM_Ticket_Manager();
                foreach ($tickets as $ticket_id) {
                    \Ticketchi\Domain\Tickets::update($ticket_id, ['status' => 'closed'], 0);
                }
            }
        }
        public function deactive()
        {
            \Ticketchi\Plugin::deactivate();
            wp_clear_scheduled_hook('tkm_auto_close');
        }

        public function admin_php_notice()
        { ?>
            <div class="notice notice-error">
                <p>افزونه تیکت چی برای اجرا صحیح نیاز به نسخه 7.4 به بالا دارد لطفا نسخه php هاست خود را ارتقا دهید
                </p>
            </div>
    <?php
        }
    }
}

// "Core" was the original (very generic) class name; keep it as an alias when it is free.
if (!class_exists('Core', false)) {
    class_alias('TKM_Core', 'Core');
}

TKM_Core::instance();
