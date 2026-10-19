<?php

namespace Ticketchi\Support;

defined('ABSPATH') || exit('NO Access');

/**
 * Every date column is stored in UTC ("Y-m-d H:i:s"). Never rely on the
 * database's CURRENT_TIMESTAMP: on many hosts MySQL runs in local time.
 */
final class Clock
{
    public static function now()
    {
        return gmdate('Y-m-d H:i:s');
    }

    public static function ts($datetime)
    {
        if ($datetime === null || $datetime === '' || $datetime === '0000-00-00 00:00:00') {
            return null;
        }
        $ts = strtotime($datetime . ' UTC');
        return $ts ?: null;
    }

    public static function from_ts($ts)
    {
        return gmdate('Y-m-d H:i:s', (int) $ts);
    }

    /** ISO 8601 for JSON payloads. */
    public static function iso($datetime)
    {
        $ts = self::ts($datetime);
        return $ts ? gmdate('c', $ts) : null;
    }
}
