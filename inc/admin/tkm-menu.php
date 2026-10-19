<?php

defined('ABSPATH') || exit('NO Access');

class TKM_MENU extends BASE_MENU
{
    public $tickets_list = NULL;
    public $from_admin_sms;
    private $wpdb;
    private $table;
    private $ticket_id = NULL;
    private $reply_table;

    public function __construct()
    {
        global $wpdb;
        $this->wpdb = $wpdb;
        $this->table = $wpdb->prefix . 'tkm_tickets';
        $this->ticket_id = isset($_GET['id']) ? absint($_GET['id']) : null;
        $this->reply_table = $wpdb->prefix . 'tkm_replies';
        $this->page_title = 'تیکت چی';
        $this->menu_title = 'تیکت چی';
        $this->menu_slug = 'tkm-settings ';
        $this->icon = TKM_ADMIN_ASSETS . 'image/messages.png';
        $this->has_sub_menu = true;
        $this->sub_item = [

            'settings' => [
                'page_title' => 'تنظیمات',
                'menu_title' => 'تنظیمات',
                'menu_slug' => 'tkm-settings',
                'callback' => '',
                'load' => [
                    'status' => false,

                ]

            ],
            'analysis' => [
                'page_title' => 'داشبورد',
                'menu_title' => 'داشبورد و گزارش‌ها',
                'menu_slug' => 'tkm-analysis',
                'callback' => 'tkm_analysis',
                'load' => [
                    'status' => false,

                ]
            ],

            'ticket' => [
                'page_title' => 'لیست تیکت ها',
                'menu_title' => 'لیست تیکت ها',
                'menu_slug' => 'tkm-tickets',
                'callback' => 'tickets_page',
                'load' => [
                    'status' => true,
                    'callback_option' => 'tickets_screen_option',
                ]

            ],
            'departments' => [
                'page_title' => 'لیست دپارتمان ها',
                'menu_title' => 'لیست دپارتمان ها ',
                'menu_slug' => 'tkm-departments',
                'callback' => 'departments_page',
                'load' => [
                    'status' => false,
                ]
            ],
            'my_departments' => [
                'page_title' => ' تیکت های دپارتمان من  ',
                'menu_title' => ' تیکت های دپارتمان من  ',
                'menu_slug' => 'tkm-my-departments',
                'callback' => 'departments_page',
                'load' => [
                    'status' => false,
                ]
            ],
            'new-ticket' => [
                'page_title' => '  ارسال تیکت',
                'menu_title' => '  ارسال تیکت',
                'menu_slug' => 'tkm-new-ticket',
                'callback' => 'new_ticket_page',
                'load' => [
                    'status' => false,
                ],
            ],
            'edit-ticket' => [
                'page_title' => '  ویرایش تیکت',
                'menu_title' => '  ویرایش تیکت',
                'menu_slug' => 'tkm-edit-ticket',
                'callback' => 'edit_ticket_page',
                'load' => [
                    'status' => false,
                ],
            ],
        ];
        parent::__construct();

        // Forms are processed before any output so we can redirect (Post/Redirect/Get).
        add_action('admin_init', [$this, 'handle_post']);
        add_action('admin_init', ['TKM_Admin_Department_Manager', 'boot']);
        add_filter('set-screen-option', [$this, 'save_screen_option'], 10, 3);
        add_filter('set_screen_option_ticket_per_page', [$this, 'save_screen_option'], 10, 3);
    }

    public function page()
    {
        // The top-level slug is shared with the Codestar settings screen, which renders itself.
        if (isset($_GET['page']) && trim(wp_unslash($_GET['page'])) === 'tkm-settings') {
            return;
        }
        $this->tkm_analysis();
    }

    public function tickets_page()
    {
        if (!$this->tickets_list) {
            $this->tickets_list = new TKM_Ticket_List();
        }
        include TKM_VIEWS_PATH . 'admin/ticket/main.php';
    }

    public function tkm_analysis()
    {
        include TKM_VIEWS_PATH . 'admin/analysis/analysis.php';
    }

