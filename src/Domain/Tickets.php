<?php

namespace Ticketchi\Domain;

use Ticketchi\Support\Clock;
use WP_Error;

defined('ABSPATH') || exit('NO Access');

/**
 * The single write path for tickets and replies.
 *
 * Every change is persisted with explicit UTC timestamps, recorded in the
 * activity log and announced through domain events that the SLA, assignment,
 * notification and automation modules listen to:
 *
 *   ticketchi/ticket_created  (int $ticket_id, int $actor_id, string $source)
 *   ticketchi/reply_added     (int $ticket_id, int $reply_id, int $actor_id, bool $staff, bool $internal)
 *   ticketchi/ticket_updated  (int $ticket_id, array $changes, int $actor_id)
 *   ticketchi/ticket_assigned (int $ticket_id, int $assignee_id, int $previous_id, int $actor_id)
 */
final class Tickets
{
    const CLOSED = ['closed', 'finish'];

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

    public static function is_closed($status)
    {
        return in_array($status, self::CLOSED, true);
    }

    public static function valid_statuses()
    {
        // tkm_get_status() only lists «trash» on wp-admin screens; REST and cron need it too.
        return array_values(array_unique(array_merge(wp_list_pluck(tkm_get_status(), 'slug'), ['trash'])));
    }

