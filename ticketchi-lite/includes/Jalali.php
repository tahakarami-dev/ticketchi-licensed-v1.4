<?php

namespace TicketchiLite;

defined('ABSPATH') || exit;

/**
 * Gregorian → Solar Hijri (Jalali) conversion, so no date library has to be
 * bundled.
 */
final class Jalali
{
    const MONTHS = ['فروردین', 'اردیبهشت', 'خرداد', 'تیر', 'مرداد', 'شهریور', 'مهر', 'آبان', 'آذر', 'دی', 'بهمن', 'اسفند'];

    /** @return int[] [year, month, day] */
    public static function from_gregorian($gy, $gm, $gd)
    {
        $g_d_m = [0, 31, 59, 90, 120, 151, 181, 212, 243, 273, 304, 334];
        $gy2 = $gm > 2 ? $gy + 1 : $gy;
        $days = 355666 + (365 * $gy) + intdiv($gy2 + 3, 4) - intdiv($gy2 + 99, 100) + intdiv($gy2 + 399, 400) + $gd + $g_d_m[$gm - 1];
        $jy = -1595 + (33 * intdiv($days, 12053));
        $days %= 12053;
        $jy += 4 * intdiv($days, 1461);
        $days %= 1461;
        if ($days > 365) {
            $jy += intdiv($days - 1, 365);
            $days = ($days - 1) % 365;
        }
        if ($days < 186) {
            $jm = 1 + intdiv($days, 31);
            $jd = 1 + ($days % 31);
        } else {
            $jm = 7 + intdiv($days - 186, 30);
            $jd = 1 + (($days - 186) % 30);
        }
        return [$jy, $jm, $jd];
    }

    /** «۱۴ مهر ۱۴۰۴ — ۱۰:۳۰» (digits are converted by the caller). */
    public static function format($ts, $with_time = true)
    {
        $local = new \DateTime('@' . (int) $ts);
        $local->setTimezone(wp_timezone());
        list($y, $m, $d) = self::from_gregorian((int) $local->format('Y'), (int) $local->format('n'), (int) $local->format('j'));

        $out = $d . ' ' . self::MONTHS[$m - 1] . ' ' . $y;
        return $with_time ? $out . ' — ' . $local->format('H:i') : $out;
    }
}
