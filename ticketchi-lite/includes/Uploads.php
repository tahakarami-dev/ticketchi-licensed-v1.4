<?php

namespace TicketchiLite;

defined('ABSPATH') || exit;

/**
 * One attachment per message, stored under uploads/tkm-uploads/YYYY/MM.
 * WordPress' own file-type check (allowed MIME types) always applies on top of
 * the extension list from the settings.
 */
final class Uploads
{
    public static function max_size_bytes()
    {
        $mb = (int) Settings::get('upload_max_size', '5');
        return max(1, $mb) * 1024 * 1024;
    }

    /** @return string[] */
    public static function allowed_extensions()
    {
        $exts = array_map(function ($ext) {
            return ltrim(trim($ext), '.');
        }, explode(',', strtolower((string) Settings::get('upload_allowed_ext'))));
        return array_values(array_filter($exts));
    }

    public static function accept_attr()
    {
        $exts = self::allowed_extensions();
        return $exts ? '.' . implode(',.', $exts) : '';
    }

    public static function hint()
    {
        $exts = self::allowed_extensions();
        $parts = ['یک فایل تا ' . ticketchi_lite_fa(round(self::max_size_bytes() / 1048576, 1)) . ' مگابایت'];
        if ($exts) {
            $shown = array_slice($exts, 0, 5);
            $rest = count($exts) - count($shown);
            $parts[] = strtoupper(implode('، ', $shown)) . ($rest > 0 ? ' و ' . ticketchi_lite_fa($rest) . ' قالب دیگر' : '');
        }
        return implode(' · ', $parts);
    }

    /** Stored «file» column value → list of URLs (JSON list or a single legacy URL). */
    public static function decode($value)
    {
        if (empty($value)) {
            return [];
        }
        $decoded = json_decode((string) $value, true);
        if (is_array($decoded)) {
            return array_values(array_filter($decoded, 'is_string'));
        }
        return [(string) $value];
    }

    public static function encode(array $urls)
    {
        $urls = array_values(array_filter($urls));
        return $urls ? wp_json_encode($urls) : null;
    }

    /** First real file out of a $_FILES entry (single or name[] array). */
    public static function first(array $files)
    {
        if (empty($files['name'])) {
            return null;
        }
        if (is_array($files['name'])) {
            foreach (array_keys($files['name']) as $i) {
                if (!empty($files['name'][$i]) && isset($files['error'][$i]) && (int) $files['error'][$i] !== UPLOAD_ERR_NO_FILE) {
                    return [
                        'name'     => $files['name'][$i],
                        'type'     => $files['type'][$i],
                        'tmp_name' => $files['tmp_name'][$i],
                        'error'    => $files['error'][$i],
                        'size'     => $files['size'][$i],
                    ];
                }
            }
            return null;
        }
        return isset($files['error']) && (int) $files['error'] === UPLOAD_ERR_NO_FILE ? null : $files;
    }

    /**
     * @param array|null $files one $_FILES entry
     * @return string|null|\WP_Error encoded «file» value, null when nothing was sent
     */
    public static function handle($files)
    {
        $file = is_array($files) ? self::first($files) : null;
        if (!$file) {
            return null;
        }

        $name = sanitize_file_name(wp_unslash($file['name']));
        $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
        $allowed = self::allowed_extensions();
        if ($allowed && !in_array($ext, $allowed, true)) {
            return new \WP_Error('ticketchi_lite_upload', 'پسوند فایل «' . $ext . '» مجاز نیست. پسوندهای مجاز: ' . implode('، ', $allowed));
        }
        if ((int) $file['size'] > self::max_size_bytes()) {
            return new \WP_Error('ticketchi_lite_upload', 'حجم فایل بیشتر از حد مجاز (' . ticketchi_lite_fa(round(self::max_size_bytes() / 1048576, 1)) . ' مگابایت) است.');
        }

        require_once ABSPATH . 'wp-admin/includes/file.php';

        add_filter('upload_dir', [__CLASS__, 'upload_dir']);
        $moved = wp_handle_upload($file, ['test_form' => false]);
        remove_filter('upload_dir', [__CLASS__, 'upload_dir']);

        if (!$moved || !empty($moved['error'])) {
            return new \WP_Error('ticketchi_lite_upload', !empty($moved['error']) ? $moved['error'] : 'آپلود فایل انجام نشد.');
        }

        return self::encode([esc_url_raw($moved['url'])]);
    }

    public static function upload_dir($dirs)
    {
        $sub = '/tkm-uploads/' . gmdate('Y') . '/' . gmdate('m');
        $dirs['subdir'] = $sub;
        $dirs['path'] = $dirs['basedir'] . $sub;
        $dirs['url'] = $dirs['baseurl'] . $sub;
        return $dirs;
    }
}
