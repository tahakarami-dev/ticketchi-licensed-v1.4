<?php

namespace Ticketchi\Automation;

use Ticketchi\Domain\Activity;
use Ticketchi\Domain\Tags;
use Ticketchi\Domain\Tickets;
use Ticketchi\Notifications\Notifier;
use Ticketchi\Support\Clock;
use Ticketchi\Team\Assignment;

defined('ABSPATH') || exit('NO Access');

/**
 * «If this, then that» rules.
 *
 * Triggers:   ticket_created | customer_replied | staff_replied | status_changed
 *             | waiting_staff (time) | waiting_customer (time)
 * Conditions: department, priority, status, tag, keyword, assignee, has_order
 * Actions:    assign, priority, status, tag, note, reply, notify
 */
final class Engine
{
    const CRON = 'tkm_automation_tick';
    const TRIGGERS = ['ticket_created', 'customer_replied', 'staff_replied', 'status_changed', 'waiting_staff', 'waiting_customer'];

    private static $running = false;

    public static function register()
    {
        add_action('ticketchi/ticket_created', function ($id) {
            self::fire('ticket_created', $id);
        }, 30);
        add_action('ticketchi/reply_added', function ($id, $reply_id, $actor, $staff, $internal) {
            if (!$internal) {
                self::fire($staff ? 'staff_replied' : 'customer_replied', $id);
            }
        }, 30, 5);
        add_action('ticketchi/ticket_updated', function ($id, $changes) {
            if (isset($changes['status'])) {
                self::fire('status_changed', $id);
            }
        }, 30, 2);

        add_action(self::CRON, [__CLASS__, 'tick']);
        if (!wp_next_scheduled(self::CRON)) {
            wp_schedule_event(time() + 600, 'hourly', self::CRON);
        }
    }

    /* ───────────── Storage ───────────── */

    public static function table()
    {
        global $wpdb;
        return $wpdb->prefix . 'tkm_automations';
    }

    public static function all()
    {
        global $wpdb;
        $rows = $wpdb->get_results('SELECT * FROM ' . self::table() . ' ORDER BY position, ID');
        return array_map([__CLASS__, 'hydrate'], $rows);
    }

    public static function find($id)
    {
        global $wpdb;
        $row = $wpdb->get_row($wpdb->prepare('SELECT * FROM ' . self::table() . ' WHERE ID = %d', (int) $id));
        return $row ? self::hydrate($row) : null;
    }

    private static function hydrate($row)
    {
        $row->conditions = $row->conditions ? (json_decode($row->conditions, true) ?: []) : [];
        $row->actions = $row->actions ? (json_decode($row->actions, true) ?: []) : [];
        $row->is_active = (int) $row->is_active === 1;
        return $row;
    }

    /** @return int|\WP_Error */
    public static function save(array $data, $id = 0)
    {
        global $wpdb;

        $name = trim(sanitize_text_field($data['name'] ?? ''));
        $trigger = $data['trigger'] ?? '';
        if ($name === '' || !in_array($trigger, self::TRIGGERS, true)) {
            return new \WP_Error('tkm_rule', 'نام و ماشه قانون الزامی است.');
        }

        $actions = self::sanitize_actions($data['actions'] ?? []);
        if (!$actions) {
            return new \WP_Error('tkm_rule', 'حداقل یک کار برای قانون تعریف کنید.');
        }

        $row = [
            'name'          => mb_substr($name, 0, 128),
            'trigger_event' => $trigger,
            'conditions'    => wp_json_encode(self::sanitize_conditions($data['conditions'] ?? [], $trigger)),
            'actions'       => wp_json_encode($actions),
            'is_active'     => !empty($data['is_active']) ? 1 : 0,
        ];

        if ($id) {
            $wpdb->update(self::table(), $row, ['ID' => (int) $id]);
            return (int) $id;
        }

        $row['created_at'] = Clock::now();
        $row['position'] = (int) $wpdb->get_var('SELECT COALESCE(MAX(position), 0) + 1 FROM ' . self::table());
        $wpdb->insert(self::table(), $row);
        return (int) $wpdb->insert_id;
    }

    public static function delete($id)
    {
        global $wpdb;
        return $wpdb->delete(self::table(), ['ID' => (int) $id], ['%d']);
    }

