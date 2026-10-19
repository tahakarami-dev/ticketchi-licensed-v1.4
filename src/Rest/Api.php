<?php

namespace Ticketchi\Rest;

use Ticketchi\Automation\Engine;
use Ticketchi\Domain\Customers;
use Ticketchi\Domain\Presenter;
use Ticketchi\Domain\Tags;
use Ticketchi\Domain\TicketQuery;
use Ticketchi\Domain\Tickets;
use Ticketchi\Kb\KnowledgeBase;
use Ticketchi\Notifications\Notifier;
use Ticketchi\Reports\Reports;
use Ticketchi\Support\Capabilities;
use Ticketchi\Support\Clock;
use Ticketchi\Team\Assignment;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

defined('ABSPATH') || exit('NO Access');

/**
 * ticketchi/v1 — the API behind the admin inbox, automations and reports.
 */
final class Api
{
    const NS = 'ticketchi/v1';

    public static function register()
    {
        add_action('rest_api_init', [__CLASS__, 'routes']);
    }

    public static function can_view()
    {
        return Capabilities::can_view();
    }

    public static function can_manage()
    {
        return Capabilities::can_manage();
    }

    public static function routes()
    {
        $view = [__CLASS__, 'can_view'];
        $manage = [__CLASS__, 'can_manage'];
        $id = ['id' => ['validate_callback' => function ($v) {
            return is_numeric($v);
        }]];

        register_rest_route(self::NS, '/meta', ['methods' => 'GET', 'callback' => [__CLASS__, 'meta'], 'permission_callback' => $view]);
        register_rest_route(self::NS, '/inbox', ['methods' => 'GET', 'callback' => [__CLASS__, 'inbox'], 'permission_callback' => $view]);
        register_rest_route(self::NS, '/tickets/bulk', ['methods' => 'POST', 'callback' => [__CLASS__, 'bulk'], 'permission_callback' => $view]);
        register_rest_route(self::NS, '/tickets/(?P<id>\d+)', [
            ['methods' => 'GET', 'callback' => [__CLASS__, 'ticket'], 'permission_callback' => $view, 'args' => $id],
            ['methods' => 'PATCH', 'callback' => [__CLASS__, 'update'], 'permission_callback' => $view, 'args' => $id],
            ['methods' => 'DELETE', 'callback' => [__CLASS__, 'destroy'], 'permission_callback' => $manage, 'args' => $id],
        ]);
        register_rest_route(self::NS, '/tickets/(?P<id>\d+)/replies', ['methods' => 'POST', 'callback' => [__CLASS__, 'reply'], 'permission_callback' => $view, 'args' => $id]);
        register_rest_route(self::NS, '/customers/(?P<id>\d+)', ['methods' => 'GET', 'callback' => [__CLASS__, 'customer'], 'permission_callback' => $view, 'args' => $id]);
        register_rest_route(self::NS, '/search', ['methods' => 'GET', 'callback' => [__CLASS__, 'search'], 'permission_callback' => $view]);

        register_rest_route(self::NS, '/tags', [
            ['methods' => 'GET', 'callback' => [__CLASS__, 'tags'], 'permission_callback' => $view],
            ['methods' => 'POST', 'callback' => [__CLASS__, 'tag_create'], 'permission_callback' => $view],
        ]);
        register_rest_route(self::NS, '/tags/(?P<id>\d+)', [
            ['methods' => 'PUT', 'callback' => [__CLASS__, 'tag_update'], 'permission_callback' => $manage, 'args' => $id],
            ['methods' => 'DELETE', 'callback' => [__CLASS__, 'tag_delete'], 'permission_callback' => $manage, 'args' => $id],
        ]);

        register_rest_route(self::NS, '/notifications', ['methods' => 'GET', 'callback' => [__CLASS__, 'notifications'], 'permission_callback' => $view]);
        register_rest_route(self::NS, '/notifications/read', ['methods' => 'POST', 'callback' => [__CLASS__, 'notifications_read'], 'permission_callback' => $view]);

        register_rest_route(self::NS, '/automations', [
            ['methods' => 'GET', 'callback' => [__CLASS__, 'automations'], 'permission_callback' => $manage],
            ['methods' => 'POST', 'callback' => [__CLASS__, 'automation_save'], 'permission_callback' => $manage],
        ]);
        register_rest_route(self::NS, '/automations/(?P<id>\d+)', [
            ['methods' => 'PUT', 'callback' => [__CLASS__, 'automation_save'], 'permission_callback' => $manage, 'args' => $id],
            ['methods' => 'DELETE', 'callback' => [__CLASS__, 'automation_delete'], 'permission_callback' => $manage, 'args' => $id],
        ]);

        register_rest_route(self::NS, '/settings', [
            ['methods' => 'GET', 'callback' => [__CLASS__, 'settings'], 'permission_callback' => $manage],
            ['methods' => 'POST', 'callback' => [__CLASS__, 'settings_save'], 'permission_callback' => $manage],
        ]);

        register_rest_route(self::NS, '/reports', ['methods' => 'GET', 'callback' => [__CLASS__, 'reports'], 'permission_callback' => $manage]);

        register_rest_route(self::NS, '/kb/search', ['methods' => 'GET', 'callback' => [__CLASS__, 'kb_search'], 'permission_callback' => '__return_true']);
    }

