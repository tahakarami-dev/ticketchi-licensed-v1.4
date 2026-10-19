<?php

defined('ABSPATH') || exit('NO Access');

if (! class_exists('WP_List_Table')) {
    require_once(ABSPATH . 'wp-admin/includes/class-wp-list-table.php');
}


class TKM_Ticket_List extends WP_List_Table
{

    private $wpdb;
    private $table;
    private $statues;
    private $processed = false;
    private $total_items = 0;

    public function __construct()
    {

        parent::__construct([
            'singular' => 'ticket',
            'plural' => 'tickets',
            'ajax' => false,
        ]);

        global $wpdb;
        $this->wpdb = $wpdb;
        $this->table = $wpdb->prefix . 'tkm_tickets';

        $this->statues = tkm_get_status();
    }

    public function get_columns()
    {
        return [
            'cb'            => '<input type="checkbox" />',
            'title'         => 'تیکت',
            'creator_id'    => 'کاربر',
            'department_id' => 'دپارتمان',
            'status'        => 'وضعیت',
            'priority'      => 'اولویت',
            'reply_date'    => 'آخرین فعالیت',
            'create_date'   => 'ایجاد',
        ];
    }

    /**
     * WHERE clause + args built from the current filters.
     */
    private function where()
    {
        $sql = ' WHERE 1=1';
        $args = [];

        if (!empty($_GET['department_id'])) {
            $sql .= ' AND department_id = %d';
            $args[] = absint($_GET['department_id']);
        }
        if (!empty($_GET['priority'])) {
            $sql .= ' AND priority = %s';
            $args[] = sanitize_key(wp_unslash($_GET['priority']));
        }
        if (!empty($_GET['creator_id'])) {
            $sql .= ' AND (creator_id = %d OR user_id = %d)';
            $args[] = absint($_GET['creator_id']);
            $args[] = absint($_GET['creator_id']);
        }
        if (isset($_GET['search']) && trim(wp_unslash($_GET['search'])) !== '') {
            $search = trim(sanitize_text_field(wp_unslash($_GET['search'])));
            $like = '%' . $this->wpdb->esc_like($search) . '%';
            $sql .= ' AND (title LIKE %s OR body LIKE %s' . (ctype_digit(ltrim($search, '#')) ? ' OR ID = %d' : '') . ')';
            $args[] = $like;
            $args[] = $like;
            if (ctype_digit(ltrim($search, '#'))) {
                $args[] = (int) ltrim($search, '#');
            }
        }

        $status = isset($_GET['status']) ? sanitize_key(wp_unslash($_GET['status'])) : '';
        if ($status !== '') {
            $sql .= ' AND status = %s';
            $args[] = $status;
        } else {
            // Trash is a separate view, like WordPress posts.
            $sql .= " AND status != 'trash'";
        }

        return [$sql, $args];
    }

    private function prepare_sql($sql, $args)
    {
        return $args ? $this->wpdb->prepare($sql, $args) : $sql;
    }

    public function get_tickets($per_page = 0, $offset = 0)
    {
        list($where, $args) = $this->where();

        $orderby = isset($_GET['orderby']) && in_array($_GET['orderby'], ['create_date', 'reply_date', 'priority', 'ID'], true) ? $_GET['orderby'] : 'reply_date';
        $order = isset($_GET['order']) && strtolower($_GET['order']) === 'asc' ? 'ASC' : 'DESC';

        if ($orderby === 'priority') {
            $orderby = "CASE priority WHEN 'high' THEN 3 WHEN 'medium' THEN 2 ELSE 1 END";
        }

        $sql = 'SELECT * FROM ' . $this->table . $where . ' ORDER BY ' . $orderby . ' ' . $order . ', ID DESC';
        if ($per_page) {
            $sql .= ' LIMIT %d OFFSET %d';
            $args[] = (int) $per_page;
            $args[] = (int) $offset;
        }

        return $this->wpdb->get_results($this->prepare_sql($sql, $args), ARRAY_A);
    }

    public function ticket_count()
    {
        list($where, $args) = $this->where();
        return (int) $this->wpdb->get_var($this->prepare_sql('SELECT COUNT(*) FROM ' . $this->table . $where, $args));
    }

    /**
     * Counts per status for the view tabs.
     */
    public function status_counts()
    {
        $rows = $this->wpdb->get_results('SELECT status, COUNT(*) AS total FROM ' . $this->table . ' GROUP BY status');
        $counts = [];
        foreach ($rows as $row) {
            $counts[$row->status] = (int) $row->total;
        }
        return $counts;
    }

