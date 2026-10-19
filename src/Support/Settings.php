<?php

namespace Ticketchi\Support;

defined('ABSPATH') || exit('NO Access');

/**
 * Typed access to the Codestar options array (option «tkm_settings»).
 */
final class Settings
{
    public static function get($key, $default = null)
    {
        $value = function_exists('tkm_settings') ? tkm_settings($key) : null;
        return ($value === null || $value === '') ? $default : $value;
    }

    public static function bool($key, $default = false)
    {
        $value = self::get($key, null);
        if ($value === null) {
            return (bool) $default;
        }
        return in_array($value, [true, 1, '1', 'true', 'yes', 'on'], true);
    }

    public static function int($key, $default = 0)
    {
        $value = self::get($key, null);
        return is_numeric($value) ? (int) $value : (int) $default;
    }

    public static function float($key, $default = 0.0)
    {
        $value = self::get($key, null);
        return is_numeric($value) ? (float) $value : (float) $default;
    }
}
