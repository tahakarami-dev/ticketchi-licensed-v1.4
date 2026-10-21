<?php

namespace TicketchiLite;

defined('ABSPATH') || exit;

/**
 * The single write path for tickets and replies. Every change is stored with
 * explicit UTC timestamps and announced through actions:
 *
 *   ticketchi_lite_ticket_created (int $ticket_id, int $actor_id)
 *   ticketchi_lite_reply_added    (int $ticket_id, int $reply_id, int $actor_id, bool $staff)
 *   ticketchi_lite_ticket_updated (int $ticket_id, array $changes, int $actor_id)
 */
final class Tickets
{
    public static function table()
    {
        global $wpdb;
        return $wpdb->prefix . 'tkm_tickets';
    }

    public static function replies_table()
    {
        global $wpdb;
        return $wpdb->prefix . 'tkm_replies';
    }

    public static function find($id)
    {
        global $wpdb;
        return $id ? $wpdb->get_row($wpdb->prepare('SELECT * FROM ' . self::table() . ' WHERE ID = %d', (int) $id)) : null;
    }

    /** The customer a ticket belongs to (recipient for staff-initiated tickets). */
    public static function customer_id($ticket)
    {
        return (int) $ticket->from_admin === 1 && $ticket->user_id ? (int) $ticket->user_id : (int) $ticket->creator_id;
    }

    /**
     * @param array $data title, body, department_id, priority, file
     * @return int|\WP_Error
     */
    public static function create(array $data, $actor_id)
    {
        global $wpdb;

        $title = isset($data['title']) ? trim(sanitize_text_field($data['title'])) : '';
        $body = isset($data['body']) ? wp_kses_post($data['body']) : '';
        $department_id = isset($data['department_id']) ? absint($data['department_id']) : 0;

        if (!$department_id || !Departments::find($department_id)) {
            return new \WP_Error('ticketchi_lite_department', 'لطفا دپارتمان تیکت را انتخاب کنید.');
        }
        if (trim(wp_strip_all_tags($body)) === '' && empty($data['file'])) {
            return new \WP_Error('ticketchi_lite_body', 'لطفا متن تیکت را وارد کنید.');
        }

        $now = Clock::now();
        $priority = isset($data['priority']) && in_array($data['priority'], ['low', 'medium', 'high'], true) ? $data['priority'] : 'medium';

        $ok = $wpdb->insert(self::table(), [
            'title'         => $title !== '' ? mb_substr($title, 0, 250) : 'بدون عنوان',
            'body'          => $body,
            'creator_id'    => (int) $actor_id,
            'department_id' => $department_id,
            'status'        => 'open',
            'priority'      => $priority,
            'create_date'   => $now,
            'reply_date'    => $now,
            'updated_at'    => $now,
            'file'          => !empty($data['file']) ? $data['file'] : null,
        ], ['%s', '%s', '%d', '%d', '%s', '%s', '%s', '%s', '%s', '%s']);

        if (!$ok) {
            return new \WP_Error('ticketchi_lite_db', 'ثبت تیکت در پایگاه داده با خطا مواجه شد.');
        }

        $id = (int) $wpdb->insert_id;
        do_action('ticketchi_lite_ticket_created', $id, (int) $actor_id);
        return $id;
    }

