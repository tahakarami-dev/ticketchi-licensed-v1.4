<?php

namespace Ticketchi\Sla;

use DateTimeImmutable;
use DateTimeZone;
use Ticketchi\Domain\Activity;
use Ticketchi\Domain\Tickets;
use Ticketchi\Support\Clock;
use Ticketchi\Support\Settings;

defined('ABSPATH') || exit('NO Access');

/**
 * Service-level targets per priority, counted in business hours.
 *
 * Due dates are stamped when a ticket is created (and re-stamped when its
 * priority changes); first_response_at / resolved_at are filled by
 * Domain\Tickets. A cron job flags breaches once and fires
 * «ticketchi/sla_breached».
 */
final class Sla
{
    const CRON = 'tkm_sla_check';

    /** Defaults in hours: [first response, resolution]. */
    const DEFAULTS = [
        'high'   => [2, 24],
        'medium' => [8, 48],
        'low'    => [24, 96],
    ];

    public static function register()
    {
        add_action('ticketchi/ticket_created', [__CLASS__, 'on_created'], 5);
        add_action('ticketchi/ticket_updated', [__CLASS__, 'on_updated'], 5, 2);
        add_action(self::CRON, [__CLASS__, 'check_breaches']);
        add_filter('cron_schedules', [__CLASS__, 'schedules']);

        if (!wp_next_scheduled(self::CRON)) {
            wp_schedule_event(time() + 300, 'tkm_quarter_hour', self::CRON);
        }
    }

    public static function schedules($schedules)
    {
        $schedules['tkm_quarter_hour'] = ['interval' => 15 * MINUTE_IN_SECONDS, 'display' => 'هر ۱۵ دقیقه (تیکت‌چی)'];
        return $schedules;
    }

    public static function enabled()
    {
        return Settings::bool('sla_enable', true);
    }

    /** @return int[] [first_response_seconds, resolution_seconds] */
    public static function targets($priority)
    {
        $defaults = isset(self::DEFAULTS[$priority]) ? self::DEFAULTS[$priority] : self::DEFAULTS['medium'];
        $first = Settings::float('sla_first_' . $priority, $defaults[0]);
        $resolve = Settings::float('sla_resolve_' . $priority, $defaults[1]);
        return [(int) round(max(0, $first) * HOUR_IN_SECONDS), (int) round(max(0, $resolve) * HOUR_IN_SECONDS)];
    }

    public static function on_created($ticket_id)
    {
        if (!self::enabled()) {
            return;
        }
        $ticket = Tickets::find($ticket_id);
        // Staff-initiated tickets are announcements: no response target.
        if (!$ticket || (int) $ticket->from_admin === 1) {
            return;
        }
        self::stamp($ticket);
    }

    public static function on_updated($ticket_id, $changes)
    {
        if (self::enabled() && isset($changes['priority'])) {
            $ticket = Tickets::find($ticket_id);
            if ($ticket && (int) $ticket->from_admin !== 1) {
                self::stamp($ticket);
            }
        }
    }

    private static function stamp($ticket)
    {
        global $wpdb;
        list($first, $resolve) = self::targets($ticket->priority);
        $start = Clock::ts($ticket->create_date) ?: time();

        $wpdb->update(Tickets::table(), [
            'first_response_due' => $first ? Clock::from_ts(self::add_business_seconds($start, $first)) : null,
            'resolution_due'     => $resolve ? Clock::from_ts(self::add_business_seconds($start, $resolve)) : null,
            'sla_notified'       => 0,
        ], ['ID' => (int) $ticket->ID]);
    }

    /* ───────────── Business hours ───────────── */

    /** @return array{days:int[],start:int,end:int}|null  null = 24/7 */
    public static function schedule()
    {
        if (!Settings::bool('sla_business_hours', false)) {
            return null;
        }

        $days = Settings::get('sla_work_days', ['6', '0', '1', '2', '3']);
        $days = array_map('intval', is_array($days) ? $days : explode(',', (string) $days));
        $start = self::minutes(Settings::get('sla_work_start', '09:00'));
        $end = self::minutes(Settings::get('sla_work_end', '17:00'));

        if (!$days || $end <= $start) {
            return null;
        }
        return ['days' => $days, 'start' => $start, 'end' => $end];
    }

