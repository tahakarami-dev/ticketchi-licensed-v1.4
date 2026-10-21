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
     * @param array $args view, department, priority, search, sort (updated|created|oldest), page, per_page
     * @return array{items: object[], total: int}
     */
    public static function run(array $args)
    {
        global $wpdb;
        $where = self::where($args);

        $per_page = max(1, min(100, (int) ($args['per_page'] ?? 30)));
        $page = max(1, (int) ($args['page'] ?? 1));

        switch ($args['sort'] ?? 'updated') {
            case 'created':
                $order = 't.create_date DESC, t.ID DESC';
                break;
            case 'oldest':
                $order = 't.updated_at ASC, t.ID ASC';
                break;
            default:
                $order = 't.updated_at DESC, t.ID DESC';
        }

        $table = Tickets::table();
        // phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $where is assembled from prepare() fragments.
        $total = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$table} t WHERE {$where}");
        $items = $wpdb->get_results($wpdb->prepare("SELECT t.* FROM {$table} t WHERE {$where} ORDER BY {$order} LIMIT %d OFFSET %d", $per_page, ($page - 1) * $per_page));
        // phpcs:enable

        return ['items' => $items, 'total' => $total];
    }

    private static function where(array $args)
    {
        global $wpdb;
        $w = [];

        $view = in_array($args['view'] ?? '', self::VIEWS, true) ? $args['view'] : 'active';
        switch ($view) {
            case 'active':
                $w[] = "t.status NOT IN ('closed','finish','trash')";
                break;
            case 'waiting':
                $w[] = "t.status = 'answerd'";
                break;
            case 'closed':
                $w[] = "t.status IN ('closed','finish')";
                break;
            case 'trash':
                $w[] = "t.status = 'trash'";
                break;
            default:
                $w[] = "t.status != 'trash'";
        }

        if (!empty($args['priority']) && in_array($args['priority'], ['low', 'medium', 'high'], true)) {
            $w[] = $wpdb->prepare('t.priority = %s', $args['priority']);
        }
        if (!empty($args['department'])) {
            $dep = (int) $args['department'];
            $w[] = $wpdb->prepare('(t.department_id = %d OR t.department_id IN (SELECT ID FROM ' . Departments::table() . ' WHERE parent = %d))', $dep, $dep);
        }

        $search = isset($args['search']) ? trim((string) $args['search']) : '';
        if ($search !== '') {
            $digits = ltrim(ticketchi_lite_en_digits($search), '#');
            $like = '%' . $wpdb->esc_like($search) . '%';
            $users = "SELECT ID FROM {$wpdb->users} WHERE display_name LIKE %s OR user_email LIKE %s OR user_login LIKE %s";
            $clause = $wpdb->prepare("(t.title LIKE %s OR t.body LIKE %s OR t.creator_id IN ({$users}) OR t.user_id IN ({$users})", $like, $like, $like, $like, $like, $like, $like, $like);
            if (ctype_digit($digits)) {
                $clause .= $wpdb->prepare(' OR t.ID = %d', (int) $digits);
            }
            $w[] = $clause . ')';
        }

        return implode(' AND ', $w);
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