    /**
     * @param array $data body, file, status (optional next status)
     * @return int|\WP_Error reply id
     */
    public static function reply($ticket_id, array $data, $actor_id)
    {
        global $wpdb;

        $ticket = self::find($ticket_id);
        if (!$ticket) {
            return new \WP_Error('ticketchi_lite_not_found', 'تیکت مورد نظر یافت نشد.');
        }

        $body = isset($data['body']) ? wp_kses_post($data['body']) : '';
        $has_text = trim(wp_strip_all_tags($body)) !== '';
        if (!$has_text && empty($data['file'])) {
            return new \WP_Error('ticketchi_lite_body', 'لطفا متن پاسخ را وارد کنید.');
        }

        $staff = (int) $actor_id !== self::customer_id($ticket) && ticketchi_lite_is_staff($actor_id);
        $now = Clock::now();

        $ok = $wpdb->insert(self::replies_table(), [
            'ticket_id'   => (int) $ticket->ID,
            'creator_id'  => (int) $actor_id,
            'from_admin'  => $staff ? 1 : null,
            'is_internal' => 0,
            'body'        => $has_text ? $body : ' ',
            'create_date' => $now,
            'file'        => !empty($data['file']) ? $data['file'] : null,
        ], ['%d', '%d', '%d', '%d', '%s', '%s', '%s']);

        if (!$ok) {
            return new \WP_Error('ticketchi_lite_db', 'ثبت پاسخ با خطا مواجه شد.');
        }
        $reply_id = (int) $wpdb->insert_id;

        $fields = ['updated_at' => $now, 'reply_date' => $now];
        if ($staff && empty($ticket->first_response_at)) {
            $fields['first_response_at'] = $now;
        }
        $wpdb->update(self::table(), $fields, ['ID' => (int) $ticket->ID]);

        // A staff answer marks the ticket «answered», a customer message re-opens it.
        if (!empty($data['status'])) {
            $next = $data['status'];
        } elseif ($staff) {
            $next = Statuses::is_closed($ticket->status) ? null : 'answerd';
        } else {
            $next = 'open';
        }
        if ($next && $next !== $ticket->status) {
            self::update($ticket->ID, ['status' => $next], $actor_id);
        }

        do_action('ticketchi_lite_reply_added', (int) $ticket->ID, $reply_id, (int) $actor_id, $staff);
        return $reply_id;
    }

    /**
     * @param array $changes status, priority, department_id
     * @return array|\WP_Error [field => [from, to]]
     */
    public static function update($ticket_id, array $changes, $actor_id)
    {
        global $wpdb;

        $ticket = self::find($ticket_id);
        if (!$ticket) {
            return new \WP_Error('ticketchi_lite_not_found', 'تیکت مورد نظر یافت نشد.');
        }

        $clean = [];
        if (isset($changes['status']) && in_array($changes['status'], Statuses::slugs(), true)) {
            $clean['status'] = $changes['status'];
        }
        if (isset($changes['priority']) && in_array($changes['priority'], ['low', 'medium', 'high'], true)) {
            $clean['priority'] = $changes['priority'];
        }
        if (!empty($changes['department_id']) && Departments::find((int) $changes['department_id'])) {
            $clean['department_id'] = (int) $changes['department_id'];
        }

        $diff = [];
        foreach ($clean as $field => $value) {
            if ((string) $ticket->$field !== (string) $value) {
                $diff[$field] = [$ticket->$field, $value];
            }
        }
        if (!$diff) {
            return [];
        }

        $now = Clock::now();
        $row = array_map(function ($pair) {
            return $pair[1];
        }, $diff);
        $row['updated_at'] = $now;

        if (isset($diff['status'])) {
            if (Statuses::is_closed($diff['status'][1]) && !Statuses::is_closed($diff['status'][0])) {
                $row['resolved_at'] = $now;
            } elseif (!Statuses::is_closed($diff['status'][1])) {
                $row['resolved_at'] = null;
            }
        }

        if ($wpdb->update(self::table(), $row, ['ID' => (int) $ticket->ID]) === false) {
            return new \WP_Error('ticketchi_lite_db', 'ذخیره تغییرات با خطا مواجه شد.');
        }

        do_action('ticketchi_lite_ticket_updated', (int) $ticket->ID, $diff, (int) $actor_id);
        return $diff;
    }

    /** Permanently removes a ticket and its replies. */
    public static function delete($ticket_id)
    {
        global $wpdb;
        $ticket_id = (int) $ticket_id;
        $wpdb->delete(self::table(), ['ID' => $ticket_id], ['%d']);
        $wpdb->delete(self::replies_table(), ['ticket_id' => $ticket_id], ['%d']);
    }