    private static function error(WP_Error $e, $status = 400)
    {
        return new WP_REST_Response(['code' => $e->get_error_code(), 'message' => $e->get_error_message()], $status);
    }

    /** @return object|WP_REST_Response */
    private static function load($id)
    {
        $ticket = Tickets::find((int) $id);
        if (!$ticket) {
            return new WP_REST_Response(['message' => 'تیکت پیدا نشد.'], 404);
        }
        if (!Capabilities::can_access_ticket($ticket)) {
            return new WP_REST_Response(['message' => 'این تیکت در دپارتمان‌های شما نیست.'], 403);
        }
        return $ticket;
    }

    /* ───────────── Meta ───────────── */

    public static function meta()
    {
        $manager = new \TKM_Admin_Department_Manager();
        $departments = [];
        foreach ($manager->get_parent_department() as $parent) {
            $children = [];
            foreach ($manager->get_child_department($parent->ID) as $child) {
                $children[] = ['id' => (int) $child->ID, 'name' => $child->name];
            }
            $departments[] = ['id' => (int) $parent->ID, 'name' => $parent->name, 'children' => $children];
        }

        $load = Assignment::workload();
        $agents = array_map(function ($u) use ($load) {
            return array_merge(Presenter::user($u->ID), ['open' => $load[$u->ID] ?? 0]);
        }, Assignment::agents());

        $statuses = [];
        foreach (tkm_get_status() as $s) {
            if ($s['slug'] !== 'trash') {
                $statuses[] = Presenter::status($s['slug']);
            }
        }

        $canned = [];
        $saved = tkm_settings('save_message');
        foreach (is_array($saved) ? $saved : [] as $item) {
            if (!empty($item['message_save'])) {
                $canned[] = ['title' => $item['save_message_title'] ?: wp_trim_words($item['message_save'], 5), 'text' => $item['message_save']];
            }
        }

        $upload = [
            'multiple'  => \TKM_Upload_File::is_multiple(),
            'max_files' => \TKM_Upload_File::max_files(),
            'max_size'  => \TKM_Upload_File::max_size_bytes(),
            'accept'    => tkm_get_accept_attr(),
            'hint'      => tkm_upload_hint_text(),
        ];

        return [
            'me'          => array_merge(Presenter::user(get_current_user_id()), ['manage' => Capabilities::can_manage()]),
            'statuses'    => $statuses,
            'departments' => $departments,
            'agents'      => $agents,
            'tags'        => self::tag_list(),
            'canned'      => $canned,
            'upload'      => $upload,
            'counts'      => TicketQuery::view_counts(get_current_user_id()),
            'unread'      => Notifier::unread_count(get_current_user_id()),
        ];
    }