    public function prepare_items()
    {
        $this->process_actions();

        $per_page = $this->get_items_per_page('ticket_per_page', 20);
        $current_page = $this->get_pagenum();

        $this->total_items = $this->ticket_count();
        $this->items = $this->get_tickets($per_page, ($current_page - 1) * $per_page);

        $this->_column_headers = [$this->get_columns(), [], $this->get_sortable_columns(), 'title'];

        $this->set_pagination_args([
            'total_items' => $this->total_items,
            'per_page'    => $per_page,
        ]);
    }

    public function get_total_items()
    {
        return $this->total_items;
    }

    /**
     * Trash / delete / bulk actions. Runs on the load-{page} hook when possible so
     * it can redirect; falls back to running during render.
     */
    public function process_actions()
    {
        if ($this->processed) {
            return;
        }
        $this->processed = true;

        $done = $this->trash_action() || $this->delete_action() || $this->bulk_action();

        if ($done && !headers_sent()) {
            wp_safe_redirect(remove_query_arg(['action', 'action2', 'id', '_wpnonce', '_wp_http_referer'], wp_unslash($_SERVER['REQUEST_URI'])));
            exit;
        }
    }

    public function bulk_action()
    {
        $action = $this->current_action();
        $ids = isset($_POST['id']) ? array_filter(array_map('absint', (array) $_POST['id'])) : [];

        if (!$action || strpos($action, 'bulk-') !== 0 || !$ids) {
            return false;
        }

        check_admin_referer('bulk-' . $this->_args['plural']);

        $action = substr($action, 5);
        $valid = wp_list_pluck($this->statues, 'slug');
        $valid[] = 'trash';

        foreach ($ids as $id) {
            if ($action === 'delete') {
                $this->delete_ticket($id);
                (new TKM_Reply_Manager($id))->delete_replies();
            } elseif (in_array($action, $valid, true)) {
                $this->update_ticket_status($id, $action);
            }
        }

        TKM_Flash_Message::add_message(tkm_fa(count($ids)) . ' تیکت به‌روزرسانی شد.');
        return true;
    }

    public function trash_action()
    {
        if (!isset($_GET['action'], $_GET['id'], $_GET['_wpnonce']) || $_GET['action'] !== 'trash') {
            return false;
        }

        if (!wp_verify_nonce($_GET['_wpnonce'], 'tkm_trash_ticket')) {
            wp_die('نانس شما تایید نشد ');
        }

        $this->update_ticket_status(absint($_GET['id']), 'trash');
        TKM_Flash_Message::add_message('تیکت به زباله‌دان منتقل شد.', TKM_Flash_Message::INFO);
        return true;
    }

    public function delete_action()
    {
        if (!isset($_GET['action'], $_GET['id'], $_GET['_wpnonce']) || $_GET['action'] !== 'delete') {
            return false;
        }

        if (!wp_verify_nonce($_GET['_wpnonce'], 'tkm_delete_ticket')) {
            wp_die('نانس شما تایید نشد ');
        }

        $id = absint($_GET['id']);
        $this->delete_ticket($id);
        (new TKM_Reply_Manager($id))->delete_replies();
        TKM_Flash_Message::add_message('تیکت برای همیشه حذف شد.', TKM_Flash_Message::INFO);
        return true;
    }

    public function delete_ticket($id)
    {
        $this->wpdb->delete($this->table, ['ID' => $id], ['%d']);
        $this->wpdb->delete($this->wpdb->prefix . 'tkm_ratings', ['ticket_id' => $id], ['%d']);
    }

    public function update_ticket_status($id, $status)
    {
        return $this->wpdb->update($this->table, ['status' => $status], ['ID' => $id], ['%s'], ['%d']);
    }

    public function column_creator_id($item)
    {
        $person = (int) $item['from_admin'] === 1 && $item['user_id'] ? (int) $item['user_id'] : (int) $item['creator_id'];

        return '<a class="tkm-user-cell" href="' . esc_url(tkm_admin_url('tkm-tickets', ['creator_id' => $person])) . '" title="فیلتر تیکت‌های این کاربر">'
            . tkm_avatar($person, 'sm')
            . '<span class="tkm-user-cell__name">' . esc_html(tkm_user_name($person)) . '</span></a>';
    }

