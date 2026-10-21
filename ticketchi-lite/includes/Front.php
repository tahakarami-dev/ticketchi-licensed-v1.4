<?php

namespace TicketchiLite;

defined('ABSPATH') || exit;

/**
 * Customer panel: the [ticketchi] shortcode, the WooCommerce «My account ›
 * Tickets» endpoint, and the AJAX endpoints behind its forms.
 */
final class Front
{
    const NONCE = 'ticketchi_lite_front';

    /** Where the panel is being rendered right now: shortcode|account|null */
    private static $context = null;

    public static function register()
    {
        add_shortcode('ticketchi', [__CLASS__, 'shortcode']);
        // Same shortcode as Ticketchi Pro, so pages keep working after an upgrade.
        if (!shortcode_exists('tickets')) {
            add_shortcode('tickets', [__CLASS__, 'shortcode']);
        }

        add_action('wp_enqueue_scripts', [__CLASS__, 'register_assets'], 5);
        add_action('wp_enqueue_scripts', [__CLASS__, 'maybe_enqueue'], 20);

        add_action('wp_ajax_ticketchi_lite_submit_ticket', [__CLASS__, 'ajax_submit_ticket']);
        add_action('wp_ajax_ticketchi_lite_submit_reply', [__CLASS__, 'ajax_submit_reply']);

        // WooCommerce «My account» endpoint.
        add_action('init', [__CLASS__, 'add_endpoint']);
        add_filter('woocommerce_get_query_vars', [__CLASS__, 'query_vars']);
        add_filter('woocommerce_account_menu_items', [__CLASS__, 'account_menu']);
        add_filter('woocommerce_endpoint_tickets_title', [__CLASS__, 'endpoint_title']);
        add_action('woocommerce_account_tickets_endpoint', [__CLASS__, 'account_page']);
        add_action('save_post_page', [__CLASS__, 'forget_shortcode_page']);
    }

    /* ───────────── Rendering ───────────── */

    public static function shortcode()
    {
        $previous = self::$context;
        self::$context = 'shortcode';
        self::enqueue();
        ob_start();
        self::render();
        self::$context = $previous;
        return ob_get_clean();
    }

    public static function account_page()
    {
        self::$context = 'account';
        self::enqueue();
        self::render();
    }

    private static function render()
    {
        // phpcs:disable WordPress.Security.NonceVerification.Recommended -- read-only routing.
        $action = isset($_GET['action']) ? sanitize_key(wp_unslash($_GET['action'])) : '';
        $has_ticket = !empty($_GET['ticket-id']);
        // phpcs:enable

        if (!is_user_logged_in()) {
            $view = 'login-required';
        } elseif ($action === 'new') {
            $view = 'new-ticket';
        } elseif ($action === 'single' && $has_ticket) {
            $view = 'single-ticket';
        } else {
            $view = 'tickets';
        }
        include TICKETCHI_LITE_PATH . 'views/front/' . $view . '.php';
    }

    /** Base URL of the customer panel in the current (or best guess) context. */
    public static function base_url()
    {
        if (self::$context === 'shortcode') {
            return get_permalink();
        }
        if (self::$context === 'account' || (function_exists('wc_get_endpoint_url') && get_option('woocommerce_myaccount_page_id'))) {
            return wc_get_endpoint_url('tickets', '', get_permalink(get_option('woocommerce_myaccount_page_id')));
        }
        $page = self::shortcode_page();
        return $page ? get_permalink($page) : home_url('/');
    }

    /** First published page that contains the shortcode (cached for a day). */
    public static function shortcode_page()
    {
        $cached = get_transient('ticketchi_lite_page');
        if ($cached !== false) {
            return (int) $cached;
        }
        global $wpdb;
        $id = (int) $wpdb->get_var("SELECT ID FROM {$wpdb->posts} WHERE post_status = 'publish' AND post_type = 'page' AND (post_content LIKE '%[ticketchi%' OR post_content LIKE '%[tickets%') ORDER BY ID LIMIT 1");
        set_transient('ticketchi_lite_page', $id, DAY_IN_SECONDS);
        return $id;
    }

    public static function forget_shortcode_page()
    {
        delete_transient('ticketchi_lite_page');
    }

    /* ───────────── Assets ───────────── */