    private static function tag_list()
    {
        return array_map(function ($t) {
            return ['id' => (int) $t->ID, 'name' => $t->name, 'color' => $t->color, 'count' => (int) $t->usage_count];
        }, Tags::all());
    }

    /* ───────────── Inbox ───────────── */

    public static function inbox(WP_REST_Request $r)
    {
        $args = [
            'view'       => $r->get_param('view') ?: 'active',
            'status'     => $r->get_param('status'),
            'priority'   => $r->get_param('priority'),
            'department' => $r->get_param('department'),
            'assignee'   => $r->get_param('assignee'),
            'tag'        => $r->get_param('tag'),
            'customer'   => $r->get_param('customer'),
            'search'     => $r->get_param('search'),
            'sort'       => $r->get_param('sort') ?: 'updated',
            'page'       => $r->get_param('page') ?: 1,
            'per_page'   => $r->get_param('per_page') ?: 30,
        ];

        $result = TicketQuery::run($args, get_current_user_id());
        $ids = wp_list_pluck($result['items'], 'ID');
        $tags = Tags::for_tickets($ids);
        $summaries = TicketQuery::summaries($ids);

        $items = [];
        foreach ($result['items'] as $ticket) {
            $items[] = Presenter::summary($ticket, $tags[(int) $ticket->ID] ?? [], $summaries[(int) $ticket->ID] ?? []);
        }

        return [
            'items'  => $items,
            'total'  => $result['total'],
            'page'   => (int) $args['page'],
            'pages'  => (int) ceil($result['total'] / max(1, (int) $args['per_page'])),
            'counts' => TicketQuery::view_counts(get_current_user_id()),
            'now'    => gmdate('c'),
        ];
    }

    public static function ticket(WP_REST_Request $r)
    {
        $ticket = self::load($r['id']);
        if ($ticket instanceof WP_REST_Response) {
            return $ticket;
        }
        $detail = Presenter::detail($ticket);
        $detail['customer_profile'] = Customers::profile(Tickets::customer_id($ticket));
        return $detail;
    }

    public static function update(WP_REST_Request $r)
    {
        $ticket = self::load($r['id']);
        if ($ticket instanceof WP_REST_Response) {
            return $ticket;
        }

        $params = $r->get_json_params() ?: $r->get_body_params();
        $changes = array_intersect_key($params, array_flip(['status', 'priority', 'department_id', 'assignee_id', 'title', 'note']));

        if ($changes) {
            $result = Tickets::update($ticket->ID, $changes, get_current_user_id());
            if (is_wp_error($result)) {
                return self::error($result);
            }
        }

        if (array_key_exists('tags', $params)) {
            $ids = [];
            foreach ((array) $params['tags'] as $tag) {
                $ids[] = is_numeric($tag) ? (int) $tag : Tags::find_or_create($tag);
            }
            Tickets::set_tags($ticket->ID, $ids, get_current_user_id());
        }

        return self::ticket($r);
    }

    public static function reply(WP_REST_Request $r)
    {
        $ticket = self::load($r['id']);
        if ($ticket instanceof WP_REST_Response) {
            return $ticket;
        }

        $body = (string) $r->get_param('body');
        // Plain text from the composer → safe HTML paragraphs with clickable links.
        if ($body !== '' && $body === wp_strip_all_tags($body)) {
            $body = make_clickable(wpautop(esc_html($body)));
        }

        $file = null;
        $files = $r->get_file_params();
        if (!empty($files['file'])) {
            $upload = (new \TKM_Upload_File($files['file']))->upload();
            if (!$upload['success']) {
                return new WP_REST_Response(['message' => $upload['message']], 400);
            }
            $file = tkm_files_encode(array_map('esc_url_raw', $upload['urls']));
        }

        $internal = filter_var($r->get_param('internal'), FILTER_VALIDATE_BOOLEAN);
        $data = ['body' => $body, 'file' => $file, 'internal' => $internal, 'staff' => true];
        $close = filter_var($r->get_param('close'), FILTER_VALIDATE_BOOLEAN);
        if ($close && !$internal) {
            $data['status'] = 'closed';
        }

        $result = Tickets::reply($ticket->ID, $data, get_current_user_id());
        if (is_wp_error($result)) {
            return self::error($result);
        }

        return self::ticket($r);
    }