    private static function sanitize_conditions($conditions, $trigger)
    {
        $out = [];
        foreach ((array) $conditions as $c) {
            $field = sanitize_key($c['field'] ?? '');
            $value = $c['value'] ?? '';
            switch ($field) {
                case 'department':
                case 'tag':
                    $value = array_values(array_filter(array_map('intval', (array) $value)));
                    break;
                case 'priority':
                    $value = array_values(array_intersect((array) $value, ['low', 'medium', 'high']));
                    break;
                case 'status':
                    $value = array_values(array_map('sanitize_key', (array) $value));
                    break;
                case 'keyword':
                    $value = trim(sanitize_text_field(is_array($value) ? implode('،', $value) : $value));
                    break;
                case 'assignee':
                    $value = in_array($value, ['none', 'any'], true) ? $value : 'none';
                    break;
                case 'has_order':
                    $value = !empty($value) && $value !== 'no' ? 'yes' : 'no';
                    break;
                case 'hours':
                    $value = max(1, min(720, (int) $value));
                    break;
                default:
                    continue 2;
            }
            if ($value === [] || $value === '') {
                continue;
            }
            $out[] = ['field' => $field, 'value' => $value];
        }

        // Time-based triggers always need a duration.
        if (in_array($trigger, ['waiting_staff', 'waiting_customer'], true) && !in_array('hours', array_column($out, 'field'), true)) {
            $out[] = ['field' => 'hours', 'value' => 24];
        }
        return $out;
    }

    private static function sanitize_actions($actions)
    {
        $out = [];
        foreach ((array) $actions as $a) {
            $type = sanitize_key($a['type'] ?? '');
            $value = $a['value'] ?? '';
            switch ($type) {
                case 'assign':
                    $value = in_array($value, ['round_robin', 'least_loaded'], true) ? $value : (int) $value;
                    break;
                case 'priority':
                    $value = in_array($value, ['low', 'medium', 'high'], true) ? $value : '';
                    break;
                case 'status':
                    $value = sanitize_key($value);
                    break;
                case 'tag':
                case 'notify':
                    $value = (int) $value;
                    break;
                case 'note':
                case 'reply':
                    $value = sanitize_textarea_field($value);
                    break;
                default:
                    continue 2;
            }
            if ($value === '' || $value === 0) {
                continue;
            }
            $out[] = ['type' => $type, 'value' => $value];
        }
        return $out;
    }

    /* ───────────── Execution ───────────── */

    public static function fire($trigger, $ticket_id)
    {
        // Actions emit events of their own; never let rules trigger rules.
        if (self::$running) {
            return;
        }

        $ticket = Tickets::find($ticket_id);
        if (!$ticket || $ticket->status === 'trash') {
            return;
        }

        foreach (self::all() as $rule) {
            if ($rule->is_active && $rule->trigger_event === $trigger && self::matches($rule, $ticket)) {
                self::apply($rule, $ticket);
                $ticket = Tickets::find($ticket_id);
            }
        }
    }

    public static function matches($rule, $ticket)
    {
        foreach ($rule->conditions as $c) {
            $v = $c['value'];
            switch ($c['field']) {
                case 'department':
                    $dep = (new \TKM_Front_Department_Manager())->get_department($ticket->department_id);
                    $ids = [(int) $ticket->department_id, $dep ? (int) $dep->parent : 0];
                    if (!array_intersect($ids, $v)) {
                        return false;
                    }
                    break;
                case 'priority':
                    if (!in_array($ticket->priority, $v, true)) {
                        return false;
                    }
                    break;
                case 'status':
                    if (!in_array($ticket->status, $v, true)) {
                        return false;
                    }
                    break;
                case 'tag':
                    if (!array_intersect(Tags::ids_for_ticket($ticket->ID), $v)) {
                        return false;
                    }
                    break;
                case 'keyword':
                    $haystack = mb_strtolower($ticket->title . ' ' . wp_strip_all_tags($ticket->body));
                    $found = false;
                    foreach (preg_split('/[,،]+/u', $v) as $word) {
                        $word = trim(mb_strtolower($word));
                        if ($word !== '' && mb_strpos($haystack, $word) !== false) {
                            $found = true;
                            break;
                        }
                    }
                    if (!$found) {
                        return false;
                    }
                    break;
                case 'assignee':
                    if (($v === 'none') !== empty($ticket->assignee_id)) {
                        return false;
                    }
                    break;
                case 'has_order':
                    $has = function_exists('wc_get_customer_order_count') && wc_get_customer_order_count(Tickets::customer_id($ticket)) > 0;
                    if (($v === 'yes') !== $has) {
                        return false;
                    }
                    break;
            }
        }
        return true;
    }

