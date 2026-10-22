<?php
/**
 * Plugin Name:       Ticketchi Lite
 * Plugin URI:        https://wordpress.org/plugins/ticketchi-lite/
 * Description:       سیستم تیکت پشتیبانی فارسی و راست‌چین برای وردپرس و ووکامرس؛ ثبت و پیگیری تیکت توسط مشتری، صندوق پشتیبانی مدرن برای مدیر، دپارتمان‌ها، پیوست فایل و اعلان ایمیلی.
 * Version:           1.0.0
 * Requires at least: 6.2
 * Requires PHP:      7.4
 * Author:            Taha Karami
 * Author URI:        https://www.rtl-theme.com/author/taha-karami
 * License:           GPLv2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       ticketchi-lite
 */

defined('ABSPATH') || exit;

// A second copy of Lite (e.g. uploaded into another folder) stays inert.
if (defined('TICKETCHI_LITE_FILE')) {
    return;
}

define('TICKETCHI_LITE_FILE', __FILE__);
define('TICKETCHI_LITE_VERSION', '1.0.0');
define('TICKETCHI_LITE_PATH', plugin_dir_path(__FILE__));
define('TICKETCHI_LITE_URL', plugin_dir_url(__FILE__));
define('TICKETCHI_LITE_PRO_URL', 'https://www.rtl-theme.com/ticketchi-wordpress-plugin/');

require_once TICKETCHI_LITE_PATH . 'includes/autoload.php';
require_once TICKETCHI_LITE_PATH . 'includes/functions.php';

register_activation_hook(__FILE__, ['TicketchiLite\\Install', 'activate']);
register_deactivation_hook(__FILE__, ['TicketchiLite\\Install', 'deactivate']);

add_action('plugins_loaded', ['TicketchiLite\\Plugin', 'boot']);
