<?php

namespace TicketchiLite;

defined('ABSPATH') || exit;

/**
 * Departments: top level + one level of sub-departments («ticket types»).
 */
final class Departments
{
    public static function table()
    {
        global $wpdb;
        return $wpdb->prefix . 'tkm_departments';
    }

    public static function all()
    {
        global $wpdb;
        return $wpdb->get_results('SELECT * FROM ' . self::table() . ' ORDER BY position, ID');
    }

    public static function parents()
    {
        global $wpdb;
        return $wpdb->get_results('SELECT * FROM ' . self::table() . ' WHERE parent = 0 ORDER BY position, ID');
    }

    public static function children($parent_id)
    {
        global $wpdb;
        return $wpdb->get_results($wpdb->prepare('SELECT * FROM ' . self::table() . ' WHERE parent = %d ORDER BY position, ID', (int) $parent_id));
    }

    public static function find($id)
    {
        static $cache = [];
        $id = (int) $id;
        if (!$id) {
            return null;
        }
        if (!array_key_exists($id, $cache)) {
            global $wpdb;
            $cache[$id] = $wpdb->get_row($wpdb->prepare('SELECT * FROM ' . self::table() . ' WHERE ID = %d', $id));
        }
        return $cache[$id];
    }

    /** Tree for the admin app: [{id, name, children: [{id, name}]}] */
    public static function tree()
    {
        $out = [];
        foreach (self::parents() as $parent) {
            $children = [];
            foreach (self::children($parent->ID) as $child) {
                $children[] = ['id' => (int) $child->ID, 'name' => $child->name];
            }
            $out[] = ['id' => (int) $parent->ID, 'name' => $parent->name, 'children' => $children];
        }
        return $out;
    }

    /**
     * Lite departments are flat; sub-departments are a Ticketchi Pro feature
     * (an existing «parent» is left untouched on edit).
     *
     * @param array $data name, position, description
     * @return int|false department id
     */
    public static function save(array $data, $id = 0)
    {
        global $wpdb;
        $id = (int) $id;
        $row = [
            'name'        => sanitize_text_field($data['name']),
            'position'    => isset($data['position']) && $data['position'] !== '' ? (int) ticketchi_lite_en_digits($data['position']) : 1,
            'description' => isset($data['description']) ? sanitize_textarea_field($data['description']) : null,
        ];

        if ($id) {
            return $wpdb->update(self::table(), $row, ['ID' => $id], ['%s', '%d', '%s'], ['%d']) === false ? false : $id;
        }
        $row['parent'] = 0;
        return $wpdb->insert(self::table(), $row, ['%s', '%d', '%s', '%d']) ? (int) $wpdb->insert_id : false;
    }

    public static function delete($id)
    {
        global $wpdb;
        $id = (int) $id;
        $wpdb->delete(self::table(), ['ID' => $id], ['%d']);
        // Children become top-level departments instead of pointing to a missing parent.
        $wpdb->update(self::table(), ['parent' => 0], ['parent' => $id], ['%d'], ['%d']);
    }

    /** [department_id => ['total' => n, 'open' => n]] */
    public static function ticket_counts()
    {
        global $wpdb;
        $rows = $wpdb->get_results('SELECT department_id, COUNT(*) AS total, SUM(CASE WHEN status = \'open\' THEN 1 ELSE 0 END) AS open_count FROM ' . Tickets::table() . ' WHERE status != \'trash\' GROUP BY department_id');
        $counts = [];
        foreach ($rows as $row) {
            $counts[(int) $row->department_id] = ['total' => (int) $row->total, 'open' => (int) $row->open_count];
        }
        return $counts;
    }
}
