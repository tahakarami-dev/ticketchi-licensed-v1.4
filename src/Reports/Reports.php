<?php

namespace Ticketchi\Reports;

use DateTimeImmutable;
use DateTimeZone;
use Ticketchi\Domain\Tickets;
use Ticketchi\Support\Clock;

defined('ABSPATH') || exit('NO Access');

/**
 * Support analytics for an arbitrary date range (site timezone, inclusive).
 */
final class Reports
{
    /** @return array{0:int,1:int} UTC timestamps [from, to) */
    public static function range($from, $to)
    {
        $tz = wp_timezone();
        $start = DateTimeImmutable::createFromFormat('!Y-m-d', (string) $from, $tz);
        $end = DateTimeImmutable::createFromFormat('!Y-m-d', (string) $to, $tz);
        if (!$start || !$end) {
            $end = new DateTimeImmutable('today', $tz);
            $start = $end->modify('-29 days');
        }
        if ($end < $start) {
            list($start, $end) = [$end, $start];
        }
        return [$start->getTimestamp(), $end->modify('+1 day')->getTimestamp()];
    }

    public static function build($from, $to)
    {
        list($a, $b) = self::range($from, $to);
        $prev = [$a - ($b - $a), $a];

        $current = self::metrics($a, $b);
        $previous = self::metrics($prev[0], $prev[1]);

        return [
            'range'       => ['from' => gmdate('c', $a), 'to' => gmdate('c', $b - 1), 'days' => (int) round(($b - $a) / DAY_IN_SECONDS)],
            'kpis'        => $current,
            'previous'    => $previous,
            'series'      => self::series($a, $b),
            'agents'      => self::agents($a, $b),
            'departments' => self::departments($a, $b),
            'priorities'  => self::priorities($a, $b),
            'heatmap'     => self::heatmap($a, $b),
            'csat'        => self::csat($a, $b),
        ];
    }

    private static function between($a, $b)
    {
        return [Clock::from_ts($a), Clock::from_ts($b)];
    }

    public static function metrics($a, $b)
    {
        global $wpdb;
        $t = Tickets::table();
        list($from, $to) = self::between($a, $b);

        $created = (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$t} WHERE status != 'trash' AND create_date >= %s AND create_date < %s AND (from_admin IS NULL OR from_admin = 0)", $from, $to));
        $resolved = (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$t} WHERE resolved_at >= %s AND resolved_at < %s", $from, $to));

        $rows = $wpdb->get_results($wpdb->prepare("SELECT create_date, first_response_at, first_response_due, resolved_at FROM {$t} WHERE status != 'trash' AND create_date >= %s AND create_date < %s AND (from_admin IS NULL OR from_admin = 0)", $from, $to));

        $first = [];
        $resolution = [];
        $sla_total = 0;
        $sla_met = 0;
        foreach ($rows as $r) {
            $c = Clock::ts($r->create_date);
            if ($r->first_response_at) {
                $first[] = Clock::ts($r->first_response_at) - $c;
            }
            if ($r->resolved_at) {
                $resolution[] = Clock::ts($r->resolved_at) - $c;
            }
            if ($r->first_response_due) {
                $due = Clock::ts($r->first_response_due);
                $answered = $r->first_response_at ? Clock::ts($r->first_response_at) : null;
                if ($answered || $due < time()) {
                    $sla_total++;
                    if ($answered && $answered <= $due) {
                        $sla_met++;
                    }
                }
            }
        }

        $rating = $wpdb->get_row($wpdb->prepare("SELECT AVG(rating) AS avg_rating, COUNT(*) AS n FROM {$wpdb->prefix}tkm_ratings WHERE create_date >= %s AND create_date < %s", $from, $to));
        $replies = (int) $wpdb->get_var($wpdb->prepare('SELECT COUNT(*) FROM ' . Tickets::replies_table() . ' WHERE from_admin = 1 AND (is_internal = 0 OR is_internal IS NULL) AND create_date >= %s AND create_date < %s', $from, $to));

        return [
            'created'         => $created,
            'resolved'        => $resolved,
            'replies'         => $replies,
            'first_response'  => self::avg($first),
            'first_response_median' => self::median($first),
            'resolution'      => self::avg($resolution),
            'sla_compliance'  => $sla_total ? (int) round($sla_met / $sla_total * 100) : null,
            'csat'            => $rating && $rating->avg_rating !== null ? round((float) $rating->avg_rating, 2) : null,
            'csat_count'      => $rating ? (int) $rating->n : 0,
            'backlog'         => (int) $wpdb->get_var("SELECT COUNT(*) FROM {$t} WHERE status NOT IN ('closed','finish','trash')"),
        ];
    }

