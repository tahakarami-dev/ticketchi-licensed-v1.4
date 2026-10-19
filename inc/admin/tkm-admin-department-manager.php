<?php

defined('ABSPATH') || exit('NO Access');

class TKM_Admin_Department_Manager
{
    private $wpdb;
    private $table;
    public function __construct()
    {
        global $wpdb;
        $this->wpdb = $wpdb;
        $this->table = $wpdb->prefix . 'tkm_departments';
    }

    /**
     * Handles add / update / delete before output so we can redirect.
     */
    public static function boot()
    {
        if (!isset($_GET['page']) || $_GET['page'] !== 'tkm-departments') {
            return;
        }

        $self = new self();
        $answerable_manager = new TKM_Answerable_Mnager();
        $list_url = tkm_admin_url('tkm-departments');

        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_department_nonce'])) {
            if (!wp_verify_nonce($_POST['add_department_nonce'], 'add_department') || !current_user_can('manage_options')) {
                wp_die('متاسفیم، نانس شما تایید نشد.', '', ['back_link' => true]);
            }

            $data = wp_unslash($_POST);

            if (trim((string) ($data['name'] ?? '')) === '') {
                tkm_flash('عنوان دپارتمان الزامی است.', 'danger');
                wp_safe_redirect(wp_get_referer() ?: $list_url);
                exit;
            }

            $users = !empty($data['department-answerabel']) ? array_filter(array_map('absint', (array) $data['department-answerabel'])) : [];

            if (!empty($data['department_id'])) {
                $department_id = absint($data['department_id']);
                $result = $self->update($department_id, $data);
                if ($result !== false) {
                    $answerable_manager->delete($department_id);
                    foreach (array_unique($users) as $user) {
                        $answerable_manager->insert(['department_id' => $department_id, 'user_id' => $user]);
                    }
                    tkm_flash('دپارتمان «' . esc_html($data['name']) . '» بروزرسانی شد.');
                } else {
                    tkm_flash('بروزرسانی دپارتمان با خطا مواجه شد.', 'danger');
                }
            } else {
                $insert = $self->insert_department($data);
                if ($insert) {
                    foreach (array_unique($users) as $user) {
                        $answerable_manager->insert(['department_id' => $insert, 'user_id' => $user]);
                    }
                    tkm_flash('دپارتمان «' . esc_html($data['name']) . '» اضافه شد.');
                } else {
                    tkm_flash('خطا در افزودن دپارتمان.', 'danger');
                }
            }

            wp_safe_redirect($list_url);
            exit;
        }

        if (isset($_GET['action'], $_GET['id']) && $_GET['action'] === 'delete') {
            if (!isset($_GET['delete_department_nonce']) || !wp_verify_nonce($_GET['delete_department_nonce'], 'delete_department') || !current_user_can('manage_options')) {
                wp_die('متاسفانه نانس شما تایید نشد.', '', ['back_link' => true]);
            }

            $id = absint($_GET['id']);
            $self->delete($id);
            $answerable_manager->delete($id);
            // Children become top-level departments instead of pointing to a missing parent.
            $self->wpdb->update($self->table, ['parent' => 0], ['parent' => $id], ['%d'], ['%d']);
            tkm_flash('دپارتمان حذف شد.', 'info');

            wp_safe_redirect($list_url);
            exit;
        }
    }

    public function page()
    {
        $answerable_manager = new TKM_Answerable_Mnager();
        $departments = $this->get_department();

        if (isset($_GET['page']) && $_GET['page'] === 'tkm-my-departments') {
            include TKM_VIEWS_PATH . 'admin/department/my.php';
            return;
        }

        if (isset($_GET['action'], $_GET['id']) && $_GET['action'] === 'edit') {
            $showdepartment = $this->get_a_department(absint($_GET['id']));
            $answerable = $showdepartment ? $answerable_manager->get_by_department($showdepartment->ID) : [];
            include TKM_VIEWS_PATH . 'admin/department/edit.php';
            return;
        }

        include TKM_VIEWS_PATH . 'admin/department/main.php';
    }

    public function get_department()
    {
        return $this->wpdb->get_results("SELECT * FROM " . $this->table . " ORDER BY position, ID");
    }

    public function get_a_department($id)
    {
        return $this->wpdb->get_row($this->wpdb->prepare("SELECT * FROM " . $this->table . " WHERE ID = %d", $id));
    }

    public function delete($id)
    {
        $this->wpdb->delete($this->table, ['ID' => $id], ['%d']);
    }

    private function sanitize($data, $id = 0)
    {
        $parent = isset($data['parent']) ? absint($data['parent']) : 0;
        if ($id && $parent === (int) $id) {
            $parent = 0;
        }

        return [
            'name' => sanitize_text_field($data['name']),
            'parent' => $parent,
            'position' => isset($data['position']) && $data['position'] !== '' ? intval($data['position']) : 1,
            'description' => isset($data['description']) ? sanitize_textarea_field($data['description']) : null,
        ];
    }

    public function update($id, $data)
    {
        return $this->wpdb->update($this->table, $this->sanitize($data, $id), ['ID' => (int) $id], ['%s', '%d', '%d', '%s'], ['%d']);
    }

    private function insert_department($data)
    {
        $insert = $this->wpdb->insert($this->table, $this->sanitize($data), ['%s', '%d', '%d', '%s']);
        return $insert ? $this->wpdb->insert_id : null;
    }

    public function get_parent_department()
    {
        return  $this->wpdb->get_results("SELECT * FROM " . $this->table . " WHERE parent = 0 ORDER BY position ");
    }

    public function get_child_department($parent_id)
    {

        return $this->wpdb->get_results($this->wpdb->prepare("SELECT * FROM " . $this->table . " WHERE parent = %d ORDER BY position ", $parent_id));
    }

    /**
     * Ticket counts per department (open vs total) for the overview.
     */
    public function ticket_counts()
    {
        $rows = $this->wpdb->get_results("SELECT department_id, COUNT(*) AS total, SUM(CASE WHEN status = 'open' THEN 1 ELSE 0 END) AS open_count FROM {$this->wpdb->prefix}tkm_tickets WHERE status != 'trash' GROUP BY department_id");
        $counts = [];
        foreach ($rows as $row) {
            $counts[(int) $row->department_id] = ['total' => (int) $row->total, 'open' => (int) $row->open_count];
        }
        return $counts;
    }
}
