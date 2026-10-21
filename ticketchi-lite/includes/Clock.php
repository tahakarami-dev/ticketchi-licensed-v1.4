<?php

namespace TicketchiLite;

defined('ABSPATH') || exit;

/**
 * Every date column is stored in UTC ("Y-m-d H:i:s").
 */
final class Clock
{
    public static function now()
    {
        return gmdate('Y-m-d H:i:s');
    }

    public static function ts($datetime)
    {
        if (is_numeric($datetime)) {
            return (int) $datetime ?: null;
        }
        if ($datetime === null || $datetime === '' || $datetime === '0000-00-00 00:00:00') {
            return null;
        }
        $ts = strtotime($datetime . ' UTC');
        return $ts ?: null;
    }

    /** ISO 8601 for JSON payloads. */
    public static function iso($datetime)
    {
        $ts = self::ts($datetime);
        return $ts ? gmdate('c', $ts) : null;
    }
}