    public static function bulk(WP_REST_Request $r)
    {
        $params = $r->get_json_params() ?: [];
        $ids = array_filter(array_map('intval', (array) ($params['ids'] ?? [])));
        $action = sanitize_key($params['action'] ?? '');
        $value = $params['value'] ?? null;
        $user = get_current_user_id();

        if (!$ids) {
            return new WP_REST_Response(['message' => 'هیچ تیکتی انتخاب نشده است.'], 400);
        }
        if ($action === 'delete' && !Capabilities::can_manage()) {
            return new WP_REST_Response(['message' => 'اجازه حذف دائمی ندارید.'], 403);
        }

        $done = 0;
        foreach ($ids as $id) {
            if (!Capabilities::can_access_ticket(Tickets::find($id))) {
                continue;
            }
            switch ($action) {
                case 'status':
                case 'priority':
                    Tickets::update($id, [$action => $value], $user);
                    break;
                case 'assign':
                    Tickets::update($id, ['assignee_id' => (int) $value], $user);
                    break;
                case 'tag':
                    $tag = is_numeric($value) ? (int) $value : Tags::find_or_create($value);
                    if ($tag) {
                        Tickets::set_tags($id, array_merge(Tags::ids_for_ticket($id), [$tag]), $user);
                    }
                    break;
                case 'trash':
                    Tickets::update($id, ['status' => 'trash'], $user);
                    break;
                case 'restore':
                    Tickets::update($id, ['status' => 'open'], $user);
                    break;
                case 'delete':
                    Tickets::delete($id);
                    break;
                default:
                    return new WP_REST_Response(['message' => 'عملیات نامعتبر است.'], 400);
            }
            $done++;
        }

        return ['done' => $done];
    }

    public static function destroy(WP_REST_Request $r)
    {
        if (!Tickets::find((int) $r['id'])) {
            return new WP_REST_Response(['message' => 'تیکت پیدا نشد.'], 404);
        }
        Tickets::delete((int) $r['id']);
        return ['deleted' => true];
    }

    public static function customer(WP_REST_Request $r)
    {
        return Customers::profile((int) $r['id']);
    }

    /** Command palette: tickets by #id/title + customers. */
    public static function search(WP_REST_Request $r)
    {
        $q = trim((string) $r->get_param('q'));
        if ($q === '') {
            return ['tickets' => [], 'customers' => []];
        }

        $result = TicketQuery::run(['view' => 'all', 'search' => $q, 'per_page' => 8], get_current_user_id());
        $tickets = array_map(function ($t) {
            return ['id' => (int) $t->ID, 'title' => $t->title, 'status' => Presenter::status($t->status), 'customer' => Presenter::user(Tickets::customer_id($t))];
        }, $result['items']);

        $users = get_users(['search' => '*' . $q . '*', 'search_columns' => ['user_login', 'user_email', 'display_name'], 'number' => 5]);
        $customers = array_map(function ($u) {
            return array_merge(Presenter::user($u->ID), ['email' => $u->user_email]);
        }, $users);

        return ['tickets' => $tickets, 'customers' => $customers];
    }

    /* ───────────── Tags ───────────── */

    public static function tags()
    {
        return self::tag_list();
    }