    private static function minutes($hhmm)
    {
        if (!preg_match('/^(\d{1,2}):(\d{2})$/', trim((string) $hhmm), $m)) {
            return 0;
        }
        return min(24 * 60, (int) $m[1] * 60 + (int) $m[2]);
    }

    /** Adds $seconds of working time to a UTC timestamp, in the site's timezone. */
    public static function add_business_seconds($start_ts, $seconds)
    {
        $schedule = self::schedule();
        if (!$schedule || $seconds <= 0) {
            return $start_ts + $seconds;
        }

        $tz = wp_timezone();
        $cursor = (new DateTimeImmutable('@' . $start_ts))->setTimezone($tz);
        $remaining = (int) $seconds;

        for ($guard = 0; $guard < 400; $guard++) {
            $day_start = $cursor->setTime(0, 0)->modify('+' . $schedule['start'] . ' minutes');
            $day_end = $cursor->setTime(0, 0)->modify('+' . $schedule['end'] . ' minutes');
            $is_workday = in_array((int) $cursor->format('w'), $schedule['days'], true);

            if ($is_workday && $cursor < $day_end) {
                if ($cursor < $day_start) {
                    $cursor = $day_start;
                }
                $available = $day_end->getTimestamp() - $cursor->getTimestamp();
                if ($remaining <= $available) {
                    return $cursor->getTimestamp() + $remaining;
                }
                $remaining -= $available;
            }

            $cursor = $cursor->setTime(0, 0)->modify('+1 day');
        }

        return $start_ts + $seconds;
    }

    /* ───────────── State ───────────── */

    /**
     * @return array{state:string,kind:string|null,due:int|null,remaining:int|null}
     *   state: none | ok | warning | breached | met
     */
    public static function state($ticket, $now = null)
    {
        $now = $now ?: time();
        $none = ['state' => 'none', 'kind' => null, 'due' => null, 'remaining' => null];

        if ((int) $ticket->from_admin === 1 || $ticket->status === 'trash') {
            return $none;
        }

        if (empty($ticket->first_response_at) && !empty($ticket->first_response_due) && !Tickets::is_closed($ticket->status)) {
            return self::evaluate('first_response', Clock::ts($ticket->first_response_due), Clock::ts($ticket->create_date), $now);
        }

        if (!empty($ticket->resolution_due)) {
            $due = Clock::ts($ticket->resolution_due);
            if (Tickets::is_closed($ticket->status)) {
                $resolved = Clock::ts($ticket->resolved_at) ?: $now;
                return ['state' => $resolved <= $due ? 'met' : 'breached', 'kind' => 'resolution', 'due' => $due, 'remaining' => null];
            }
            return self::evaluate('resolution', $due, Clock::ts($ticket->create_date), $now);
        }

        return $none;
    }

    private static function evaluate($kind, $due, $start, $now)
    {
        $remaining = $due - $now;
        $window = max(1, $due - (int) $start);
        if ($remaining < 0) {
            $state = 'breached';
        } elseif ($remaining < min(HOUR_IN_SECONDS, $window * 0.25) || $remaining < $window * 0.2) {
            $state = 'warning';
        } else {
            $state = 'ok';
        }
        return ['state' => $state, 'kind' => $kind, 'due' => $due, 'remaining' => $remaining];
    }

    /** Cron: flag each overdue ticket once and announce it. */
    public static function check_breaches()
    {
        if (!self::enabled()) {
            return;
        }

        global $wpdb;
        $now = Clock::now();
        $rows = $wpdb->get_results($wpdb->prepare(
            'SELECT * FROM ' . Tickets::table() . " WHERE sla_notified = 0 AND status NOT IN ('closed','finish','trash')
             AND ((first_response_at IS NULL AND first_response_due IS NOT NULL AND first_response_due < %s)
               OR (resolution_due IS NOT NULL AND resolution_due < %s)) LIMIT 200",
            $now,
            $now
        ));

        foreach ($rows as $ticket) {
            $kind = (empty($ticket->first_response_at) && $ticket->first_response_due && $ticket->first_response_due < $now) ? 'first_response' : 'resolution';
            $wpdb->update(Tickets::table(), ['sla_notified' => 1], ['ID' => (int) $ticket->ID], ['%d'], ['%d']);
            Activity::log($ticket->ID, 'sla_breached', ['kind' => $kind]);
            do_action('ticketchi/sla_breached', (int) $ticket->ID, $kind);
        }
    }
}
