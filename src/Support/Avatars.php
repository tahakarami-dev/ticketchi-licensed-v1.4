<?php

namespace Ticketchi\Support;

defined('ABSPATH') || exit('NO Access');

/**
 * Avatars: an uploaded photo, or one of the bundled illustrations for
 * support staff. Customers without a photo get a flat initial.
 *
 * user meta: tkm_avatar_id (attachment ID) · tkm_avatar_preset (agent-01 …)
 */
final class Avatars
{
    const META_UPLOAD = 'tkm_avatar_id';
    const META_PRESET = 'tkm_avatar_preset';
    const MAX_BYTES = 2097152; // 2 MB

    /** @var array<int, string|null> per-request URL cache */
    private static $cache = [];

    /** @return string[] preset ids */
    public static function presets()
    {
        static $list = null;
        if ($list === null) {
            $list = [];
            foreach ((array) glob(TKM_PATH . 'assets/shared/avatars/agent-*.svg') as $file) {
                $list[] = basename($file, '.svg');
            }
            sort($list);
        }
        return $list;
    }

    public static function preset_url($id)
    {
        return TKM_URL . 'assets/shared/avatars/' . $id . '.svg';
    }

    /** Image URL for the user, or null when the initial should be shown. */
    public static function url($user_id)
    {
        $user_id = (int) $user_id;
        if (!$user_id) {
            return null;
        }
        if (array_key_exists($user_id, self::$cache)) {
            return self::$cache[$user_id];
        }

        $url = null;
        $upload = (int) get_user_meta($user_id, self::META_UPLOAD, true);
        if ($upload) {
            $url = wp_get_attachment_image_url($upload, 'thumbnail') ?: null;
        }
        if (!$url) {
            $preset = (string) get_user_meta($user_id, self::META_PRESET, true);
            if ($preset && in_array($preset, self::presets(), true)) {
                $url = self::preset_url($preset);
            } elseif (Capabilities::can_view($user_id) && self::presets()) {
                // Staff never fall back to a bare letter: pick a stable illustration.
                $all = self::presets();
                $url = self::preset_url($all[crc32((string) $user_id) % count($all)]);
            }
        }
        return self::$cache[$user_id] = $url;
    }

    public static function initial($user_id)
    {
        $user = $user_id ? get_userdata($user_id) : false;
        $name = trim($user ? $user->display_name : '');
        if ($name === '') {
            return '؟';
        }
        return function_exists('mb_substr') ? mb_substr($name, 0, 1) : substr($name, 0, 1);
    }

    public static function hue($user_id)
    {
        return $user_id ? crc32((string) $user_id) % 360 : 220;
    }

    /** Shape used in REST responses: {initial, hue, url}. */
    public static function data($user_id)
    {
        return [
            'initial' => self::initial($user_id),
            'hue'     => self::hue($user_id),
            'url'     => self::url($user_id),
        ];
    }

    public static function html($user_id, $size = '', $extra_class = '')
    {
        $user = $user_id ? get_userdata($user_id) : false;
        $name = $user ? $user->display_name : 'کاربر';
        $class = 'tkm-avatar' . ($size ? ' tkm-avatar--' . $size : '') . ($extra_class ? ' ' . $extra_class : '');
        $url = self::url($user_id);

        if ($url) {
            return '<img class="' . esc_attr($class . ' tkm-avatar--img') . '" src="' . esc_url($url) . '" alt="" title="' . esc_attr($name) . '" loading="lazy" decoding="async">';
        }
        return '<span class="' . esc_attr($class) . '" style="--h:' . (int) self::hue($user_id) . '" title="' . esc_attr($name) . '" aria-hidden="true">' . esc_html(self::initial($user_id)) . '</span>';
    }

    public static function set_preset($user_id, $preset)
    {
        if (!in_array($preset, self::presets(), true)) {
            return new \WP_Error('tkm_avatar', 'آواتار انتخاب‌شده معتبر نیست.');
        }
        self::remove_upload($user_id);
        update_user_meta($user_id, self::META_PRESET, $preset);
        unset(self::$cache[(int) $user_id]);
        return true;
    }

    /** @param array $file one entry of $_FILES */
    public static function upload($user_id, array $file)
    {
        if (empty($file['tmp_name']) || !empty($file['error'])) {
            return new \WP_Error('tkm_avatar', 'فایلی دریافت نشد.');
        }
        if ((int) $file['size'] > self::MAX_BYTES) {
            return new \WP_Error('tkm_avatar', 'حجم تصویر باید کمتر از ۲ مگابایت باشد.');
        }
        $type = wp_check_filetype_and_ext($file['tmp_name'], $file['name'], [
            'jpg|jpeg' => 'image/jpeg',
            'png'      => 'image/png',
            'webp'     => 'image/webp',
            'gif'      => 'image/gif',
        ]);
        if (empty($type['type']) || strpos($type['type'], 'image/') !== 0 || !@getimagesize($file['tmp_name'])) {
            return new \WP_Error('tkm_avatar', 'فقط تصویر JPG، PNG، WEBP یا GIF قابل قبول است.');
        }

        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/image.php';
        require_once ABSPATH . 'wp-admin/includes/media.php';

        $moved = wp_handle_upload($file, ['test_form' => false, 'mimes' => [$type['ext'] => $type['type']]]);
        if (!empty($moved['error'])) {
            return new \WP_Error('tkm_avatar', $moved['error']);
        }

        $attachment = wp_insert_attachment([
            'post_mime_type' => $moved['type'],
            'post_title'     => 'آواتار ' . get_the_author_meta('display_name', $user_id),
            'post_status'    => 'inherit',
            'post_author'    => $user_id,
        ], $moved['file']);
        if (is_wp_error($attachment) || !$attachment) {
            return new \WP_Error('tkm_avatar', 'ذخیره تصویر انجام نشد.');
        }
        wp_update_attachment_metadata($attachment, wp_generate_attachment_metadata($attachment, $moved['file']));

        self::remove_upload($user_id);
        update_user_meta($user_id, self::META_UPLOAD, (int) $attachment);
        delete_user_meta($user_id, self::META_PRESET);
        unset(self::$cache[(int) $user_id]);
        return (int) $attachment;
    }

    /** Back to the default (stable illustration for staff, initial for customers). */
    public static function reset($user_id)
    {
        self::remove_upload($user_id);
        delete_user_meta($user_id, self::META_PRESET);
        unset(self::$cache[(int) $user_id]);
    }

    private static function remove_upload($user_id)
    {
        unset(self::$cache[(int) $user_id]);
        $old = (int) get_user_meta($user_id, self::META_UPLOAD, true);
        if ($old) {
            wp_delete_attachment($old, true);
            delete_user_meta($user_id, self::META_UPLOAD);
        }
    }
}
