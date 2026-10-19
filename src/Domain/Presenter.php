<?php

namespace Ticketchi\Domain;

use Ticketchi\Sla\Sla;
use Ticketchi\Support\Clock;

defined('ABSPATH') || exit('NO Access');

/**
 * Stable JSON shapes for the REST API.
 */
final class Presenter
{
    public static function status($slug)
    {
        static $map = null;
        if ($map === null) {
            $map = [];
            foreach (tkm_get_status() as $s) {
                $map[$s['slug']] = ['slug' => $s['slug'], 'name' => trim($s['name']), 'color' => $s['color'] ?: '#8a94a6'];
            }
            $map['trash'] = ['slug' => 'trash', 'name' => 'زباله‌دان', 'color' => '#64748b'];
        }
        return isset($map[$slug]) ? $map[$slug] : ['slug' => (string) $slug, 'name' => (string) $slug, 'color' => '#8a94a6'];
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
            'avatar' => Customers::avatar($user_id),
        ];
    }

    public static function department($id)
    {
        static $cache = [];
        $id = (int) $id;
        if (!isset($cache[$id])) {
            $dep = $id ? (new \TKM_Front_Department_Manager())->get_department($id) : null;
            $cache[$id] = $dep ? ['id' => (int) $dep->ID, 'name' => $dep->name, 'parent' => (int) $dep->parent] : null;
        }
        return $cache[$id];
    }

    public static function sla($ticket)
    {
        $s = Sla::state($ticket);
        return [
            'state'     => $s['state'],
            'kind'      => $s['kind'],
            'due'       => $s['due'] ? gmdate('c', $s['due']) : null,
            'remaining' => $s['remaining'],
        ];
    }

    /** Compact row for the inbox list. */
    public static function summary($ticket, array $tags = [], array $summary = [])
    {
        $last = isset($summary['last']) ? $summary['last'] : null;
        $snippet = wp_trim_words(wp_strip_all_tags($last ? $last->body : $ticket->body), 18, '…');

        return [
            'id'          => (int) $ticket->ID,
            'title'       => $ticket->title,
            'status'      => self::status($ticket->status),
            'priority'    => $ticket->priority,
            'department'  => self::department($ticket->department_id),
            'customer'    => self::user(Tickets::customer_id($ticket)),
            'assignee'    => self::user($ticket->assignee_id),
            'tags'        => $tags,
            'outbound'    => (int) $ticket->from_admin === 1,
            'snippet'     => $snippet,
            'last_from'   => $last ? ((int) $last->from_admin === 1 ? 'staff' : 'customer') : ((int) $ticket->from_admin === 1 ? 'staff' : 'customer'),
            'replies'     => isset($summary['count']) ? (int) $summary['count'] : 0,
            'created_at'  => Clock::iso($ticket->create_date),
            'updated_at'  => Clock::iso($ticket->updated_at ?: $ticket->reply_date),
            'sla'         => self::sla($ticket),
        ];
    }

    public static function message($row, $kind = 'reply')
    {
        $files = [];
        foreach (tkm_get_files($row->file) as $url) {
            $files[] = ['url' => esc_url_raw($url), 'name' => tkm_get_file_name($url)];
        }
        $author = (int) $row->creator_id;

        return [
            'id'       => (int) $row->ID,
            'kind'     => $kind,
            'internal' => !empty($row->is_internal),
            'staff'    => $kind === 'reply' ? (int) $row->from_admin === 1 : (int) $row->from_admin === 1,
            'author'   => self::user($author),
            'html'     => trim(wp_strip_all_tags($row->body)) === '' ? '' : tkm_content($row->body),
            'files'    => $files,
            'voice'    => $row->voice ? esc_url_raw($row->voice) : null,
            'date'     => Clock::iso($kind === 'opening' ? $row->create_date : $row->create_date),
        ];
    }

    /** Full ticket for the conversation pane. */
    public static function detail($ticket)
    {
        global $wpdb;

        $messages = [self::message($ticket, 'opening')];
        foreach (Tickets::replies($ticket->ID, true) as $reply) {
            // Staff replies written before v2 have no from_admin flag; infer it.
            if ($reply->from_admin === null && (int) $reply->creator_id !== Tickets::customer_id($ticket) && tkm_is_staff($reply->creator_id)) {
                $reply->from_admin = 1;
            }
            $messages[] = self::message($reply, 'reply');
        }

        $activity = array_map(function ($row) {
            return [
                'id'   => (int) $row->ID,
                'type' => $row->type,
                'text' => Activity::describe($row),
                'user' => self::user($row->user_id),
                'date' => Clock::iso($row->created_at),
            ];
        }, Activity::for_ticket($ticket->ID, 60));

        $rating = $wpdb->get_var($wpdb->prepare("SELECT rating FROM {$wpdb->prefix}tkm_ratings WHERE ticket_id = %d ORDER BY ID DESC LIMIT 1", (int) $ticket->ID));
        $tags = Tags::for_tickets([$ticket->ID]);

        $product = null;
        if ($ticket->product) {
            $info = get_product_name_and_link_by_id($ticket->product);
            $product = ['name' => $info['name'], 'url' => $info['link']];
        }

        return array_merge(self::summary($ticket, isset($tags[$ticket->ID]) ? $tags[$ticket->ID] : []), [
            'note'               => (string) $ticket->note,
            'rating'             => $rating !== null ? (int) $rating : null,
            'product'            => $product,
            'messages'           => $messages,
            'activity'           => $activity,
            'first_response_at'  => Clock::iso($ticket->first_response_at),
            'resolved_at'        => Clock::iso($ticket->resolved_at),
            'customer_url'       => \Ticketchi\Support\Links::customer_ticket($ticket->ID),
        ]);
    }
}
