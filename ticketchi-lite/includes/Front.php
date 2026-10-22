<?php

namespace TicketchiLite;

defined('ABSPATH') || exit;

/**
 * Customer panel: the [ticketchi] shortcode and the AJAX endpoints behind
 * its forms.
 */
final class Front
{
    const NONCE = 'ticketchi_lite_front';

    /** True while the shortcode is being rendered. */
    private static $rendering = false;

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

        add_action('save_post_page', [__CLASS__, 'forget_shortcode_page']);
    }

    /* ───────────── Rendering ───────────── */

    public static function shortcode()
    {
        $previous = self::$rendering;
        self::$rendering = true;
        self::enqueue();
        ob_start();

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

        self::$rendering = $previous;
        return ob_get_clean();
    }

    /** Base URL of the customer panel. */
    public static function base_url()
    {
        if (self::$rendering) {
            return get_permalink();
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
        $id = (int) $wpdb->get_var($wpdb->prepare('SELECT ID FROM %i WHERE post_status = %s AND post_type = %s AND (post_content LIKE %s OR post_content LIKE %s) ORDER BY ID LIMIT 1', $wpdb->posts, 'publish', 'page', '%[ticketchi%', '%[tickets%'));
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

        wp_register_script('ticketchi-lite-front', TICKETCHI_LITE_URL . 'assets/js/front.js', ['jquery'], TICKETCHI_LITE_VERSION, true);
        wp_localize_script('ticketchi-lite-front', 'TICKETCHI_LITE_FRONT', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce'    => wp_create_nonce(self::NONCE),
        ]);
    }

    public static function maybe_enqueue()
    {
        $post = get_post();
        if ($post && (has_shortcode((string) $post->post_content, 'ticketchi') || has_shortcode((string) $post->post_content, 'tickets'))) {
            self::enqueue();
        }
    }

    /** Also called while rendering, for page builders and block themes. */
    public static function enqueue()
    {
        self::register_assets();
        wp_enqueue_style('ticketchi-lite-front');
        wp_enqueue_script('ticketchi-lite-front');
    }

    /* ───────────── AJAX ───────────── */

    private static function post($key)
    {
        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- verified in guard().
        return isset($_POST[$key]) ? wp_unslash($_POST[$key]) : '';
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

        $id = Tickets::create([
            'title'         => sanitize_text_field(self::post('title_ticket')),
            'body'          => wp_kses_post(self::post('content')),
            'department_id' => absint(self::post('department')),
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

        // Customers can keep the ticket open or close it themselves.
        $result = Tickets::reply($ticket->ID, [
            'body'   => wp_kses_post(self::post('body')),
            'status' => self::post('status') === 'closed' ? 'closed' : 'open',
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
