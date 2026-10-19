<?php

namespace Ticketchi\Domain;

use Ticketchi\Support\Clock;

defined('ABSPATH') || exit('NO Access');

/**
 * Per-ticket audit trail: who changed what, and when.
 */
final class Activity
{
    public static function table()
    {
        global $wpdb;
        return $wpdb->prefix . 'tkm_activity';
    }

    public static function log($ticket_id, $type, array $data = [], $user_id = null)
    {
        global $wpdb;
        $wpdb->insert(self::table(), [
            'ticket_id'  => (int) $ticket_id,
            'user_id'    => $user_id ? (int) $user_id : null,
            'type'       => substr(sanitize_key($type), 0, 32),
            'data'       => $data ? wp_json_encode($data) : null,
            'created_at' => Clock::now(),
        ], ['%d', '%d', '%s', '%s', '%s']);

        return (int) $wpdb->insert_id;
    }

    public static function for_ticket($ticket_id, $limit = 100)
    {
        global $wpdb;
        $rows = $wpdb->get_results($wpdb->prepare(
            'SELECT * FROM ' . self::table() . ' WHERE ticket_id = %d ORDER BY ID DESC LIMIT %d',
            (int) $ticket_id,
            (int) $limit
        ));

        foreach ($rows as $row) {
            $row->data = $row->data ? json_decode($row->data, true) : [];
        }
        return $rows;
    }

    public static function delete_for_ticket($ticket_id)
    {
        global $wpdb;
        $wpdb->delete(self::table(), ['ticket_id' => (int) $ticket_id], ['%d']);
    }

    /** Human readable Persian sentence for the timeline. */
    public static function describe($row)
    {
        $d = is_array($row->data) ? $row->data : [];
        $who = $row->user_id ? tkm_user_name($row->user_id) : 'سیستم';

        switch ($row->type) {
            case 'created':
                return $who . ' تیکت را ایجاد کرد';
            case 'reply':
                return $who . ' پاسخ داد';
            case 'note':
                return $who . ' یادداشت داخلی گذاشت';
            case 'status':
                return $who . ' وضعیت را از «' . self::status_label($d['from'] ?? '') . '» به «' . self::status_label($d['to'] ?? '') . '» تغییر داد';
            case 'priority':
                return $who . ' اولویت را به «' . get_priority_name($d['to'] ?? '') . '» تغییر داد';
            case 'department':
                return $who . ' دپارتمان را به «' . esc_html($d['name'] ?? '—') . '» منتقل کرد';
            case 'assignee':
                return empty($d['to']) ? $who . ' تیکت را از مسئول خارج کرد' : $who . ' تیکت را به ' . tkm_user_name($d['to']) . ' سپرد';
            case 'tags':
                return $who . ' برچسب‌ها را به‌روز کرد';
            case 'title':
                return $who . ' عنوان را ویرایش کرد';
            case 'sla_breached':
                return 'مهلت ' . (($d['kind'] ?? '') === 'resolution' ? 'حل' : 'اولین پاسخ') . ' تیکت گذشت';
            case 'automation':
                return 'اتوماسیون «' . esc_html($d['name'] ?? '') . '» اجرا شد';
            case 'rating':
                return $who . ' به پشتیبانی ' . tkm_fa((int) ($d['rating'] ?? 0)) . ' ستاره داد';
            default:
                return $who . ' — ' . esc_html($row->type);
        }
    }

    private static function status_label($slug)
    {
        $name = get_status_name($slug);
        return $name ? trim($name) : (string) $slug;
    }
}
