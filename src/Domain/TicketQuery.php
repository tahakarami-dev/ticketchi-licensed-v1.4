<?php

namespace Ticketchi\Domain;

use Ticketchi\Support\Clock;

defined('ABSPATH') || exit('NO Access');

/**
 * Inbox queries: saved views + filters + search + sorting + paging, all in SQL.
 */
final class TicketQuery
{
    const VIEWS = ['mine', 'unassigned', 'active', 'waiting', 'sla', 'closed', 'all', 'trash'];

    /**
     * @param array $args view, status, priority, department, assignee, tag, customer, search,
     *                    sort (updated|created|priority|due), page, per_page
     * @return array{items: object[], total: int}
     */
    public static function run(array $args, $user_id)
    {
        global $wpdb;
        list($where, $params) = self::where($args, $user_id);

        $per_page = max(1, min(100, (int) ($args['per_page'] ?? 30)));
        $page = max(1, (int) ($args['page'] ?? 1));
        $offset = ($page - 1) * $per_page;

        switch ($args['sort'] ?? 'updated') {
            case 'created':
                $order = 't.create_date DESC, t.ID DESC';
                break;
            case 'oldest':
                $order = 't.updated_at ASC, t.ID ASC';
                break;
            case 'priority':
                $order = "CASE t.priority WHEN 'high' THEN 3 WHEN 'medium' THEN 2 ELSE 1 END DESC, t.updated_at DESC";
                break;
            case 'due':
                $order = 'CASE WHEN t.first_response_at IS NULL THEN t.first_response_due ELSE t.resolution_due END IS NULL, CASE WHEN t.first_response_at IS NULL THEN t.first_response_due ELSE t.resolution_due END ASC';
                break;
            default:
                $order = 't.updated_at DESC, t.ID DESC';
        }

        $table = Tickets::table();
        $count_sql = "SELECT COUNT(*) FROM {$table} t WHERE {$where}";
        $total = (int) $wpdb->get_var($params ? $wpdb->prepare($count_sql, $params) : $count_sql);

        $sql = "SELECT t.* FROM {$table} t WHERE {$where} ORDER BY {$order} LIMIT %d OFFSET %d";
        $items = $wpdb->get_results($wpdb->prepare($sql, array_merge($params, [$per_page, $offset])));

        return ['items' => $items, 'total' => $total];
    }