    private static function avg(array $values)
    {
        $values = array_filter($values, function ($v) {
            return $v >= 0;
        });
        return $values ? (int) round(array_sum($values) / count($values)) : null;
    }

    private static function median(array $values)
    {
        $values = array_values(array_filter($values, function ($v) {
            return $v >= 0;
        }));
        if (!$values) {
            return null;
        }
        sort($values);
        $n = count($values);
        return (int) ($n % 2 ? $values[intdiv($n, 2)] : ($values[$n / 2 - 1] + $values[$n / 2]) / 2);
    }

    /** Daily created vs resolved. */
    public static function series($a, $b)
    {
        global $wpdb;
        $t = Tickets::table();
        list($from, $to) = self::between($a, $b);
        $tz = wp_timezone();

        $days = [];
        for ($ts = $a; $ts < $b; $ts += DAY_IN_SECONDS) {
            $days[(new DateTimeImmutable('@' . $ts))->setTimezone($tz)->format('Y-m-d')] = ['created' => 0, 'resolved' => 0];
        }

        $bucket = function ($datetime) use ($tz) {
            return (new DateTimeImmutable($datetime, new DateTimeZone('UTC')))->setTimezone($tz)->format('Y-m-d');
        };

        foreach ($wpdb->get_col($wpdb->prepare("SELECT create_date FROM {$t} WHERE status != 'trash' AND create_date >= %s AND create_date < %s AND (from_admin IS NULL OR from_admin = 0)", $from, $to)) as $d) {
            $k = $bucket($d);
            if (isset($days[$k])) {
                $days[$k]['created']++;
            }
        }
        foreach ($wpdb->get_col($wpdb->prepare("SELECT resolved_at FROM {$t} WHERE resolved_at >= %s AND resolved_at < %s", $from, $to)) as $d) {
            $k = $bucket($d);
            if (isset($days[$k])) {
                $days[$k]['resolved']++;
            }
        }

        $out = [];
        foreach ($days as $day => $v) {
            $out[] = ['date' => $day, 'label' => self::jalali($day), 'created' => $v['created'], 'resolved' => $v['resolved']];
        }
        return $out;
    }