    public static function apply($rule, $ticket)
    {
        global $wpdb;
        self::$running = true;

        try {
            $changes = [];
            foreach ($rule->actions as $a) {
                switch ($a['type']) {
                    case 'assign':
                        $agent = is_numeric($a['value']) ? (int) $a['value'] : self::pick_agent($ticket, $a['value']);
                        if ($agent) {
                            $changes['assignee_id'] = $agent;
                        }
                        break;
                    case 'priority':
                    case 'status':
                        $changes[$a['type']] = $a['value'];
                        break;
                    case 'tag':
                        if (Tags::add($ticket->ID, $a['value'])) {
                            Activity::log($ticket->ID, 'tags', ['added' => (int) $a['value']]);
                        }
                        break;
                    case 'note':
                    case 'reply':
                        Tickets::reply($ticket->ID, [
                            'body'     => wpautop(esc_html(self::render($a['value'], $ticket))),
                            'internal' => $a['type'] === 'note',
                            'staff'    => true,
                        ], self::bot_user());
                        break;
                    case 'notify':
                        Notifier::push($a['value'], $ticket->ID, 'automation', 'قانون «' . $rule->name . '»: ' . tkm_ticket_number($ticket->ID) . ' — ' . $ticket->title);
                        break;
                }
            }

            if ($changes) {
                Tickets::update($ticket->ID, $changes, 0);
            }

            Activity::log($ticket->ID, 'automation', ['rule' => (int) $rule->ID, 'name' => $rule->name]);
            $wpdb->query($wpdb->prepare('UPDATE ' . self::table() . ' SET run_count = run_count + 1 WHERE ID = %d', (int) $rule->ID));
        } finally {
            self::$running = false;
        }
    }

    private static function pick_agent($ticket, $mode)
    {
        $agents = Assignment::department_agents($ticket->department_id);
        if (!$agents) {
            return 0;
        }
        if ($mode === 'least_loaded') {
            $load = Assignment::workload();
            usort($agents, function ($a, $b) use ($load) {
                return ($load[$a] ?? 0) - ($load[$b] ?? 0);
            });
            return $agents[0];
        }
        return Assignment::pick($ticket->department_id);
    }

    /** Placeholders: {customer} {ticket_id} {title} {site} */
    private static function render($text, $ticket)
    {
        return strtr($text, [
            '{customer}'  => tkm_user_name(Tickets::customer_id($ticket)),
            '{ticket_id}' => tkm_ticket_number($ticket->ID),
            '{title}'     => $ticket->title,
            '{site}'      => get_bloginfo('name'),
        ]);
    }

    /** Messages written by rules are authored by the first administrator. */
    private static function bot_user()
    {
        $admins = get_users(['role' => 'administrator', 'number' => 1, 'fields' => 'ID', 'orderby' => 'ID']);
        return $admins ? (int) $admins[0] : 0;
    }

    /** Cron: time-based rules, at most once per ticket per waiting period. */
    public static function tick()
    {
        global $wpdb;

        foreach (self::all() as $rule) {
            if (!$rule->is_active || !in_array($rule->trigger_event, ['waiting_staff', 'waiting_customer'], true)) {
                continue;
            }

            $hours = 24;
            foreach ($rule->conditions as $c) {
                if ($c['field'] === 'hours') {
                    $hours = (int) $c['value'];
                }
            }

            $status = $rule->trigger_event === 'waiting_staff' ? 'open' : 'answerd';
            $before = Clock::from_ts(time() - $hours * HOUR_IN_SECONDS);
            $tickets = $wpdb->get_results($wpdb->prepare(
                'SELECT * FROM ' . Tickets::table() . ' WHERE status = %s AND reply_date < %s ORDER BY reply_date ASC LIMIT 200',
                $status,
                $before
            ));

            foreach ($tickets as $ticket) {
                // Already handled since the last message?
                $done = $wpdb->get_var($wpdb->prepare(
                    'SELECT ID FROM ' . Activity::table() . " WHERE ticket_id = %d AND type = 'automation' AND data LIKE %s AND created_at >= %s LIMIT 1",
                    (int) $ticket->ID,
                    '%"rule":' . (int) $rule->ID . ',%',
                    $ticket->reply_date
                ));
                if (!$done && self::matches($rule, $ticket)) {
                    self::apply($rule, $ticket);
                }
            }
        }
    }
}
