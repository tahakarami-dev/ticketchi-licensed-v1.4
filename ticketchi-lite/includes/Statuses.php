<?php

namespace TicketchiLite;

defined('ABSPATH') || exit;

/**
 * The four ticket statuses (+ trash). Slugs match Ticketchi Pro.
 */
final class Statuses
{
    const CLOSED = ['closed', 'finish'];

    /** @return array[] slug, name, color */
    public static function all($with_trash = false)
    {
        $list = [
            ['slug' => 'open', 'name' => 'باز', 'color' => Settings::get('open-color', '#4bcc58')],
            ['slug' => 'answerd', 'name' => 'پاسخ داده شده', 'color' => Settings::get('close-color', '#3150d8')],
            ['slug' => 'closed', 'name' => 'بسته شده', 'color' => Settings::get('answerd-color', '#ff2121')],
            ['slug' => 'finish', 'name' => 'پایان یافته', 'color' => Settings::get('finish-color', '#000000')],
        ];
        if ($with_trash) {
            $list[] = ['slug' => 'trash', 'name' => 'زباله‌دان', 'color' => '#64748b'];
        }
        return $list;
    }

    public static function slugs()
    {
        return wp_list_pluck(self::all(true), 'slug');
    }

    public static function get($slug)
    {
        foreach (self::all(true) as $status) {
            if ($status['slug'] === $slug) {
                return $status;
            }
        }
        // Statuses created in Ticketchi Pro: shown by slug.
        return ['slug' => (string) $slug, 'name' => (string) $slug, 'color' => '#8a94a6'];
    }

    public static function is_closed($slug)
    {
        return in_array($slug, self::CLOSED, true);
    }
}