    public static function agents($a, $b)
    {
        global $wpdb;
        $t = Tickets::table();
        $r = Tickets::replies_table();
        list($from, $to) = self::between($a, $b);

        $assigned = $wpdb->get_results($wpdb->prepare("SELECT assignee_id AS uid, COUNT(*) AS assigned, SUM(CASE WHEN resolved_at IS NOT NULL THEN 1 ELSE 0 END) AS resolved FROM {$t} WHERE assignee_id IS NOT NULL AND status != 'trash' AND create_date >= %s AND create_date < %s GROUP BY assignee_id", $from, $to), OBJECT_K);
        $replies = $wpdb->get_results($wpdb->prepare("SELECT creator_id AS uid, COUNT(*) AS replies FROM {$r} WHERE from_admin = 1 AND (is_internal = 0 OR is_internal IS NULL) AND create_date >= %s AND create_date < %s GROUP BY creator_id", $from, $to), OBJECT_K);
        $csat = $wpdb->get_results($wpdb->prepare("SELECT t.assignee_id AS uid, AVG(x.rating) AS csat FROM {$wpdb->prefix}tkm_ratings x INNER JOIN {$t} t ON t.ID = x.ticket_id WHERE t.assignee_id IS NOT NULL AND x.create_date >= %s AND x.create_date < %s GROUP BY t.assignee_id", $from, $to), OBJECT_K);
        $open = \Ticketchi\Team\Assignment::workload();

        $first = [];
        foreach ($wpdb->get_results($wpdb->prepare("SELECT assignee_id, create_date, first_response_at FROM {$t} WHERE assignee_id IS NOT NULL AND first_response_at IS NOT NULL AND create_date >= %s AND create_date < %s", $from, $to)) as $row) {
            $first[(int) $row->assignee_id][] = Clock::ts($row->first_response_at) - Clock::ts($row->create_date);
        }

        $ids = array_unique(array_merge(array_keys($assigned), array_keys($replies), array_keys($open)));
        $out = [];
        foreach ($ids as $uid) {
            $uid = (int) $uid;
            if (!$uid || !get_userdata($uid)) {
                continue;
            }
            $out[] = [
                'user'           => \Ticketchi\Domain\Presenter::user($uid),
                'assigned'       => isset($assigned[$uid]) ? (int) $assigned[$uid]->assigned : 0,
                'resolved'       => isset($assigned[$uid]) ? (int) $assigned[$uid]->resolved : 0,
                'replies'        => isset($replies[$uid]) ? (int) $replies[$uid]->replies : 0,
                'open'           => isset($open[$uid]) ? $open[$uid] : 0,
                'first_response' => isset($first[$uid]) ? self::avg($first[$uid]) : null,
                'csat'           => isset($csat[$uid]) && $csat[$uid]->csat !== null ? round((float) $csat[$uid]->csat, 1) : null,
            ];
        }
        usort($out, function ($x, $y) {
            return $y['replies'] + $y['assigned'] - ($x['replies'] + $x['assigned']);
        });
        return $out;
    }

    public static function departments($a, $b)
    {
        global $wpdb;
        $t = Tickets::table();
        list($from, $to) = self::between($a, $b);
        $rows = $wpdb->get_results($wpdb->prepare("SELECT d.ID, d.name, COUNT(t.ID) AS total, SUM(CASE WHEN t.status NOT IN ('closed','finish') THEN 1 ELSE 0 END) AS open_count, SUM(CASE WHEN t.resolved_at IS NOT NULL THEN 1 ELSE 0 END) AS resolved FROM {$wpdb->prefix}tkm_departments d INNER JOIN {$t} t ON t.department_id = d.ID AND t.status != 'trash' AND t.create_date >= %s AND t.create_date < %s GROUP BY d.ID, d.name ORDER BY total DESC", $from, $to));
        return array_map(function ($r) {
            return ['id' => (int) $r->ID, 'name' => $r->name, 'total' => (int) $r->total, 'open' => (int) $r->open_count, 'resolved' => (int) $r->resolved];
        }, $rows);
    }

    public static function priorities($a, $b)
    {
        global $wpdb;
        list($from, $to) = self::between($a, $b);
        $rows = $wpdb->get_results($wpdb->prepare('SELECT priority, COUNT(*) AS c FROM ' . Tickets::table() . " WHERE status != 'trash' AND create_date >= %s AND create_date < %s GROUP BY priority", $from, $to), OBJECT_K);
        $out = [];
        foreach (['high', 'medium', 'low'] as $p) {
            $out[] = ['priority' => $p, 'count' => isset($rows[$p]) ? (int) $rows[$p]->c : 0];
        }
        return $out;
    }

