<?php

defined('ABSPATH') || exit('NO Access');

class TKM_WC_Dashboard
{

    public function __construct()
    {
        add_action('init', [$this, 'add_tickets_endpoint']);
        add_filter('woocommerce_account_menu_items', [$this, 'ticket_account_menu']);
        add_action('woocommerce_account_tickets_endpoint', [$this, 'tickets_endpoint_page']);
        add_filter('woocommerce_get_query_vars', [$this, 'register_query_var']);
        add_filter('woocommerce_endpoint_tickets_title', [$this, 'endpoint_title']);
    }

    /**
     * Lets WooCommerce know about the endpoint (active menu item, is_wc_endpoint_url()).
     */
    public function register_query_var($vars)
    {
        $vars['tickets'] = 'tickets';
        return $vars;
    }

    public function endpoint_title()
    {
        return 'تیکت های پشتیبانی';
    }

    public function ticket_account_menu($items)
    {
        $logout = null;

        if (isset($items['customer-logout'])) {
            $logout = $items['customer-logout'];
        }

        unset($items['customer-logout']);
        $items['tickets'] = 'تیکت های پشتیبانی';

        if ($logout) {
            $items['customer-logout'] = $logout;
        }

        return $items;
    }

    public function add_tickets_endpoint()
    {
        add_rewrite_endpoint('tickets', EP_PAGES);

        // Flushing is expensive: only do it once per plugin version.
        if (get_option('tkm_rewrite_version') !== TKM_VER) {
            flush_rewrite_rules(false);
            update_option('tkm_rewrite_version', TKM_VER);
        }
    }

    public function tickets_endpoint_page()
    {
        $GLOBALS['tkm_render_context'] = 'account';
        TKM_ASSETS::enqueue_front();
        include $this->get_view();
    }

    public function get_view()
    {
        if (isset($_GET['action']) && $_GET['action'] == 'new') {
            return TKM_VIEWS_PATH . 'front/new-ticket.php';
        }
        if (isset($_GET['action'], $_GET['ticket-id']) && $_GET['action'] === 'single') {
            return TKM_VIEWS_PATH . 'front/single-ticket.php';
        }

        return TKM_VIEWS_PATH . 'front/tickets.php';
    }
}
