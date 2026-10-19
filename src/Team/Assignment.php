<?php

namespace Ticketchi\Team;

use Ticketchi\Domain\Tickets;
use Ticketchi\Support\Settings;

defined('ABSPATH') || exit('NO Access');

/**
 * Auto-assignment of new customer tickets to the agents of their department.
 *
 * Modes (setting «assign_mode»): manual | round_robin | least_loaded.
 * Agents of a parent department also cover its sub-departments.
 */
final class Assignment
{
    public static function register()
    {
        // After SLA stamping (5) and before notifications (20) / automations (30).
        add_action('ticketchi/ticket_created', [__CLASS__, 'on_created'], 10, 3);
    }

    public static function mode()
    {
        $mode = Settings::get('assign_mode', 'manual');
        return in_array($mode, ['manual', 'round_robin', 'least_loaded'], true) ? $mode : 'manual';
    }

    public static function on_created($ticket_id, $actor_id, $source)
    {
        $ticket = Tickets::find($ticket_id);
        if (!$ticket || $ticket->assignee_id || (int) $ticket->from_admin === 1 || self::mode() === 'manual') {
            return;
        }

        $agent = self::pick($ticket->department_id);
        if ($agent) {
            Tickets::update($ticket_id, ['assignee_id' => $agent], 0);
        }
    }

    /** All users who can work tickets (department members + users with the agent capability). */
    public static function agents()
    {
        global $wpdb;
        $ids = array_map('intval', $wpdb->get_col("SELECT DISTINCT user_id FROM {$wpdb->prefix}tkm_users"));
        $role_users = get_users(['role__in' => ['tkm_agent', 'administrator', 'shop_manager'], 'fields' => 'ID', 'number' => 200]);
        $ids = array_values(array_unique(array_merge($ids, array_map('intval', $role_users))));

        $agents = [];
        foreach ($ids as $id) {
            $user = get_userdata($id);
            if ($user && user_can($user, 'tkm_view')) {
                $agents[] = $user;
            }
        }
        usort($agents, function ($a, $b) {
            return strcmp($a->display_name, $b->display_name);
        });
        return $agents;
    }

    /** Agents responsible for a department (or its parent). */
    public static function department_agents($department_id)
    {
        global $wpdb;
        $department = (new \TKM_Front_Department_Manager())->get_department((int) $department_id);
        if (!$department) {
            return [];
        }
        $ids = [(int) $department->ID];
        if ((int) $department->parent) {
            $ids[] = (int) $department->parent;
        }
        $users = $wpdb->get_col('SELECT DISTINCT user_id FROM ' . $wpdb->prefix . 'tkm_users WHERE department_id IN (' . implode(',', $ids) . ') ORDER BY user_id');
        return array_values(array_filter(array_map('intval', $users), function ($id) {
            return get_userdata($id) !== false;
        }));
    }

    public static function pick($department_id)
    {
        $candidates = self::department_agents($department_id);
        if (!$candidates) {
            return 0;
        }

        if (self::mode() === 'least_loaded') {
            global $wpdb;
            $loads = $wpdb->get_results('SELECT assignee_id, COUNT(*) AS c FROM ' . Tickets::table() . " WHERE status NOT IN ('closed','finish','trash') AND assignee_id IN (" . implode(',', $candidates) . ') GROUP BY assignee_id', OBJECT_K);
            usort($candidates, function ($a, $b) use ($loads) {
                $la = isset($loads[$a]) ? (int) $loads[$a]->c : 0;
                $lb = isset($loads[$b]) ? (int) $loads[$b]->c : 0;
                return $la === $lb ? $a - $b : $la - $lb;
            });
            return $candidates[0];
        }

        // Round robin, remembered per department.
        $pointers = get_option('tkm_rr_pointers', []);
        $pointers = is_array($pointers) ? $pointers : [];
        $last = isset($pointers[$department_id]) ? (int) $pointers[$department_id] : 0;
        $next = $candidates[0];
        foreach ($candidates as $candidate) {
            if ($candidate > $last) {
                $next = $candidate;
                break;
            }
        }
        $pointers[$department_id] = $next;
        update_option('tkm_rr_pointers', $pointers, false);
        return $next;
    }

    /** Open workload per agent: [user_id => count]. */
    public static function workload()
    {
        global $wpdb;
        $rows = $wpdb->get_results('SELECT assignee_id, COUNT(*) AS c FROM ' . Tickets::table() . " WHERE status NOT IN ('closed','finish','trash') AND assignee_id IS NOT NULL GROUP BY assignee_id");
        $map = [];
        foreach ($rows as $row) {
            $map[(int) $row->assignee_id] = (int) $row->c;
        }
        return $map;
    }
}