    public static function register_assets()
    {
        if (wp_style_is('ticketchi-lite-front', 'registered')) {
            return;
        }
        wp_register_style('ticketchi-lite-ui', TICKETCHI_LITE_URL . 'assets/css/ui.css', [], TICKETCHI_LITE_VERSION);
        wp_register_style('ticketchi-lite-front', TICKETCHI_LITE_URL . 'assets/css/front.css', ['ticketchi-lite-ui'], TICKETCHI_LITE_VERSION);
        wp_add_inline_style('ticketchi-lite-front', self::brand_css());

        wp_register_script('ticketchi-lite-front', TICKETCHI_LITE_URL . 'assets/js/front.js', ['jquery'], TICKETCHI_LITE_VERSION, true);
        wp_localize_script('ticketchi-lite-front', 'TICKETCHI_LITE_FRONT', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce'    => wp_create_nonce(self::NONCE),
            'upload'   => [
                'max_size'    => Uploads::max_size_bytes(),
                'allowed_ext' => Uploads::allowed_extensions(),
            ],
        ]);
    }

    public static function maybe_enqueue()
    {
        $post = get_post();
        $has_shortcode = $post && (has_shortcode((string) $post->post_content, 'ticketchi') || has_shortcode((string) $post->post_content, 'tickets'));
        if ($has_shortcode || (function_exists('is_wc_endpoint_url') && is_wc_endpoint_url('tickets'))) {
            self::enqueue();
        }
    }

    /** Also called from the views, for page builders and block themes. */
    public static function enqueue()
    {
        self::register_assets();
        wp_enqueue_style('ticketchi-lite-front');
        wp_enqueue_script('ticketchi-lite-front');
    }

    /** Brand colours from the settings as CSS custom properties. */
    public static function brand_css()
    {
        $vars = [];
        $primary = sanitize_hex_color(Settings::get('primary_color'));
        $hover = sanitize_hex_color(Settings::get('secondary_color'));
        $on_primary = sanitize_hex_color(Settings::get('text_color'));

        if ($primary) {
            $vars[] = '--tkm-primary:' . $primary;
        }
        if ($hover && strlen($hover) === 7 && strtolower($hover) !== strtolower((string) $primary)) {
            $vars[] = '--tkm-primary-hover:' . $hover;
        }
        if ($on_primary) {
            $vars[] = '--tkm-on-primary:' . $on_primary;
        }
        return $vars ? '.tkm-ui{' . implode(';', $vars) . '}' : '';
    }

    /* ───────────── WooCommerce ───────────── */

    public static function add_endpoint()
    {
        add_rewrite_endpoint('tickets', EP_PAGES);
        if (get_option('ticketchi_lite_flush_rewrite')) {
            delete_option('ticketchi_lite_flush_rewrite');
            flush_rewrite_rules(false);
        }
    }

    public static function query_vars($vars)
    {
        $vars['tickets'] = 'tickets';
        return $vars;
    }

    public static function endpoint_title()
    {
        return 'تیکت‌های پشتیبانی';
    }

    public static function account_menu($items)
    {
        $logout = isset($items['customer-logout']) ? $items['customer-logout'] : null;
        unset($items['customer-logout']);
        $items['tickets'] = 'تیکت‌های پشتیبانی';
        if ($logout) {
            $items['customer-logout'] = $logout;
        }
        return $items;
    }

    /* ───────────── AJAX ───────────── */

    private static function post($key)
    {
        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- verified in guard().
        return isset($_POST[$key]) ? wp_unslash($_POST[$key]) : '';
    }

    /** The «file» upload; validated by Uploads::handle() and wp_handle_upload(). */
    private static function uploaded_file()
    {
        // phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- nonce verified in guard(); the file is validated on upload.
        return isset($_FILES['file']) && is_array($_FILES['file']) ? $_FILES['file'] : null;
    }

    private static function guard()
    {
        if (!check_ajax_referer(self::NONCE, 'nonce', false)) {
            wp_send_json(['success' => false, 'result' => 'درخواست نامعتبر است. صفحه را دوباره بارگذاری کنید.']);
        }
        if (!is_user_logged_in()) {
            wp_send_json(['success' => false, 'result' => 'برای ادامه ابتدا وارد حساب کاربری خود شوید.']);
        }
    }

    public static function ajax_submit_ticket()
    {
        self::guard();

        $file = Uploads::handle(self::uploaded_file());
        if (is_wp_error($file)) {
            wp_send_json(['success' => false, 'result' => $file->get_error_message()]);
        }

        $id = Tickets::create([
            'title'         => sanitize_text_field(self::post('title_ticket')),
            'body'          => wp_kses_post(self::post('content')),
            'priority'      => sanitize_key(self::post('priority')),
            'department_id' => absint(self::post('department')),
            'file'          => $file,
        ], get_current_user_id());

        if (is_wp_error($id)) {
            wp_send_json(['success' => false, 'result' => $id->get_error_message()]);
        }
        wp_send_json(['success' => true, 'ticket_id' => (int) $id]);
    }

    public static function ajax_submit_reply()
    {
        self::guard();

        $user_id = get_current_user_id();
        $ticket = Tickets::find(absint(self::post('ticket_id')));

        if (!$ticket || !ticketchi_lite_user_can_access_ticket($ticket, $user_id) || $ticket->status === 'trash') {
            wp_send_json(['success' => false, 'result' => 'تیکت مورد نظر یافت نشد.']);
        }
        if ($ticket->status === 'finish') {
            wp_send_json(['success' => false, 'result' => 'این تیکت پایان یافته و امکان پاسخ به آن وجود ندارد.']);
        }

        $file = Uploads::handle(self::uploaded_file());
        if (is_wp_error($file)) {
            wp_send_json(['success' => false, 'result' => $file->get_error_message()]);
        }

        // Customers can keep the ticket open or close it themselves.
        $status = self::post('status') === 'closed' ? 'closed' : 'open';
        $result = Tickets::reply($ticket->ID, [
            'body'   => wp_kses_post(self::post('body')),
            'file'   => $file,
            'status' => $status,
        ], $user_id);

        if (is_wp_error($result)) {
            wp_send_json(['success' => false, 'result' => $result->get_error_message()]);
        }

        $ticket = Tickets::find($ticket->ID);
        $replies = Tickets::replies($ticket->ID);
        ob_start();
        include TICKETCHI_LITE_PATH . 'views/front/replies.php';

        wp_send_json([
            'success'      => true,
            'replies_html' => ob_get_clean(),
            'status_badge' => ticketchi_lite_status_badge($ticket->status),
        ]);
    }
}