    /**
     * Replies in order. Internal staff notes (written in Ticketchi Pro) are
     * never part of the public conversation.
     */
    public static function replies($ticket_id, $include_internal = false)
    {
        global $wpdb;
        $sql = 'SELECT * FROM ' . self::replies_table() . ' WHERE ticket_id = %d' . ($include_internal ? '' : ' AND (is_internal = 0 OR is_internal IS NULL)') . ' ORDER BY create_date ASC, ID ASC';
        return $wpdb->get_results($wpdb->prepare($sql, (int) $ticket_id));
    }

    /** Was this reply written by support? (rows from before v2 have no flag) */
    public static function is_staff_reply($reply, $ticket)
    {
        if ((int) $reply->from_admin === 1) {
            return true;
        }
        $author = (int) $reply->creator_id;
        return $reply->from_admin === null && $author && $author !== self::customer_id($ticket) && ticketchi_lite_is_staff($author);
    }

    /* ───────────── Customer panel ───────────── */

    /** WHERE clause for «tickets of this customer». */
    private static function customer_where($user_id)
    {
        global $wpdb;
        return $wpdb->prepare("(creator_id = %d OR user_id = %d) AND status != 'trash'", (int) $user_id, (int) $user_id);
    }

    /**
     * @param array $args status, priority, orderby (reply-date|create-date), page
     * @return array{items: object[], total: int, pages: int}
     */
    public static function for_customer($user_id, array $args, $per_page = 10)
    {
        global $wpdb;
        $where = self::customer_where($user_id);
        if (!empty($args['status']) && $args['status'] !== 'all') {
            $where .= $wpdb->prepare(' AND status = %s', $args['status']);
        }
        if (!empty($args['priority']) && $args['priority'] !== 'all-priority') {
            $where .= $wpdb->prepare(' AND priority = %s', $args['priority']);
        }
        $order = (isset($args['orderby']) && $args['orderby'] === 'create-date') ? 'create_date DESC, ID DESC' : 'reply_date DESC, ID DESC';
        $page = max(1, (int) ($args['page'] ?? 1));

        $table = self::table();
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $where is built with prepare() above.
        $total = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$table} WHERE {$where}");
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $where is built with prepare() above.
        $items = $wpdb->get_results($wpdb->prepare("SELECT * FROM {$table} WHERE {$where} ORDER BY {$order} LIMIT %d OFFSET %d", $per_page, ($page - 1) * $per_page));

        return ['items' => $items, 'total' => $total, 'pages' => (int) ceil($total / $per_page)];
    }

    /** [status => count] for a customer, plus 'all'. */
    public static function customer_counts($user_id)
    {
        global $wpdb;
        $table = self::table();
        $where = self::customer_where($user_id);
        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $where is built with prepare().
        $rows = $wpdb->get_results("SELECT status, COUNT(*) AS c FROM {$table} WHERE {$where} GROUP BY status");
        $counts = ['all' => 0];
        foreach ($rows as $row) {
            $counts[$row->status] = (int) $row->c;
            $counts['all'] += (int) $row->c;
        }
        return $counts;
    }

    /** [ticket_id => public reply count] */
    public static function reply_counts(array $ticket_ids)
    {
        global $wpdb;
        $ids = array_values(array_filter(array_map('intval', $ticket_ids)));
        if (!$ids) {
            return [];
        }
        $in = implode(',', array_fill(0, count($ids), '%d'));
        $rows = $wpdb->get_results($wpdb->prepare('SELECT ticket_id, COUNT(*) AS c FROM ' . self::replies_table() . " WHERE ticket_id IN ({$in}) AND (is_internal = 0 OR is_internal IS NULL) GROUP BY ticket_id", $ids));
        $out = [];
        foreach ($rows as $row) {
            $out[(int) $row->ticket_id] = (int) $row->c;
        }
        return $out;
    }
}
