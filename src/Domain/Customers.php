<?php

namespace Ticketchi\Domain;

use Ticketchi\Support\Clock;

defined('ABSPATH') || exit('NO Access');

/**
 * 360° customer profile shown next to every conversation.
 */
final class Customers
{
    public static function profile($user_id)
    {
        global $wpdb;
        $user_id = (int) $user_id;
        $user = $user_id ? get_userdata($user_id) : false;
        $tickets = Tickets::table();

        $stats = $wpdb->get_row($wpdb->prepare(
            "SELECT COUNT(*) AS total,
                SUM(CASE WHEN status NOT IN ('closed','finish','trash') THEN 1 ELSE 0 END) AS open_count,
                MIN(create_date) AS first_ticket
             FROM {$tickets}
             WHERE status != 'trash' AND ((creator_id = %d AND (from_admin IS NULL OR from_admin = 0)) OR (user_id = %d AND from_admin = 1))",
            $user_id,
            $user_id
        ));

        $rating = $wpdb->get_var($wpdb->prepare("SELECT AVG(rating) FROM {$wpdb->prefix}tkm_ratings WHERE user_id = %d", $user_id));

        $recent = $wpdb->get_results($wpdb->prepare(
            "SELECT ID, title, status, create_date FROM {$tickets}
             WHERE status != 'trash' AND ((creator_id = %d AND (from_admin IS NULL OR from_admin = 0)) OR (user_id = %d AND from_admin = 1))
             ORDER BY create_date DESC LIMIT 6",
            $user_id,
            $user_id
        ));

        $profile = [
            'id'           => $user_id,
            'name'         => $user ? $user->display_name : 'کاربر حذف‌شده',
            'email'        => $user ? $user->user_email : '',
            'phone'        => $user ? (string) (get_user_meta($user_id, 'billing_phone', true) ?: '') : '',
            'registered'   => $user ? Clock::iso($user->user_registered) : null,
            'avatar'       => self::avatar($user_id),
            'profile_url'  => $user ? get_edit_user_link($user_id) : '',
            'tickets'      => [
                'total' => (int) ($stats->total ?? 0),
                'open'  => (int) ($stats->open_count ?? 0),
                'first' => Clock::iso($stats->first_ticket ?? null),
            ],
            'rating'       => $rating !== null ? round((float) $rating, 1) : null,
            'recent'       => array_map(function ($t) {
                return [
                    'id'      => (int) $t->ID,
                    'title'   => $t->title,
                    'status'  => Presenter::status($t->status),
                    'created' => Clock::iso($t->create_date),
                ];
            }, $recent),
            'woocommerce'  => null,
        ];

        if ($user && function_exists('wc_get_orders')) {
            $orders = wc_get_orders(['customer_id' => $user_id, 'limit' => 5, 'orderby' => 'date', 'order' => 'DESC', 'status' => array_keys(wc_get_order_statuses())]);
            $count = function_exists('wc_get_customer_order_count') ? (int) wc_get_customer_order_count($user_id) : count($orders);
            $spent = function_exists('wc_get_customer_total_spent') ? (float) wc_get_customer_total_spent($user_id) : 0.0;

            $profile['woocommerce'] = [
                'orders'      => $count,
                'total_spent' => wp_strip_all_tags(wc_price($spent)),
                'recent'      => array_map(function ($order) {
                    return [
                        'id'     => $order->get_id(),
                        'number' => $order->get_order_number(),
                        'total'  => wp_strip_all_tags($order->get_formatted_order_total()),
                        'status' => wc_get_order_status_name($order->get_status()),
                        'date'   => $order->get_date_created() ? $order->get_date_created()->date('c') : null,
                        'url'    => $order->get_edit_order_url(),
                        'items'  => implode('، ', array_map(function ($item) {
                            return $item->get_name();
                        }, array_slice($order->get_items(), 0, 3))),
                    ];
                }, $orders),
            ];
        }

        return $profile;
    }

    public static function avatar($user_id)
    {
        $user = $user_id ? get_userdata($user_id) : false;
        $name = $user ? $user->display_name : '؟';
        return [
            'initial' => function_exists('mb_substr') ? mb_substr(trim($name), 0, 1) : substr(trim($name), 0, 1),
            'hue'     => $user_id ? crc32((string) $user_id) % 360 : 220,
        ];
    }
}
