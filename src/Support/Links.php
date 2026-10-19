<?php

namespace Ticketchi\Support;

defined('ABSPATH') || exit('NO Access');

/**
 * URLs that must work outside a render context (emails, REST, cron).
 */
final class Links
{
    public static function admin_ticket($ticket_id)
    {
        return add_query_arg(['page' => 'tkm-tickets'], admin_url('admin.php')) . '#/ticket/' . (int) $ticket_id;
    }

    /** Page that hosts the [tickets] shortcode, if any (cached). */
    public static function shortcode_page()
    {
        $cached = get_transient('tkm_shortcode_page');
        if ($cached !== false) {
            return $cached ? (int) $cached : 0;
        }
        global $wpdb;
        $id = (int) $wpdb->get_var("SELECT ID FROM {$wpdb->posts} WHERE post_status = 'publish' AND post_type = 'page' AND post_content LIKE '%[tickets%' ORDER BY ID LIMIT 1");
        set_transient('tkm_shortcode_page', $id, DAY_IN_SECONDS);
        return $id;
    }

    public static function customer_list()
    {
        if (function_exists('wc_get_endpoint_url') && get_option('woocommerce_myaccount_page_id')) {
            return \TKM_Ticket_Url::all();
        }
        $page = self::shortcode_page();
        return $page ? get_permalink($page) : home_url('/');
    }

    public static function customer_ticket($ticket_id)
    {
        return add_query_arg(['action' => 'single', 'ticket-id' => (int) $ticket_id], self::customer_list());
    }
}
