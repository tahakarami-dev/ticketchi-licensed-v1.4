<?php

namespace TicketchiLite;

defined('ABSPATH') || exit;

/**
 * Support inbox: one view at a time, most recent activity first. Search,
 * filters and sorting are Ticketchi Pro features.
 */
final class Query
{
    const VIEWS = ['active', 'waiting', 'closed', 'all', 'trash'];

    /** view => [statuses, include (true) or exclude (false)] */
    const VIEW_STATUSES = [
        'active'  => [['closed', 'finish', 'trash'], false],
        'waiting' => [['answerd'], true],
        'closed'  => [['closed', 'finish'], true],
        'all'     => [['trash'], false],
        'trash'   => [['trash'], true],
    ];

    /**
     * @param array $args view, page, per_page
     * @return array{items: object[], total: int}
     */
    public static function run(array $args)
    {
        global $wpdb;
        $view = isset($args['view']) && isset(self::VIEW_STATUSES[$args['view']]) ? $args['view'] : 'active';
        list($statuses, $include) = self::VIEW_STATUSES[$view];
        // Always three status slots so every query below is a literal string.
        $s = array_pad($statuses, 3, $statuses[0]);
        $per_page = max(1, min(100, (int) ($args['per_page'] ?? 30)));
        $offset = (max(1, (int) ($args['page'] ?? 1)) - 1) * $per_page;
        $table = Tickets::table();

        if ($include) {
            $total = (int) $wpdb->get_var($wpdb->prepare('SELECT COUNT(*) FROM %i WHERE status IN (%s, %s, %s)', $table, $s[0], $s[1], $s[2]));
            $items = $wpdb->get_results($wpdb->prepare('SELECT * FROM %i WHERE status IN (%s, %s, %s) ORDER BY updated_at DESC, ID DESC LIMIT %d OFFSET %d', $table, $s[0], $s[1], $s[2], $per_page, $offset));
        } else {
            $total = (int) $wpdb->get_var($wpdb->prepare('SELECT COUNT(*) FROM %i WHERE status NOT IN (%s, %s, %s)', $table, $s[0], $s[1], $s[2]));
            $items = $wpdb->get_results($wpdb->prepare('SELECT * FROM %i WHERE status NOT IN (%s, %s, %s) ORDER BY updated_at DESC, ID DESC LIMIT %d OFFSET %d', $table, $s[0], $s[1], $s[2], $per_page, $offset));
        }

        return ['items' => $items, 'total' => $total];
    }

    /** Badge counts for the sidebar views. */
    public static function view_counts()
    {
        global $wpdb;
        $rows = $wpdb->get_results($wpdb->prepare('SELECT status, COUNT(*) AS c FROM %i GROUP BY status', Tickets::table()));
        $by = [];
        foreach ($rows as $row) {
            $by[$row->status] = (int) $row->c;
        }

        $counts = [];
        foreach (self::VIEW_STATUSES as $view => list($statuses, $include)) {
            $counts[$view] = 0;
            foreach ($by as $status => $c) {
                if (in_array($status, $statuses, true) === $include) {
                    $counts[$view] += $c;
                }
            }
        }
        return $counts;
    }

    /** Reply counts + last public message per ticket. */
    public static function summaries(array $ticket_ids)
    {
        global $wpdb;
        $ids = array_values(array_filter(array_map('intval', $ticket_ids)));
        if (!$ids) {
            return [];
        }
        $placeholders = implode(',', array_fill(0, count($ids), '%d'));
        $replies = Tickets::replies_table();

        $out = [];
        // phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare -- $placeholders is a list of %d placeholders, one per id.
        $counts = $wpdb->get_results($wpdb->prepare("SELECT ticket_id, COUNT(*) AS c FROM %i WHERE ticket_id IN ({$placeholders}) AND (is_internal = 0 OR is_internal IS NULL) GROUP BY ticket_id", array_merge([$replies], $ids)));
        $last = $wpdb->get_results($wpdb->prepare("SELECT r.ticket_id, r.body, r.from_admin, r.creator_id FROM %i r INNER JOIN (SELECT ticket_id, MAX(ID) AS mid FROM %i WHERE ticket_id IN ({$placeholders}) AND (is_internal = 0 OR is_internal IS NULL) GROUP BY ticket_id) m ON m.mid = r.ID", array_merge([$replies, $replies], $ids)));
        // phpcs:enable

        foreach ($counts as $row) {
            $out[(int) $row->ticket_id]['count'] = (int) $row->c;
        }
        foreach ($last as $row) {
            $out[(int) $row->ticket_id]['last'] = $row;
        }
        return $out;
    }
}