    public static function tag_create(WP_REST_Request $r)
    {
        $id = Tags::find_or_create((string) $r->get_param('name'), $r->get_param('color'));
        return $id ? ['id' => $id, 'tags' => self::tag_list()] : new WP_REST_Response(['message' => 'نام برچسب الزامی است.'], 400);
    }

    public static function tag_update(WP_REST_Request $r)
    {
        Tags::update((int) $r['id'], (string) $r->get_param('name'), (string) $r->get_param('color'));
        return self::tag_list();
    }

    public static function tag_delete(WP_REST_Request $r)
    {
        Tags::delete((int) $r['id']);
        return self::tag_list();
    }

    /* ───────────── Notifications ───────────── */

    public static function notifications()
    {
        $user = get_current_user_id();
        return [
            'unread' => Notifier::unread_count($user),
            'items'  => array_map(function ($n) {
                return [
                    'id'        => (int) $n->ID,
                    'ticket_id' => $n->ticket_id ? (int) $n->ticket_id : null,
                    'type'      => $n->type,
                    'message'   => $n->message,
                    'read'      => (int) $n->is_read === 1,
                    'date'      => Clock::iso($n->created_at),
                ];
            }, Notifier::for_user($user, 25)),
        ];
    }

    public static function notifications_read(WP_REST_Request $r)
    {
        Notifier::mark_read(get_current_user_id(), $r->get_param('id') ? (int) $r->get_param('id') : null);
        return self::notifications();
    }

    /* ───────────── Automations ───────────── */

    public static function automations()
    {
        return array_map(function ($rule) {
            return [
                'id'         => (int) $rule->ID,
                'name'       => $rule->name,
                'trigger'    => $rule->trigger_event,
                'conditions' => $rule->conditions,
                'actions'    => $rule->actions,
                'is_active'  => (int) $rule->is_active === 1,
                'runs'       => (int) $rule->run_count,
            ];
        }, Engine::all());
    }

    public static function automation_save(WP_REST_Request $r)
    {
        $data = $r->get_json_params() ?: [];
        $result = Engine::save($data, (int) ($r['id'] ?? 0));
        if (is_wp_error($result)) {
            return self::error($result);
        }
        return self::automations();
    }

    public static function automation_delete(WP_REST_Request $r)
    {
        Engine::delete((int) $r['id']);
        return self::automations();
    }

    /* ───────────── Settings ───────────── */

    public static function settings()
    {
        $sections = \Ticketchi\Admin\SettingsSchema::sections();
        foreach ($sections as &$section) {
            foreach ($section['groups'] as &$group) {
                foreach ($group['fields'] as &$field) {
                    if (isset($field['options']) && $field['options'] === 'user_meta') {
                        $field['options'] = function_exists('tkm_user_meta_key') ? array_values(tkm_user_meta_key()) : [];
                    }
                }
            }
        }
        unset($section, $group, $field);

        return [
            'sections' => $sections,
            'values'   => \Ticketchi\Admin\SettingsSchema::values(),
            'defaults' => \Ticketchi\Admin\SettingsSchema::defaults(),
        ];
    }

    public static function settings_save(WP_REST_Request $r)
    {
        $values = $r->get_json_params();
        $values = isset($values['values']) && is_array($values['values']) ? $values['values'] : [];
        $result = \Ticketchi\Admin\SettingsSchema::save($values);
        if (is_wp_error($result)) {
            $data = $result->get_error_data();
            return new WP_REST_Response(['message' => $result->get_error_message(), 'fields' => isset($data['fields']) ? $data['fields'] : []], 400);
        }
        return ['values' => $result];
    }

    /* ───────────── Reports & KB ───────────── */

    public static function reports(WP_REST_Request $r)
    {
        return Reports::build((string) $r->get_param('from'), (string) $r->get_param('to'));
    }

    public static function kb_search(WP_REST_Request $r)
    {
        if (!KnowledgeBase::enabled()) {
            return [];
        }
        return KnowledgeBase::search((string) $r->get_param('q'), 5);
    }
}
