<?php

namespace Ticketchi\Support;

defined('ABSPATH') || exit('NO Access');

/**
 * tkm_view   — work in the support inbox (agents).
 * tkm_manage — settings, departments, automations, reports (managers).
 *
 * Site admins / shop managers always get both; anyone assigned to a
 * department gets tkm_view. Granted dynamically so it also works for users
 * created before the role existed.
 */
final class Capabilities
{
    const VIEW = 'tkm_view';
    const MANAGE = 'tkm_manage';
    const ROLE = 'tkm_agent';

    public static function register()
    {
        add_filter('user_has_cap', [__CLASS__, 'grant'], 10, 4);

        // WooCommerce sends users without edit_posts back to «My account»; agents need wp-admin.
        add_filter('woocommerce_prevent_admin_access', function ($prevent) {
            return $prevent && !self::is_agent_only();
        });
        add_filter('woocommerce_disable_admin_bar', function ($disable) {
            return $disable && !self::is_agent_only();
        });
        add_filter('login_redirect', [__CLASS__, 'login_redirect'], 20, 3);
        add_filter('woocommerce_login_redirect', function ($redirect, $user) {
            return self::is_agent_only($user) ? admin_url('admin.php?page=tkm-tickets') : $redirect;
        }, 20, 2);
    }

    /** Support staff who would otherwise have no reason to be in wp-admin. */
    public static function is_agent_only($user = null)
    {
        $user = $user ?: wp_get_current_user();
        if (!$user || empty($user->ID)) {
            return false;
        }
        return user_can($user, self::VIEW) && !user_can($user, 'edit_posts');
    }

    public static function login_redirect($redirect, $requested, $user)
    {
        if ($user instanceof \WP_User && self::is_agent_only($user) && (!$requested || strpos($requested, 'wp-admin') === false || $requested === admin_url())) {
            return admin_url('admin.php?page=tkm-tickets');
        }
        return $redirect;
    }

    public static function install()
    {
        if (!get_role(self::ROLE)) {
            add_role(self::ROLE, 'کارشناس پشتیبانی', [
                'read'         => true,
                'upload_files' => true,
                self::VIEW     => true,
            ]);
        }
    }

    public static function grant($allcaps, $caps, $args, $user)
    {
        $wants = array_intersect($caps, [self::VIEW, self::MANAGE]);
        if (!$wants || empty($user->ID)) {
            return $allcaps;
        }

        if (!empty($allcaps['manage_options']) || !empty($allcaps['manage_woocommerce'])) {
            $allcaps[self::VIEW] = true;
            $allcaps[self::MANAGE] = true;
            return $allcaps;
        }

        if (in_array(self::VIEW, $wants, true) && empty($allcaps[self::VIEW]) && self::is_department_member($user->ID)) {
            $allcaps[self::VIEW] = true;
        }

        return $allcaps;
    }

    public static function is_department_member($user_id)
    {
        static $cache = [];
        $user_id = (int) $user_id;
        if (!isset($cache[$user_id])) {
            global $wpdb;
            $cache[$user_id] = (bool) $wpdb->get_var($wpdb->prepare("SELECT ID FROM {$wpdb->prefix}tkm_users WHERE user_id = %d LIMIT 1", $user_id));
        }
        return $cache[$user_id];
    }

    public static function can_view($user_id = null)
    {
        return $user_id ? user_can($user_id, self::VIEW) : current_user_can(self::VIEW);
    }

    public static function can_manage($user_id = null)
    {
        return $user_id ? user_can($user_id, self::MANAGE) : current_user_can(self::MANAGE);
    }

    /**
     * Departments whose tickets an agent may see. null = everything (managers,
     * or when the "agents see all tickets" setting is on).
     *
     * @return int[]|null
     */
    public static function scope($user_id)
    {
        $user_id = (int) $user_id;
        if (self::can_manage($user_id) || Settings::get('agent_scope', 'departments') === 'all') {
            return null;
        }
        static $cache = [];
        if (!isset($cache[$user_id])) {
            global $wpdb;
            $own = array_map('intval', $wpdb->get_col($wpdb->prepare("SELECT DISTINCT department_id FROM {$wpdb->prefix}tkm_users WHERE user_id = %d", $user_id)));
            $children = $own ? array_map('intval', $wpdb->get_col('SELECT ID FROM ' . $wpdb->prefix . 'tkm_departments WHERE parent IN (' . implode(',', $own) . ')')) : [];
            $cache[$user_id] = array_values(array_unique(array_merge($own, $children)));
        }
        return $cache[$user_id];
    }

    /** May this user open the ticket? (scope check on top of tkm_view) */
    public static function can_access_ticket($ticket, $user_id = null)
    {
        $user_id = $user_id ?: get_current_user_id();
        if (!$ticket || !self::can_view($user_id)) {
            return false;
        }
        $scope = self::scope($user_id);
        return $scope === null || (int) $ticket->assignee_id === (int) $user_id || in_array((int) $ticket->department_id, $scope, true);
    }
}
