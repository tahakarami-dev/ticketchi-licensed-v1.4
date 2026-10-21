<?php

namespace TicketchiLite;

defined('ABSPATH') || exit;

/**
 * Local avatars, no Gravatar requests: support staff get one of the bundled
 * illustrations, customers a flat initial in a stable colour.
 */
final class Avatars
{
    /** @return string[] preset ids */
    public static function presets()
    {
        static $list = null;
        if ($list === null) {
            $list = [];
            foreach ((array) glob(TICKETCHI_LITE_PATH . 'assets/avatars/agent-*.svg') as $file) {
                $list[] = basename($file, '.svg');
            }
            sort($list);
        }
        return $list;
    }

    /** Image URL, or null when the initial should be shown. */
    public static function url($user_id)
    {
        $user_id = (int) $user_id;
        $presets = self::presets();
        if (!$user_id || !$presets || !ticketchi_lite_is_staff($user_id)) {
            return null;
        }
        return TICKETCHI_LITE_URL . 'assets/avatars/' . $presets[crc32((string) $user_id) % count($presets)] . '.svg';
    }

    public static function initial($user_id)
    {
        $name = trim(ticketchi_lite_user_name($user_id, ''));
        return $name === '' ? '؟' : mb_substr($name, 0, 1);
    }

    public static function hue($user_id)
    {
        return $user_id ? crc32((string) $user_id) % 360 : 220;
    }

    /** Shape used in REST responses: {initial, hue, url}. */
    public static function data($user_id)
    {
        return ['initial' => self::initial($user_id), 'hue' => self::hue($user_id), 'url' => self::url($user_id)];
    }

    public static function html($user_id, $size = '')
    {
        $name = ticketchi_lite_user_name($user_id, 'کاربر');
        $class = 'tkm-avatar' . ($size ? ' tkm-avatar--' . $size : '');
        $url = self::url($user_id);

        if ($url) {
            return '<img class="' . esc_attr($class . ' tkm-avatar--img') . '" src="' . esc_url($url) . '" alt="" title="' . esc_attr($name) . '" loading="lazy" decoding="async">';
        }
        return '<span class="' . esc_attr($class) . '" style="--h:' . (int) self::hue($user_id) . '" title="' . esc_attr($name) . '" aria-hidden="true">' . esc_html(self::initial($user_id)) . '</span>';
    }
}