    /**
     * @param array  $data   title, body, department_id, priority, status, creator_id, user_id,
     *                       from_admin, file, voice, product, edd_product, note, assignee_id
     * @param string $source customer|staff|api
     * @return int|WP_Error
     */
    public static function create(array $data, $actor_id, $source = 'customer')
    {
        global $wpdb;

        $title = isset($data['title']) ? trim(sanitize_text_field($data['title'])) : '';
        $body = isset($data['body']) ? wp_kses_post($data['body']) : '';
        $department_id = isset($data['department_id']) ? absint($data['department_id']) : 0;

        if (!$department_id) {
            return new WP_Error('tkm_department', 'لطفا ابتدا نوع تیکت را انتخاب نمایید');
        }
        if (trim(wp_strip_all_tags($body)) === '' && empty($data['voice']) && empty($data['file'])) {
            return new WP_Error('tkm_body', 'لطفا محتوا تیکت را وارد نمایید');
        }

        $now = Clock::now();
        $status = isset($data['status']) && in_array($data['status'], self::valid_statuses(), true) ? $data['status'] : 'open';
        $priority = isset($data['priority']) && in_array($data['priority'], ['low', 'medium', 'high'], true) ? $data['priority'] : 'medium';

        $row = [
            'title'         => $title !== '' ? mb_substr($title, 0, 250) : 'بدون عنوان',
            'body'          => $body,
            'creator_id'    => !empty($data['creator_id']) ? (int) $data['creator_id'] : null,
            'user_id'       => !empty($data['user_id']) ? (int) $data['user_id'] : null,
            'from_admin'    => !empty($data['from_admin']) ? 1 : null,
            'department_id' => $department_id,
            'status'        => $status,
            'priority'      => $priority,
            'create_date'   => $now,
            'reply_date'    => $now,
            'updated_at'    => $now,
            'file'          => !empty($data['file']) ? $data['file'] : null,
            'voice'         => !empty($data['voice']) ? $data['voice'] : null,
            'note'          => !empty($data['note']) ? sanitize_textarea_field($data['note']) : null,
            'product'       => !empty($data['product']) ? (int) $data['product'] : null,
            'edd_product'   => !empty($data['edd_product']) ? (int) $data['edd_product'] : null,
            'assignee_id'   => !empty($data['assignee_id']) ? (int) $data['assignee_id'] : null,
        ];

        $ok = $wpdb->insert(self::table(), $row, ['%s', '%s', '%d', '%d', '%d', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%d', '%d']);
        if (!$ok) {
            return new WP_Error('tkm_db', 'ثبت تیکت در پایگاه داده با خطا مواجه شد.');
        }

        $id = (int) $wpdb->insert_id;
        Activity::log($id, 'created', ['source' => $source], $actor_id);

        do_action('ticketchi/ticket_created', $id, (int) $actor_id, $source);

        return $id;
    }

    /**
     * @param array $data body, file, voice, internal(bool), staff(bool|null: auto-detect)
     * @return int|WP_Error reply id
     */
    public static function reply($ticket_id, array $data, $actor_id)
    {
        global $wpdb;

        $ticket = self::find($ticket_id);
        if (!$ticket) {
            return new WP_Error('tkm_not_found', 'تیکت مورد نظر یافت نشد.');
        }

        $body = isset($data['body']) ? wp_kses_post($data['body']) : '';
        $has_text = trim(wp_strip_all_tags($body)) !== '';
        if (!$has_text && empty($data['file']) && empty($data['voice'])) {
            return new WP_Error('tkm_body', 'لطفا پاسخ تیکت را وارد نمایید');
        }

        $internal = !empty($data['internal']);
        $staff = isset($data['staff']) ? (bool) $data['staff'] : ((int) $actor_id !== self::customer_id($ticket) && tkm_is_staff($actor_id));
        $now = Clock::now();

        $ok = $wpdb->insert(self::replies_table(), [
            'ticket_id'   => (int) $ticket->ID,
            'creator_id'  => $actor_id ? (int) $actor_id : null,
            'from_admin'  => $staff ? 1 : null,
            'is_internal' => $internal ? 1 : 0,
            'body'        => $has_text ? $body : ' ',
            'create_date' => $now,
            'file'        => !empty($data['file']) ? $data['file'] : null,
            'voice'       => !empty($data['voice']) ? $data['voice'] : null,
        ], ['%d', '%d', '%d', '%d', '%s', '%s', '%s', '%s']);

        if (!$ok) {
            return new WP_Error('tkm_db', 'ثبت پاسخ با خطا مواجه شد.');
        }
        $reply_id = (int) $wpdb->insert_id;

        $fields = ['updated_at' => $now];
        if (!$internal) {
            $fields['reply_date'] = $now;
            if ($staff && !$ticket->first_response_at) {
                $fields['first_response_at'] = $now;
                $fields['sla_notified'] = 0; // allow a later resolution-breach alert
            }
        }
        $wpdb->update(self::table(), $fields, ['ID' => (int) $ticket->ID]);

        Activity::log($ticket->ID, $internal ? 'note' : 'reply', ['reply_id' => $reply_id], $actor_id);

        // Workflow: a staff answer marks the ticket «answered», a customer message re-opens it.
        if (array_key_exists('status', $data)) {
            $next = $data['status'];
        } elseif ($internal) {
            $next = null;
        } elseif ($staff) {
            $next = self::is_closed($ticket->status) ? null : 'answerd';
        } else {
            $next = 'open';
        }
        if ($next && $next !== $ticket->status) {
            self::update($ticket->ID, ['status' => $next], $actor_id);
        }

        do_action('ticketchi/reply_added', (int) $ticket->ID, $reply_id, (int) $actor_id, $staff, $internal);

        return $reply_id;
    }

    /**
     * Applies a set of field changes and records each one.
     *
     * @param array $changes status, priority, department_id, assignee_id, title, body, note,
     *                       creator_id, user_id, create_date, file
     * @return array|WP_Error  [field => [from, to]] of what actually changed
     */
    public static function update($ticket_id, array $changes, $actor_id)
    {
        global $wpdb;

        $ticket = self::find($ticket_id);
        if (!$ticket) {
            return new WP_Error('tkm_not_found', 'تیکت مورد نظر یافت نشد.');
        }

        $clean = [];
        foreach ($changes as $field => $value) {
            switch ($field) {
                case 'status':
                    if (in_array($value, self::valid_statuses(), true)) {
                        $clean['status'] = $value;
                    }
                    break;
                case 'priority':
                    if (in_array($value, ['low', 'medium', 'high'], true)) {
                        $clean['priority'] = $value;
                    }
                    break;
                case 'department_id':
                    if ($value && (new \TKM_Front_Department_Manager())->get_department((int) $value)) {
                        $clean['department_id'] = (int) $value;
                    }
                    break;
                case 'assignee_id':
                    $value = (int) $value;
                    if ($value === 0 || user_can($value, 'tkm_view')) {
                        $clean['assignee_id'] = $value ?: null;
                    }
                    break;
                case 'title':
                    $value = trim(sanitize_text_field($value));
                    if ($value !== '') {
                        $clean['title'] = mb_substr($value, 0, 250);
                    }
                    break;
                case 'body':
                    if (trim(wp_strip_all_tags((string) $value)) !== '') {
                        $clean['body'] = wp_kses_post($value);
                    }
                    break;
                case 'note':
                    $clean['note'] = sanitize_textarea_field((string) $value) ?: null;
                    break;
                case 'creator_id':
                case 'user_id':
                    $clean[$field] = (int) $value ?: null;
                    break;
                case 'create_date':
                    if (preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', (string) $value)) {
                        $clean['create_date'] = $value;
                    }
                    break;
                case 'file':
                    $clean['file'] = $value ?: null;
                    break;
            }
        }

        $diff = [];
        foreach ($clean as $field => $value) {
            $old = $ticket->$field;
            if ((string) $old !== (string) $value) {
                $diff[$field] = [$old, $value];
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
            if (self::is_closed($diff['status'][1]) && !self::is_closed($diff['status'][0])) {
                $row['resolved_at'] = $now;
            } elseif (!self::is_closed($diff['status'][1]) && self::is_closed($diff['status'][0])) {
                $row['resolved_at'] = null;
            }
        }

        $ok = $wpdb->update(self::table(), $row, ['ID' => (int) $ticket->ID]);
        if ($ok === false) {
            return new WP_Error('tkm_db', 'ذخیره تغییرات با خطا مواجه شد.');
        }

        foreach ($diff as $field => $pair) {
            switch ($field) {
                case 'status':
                case 'priority':
                    Activity::log($ticket->ID, $field, ['from' => $pair[0], 'to' => $pair[1]], $actor_id);
                    break;
                case 'department_id':
                    $dep = (new \TKM_Front_Department_Manager())->get_department($pair[1]);
                    Activity::log($ticket->ID, 'department', ['from' => (int) $pair[0], 'to' => (int) $pair[1], 'name' => $dep ? $dep->name : ''], $actor_id);
                    break;
                case 'assignee_id':
                    Activity::log($ticket->ID, 'assignee', ['from' => (int) $pair[0], 'to' => (int) $pair[1]], $actor_id);
                    break;
                case 'title':
                    Activity::log($ticket->ID, 'title', ['from' => $pair[0], 'to' => $pair[1]], $actor_id);
                    break;
            }
        }

        do_action('ticketchi/ticket_updated', (int) $ticket->ID, $diff, (int) $actor_id);

        if (isset($diff['assignee_id'])) {
            do_action('ticketchi/ticket_assigned', (int) $ticket->ID, (int) $diff['assignee_id'][1], (int) $diff['assignee_id'][0], (int) $actor_id);
        }

        return $diff;
    }

    public static function set_tags($ticket_id, array $tag_ids, $actor_id)
    {
        $before = Tags::ids_for_ticket($ticket_id);
        $after = Tags::sync($ticket_id, $tag_ids);
        sort($before);
        sort($after);
        if ($before !== $after) {
            Activity::log($ticket_id, 'tags', ['from' => $before, 'to' => $after], $actor_id);
            do_action('ticketchi/ticket_updated', (int) $ticket_id, ['tags' => [$before, $after]], (int) $actor_id);
        }
        return $after;
    }

    /** Permanently removes a ticket and everything attached to it. */
    public static function delete($ticket_id)
    {
        global $wpdb;
        $ticket_id = (int) $ticket_id;
        $wpdb->delete(self::table(), ['ID' => $ticket_id], ['%d']);
        $wpdb->delete(self::replies_table(), ['ticket_id' => $ticket_id], ['%d']);
        $wpdb->delete($wpdb->prefix . 'tkm_ratings', ['ticket_id' => $ticket_id], ['%d']);
        $wpdb->delete(Tags::pivot(), ['ticket_id' => $ticket_id], ['%d']);
        $wpdb->delete($wpdb->prefix . 'tkm_notifications', ['ticket_id' => $ticket_id], ['%d']);
        Activity::delete_for_ticket($ticket_id);
    }

    /** Public replies (customer view) or everything (staff view). */
    public static function replies($ticket_id, $include_internal = true)
    {
        global $wpdb;
        $sql = 'SELECT * FROM ' . self::replies_table() . ' WHERE ticket_id = %d' . ($include_internal ? '' : ' AND (is_internal = 0 OR is_internal IS NULL)') . ' ORDER BY create_date ASC, ID ASC';
        return $wpdb->get_results($wpdb->prepare($sql, (int) $ticket_id));
    }
}
