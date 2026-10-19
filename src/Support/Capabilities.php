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
}