    /** 7 × 24 matrix of ticket creation (site timezone), Saturday first. */
    public static function heatmap($a, $b)
    {
        global $wpdb;
        list($from, $to) = self::between($a, $b);
        $tz = wp_timezone();
        $grid = array_fill(0, 7, array_fill(0, 24, 0));
        $order = [6 => 0, 0 => 1, 1 => 2, 2 => 3, 3 => 4, 4 => 5, 5 => 6];

        foreach ($wpdb->get_col($wpdb->prepare('SELECT create_date FROM ' . Tickets::table() . " WHERE status != 'trash' AND create_date >= %s AND create_date < %s AND (from_admin IS NULL OR from_admin = 0)", $from, $to)) as $d) {
            $local = (new DateTimeImmutable($d, new DateTimeZone('UTC')))->setTimezone($tz);
            $grid[$order[(int) $local->format('w')]][(int) $local->format('G')]++;
        }
        return $grid;
    }

    public static function csat($a, $b)
    {
        global $wpdb;
        list($from, $to) = self::between($a, $b);
        $rows = $wpdb->get_results($wpdb->prepare("SELECT rating, COUNT(*) AS c FROM {$wpdb->prefix}tkm_ratings WHERE create_date >= %s AND create_date < %s GROUP BY rating", $from, $to), OBJECT_K);
        $out = [];
        for ($i = 5; $i >= 1; $i--) {
            $out[] = ['rating' => $i, 'count' => isset($rows[$i]) ? (int) $rows[$i]->c : 0];
        }
        return $out;
    }

    public static function jalali($ymd, $format = 'j F')
    {
        $ts = strtotime($ymd . ' 12:00:00');
        return class_exists('\Morilog\Jalali\Jalalian') ? tkm_fa(\Morilog\Jalali\Jalalian::forge($ts)->format($format)) : $ymd;
    }

    /** Streams a CSV of tickets created in the range. */
    public static function csv($from, $to)
    {
        global $wpdb;
        list($a, $b) = self::range($from, $to);
        list($f, $t) = self::between($a, $b);

        $rows = $wpdb->get_results($wpdb->prepare('SELECT * FROM ' . Tickets::table() . " WHERE status != 'trash' AND create_date >= %s AND create_date < %s ORDER BY ID", $f, $t));

        nocache_headers();
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="ticketchi-' . gmdate('Ymd', $a) . '-' . gmdate('Ymd', $b - 1) . '.csv"');

        $out = fopen('php://output', 'w');
        fwrite($out, "\xEF\xBB\xBF"); // UTF-8 BOM for Excel
        fputcsv($out, ['شناسه', 'عنوان', 'مشتری', 'ایمیل', 'دپارتمان', 'وضعیت', 'اولویت', 'مسئول', 'ایجاد', 'اولین پاسخ', 'حل', 'زمان اولین پاسخ (دقیقه)', 'امتیاز']);

        foreach ($rows as $row) {
            $customer = get_userdata(Tickets::customer_id($row));
            $dep = (new \TKM_Front_Department_Manager())->get_department($row->department_id);
            $rating = $wpdb->get_var($wpdb->prepare("SELECT rating FROM {$wpdb->prefix}tkm_ratings WHERE ticket_id = %d LIMIT 1", (int) $row->ID));
            $first = $row->first_response_at ? (int) round((Clock::ts($row->first_response_at) - Clock::ts($row->create_date)) / 60) : '';
            fputcsv($out, [
                $row->ID,
                $row->title,
                $customer ? $customer->display_name : '',
                $customer ? $customer->user_email : '',
                $dep ? $dep->name : '',
                trim((string) get_status_name($row->status)) ?: $row->status,
                get_priority_name($row->priority),
                $row->assignee_id ? tkm_user_name($row->assignee_id) : '',
                tkm_date($row->create_date),
                $row->first_response_at ? tkm_date($row->first_response_at) : '',
                $row->resolved_at ? tkm_date($row->resolved_at) : '',
                $first,
                $rating ?: '',
            ]);
        }
        fclose($out);
        exit;
    }
}