    /** @return array [sql, params] */
    private static function where(array $args, $user_id)
    {
        global $wpdb;
        $w = ['1=1'];
        $p = [];

        $view = in_array($args['view'] ?? '', self::VIEWS, true) ? $args['view'] : 'active';
        $closed = "('closed','finish','trash')";

        switch ($view) {
            case 'mine':
                $w[] = "t.status NOT IN {$closed} AND t.assignee_id = %d";
                $p[] = (int) $user_id;
                break;
            case 'unassigned':
                $w[] = "t.status NOT IN {$closed} AND (t.assignee_id IS NULL OR t.assignee_id = 0)";
                break;
            case 'active':
                $w[] = "t.status NOT IN {$closed}";
                break;
            case 'waiting':
                $w[] = "t.status = 'answerd'";
                break;
            case 'sla':
                // Overdue or due within the next 2 hours.
                $soon = Clock::from_ts(time() + 2 * HOUR_IN_SECONDS);
                $w[] = "t.status NOT IN {$closed} AND ((t.first_response_at IS NULL AND t.first_response_due IS NOT NULL AND t.first_response_due < %s) OR (t.resolution_due IS NOT NULL AND t.resolution_due < %s))";
                $p[] = $soon;
                $p[] = $soon;
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

        $scope = \Ticketchi\Support\Capabilities::scope($user_id);
        if ($scope !== null) {
            $w[] = $scope ? '(t.department_id IN (' . implode(',', $scope) . ') OR t.assignee_id = %d)' : 't.assignee_id = %d';
            $p[] = (int) $user_id;
        }

        if (!empty($args['status'])) {
            $w[] = 't.status = %s';
            $p[] = sanitize_key($args['status']);
        }
        if (!empty($args['priority']) && in_array($args['priority'], ['low', 'medium', 'high'], true)) {
            $w[] = 't.priority = %s';
            $p[] = $args['priority'];
        }
        if (!empty($args['department'])) {
            $dep = (int) $args['department'];
            $w[] = "(t.department_id = %d OR t.department_id IN (SELECT ID FROM {$wpdb->prefix}tkm_departments WHERE parent = %d))";
            $p[] = $dep;
            $p[] = $dep;
        }
        if (isset($args['assignee']) && $args['assignee'] !== '' && $args['assignee'] !== null) {
            if ((string) $args['assignee'] === '0') {
                $w[] = '(t.assignee_id IS NULL OR t.assignee_id = 0)';
            } else {
                $w[] = 't.assignee_id = %d';
                $p[] = (int) $args['assignee'];
            }
        }
        if (!empty($args['tag'])) {
            $w[] = 't.ID IN (SELECT ticket_id FROM ' . Tags::pivot() . ' WHERE tag_id = %d)';
            $p[] = (int) $args['tag'];
        }
        if (!empty($args['customer'])) {
            $w[] = '((t.creator_id = %d AND (t.from_admin IS NULL OR t.from_admin = 0)) OR (t.user_id = %d AND t.from_admin = 1))';
            $p[] = (int) $args['customer'];
            $p[] = (int) $args['customer'];
        }

        $search = isset($args['search']) ? trim((string) $args['search']) : '';
        if ($search !== '') {
            $digits = ltrim(strtr($search, ['۰' => '0', '۱' => '1', '۲' => '2', '۳' => '3', '۴' => '4', '۵' => '5', '۶' => '6', '۷' => '7', '۸' => '8', '۹' => '9']), '#');
            $like = '%' . $wpdb->esc_like($search) . '%';
            $users = "SELECT ID FROM {$wpdb->users} WHERE display_name LIKE %s OR user_email LIKE %s OR user_login LIKE %s";
            $clause = "(t.title LIKE %s OR t.body LIKE %s OR t.creator_id IN ({$users}) OR t.user_id IN ({$users})";
            array_push($p, $like, $like, $like, $like, $like, $like, $like, $like);
            if (ctype_digit($digits)) {
                $clause .= ' OR t.ID = %d';
                $p[] = (int) $digits;
            }
            $w[] = $clause . ')';
        }

        return [implode(' AND ', $w), $p];
    }

    /** Badge counts for the sidebar views. */
    public static function view_counts($user_id)
    {
        $counts = [];
        foreach (['mine', 'unassigned', 'active', 'waiting', 'sla'] as $view) {
            global $wpdb;
            list($where, $params) = self::where(['view' => $view], $user_id);
            $sql = 'SELECT COUNT(*) FROM ' . Tickets::table() . " t WHERE {$where}";
            $counts[$view] = (int) $wpdb->get_var($params ? $wpdb->prepare($sql, $params) : $sql);
        }
        return $counts;
    }

    /** reply counts + last public message per ticket, in two queries. */
    public static function summaries(array $ticket_ids)
    {
        global $wpdb;
        $ids = array_filter(array_map('intval', $ticket_ids));
        if (!$ids) {
            return [];
        }
        $in = implode(',', $ids);
        $replies = Tickets::replies_table();

        $out = [];
        foreach ($wpdb->get_results("SELECT ticket_id, COUNT(*) AS c FROM {$replies} WHERE ticket_id IN ({$in}) AND (is_internal = 0 OR is_internal IS NULL) GROUP BY ticket_id") as $row) {
            $out[(int) $row->ticket_id]['count'] = (int) $row->c;
        }
        $last = $wpdb->get_results("SELECT r.ticket_id, r.body, r.from_admin, r.creator_id FROM {$replies} r INNER JOIN (SELECT ticket_id, MAX(ID) AS mid FROM {$replies} WHERE ticket_id IN ({$in}) AND (is_internal = 0 OR is_internal IS NULL) GROUP BY ticket_id) m ON m.mid = r.ID");
        foreach ($last as $row) {
            $out[(int) $row->ticket_id]['last'] = $row;
        }
        return $out;
    }
}