    public function tickets_screen_option()
    {
        // add screen option
        $args = [
            'label' => 'تعداد تیکت در هر صفحه :',
            'default' => 20,
            'option' => 'ticket_per_page',
        ];
        add_screen_option('per_page', $args);

        $this->tickets_list = new TKM_Ticket_List();
        $this->tickets_list->process_actions();
    }

    public function save_screen_option($status, $option, $value)
    {
        return $option === 'ticket_per_page' ? max(1, min(200, (int) $value)) : $status;
    }

    public function departments_page()
    {
        $manager = new TKM_Admin_Department_Manager();
        $manager->page();
    }

    /* ───────────────────────── POST handling ───────────────────────── */

    public function handle_post()
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['publish'], $_GET['page'])) {
            return;
        }

        $page = sanitize_key(wp_unslash($_GET['page']));
        if (!in_array($page, ['tkm-new-ticket', 'tkm-edit-ticket'], true)) {
            return;
        }

        if (!isset($_POST['ticket_nonce']) || !wp_verify_nonce($_POST['ticket_nonce'], 'ticket_send')) {
            wp_die('نشست شما منقضی شده است. صفحه را دوباره بارگذاری کنید.', '', ['back_link' => true]);
        }

        if (!tkm_is_staff()) {
            wp_die('شما اجازه انجام این کار را ندارید.', '', ['response' => 403]);
        }

        if ($page === 'tkm-new-ticket') {
            $ids = $this->create_ticket(get_current_user_id(), wp_unslash($_POST));
            if ($ids) {
                $this->redirect(count($ids) === 1 ? tkm_admin_url('tkm-edit-ticket', ['id' => $ids[0]]) : tkm_admin_url('tkm-tickets'));
            }
            // Validation failed: keep the submitted values on the page (no redirect).
            return;
        }

        $this->process_edit();
        $this->redirect(tkm_admin_url('tkm-edit-ticket', ['id' => $this->ticket_id]));
    }

    private function redirect($url)
    {
        wp_safe_redirect($url);
        exit;
    }

    public function new_ticket_page()
    {
        $is_edit = false;
        include TKM_VIEWS_PATH . 'admin/ticket/new.php';
    }

    /**
     * @return int[] Inserted ticket ids (empty on validation error).
     */
    public function create_ticket($creator_id, $data)
    {
        $users = !empty($data['user-id']) && is_array($data['user-id']) ? array_filter(array_map('absint', $data['user-id'])) : [];
        $title = isset($data['ticket-title']) ? sanitize_text_field($data['ticket-title']) : '';
        $body = isset($data['tkm-content']) ? wp_kses_post($data['tkm-content']) : '';
        $department_id = isset($data['department_id']) ? absint($data['department_id']) : 0;

        if (!$users || $title === '' || trim(wp_strip_all_tags($body)) === '' || !$department_id) {
            tkm_flash('ارسال تیکت با خطا مواجه شد؛ لطفا موارد ضروری (*) را تکمیل نمایید.', 'danger');
            return [];
        }

        $file_value = $this->collect_files(null, 'tkm_ticket_files');
        $statuses = wp_list_pluck(tkm_get_status(), 'slug');
        $status = isset($data['status']) && in_array($data['status'], $statuses, true) ? $data['status'] : 'open';
        $priority = isset($data['priority']) && in_array($data['priority'], ['low', 'medium', 'high'], true) ? $data['priority'] : 'medium';
        $now = date('Y-m-d H:i:s');
        $ticket_ids = [];

        foreach (array_unique($users) as $user_id) {
            $inserted = $this->wpdb->insert(
                $this->table,
                [
                    'title' => $title,
                    'body' => $body,
                    'status' => $status,
                    'priority' => $priority,
                    'creator_id' => $creator_id,
                    'user_id' => $user_id,
                    'from_admin' => 1,
                    'department_id' => $department_id,
                    'file' => $file_value,
                    'note' => !empty($data['note']) ? sanitize_text_field($data['note']) : null,
                    'create_date' => $now,
                    'reply_date' => $now,
                ],
                ['%s', '%s', '%s', '%s', '%d', '%d', '%d', '%d', '%s', '%s', '%s', '%s']
            );

            if ($inserted) {
                $ticket_ids[] = (int) $this->wpdb->insert_id;
                if (isset($data['send-copy'])) {
                    do_action('tkm_submit_ticket', $this->wpdb->insert_id);
                }
            }
        }

        if ($ticket_ids) {
            tkm_flash(count($ticket_ids) > 1 ? tkm_fa(count($ticket_ids)) . ' تیکت با موفقیت ارسال شد.' : 'تیکت با موفقیت ارسال شد.');
        } else {
            tkm_flash('ذخیره تیکت در پایگاه داده با خطا مواجه شد.', 'danger');
        }

        return $ticket_ids;
    }

    public function get_from_adminsms()
    {
        return $from_admin_sms = true;
    }

    /**
     * Saves ticket properties, edits/deletes existing replies and adds a new reply.
     */
    private function process_edit()
    {
        $ticket = $this->get_ticket();
        if (!$ticket) {
            tkm_flash('تیکت مورد نظر یافت نشد.', 'danger');
            return;
        }

        $data = wp_unslash($_POST);
        $reply_manager = new TKM_Reply_Manager($this->ticket_id);

        foreach ($reply_manager->get_replys() as $reply) {
            $key = 'tkm-reply-body-' . $reply->ID;
            if (!isset($data[$key])) {
                continue;
            }

            if (trim($data[$key]) !== '') {
                $this->update_reply([
                    'ID' => $reply->ID,
                    'body' => wp_kses_post($data[$key]),
                    'file' => $this->collect_files($reply->file, 'tkm_reply_files_' . $reply->ID, 'remove_reply_file_' . $reply->ID),
                ]);
            } elseif ($reply_manager->delete_reply($reply->ID)) {
                tkm_flash('پیام با موفقیت حذف شد.', 'info');
            }
        }

        $this->update_ticket($data);

        if (!empty($data['reply_content']) && trim(wp_strip_all_tags($data['reply_content'], true)) !== '') {
            $insert_reply = $this->create_reply([
                'ticket_id' => $this->ticket_id,
                'creator_id' => get_current_user_id(),
                'from_admin' => 1,
                'body' => wp_kses_post($data['reply_content']),
                'file' => $this->collect_files(null, 'tkm_reply_files'),
            ]);

            if ($insert_reply) {
                $this->update_reply_date();
                // After a staff reply the ticket becomes «answered» unless it was explicitly closed/finished.
                $status = isset($data['status']) ? sanitize_key($data['status']) : '';
                if (!in_array($status, ['closed', 'finish'], true)) {
                    $this->update_status('answerd');
                }
            }
        }
    }

    public function edit_ticket_page()
    {
        $is_edit = true;
        $ticket = $this->get_ticket();
        $replies = $ticket ? (new TKM_Reply_Manager($ticket->ID))->get_replys() : [];

        include TKM_VIEWS_PATH . 'admin/ticket/new.php';
    }

    public function create_reply($data)
    {
        $insert = $this->wpdb->insert(
            $this->reply_table,
            array_merge($data, ['create_date' => date('Y-m-d H:i:s')]),
            ['%d', '%d', '%d', '%s', '%s', '%s']
        );

        if ($insert) {
            tkm_flash('پاسخ با موفقیت ارسال شد.');
        } else {
            tkm_flash('ارسال پاسخ با خطا مواجه شد.', 'danger');
        }

        return $insert ? $this->wpdb->insert_id : null;
    }

    public function update_reply_date()
    {
        return $this->wpdb->update($this->table, ['reply_date' => date('Y-m-d H:i:s')], ['ID' => (int) $this->ticket_id], ['%s'], ['%d']);
    }

    public function update_status($status)
    {
        return $this->wpdb->update(
            $this->table,
            ['status' => $status],
            ['ID' => intval($this->ticket_id)],
            ['%s'],
            ['%d']
        );
    }

    /**
     * جمع‌آوری فایل‌های نهایی برای ذخیره:
     * فایل‌های موجود (منهای حذف‌شده‌ها) + فایل‌های تازه آپلودشده.
     * خروجی: مقدار JSON قابل ذخیره در ستون file یا null.
     */
    private function collect_files($current_value, $files_key, $remove_key = null)
    {
        $files = tkm_get_files($current_value);

        if ($remove_key && !empty($_POST[$remove_key]) && is_array($_POST[$remove_key])) {
            $remove = array_map('esc_url_raw', wp_unslash($_POST[$remove_key]));
            $files = array_values(array_filter($files, function ($url) use ($remove) {
                return !in_array($url, $remove, true);
            }));
        }

        if (!empty($_FILES[$files_key]) && !empty($_FILES[$files_key]['name'])) {
            $uploader = new TKM_Upload_File($_FILES[$files_key]);
            $result = $uploader->upload();

            if (!$result['success']) {
                tkm_flash(esc_html($result['message']), 'danger');
            } else {
                foreach ($result['urls'] as $url) {
                    $files[] = esc_url_raw($url);
                }
            }
        }

        return tkm_files_encode($files);
    }

    public function update_ticket($data)
    {
        $title = isset($data['ticket-title']) ? sanitize_text_field($data['ticket-title']) : '';
        $body = isset($data['tkm-content']) ? wp_kses_post($data['tkm-content']) : '';
        $department_id = isset($data['department_id']) ? absint($data['department_id']) : 0;

        if ($title === '' || trim(wp_strip_all_tags($body)) === '' || !$department_id) {
            tkm_flash('ذخیره تیکت انجام نشد؛ عنوان، متن و دپارتمان الزامی هستند.', 'danger');
            return false;
        }

        $current_ticket = $this->get_ticket();
        $statuses = wp_list_pluck(tkm_get_status(), 'slug');

        $fields = [
            'title' => $title,
            'body' => $body,
            'department_id' => $department_id,
            'file' => $this->collect_files($current_ticket ? $current_ticket->file : null, 'tkm_ticket_files', 'remove_ticket_file'),
            'note' => isset($data['note']) ? sanitize_textarea_field($data['note']) : null,
        ];
        $formats = ['%s', '%s', '%d', '%s', '%s'];

        if (isset($data['status']) && in_array($data['status'], $statuses, true)) {
            $fields['status'] = $data['status'];
            $formats[] = '%s';
        }
        if (isset($data['priority']) && in_array($data['priority'], ['low', 'medium', 'high'], true)) {
            $fields['priority'] = $data['priority'];
            $formats[] = '%s';
        }

        // creator / recipient are optional: customer tickets have no recipient.
        $creator_id = !empty($data['creator-id']) ? absint(is_array($data['creator-id']) ? reset($data['creator-id']) : $data['creator-id']) : 0;
        if ($creator_id) {
            $fields['creator_id'] = $creator_id;
            $formats[] = '%d';
        }
        $user_id = !empty($data['user-id']) ? absint(is_array($data['user-id']) ? reset($data['user-id']) : $data['user-id']) : 0;
        if ($user_id) {
            $fields['user_id'] = $user_id;
            $formats[] = '%d';
        }

        // <input type="datetime-local"> sends site-local time; the column stores UTC (PHP date()).
        if (!empty($data['date_ticket'])) {
            $local = date_create_immutable_from_format('Y-m-d\TH:i', $data['date_ticket'], wp_timezone());
            if ($local) {
                $fields['create_date'] = $local->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');
                $formats[] = '%s';
            }
        }

        $updated = $this->wpdb->update($this->table, $fields, ['ID' => intval($this->ticket_id)], $formats, ['%d']);

        if ($updated === false) {
            tkm_flash('مشکلی در ذخیره تیکت رخ داد.', 'danger');
            return false;
        }

        if ($updated) {
            tkm_flash('تغییرات تیکت ذخیره شد.');
        }

        return true;
    }

    public function get_ticket()
    {
        if (!intval($this->ticket_id)) {
            return null;
        }
        return  $this->wpdb->get_row($this->wpdb->prepare("SELECT * FROM " . $this->table . " WHERE ID = %d", $this->ticket_id));
    }

    public function update_reply($data)
    {
        return $this->wpdb->update(
            $this->reply_table,
            [
                'body' => $data['body'],
                'file' => $data['file']
            ],
            ['ID' => $data['ID']],
            ['%s', '%s'],
            ['%d']
        );
    }
}
