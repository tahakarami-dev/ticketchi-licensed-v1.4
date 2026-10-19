<?php

defined('ABSPATH') || exit('NO Access');

class TKM_Admin_Ajax
{
    public function __construct()
    {
        add_action('wp_ajax_tkm_search_user', [$this,  'search_users']);
    }

    public function search_users()
    {
        // Only staff may look users up (this used to be open to any logged-in user).
        if (!tkm_is_staff() || !check_ajax_referer('tkm_admin_nonce', 'nonce', false)) {
            wp_send_json([], 403);
        }

        $term = isset($_POST['term']) ? sanitize_text_field(wp_unslash($_POST['term'])) : '';
        if ($term === '') {
            wp_send_json([]);
        }

        $user_query = new WP_User_Query([
            'search'         => '*' . $term . '*',
            'search_columns' => ['user_login', 'user_email', 'user_nicename', 'display_name'],
            'number'         => 20,
            'fields'         => ['ID', 'user_login', 'display_name', 'user_email'],
        ]);

        $result = [];
        foreach ($user_query->get_results() as $user) {
            // [id, label] shape kept for backwards compatibility with select2 callers.
            $result[] = [(int) $user->ID, $user->display_name . ' (' . $user->user_login . ')', $user->user_email];
        }

        $this->make_response($result);
    }

    public function make_response($result)
    {
        if (is_array($result)) {
            wp_send_json($result);
        }

        echo $result;
        wp_die();
    }
}