    public function column_title($item)
    {
        $edit = tkm_admin_url('tkm-edit-ticket', ['id' => absint($item['ID'])]);
        $incoming = (int) $item['from_admin'] === 1;

        $title = '<div class="tkm-title-cell">'
            . '<a class="tkm-title-cell__title row-title" href="' . esc_url($edit) . '">' . esc_html($item['title']) . '</a>'
            . '<span class="tkm-title-cell__meta"><span class="tkm-num">' . esc_html(tkm_ticket_number($item['ID'])) . '</span>'
            . ($incoming ? '<span class="tkm-tag">' . tkm_icon('send', 11) . ' ارسالی پشتیبانی</span>' : '')
            . ($item['note'] ? '<span class="tkm-tag tkm-tag--note" title="' . esc_attr($item['note']) . '">' . tkm_icon('note', 11) . ' یادداشت</span>' : '')
            . '</span></div>';

        $actions = [
            'edit' => '<a href="' . esc_url($edit) . '">مشاهده و پاسخ</a>',
        ];

        if (isset($_GET['status']) && $_GET['status'] === 'trash') {
            $actions['delete'] = '<a class="tkm-danger-link" data-confirm="این تیکت برای همیشه حذف می‌شود. ادامه می‌دهید؟" href="' . esc_url(wp_nonce_url(tkm_admin_url('tkm-tickets', ['action' => 'delete', 'id' => absint($item['ID']), 'status' => 'trash']), 'tkm_delete_ticket')) . '">حذف همیشگی</a>';
        } else {
            $actions['trash'] = '<a class="tkm-danger-link" href="' . esc_url(wp_nonce_url(tkm_admin_url('tkm-tickets', ['action' => 'trash', 'id' => absint($item['ID'])]), 'tkm_trash_ticket')) . '">زباله‌دان</a>';
        }

        return $title . $this->row_actions($actions);
    }

    public function column_cb($item)
    {
        return sprintf('<input type="checkbox" name="id[]" value="%d" aria-label="انتخاب تیکت %d">', absint($item['ID']), absint($item['ID']));
    }

    public function column_default($item, $column_name)
    {
        switch ($column_name) {
            case 'department_id':
                $department = (new TKM_Front_Department_Manager())->get_department($item['department_id']);
                return '<a class="tkm-muted-link" href="' . esc_url(tkm_admin_url('tkm-tickets', ['department_id' => absint($item['department_id'])])) . '">' . esc_html($department ? $department->name : '—') . '</a>';

            case 'status':
                return '<a href="' . esc_url(tkm_admin_url('tkm-tickets', ['status' => $item['status']])) . '">' . tkm_status_badge($item['status']) . '</a>';

            case 'priority':
                return '<a href="' . esc_url(tkm_admin_url('tkm-tickets', ['priority' => $item['priority']])) . '">' . tkm_priority_badge($item['priority']) . '</a>';

            case 'create_date':
            case 'reply_date':
                return '<span class="tkm-date-cell" title="' . esc_attr(tkm_date($item[$column_name])) . '">' . esc_html(tkm_time_ago($item[$column_name])) . '</span>';

            default:
                return isset($item[$column_name]) ? esc_html($item[$column_name]) : '';
        }
    }

    public function get_sortable_columns()
    {
        return [
            'title'       => ['ID', true],
            'priority'    => ['priority', true],
            'create_date' => ['create_date', true],
            'reply_date'  => ['reply_date', true],
        ];
    }

    public function no_items()
    {
        echo '<div class="tkm-empty"><div class="tkm-empty__art">' . tkm_icon('inbox', 30) . '</div><h3 class="tkm-empty__title">تیکتی پیدا نشد</h3><p class="tkm-empty__text">فیلترها را تغییر دهید یا تیکت جدیدی برای کاربران ارسال کنید.</p></div>';
    }

    public function single_row($item)
    {
        echo '<tr data-href="' . esc_url(tkm_admin_url('tkm-edit-ticket', ['id' => absint($item['ID'])])) . '">';
        $this->single_row_columns($item);
        echo '</tr>';
    }

    // To show bulk action dropdown
    function get_bulk_actions()
    {
        $actions = [];

        foreach ($this->statues as $status) {
            $actions['bulk-' . $status['slug']] = 'تغییر به: ' . trim($status['name']);
        }
        if (isset($_GET['status']) && $_GET['status'] == 'trash') {
            unset($actions['bulk-trash']);
            $actions['bulk-delete'] = 'حذف همیشگی';
        } else {
            $actions['bulk-trash'] = 'انتقال به زباله‌دان';
        }
        return $actions;
    }
}
