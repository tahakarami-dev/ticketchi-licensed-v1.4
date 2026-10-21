<?php

namespace TicketchiLite;

defined('ABSPATH') || exit;

/**
 * Stable JSON shapes for the REST API.
 */
final class Presenter
{
    public static function status($slug)
    {
        $s = Statuses::get($slug);
        return ['slug' => $s['slug'], 'name' => $s['name'], 'color' => $s['color'] ?: '#8a94a6'];
    }

    public static function user($user_id)
    {
        $user_id = (int) $user_id;
        if (!$user_id) {
            return null;
        }
        $user = get_userdata($user_id);
        return [
            'id'     => $user_id,
            'name'   => $user ? $user->display_name : 'کاربر حذف‌شده',
            'email'  => $user ? $user->user_email : '',
            'avatar' => Avatars::data($user_id),
        ];
    }

    public static function department($id)
    {
        $dep = Departments::find($id);
        return $dep ? ['id' => (int) $dep->ID, 'name' => $dep->name, 'parent' => (int) $dep->parent] : null;
    }

    /** Compact row for the inbox list. */
    public static function summary($ticket, array $summary = [])
    {
        $last = isset($summary['last']) ? $summary['last'] : null;
        $last_staff = $last ? Tickets::is_staff_reply($last, $ticket) : (int) $ticket->from_admin === 1;

        return [
            'id'         => (int) $ticket->ID,
            'title'      => $ticket->title,
            'status'     => self::status($ticket->status),
            'priority'   => $ticket->priority,
            'department' => self::department($ticket->department_id),
            'customer'   => self::user(Tickets::customer_id($ticket)),
            'snippet'    => wp_trim_words(wp_strip_all_tags($last ? $last->body : $ticket->body), 18, '…'),
            'last_from'  => $last_staff ? 'staff' : 'customer',
            'replies'    => isset($summary['count']) ? (int) $summary['count'] : 0,
            'created_at' => Clock::iso($ticket->create_date),
            'updated_at' => Clock::iso($ticket->updated_at ?: $ticket->reply_date),
        ];
    }

    public static function message($row, $staff, $kind = 'reply')
    {
        $files = [];
        foreach (Uploads::decode($row->file) as $url) {
            $files[] = ['url' => esc_url_raw($url), 'name' => wp_basename((string) wp_parse_url($url, PHP_URL_PATH))];
        }
        return [
            'id'     => (int) $row->ID,
            'kind'   => $kind,
            'staff'  => (bool) $staff,
            'author' => self::user($row->creator_id),
            'html'   => trim(wp_strip_all_tags((string) $row->body)) === '' ? '' : ticketchi_lite_content($row->body),
            'files'  => $files,
            'voice'  => !empty($row->voice) ? esc_url_raw($row->voice) : null,
            'date'   => Clock::iso($row->create_date),
        ];
    }

    /** Full ticket for the conversation pane. */
    public static function detail($ticket)
    {
        $messages = [self::message($ticket, (int) $ticket->from_admin === 1, 'opening')];
        foreach (Tickets::replies($ticket->ID) as $reply) {
            $messages[] = self::message($reply, Tickets::is_staff_reply($reply, $ticket));
        }

        return array_merge(self::summary($ticket), [
            'messages'          => $messages,
            'first_response_at' => Clock::iso($ticket->first_response_at),
            'resolved_at'       => Clock::iso($ticket->resolved_at),
            'customer_url'      => ticketchi_lite_url('single', $ticket->ID),
        ]);
    }
}
