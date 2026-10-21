<?php

namespace TicketchiLite;

use WP_REST_Request;
use WP_REST_Response;

defined('ABSPATH') || exit;

/**
 * ticketchi-lite/v1 — the API behind the support inbox and the settings app.
 * Every route requires a logged-in administrator / shop manager and the
 * wp_rest nonce.
 */
final class Rest
{
    const NS = 'ticketchi-lite/v1';

    public static function register()
    {
        add_action('rest_api_init', [__CLASS__, 'routes']);
    }

    public static function allowed()
    {
        return ticketchi_lite_is_staff();
    }

    public static function routes()
    {
        $can = [__CLASS__, 'allowed'];
        $id = ['id' => ['validate_callback' => function ($v) {
            return is_numeric($v);
        }]];

        register_rest_route(self::NS, '/meta', ['methods' => 'GET', 'callback' => [__CLASS__, 'meta'], 'permission_callback' => $can]);
        register_rest_route(self::NS, '/inbox', ['methods' => 'GET', 'callback' => [__CLASS__, 'inbox'], 'permission_callback' => $can]);
        register_rest_route(self::NS, '/tickets/(?P<id>\d+)', [
            ['methods' => 'GET', 'callback' => [__CLASS__, 'ticket'], 'permission_callback' => $can, 'args' => $id],
            ['methods' => 'PATCH', 'callback' => [__CLASS__, 'update'], 'permission_callback' => $can, 'args' => $id],
            ['methods' => 'DELETE', 'callback' => [__CLASS__, 'destroy'], 'permission_callback' => $can, 'args' => $id],
        ]);
        register_rest_route(self::NS, '/tickets/(?P<id>\d+)/replies', ['methods' => 'POST', 'callback' => [__CLASS__, 'reply'], 'permission_callback' => $can, 'args' => $id]);
        register_rest_route(self::NS, '/settings', [
            ['methods' => 'GET', 'callback' => [__CLASS__, 'settings'], 'permission_callback' => $can],
            ['methods' => 'POST', 'callback' => [__CLASS__, 'settings_save'], 'permission_callback' => $can],
        ]);
    }

    private static function fail($message, $status = 400)
    {
        return new WP_REST_Response(['message' => $message], $status);
    }

    public static function meta()
    {
        $statuses = array_map(function ($s) {
            return Presenter::status($s['slug']);
        }, Statuses::all());

        return [
            'me'          => Presenter::user(get_current_user_id()),
            'statuses'    => $statuses,
            'departments' => Departments::tree(),
            'upload'      => [
                'max_size' => Uploads::max_size_bytes(),
                'accept'   => Uploads::accept_attr(),
                'hint'     => Uploads::hint(),
            ],
            'counts'      => Query::view_counts(),
        ];
    }

    public static function inbox(WP_REST_Request $r)
    {
        $args = [
            'view'       => sanitize_key((string) $r->get_param('view')) ?: 'active',
            'department' => absint($r->get_param('department')),
            'priority'   => sanitize_key((string) $r->get_param('priority')),
            'search'     => sanitize_text_field((string) $r->get_param('search')),
            'sort'       => sanitize_key((string) $r->get_param('sort')) ?: 'updated',
            'page'       => absint($r->get_param('page')) ?: 1,
            'per_page'   => absint($r->get_param('per_page')) ?: 30,
        ];

        $result = Query::run($args);
        $summaries = Query::summaries(wp_list_pluck($result['items'], 'ID'));

        $items = [];
        foreach ($result['items'] as $ticket) {
            $items[] = Presenter::summary($ticket, $summaries[(int) $ticket->ID] ?? []);
        }

        return [
            'items'  => $items,
            'total'  => $result['total'],
            'page'   => $args['page'],
            'pages'  => (int) ceil($result['total'] / max(1, $args['per_page'])),
            'counts' => Query::view_counts(),
        ];
    }

    public static function ticket(WP_REST_Request $r)
    {
        $ticket = Tickets::find((int) $r['id']);
        return $ticket ? Presenter::detail($ticket) : self::fail('تیکت پیدا نشد.', 404);
    }

    public static function update(WP_REST_Request $r)
    {
        $ticket = Tickets::find((int) $r['id']);
        if (!$ticket) {
            return self::fail('تیکت پیدا نشد.', 404);
        }

        $params = $r->get_json_params() ?: $r->get_body_params();
        $changes = array_intersect_key((array) $params, array_flip(['status', 'priority', 'department_id']));
        $changes = array_map(function ($v) {
            return is_scalar($v) ? sanitize_key((string) $v) : '';
        }, $changes);

        $result = Tickets::update($ticket->ID, $changes, get_current_user_id());
        if (is_wp_error($result)) {
            return self::fail($result->get_error_message());
        }
        return Presenter::detail(Tickets::find($ticket->ID));
    }

    public static function reply(WP_REST_Request $r)
    {
        $ticket = Tickets::find((int) $r['id']);
        if (!$ticket) {
            return self::fail('تیکت پیدا نشد.', 404);
        }

        $body = (string) $r->get_param('body');
        // Plain text from the composer → safe HTML paragraphs with clickable links.
        if ($body !== '' && $body === wp_strip_all_tags($body)) {
            $body = make_clickable(wpautop(esc_html($body)));
        }

        $files = $r->get_file_params();
        $file = Uploads::handle(isset($files['file']) ? $files['file'] : null);
        if (is_wp_error($file)) {
            return self::fail($file->get_error_message());
        }

        $data = ['body' => $body, 'file' => $file];
        if (filter_var($r->get_param('close'), FILTER_VALIDATE_BOOLEAN)) {
            $data['status'] = 'closed';
        }

        $result = Tickets::reply($ticket->ID, $data, get_current_user_id());
        if (is_wp_error($result)) {
            return self::fail($result->get_error_message());
        }
        return Presenter::detail(Tickets::find($ticket->ID));
    }

    public static function destroy(WP_REST_Request $r)
    {
        if (!Tickets::find((int) $r['id'])) {
            return self::fail('تیکت پیدا نشد.', 404);
        }
        Tickets::delete((int) $r['id']);
        return ['deleted' => true];
    }

    public static function settings()
    {
        return ['sections' => Settings::sections(), 'values' => Settings::values()];
    }

    public static function settings_save(WP_REST_Request $r)
    {
        $params = $r->get_json_params();
        $values = isset($params['values']) && is_array($params['values']) ? $params['values'] : [];
        $result = Settings::save($values);
        if (is_wp_error($result)) {
            $data = $result->get_error_data();
            return new WP_REST_Response(['message' => $result->get_error_message(), 'fields' => isset($data['fields']) ? $data['fields'] : []], 400);
        }
        return ['values' => $result];
    }
}
