<?php

namespace TicketchiLite;

defined('ABSPATH') || exit;

/**
 * Support inbox: views + department filter + search + paging, in SQL.
 */
final class Query
{
    const VIEWS = ['active', 'waiting', 'closed', 'all', 'trash'];

    /**
     * Search, filters and sorting are Ticketchi Pro features; Lite lists one
     * view at a time, most recent activity first.
     *
     * @param array $args view, page, per_page
     * @return array{items: object[], total: int}
     */
    public static function run(array $args)
    {
        global $wpdb;
        $where = self::where(isset($args['view']) ? $args['view'] : 'active');
        $per_page = max(1, min(100, (int) ($args['per_page'] ?? 30)));
        $page = max(1, (int) ($args['page'] ?? 1));
        $table = Tickets::table();

        // phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $where is a fixed clause from where().
        $total = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$table} t WHERE {$where}");
        $items = $wpdb->get_results($wpdb->prepare("SELECT t.* FROM {$table} t WHERE {$where} ORDER BY t.updated_at DESC, t.ID DESC LIMIT %d OFFSET %d", $per_page, ($page - 1) * $per_page));
        // phpcs:enable

        return ['items' => $items, 'total' => $total];
    }

    private static function where($view)
    {
        switch (in_array($view, self::VIEWS, true) ? $view : 'active') {
            case 'waiting':
                return "t.status = 'answerd'";
            case 'closed':
                return "t.status IN ('closed','finish')";
            case 'trash':
                return "t.status = 'trash'";
            case 'all':
                return "t.status != 'trash'";
            default:
                return "t.status NOT IN ('closed','finish','trash')";
        }
    }

    /** Badge counts for the sidebar views. */
    public static function view_counts()
    {
        global $wpdb;
        $table = Tickets::table();
        $rows = $wpdb->get_results("SELECT status, COUNT(*) AS c FROM {$table} GROUP BY status");
        $by = [];
        foreach ($rows as $row) {
            $by[$row->status] = (int) $row->c;
        }
        $sum = function (array $statuses, $exclude = false) use ($by) {
            $n = 0;
            foreach ($by as $status => $c) {
                if (in_array($status, $statuses, true) !== $exclude) {
                    $n += $c;
                }
            }
            return $n;
        };
        return [
            'active'  => $sum(['closed', 'finish', 'trash'], true),
            'waiting' => $sum(['answerd']),
            'closed'  => $sum(['closed', 'finish']),
            'all'     => $sum(['trash'], true),
            'trash'   => $sum(['trash']),
        ];
    }

    /** Reply counts + last public message per ticket. */
    public static function summaries(array $ticket_ids)
    {
        global $wpdb;
        $ids = array_values(array_filter(array_map('intval', $ticket_ids)));
        if (!$ids) {
            return [];
        }
        $in = implode(',', array_fill(0, count($ids), '%d'));
        $replies = Tickets::replies_table();
        $public = '(is_internal = 0 OR is_internal IS NULL)';

        $out = [];
        // phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- placeholders are generated above.
        foreach ($wpdb->get_results($wpdb->prepare("SELECT ticket_id, COUNT(*) AS c FROM {$replies} WHERE ticket_id IN ({$in}) AND {$public} GROUP BY ticket_id", $ids)) as $row) {
            $out[(int) $row->ticket_id]['count'] = (int) $row->c;
        }
        $last = $wpdb->get_results($wpdb->prepare("SELECT r.ticket_id, r.body, r.from_admin, r.creator_id FROM {$replies} r INNER JOIN (SELECT ticket_id, MAX(ID) AS mid FROM {$replies} WHERE ticket_id IN ({$in}) AND {$public} GROUP BY ticket_id) m ON m.mid = r.ID", $ids));
        // phpcs:enable
        foreach ($last as $row) {
            $out[(int) $row->ticket_id]['last'] = $row;
        }
        return $out;
    }
}
