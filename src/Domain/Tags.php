<?php

namespace Ticketchi\Domain;

defined('ABSPATH') || exit('NO Access');

final class Tags
{
    public static function table()
    {
        global $wpdb;
        return $wpdb->prefix . 'tkm_tags';
    }

    public static function pivot()
    {
        global $wpdb;
        return $wpdb->prefix . 'tkm_ticket_tags';
    }

    public static function all()
    {
        global $wpdb;
        return $wpdb->get_results('SELECT t.*, (SELECT COUNT(*) FROM ' . self::pivot() . ' p WHERE p.tag_id = t.ID) AS usage_count FROM ' . self::table() . ' t ORDER BY t.name');
    }

    public static function find_or_create($name, $color = null)
    {
        global $wpdb;
        $name = trim(sanitize_text_field($name));
        if ($name === '') {
            return 0;
        }

        $id = (int) $wpdb->get_var($wpdb->prepare('SELECT ID FROM ' . self::table() . ' WHERE name = %s', $name));
        if ($id) {
            return $id;
        }

        $palette = ['#6366f1', '#0ea5e9', '#10b981', '#f59e0b', '#ef4444', '#ec4899', '#8b5cf6', '#14b8a6'];
        $wpdb->insert(self::table(), [
            'name'  => mb_substr($name, 0, 64),
            'color' => sanitize_hex_color((string) $color) ?: $palette[crc32($name) % count($palette)],
        ], ['%s', '%s']);

        return (int) $wpdb->insert_id;
    }

    public static function update($id, $name, $color)
    {
        global $wpdb;
        return $wpdb->update(self::table(), [
            'name'  => mb_substr(trim(sanitize_text_field($name)), 0, 64),
            'color' => sanitize_hex_color((string) $color) ?: '#6366f1',
        ], ['ID' => (int) $id], ['%s', '%s'], ['%d']);
    }

    public static function delete($id)
    {
        global $wpdb;
        $wpdb->delete(self::pivot(), ['tag_id' => (int) $id], ['%d']);
        return $wpdb->delete(self::table(), ['ID' => (int) $id], ['%d']);
    }

    /** @return array<int, array> ticket_id => [tags] */
    public static function for_tickets(array $ticket_ids)
    {
        global $wpdb;
        $ticket_ids = array_filter(array_map('intval', $ticket_ids));
        if (!$ticket_ids) {
            return [];
        }

        $rows = $wpdb->get_results('SELECT p.ticket_id, t.ID, t.name, t.color FROM ' . self::pivot() . ' p INNER JOIN ' . self::table() . ' t ON t.ID = p.tag_id WHERE p.ticket_id IN (' . implode(',', $ticket_ids) . ') ORDER BY t.name');

        $map = [];
        foreach ($rows as $row) {
            $map[(int) $row->ticket_id][] = ['id' => (int) $row->ID, 'name' => $row->name, 'color' => $row->color];
        }
        return $map;
    }

    public static function ids_for_ticket($ticket_id)
    {
        global $wpdb;
        return array_map('intval', $wpdb->get_col($wpdb->prepare('SELECT tag_id FROM ' . self::pivot() . ' WHERE ticket_id = %d', (int) $ticket_id)));
    }

    public static function sync($ticket_id, array $tag_ids)
    {
        global $wpdb;
        $ticket_id = (int) $ticket_id;
        $tag_ids = array_values(array_unique(array_filter(array_map('intval', $tag_ids))));

        $wpdb->delete(self::pivot(), ['ticket_id' => $ticket_id], ['%d']);
        foreach ($tag_ids as $tag_id) {
            $wpdb->insert(self::pivot(), ['ticket_id' => $ticket_id, 'tag_id' => $tag_id], ['%d', '%d']);
        }
        return $tag_ids;
    }

    public static function add($ticket_id, $tag_id)
    {
        $ids = self::ids_for_ticket($ticket_id);
        if (!in_array((int) $tag_id, $ids, true)) {
            $ids[] = (int) $tag_id;
            self::sync($ticket_id, $ids);
            return true;
        }
        return false;
    }
}
