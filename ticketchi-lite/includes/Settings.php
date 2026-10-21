<?php

namespace TicketchiLite;

defined('ABSPATH') || exit;

/**
 * Every Ticketchi Lite setting, grouped for the settings screen, stored in the
 * «ticketchi_lite_settings» option. Keys match Ticketchi Pro, which imports
 * them when a site upgrades.
 *
 * Field: id, type, label, desc, default, options, unit, placeholder, ltr,
 *        show_if => [field, value]
 * Types: switch, text, textarea, number, color, select, note
 */
final class Settings
{
    const OPTION = 'ticketchi_lite_settings';

    public static function sections()
    {
        return [
            [
                'id' => 'general', 'title' => 'عمومی', 'icon' => 'sliders',
                'desc' => 'رفتار کلی پنل تیکت مشتری.',
                'groups' => [
                    ['title' => 'پیش از ثبت تیکت', 'fields' => [
                        ['id' => 'new-ticket-alert', 'type' => 'switch', 'label' => 'نمایش پیغام بالای فرم ثبت تیکت', 'desc' => 'مثلاً برای اعلام ساعات پاسخ‌گویی.', 'default' => '0'],
                        ['id' => 'alert-message-ticket', 'type' => 'textarea', 'label' => 'متن پیغام', 'default' => 'کاربر گرامی، تیکت‌ها در ساعات کاری بررسی و پاسخ داده می‌شوند.', 'show_if' => ['new-ticket-alert', '1']],
                    ]],
                ],
            ],
            [
                'id' => 'brand', 'title' => 'ظاهر و برند', 'icon' => 'sparkles', 'preview' => 'brand',
                'desc' => 'رنگ‌ها روی پنل مشتری، صندوق پشتیبانی و ایمیل‌ها اعمال می‌شوند.',
                'groups' => [
                    ['title' => 'رنگ‌ها', 'fields' => [
                        ['id' => 'primary_color', 'type' => 'color', 'label' => 'رنگ اصلی', 'desc' => 'دکمه‌ها، لینک‌ها و حالت فعال.', 'default' => '#2A48CD'],
                        ['id' => 'secondary_color', 'type' => 'color', 'label' => 'رنگ هاور', 'desc' => 'خالی بگذارید تا خودکار ساخته شود.', 'default' => ''],
                        ['id' => 'text_color', 'type' => 'color', 'label' => 'رنگ متن روی رنگ اصلی', 'default' => '#ffffff'],
                    ]],
                    ['title' => 'عنوان پنل مشتری', 'fields' => [
                        ['id' => 'title_dashboard', 'type' => 'text', 'label' => 'عنوان', 'default' => 'داشبورد تیکت‌ها'],
                        ['id' => 'description_dashboard', 'type' => 'text', 'label' => 'توضیح کوتاه', 'default' => 'مدیریت تیکت‌های پشتیبانی شما'],
                    ]],
                ],
            ],
            [
                'id' => 'upload', 'title' => 'پیوست فایل', 'icon' => 'paperclip',
                'desc' => 'هر پیام می‌تواند یک فایل پیوست داشته باشد.',
                'groups' => [
                    ['title' => 'محدودیت‌ها', 'fields' => [
                        ['id' => 'upload_max_size', 'type' => 'number', 'label' => 'حداکثر حجم فایل', 'unit' => 'مگابایت', 'min' => 1, 'max' => 64, 'default' => '5'],
                        ['id' => 'upload_allowed_ext', 'type' => 'text', 'label' => 'پسوندهای مجاز', 'desc' => 'با ویرگول جدا کنید؛ مثلا jpg,png,pdf,zip', 'ltr' => true, 'default' => 'jpg,jpeg,png,gif,webp,pdf,zip,txt'],
                    ]],
                ],
            ],
            [
                'id' => 'statuses', 'title' => 'وضعیت‌ها', 'icon' => 'flag',
                'desc' => 'رنگ هر وضعیت در پنل مشتری و صندوق پشتیبانی.',
                'groups' => [
                    ['title' => 'رنگ وضعیت‌ها', 'columns' => 2, 'fields' => [
                        ['id' => 'open-color', 'type' => 'color', 'label' => 'باز', 'default' => '#4bcc58'],
                        ['id' => 'close-color', 'type' => 'color', 'label' => 'پاسخ داده شده', 'default' => '#3150d8'],
                        ['id' => 'answerd-color', 'type' => 'color', 'label' => 'بسته شده', 'default' => '#ff2121'],
                        ['id' => 'finish-color', 'type' => 'color', 'label' => 'پایان یافته', 'default' => '#000000'],
                    ]],
                ],
            ],
            [
                'id' => 'notify', 'title' => 'ایمیل‌ها', 'icon' => 'bell',
                'desc' => 'ایمیل‌ها راست‌چین و با رنگ برند شما ارسال می‌شوند.',
                'groups' => [
                    ['title' => 'ایمیل به مشتری', 'fields' => [
                        ['id' => 'notify_customer_created', 'type' => 'switch', 'label' => 'تایید ثبت تیکت', 'default' => '1'],
                        ['id' => 'notify_customer_reply', 'type' => 'switch', 'label' => 'پاسخ جدید پشتیبانی', 'default' => '1'],
                    ]],
                    ['title' => 'ایمیل به پشتیبانی', 'fields' => [
                        ['id' => 'notify_agent_email', 'type' => 'switch', 'label' => 'تیکت جدید و پاسخ مشتری', 'default' => '1'],
                        ['id' => 'notify_staff_address', 'type' => 'text', 'label' => 'ایمیل دریافت‌کننده', 'desc' => 'خالی بگذارید تا به ایمیل مدیر سایت ارسال شود.', 'ltr' => true, 'placeholder' => get_option('admin_email'), 'default' => '', 'show_if' => ['notify_agent_email', '1']],
                    ]],
                    ['title' => 'فرستنده', 'columns' => 2, 'fields' => [
                        ['id' => 'email_from_name', 'type' => 'text', 'label' => 'نام فرستنده', 'placeholder' => get_bloginfo('name'), 'default' => ''],
                        ['id' => 'email_from_address', 'type' => 'text', 'label' => 'ایمیل فرستنده', 'ltr' => true, 'placeholder' => get_option('admin_email'), 'default' => ''],
                    ]],
                ],
            ],
            [
                'id' => 'integrations', 'title' => 'شورت‌کد', 'icon' => 'hash',
                'desc' => 'افزودن پنل تیکت به هر صفحه‌ای از سایت.',
                'groups' => [
                    ['fields' => [
                        ['type' => 'note', 'html' => '<p><code class="tkm-copy" data-copy="[ticketchi]">[ticketchi]</code> پنل کامل تیکت مشتری (فهرست، ثبت تیکت و گفتگو). اگر ووکامرس فعال باشد، پنل در «حساب کاربری › تیکت‌های پشتیبانی» هم در دسترس است.</p>'],
                    ]],
                ],
            ],
        ];
    }

