<?php

defined('ABSPATH') || exit('NO Access');

class TKM_Ticket_Manager
{
    private $wpdb;
    private $table;

    public function __construct()
    {
        global $wpdb;
        $this->wpdb = $wpdb;
        $this->table = $wpdb->prefix . 'tkm_tickets';
    }

    /**
     * Legacy entry point, kept for third-party callers. Delegates to the v2 domain service.
     *
     * @return array ['ticket_id' => int] on success, list of error messages otherwise
     */
    public function insert($data)
    {
        $id = \Ticketchi\Domain\Tickets::create($data, !empty($data['creator_id']) ? (int) $data['creator_id'] : 0, 'customer');
        return is_wp_error($id) ? [$id->get_error_message()] : ['ticket_id' => $id];
    }

    public function get_tickets($user_id, $type = NULL, $status = NULL, $orderby = NULL, $page_num = NULL, $priority = NULL)
    {
        if (!intval($user_id)) {
            return [];
        }

        $args = [];
        $conditions = [];

        // Type filter
        switch ($type) {
            case 'send': 
                $conditions[] = "creator_id = %d";
                $args[] = $user_id;
                break;

            case 'get': 
                $conditions[] = "user_id = %d AND from_admin = 1";
                $args[] = $user_id;
                break;

            default:
                $conditions[] = "(creator_id = %d OR user_id = %d)";
                $args[] = $user_id;
                $args[] = $user_id;
                break;
        }

        // Priority filter
        if ($priority !== NULL && $priority !== 'all-priority') {
            $conditions[] = "priority = %s";
            $args[] = $priority;
        }

        // Status filter
        if ($status !== NULL && $status !== 'all') {
            $conditions[] = "status = %s";
            $args[] = $status;
        }

        // Order by filter
        switch ($orderby) {
            case 'create-date':
                $orderby_sql = "ORDER BY create_date DESC";
                break;

            case 'reply-date':
            default:
                $orderby_sql = "ORDER BY reply_date DESC";
                break;
        }

        // Pagination filter
        $page_sql = "";
        if ($page_num) {
            $per_page = 10;
            $page_sql = "LIMIT %d";
            $args[] = $per_page;

            if ($page_num != 1) {
                $offset = ($page_num - 1) * $per_page;
                $page_sql .= " OFFSET %d";
                $args[] = $offset;
            }
        }

        // Combine conditions
        $where_sql = implode(' AND ', $conditions);

        $query = "SELECT * FROM " . $this->table;
        if (!empty($where_sql)) {
            $query .= " WHERE " . $where_sql;
        }
        $query .= " " . $orderby_sql . " " . $page_sql;

        return $this->wpdb->get_results($this->wpdb->prepare($query, $args));
    }

    public function ticket_count($user_id, $type = NULL, $status = NULL, $priority = NULL)
    {
        if (!intval($user_id)) {
            return 0;
        }

        $args = [];
        $conditions = [];

        switch ($type) {
            case 'send':
                $conditions[] = "creator_id = %d";
                $args[] = $user_id;
                break;
            case 'get':
                $conditions[] = "user_id = %d AND from_admin = 1";
                $args[] = $user_id;
                break;
            default:
                $conditions[] = "(user_id = %d OR creator_id = %d)";
                $args[] = $user_id;
                $args[] = $user_id;
                break;
        }

        if ($status && $status !== 'all') {
            $conditions[] = "status = %s";
            $args[] = $status;
        }

        if ($priority && $priority !== 'all-priority') {
            $conditions[] = "priority = %s";
            $args[] = $priority;
        }

        $sql = "SELECT COUNT(*) FROM " . $this->table . " WHERE " . implode(" AND ", $conditions);

        return $this->wpdb->get_var($this->wpdb->prepare($sql, $args));
    }



    public function get_info_ticket($ticket_id)
    {
        if (!intval($ticket_id)) {
            return NULL;
        }

        return $this->wpdb->get_row($this->wpdb->prepare("SELECT * FROM " . $this->table . " WHERE ID = %d", $ticket_id));
    }

    public function update_status($ticket_id, $status)
    {
        $result = \Ticketchi\Domain\Tickets::update($ticket_id, ['status' => $status], get_current_user_id());
        return !is_wp_error($result);
    }

    public function update_reply_date($ticket_id)
    {
        $date = gmdate("Y-m-d H:i:s");
        return $this->wpdb->update($this->table, ['reply_date' => $date, 'updated_at' => $date], ['ID' => (int) $ticket_id]);
    }


    public function get_count_tickets()
    {
        return $this->wpdb->get_var("SELECT COUNT(*) FROM " . $this->table);
    }

    public function open_tickets()
    {
        return $this->wpdb->get_var("SELECT COUNT(*) FROM " . $this->table . " WHERE status='open' ");
    }

    public function closed_tickets()
    {
        return $this->wpdb->get_var("SELECT COUNT(*) FROM " . $this->table . " WHERE status='closed' ");
    }
    public function answerd_tickets()
    {
        return $this->wpdb->get_var("SELECT COUNT(*) FROM " . $this->table . " WHERE status='answerd' ");
    }
    public function has_user_rated_ticket($ticket_id)
    {
        if (!is_user_logged_in()) {
            return false; 
        }
        global $wpdb;
        $table_name = $wpdb->prefix . 'tkm_ratings';
        $user_id = get_current_user_id();

        $rating_exists = $wpdb->get_var($wpdb->prepare(
            "SELECT ID FROM $table_name WHERE ticket_id = %d AND user_id = %d",
            $ticket_id,
            $user_id
        ));

        return !empty($rating_exists);
    }
    public function get_ticket_rating($ticket_id)
    {
        if (!is_user_logged_in()) {
            return 0; 
        }
        global $wpdb;
        $table_name = $wpdb->prefix . 'tkm_ratings'; 
        $user_id = get_current_user_id(); 

        $rating = $wpdb->get_var($wpdb->prepare(
            "SELECT rating FROM $table_name WHERE ticket_id = %d AND user_id = %d",
            $ticket_id,
            $user_id
        ));

        return $rating ? intval($rating) : 0; 
    }

    public function get_user_purchased_products($user_id)
    {
        if (!function_exists('wc_get_orders')) {
            return [];
        }

        $customer_orders = wc_get_orders(array(
            'customer_id' => $user_id,
            'status' => array('wc-completed', 'wc-processing'), 
            'limit' => -1 
        ));

        $products = array();

        foreach ($customer_orders as $order) {
            foreach ($order->get_items() as $item) {
                $product_id = $item->get_product_id();
                $product_name = $item->get_name();
                $products[$product_id] = $product_name;
            }
        }

        return $products;
    }
}