    /** Flat id => field map. */
    public static function fields()
    {
        static $fields = null;
        if ($fields === null) {
            $fields = [];
            foreach (self::sections() as $section) {
                foreach ($section['groups'] as $group) {
                    foreach ($group['fields'] as $field) {
                        if (!empty($field['id'])) {
                            $fields[$field['id']] = $field;
                        }
                    }
                }
            }
        }
        return $fields;
    }

    public static function defaults()
    {
        $out = [];
        foreach (self::fields() as $id => $field) {
            if (array_key_exists('default', $field)) {
                $out[$id] = $field['default'];
            }
        }
        return $out;
    }

    private static function stored()
    {
        $saved = get_option(self::OPTION, []);
        return is_array($saved) ? $saved : [];
    }

    public static function get($key, $default = null)
    {
        $saved = self::stored();
        $value = array_key_exists($key, $saved) ? $saved[$key] : null;
        if ($value === null || $value === '') {
            $defaults = self::defaults();
            $value = $default !== null ? $default : (isset($defaults[$key]) ? $defaults[$key] : null);
        }
        return $value;
    }

    public static function bool($key)
    {
        return in_array(self::get($key), [true, 1, '1', 'true', 'yes', 'on'], true);
    }

    /** Current values, with defaults for anything never saved. */
    public static function values()
    {
        $saved = self::stored();
        $values = [];
        foreach (self::fields() as $id => $field) {
            $value = array_key_exists($id, $saved) ? $saved[$id] : (isset($field['default']) ? $field['default'] : '');
            if ($field['type'] === 'switch') {
                $value = !empty($value) && $value !== '0' ? '1' : '0';
            } elseif (is_array($value)) {
                $value = '';
            }
            $values[$id] = (string) $value;
        }
        return $values;
    }

    public static function ensure_defaults()
    {
        $saved = self::stored();
        $merged = $saved + self::defaults();
        if ($merged !== $saved) {
            update_option(self::OPTION, $merged);
        }
    }

    /**
     * Validates and stores submitted values. Unknown keys are ignored.
     *
     * @return array|\WP_Error saved values
     */
    public static function save(array $input)
    {
        $fields = self::fields();
        $saved = self::stored();
        $errors = [];

        foreach ($input as $id => $value) {
            if (!isset($fields[$id]) || $fields[$id]['type'] === 'note' || is_array($value)) {
                continue;
            }
            $clean = self::sanitize($fields[$id], $value, $errors);
            if ($clean !== null) {
                $saved[$id] = $clean;
            }
        }

        if ($errors) {
            return new \WP_Error('ticketchi_lite_settings', implode(' ', $errors), ['fields' => array_keys($errors)]);
        }

        update_option(self::OPTION, $saved);
        return self::values();
    }

    private static function sanitize(array $field, $value, array &$errors)
    {
        $id = $field['id'];
        switch ($field['type']) {
            case 'switch':
                return !empty($value) && $value !== '0' && $value !== 'false' ? '1' : '0';
            case 'number':
                $raw = ticketchi_lite_en_digits((string) $value);
                if (!is_numeric($raw)) {
                    $errors[$id] = '«' . $field['label'] . '» باید عدد باشد.';
                    return null;
                }
                return (string) max(isset($field['min']) ? $field['min'] : 0, min(isset($field['max']) ? $field['max'] : PHP_INT_MAX, (int) $raw));
            case 'color':
                $value = trim((string) $value);
                if ($value === '') {
                    return '';
                }
                if (!preg_match('/^#([0-9a-f]{3}|[0-9a-f]{6}|[0-9a-f]{8})$/i', $value)) {
                    $errors[$id] = 'رنگ «' . $field['label'] . '» معتبر نیست.';
                    return null;
                }
                return $value;
            case 'select':
                return isset($field['options'][(string) $value]) ? (string) $value : (isset($field['default']) ? $field['default'] : '');
            case 'textarea':
                return sanitize_textarea_field((string) $value);
            case 'text':
            default:
                $value = sanitize_text_field((string) $value);
                if (in_array($id, ['email_from_address', 'notify_staff_address'], true) && $value !== '' && !is_email($value)) {
                    $errors[$id] = '«' . $field['label'] . '» یک ایمیل معتبر نیست.';
                    return null;
                }
                if ($id === 'upload_allowed_ext') {
                    return implode(',', array_filter(array_map(function ($ext) {
                        return preg_replace('/[^a-z0-9]/', '', strtolower(trim($ext)));
                    }, explode(',', str_replace('،', ',', $value)))));
                }
                return $value;
        }
    }
}
