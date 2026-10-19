<?php

defined('ABSPATH') || exit('NO Access');

/**
 * Admin screens. Registered through the (ionCube-encoded) BASE_MENU so the
 * marketplace licence check stays in charge of every page.
 */
class TKM_MENU extends BASE_MENU
{
    public $tickets_list = NULL;
    public $from_admin_sms;
    private $wpdb;
    private $table;
    private $ticket_id = NULL;

    public function __construct()
    {
        global $wpdb;
        $this->wpdb = $wpdb;
        $this->table = $wpdb->prefix . 'tkm_tickets';
        $this->ticket_id = isset($_GET['id']) ? absint($_GET['id']) : null;
        $this->page_title = 'تیکت چی';
        $this->menu_title = 'تیکت چی';
        $this->menu_slug = 'tkm-settings ';
        $this->icon = TKM_ADMIN_ASSETS . 'image/messages.png';
        // Agents (role «کارشناس پشتیبانی» / department members) can open the workspace.
        $this->capability = 'tkm_view';
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
                'menu_title' => 'داشبورد',
                'menu_slug' => 'tkm-analysis',
                'callback' => 'tkm_analysis',
                'load' => [
                    'status' => false,
                ]
            ],
            'ticket' => [
                'page_title' => 'صندوق پشتیبانی',
                'menu_title' => 'صندوق پشتیبانی',
                'menu_slug' => 'tkm-tickets',
                'callback' => 'tickets_page',
                'load' => [
                    'status' => false,
                ]
            ],
            'new-ticket' => [
                'page_title' => 'ارسال تیکت',
                'menu_title' => 'ارسال تیکت',
                'menu_slug' => 'tkm-new-ticket',
                'callback' => 'new_ticket_page',
                'load' => [
                    'status' => false,
                ],
            ],
            'reports' => [
                'page_title' => 'گزارش‌ها',
                'menu_title' => 'گزارش‌ها',
                'menu_slug' => 'tkm-reports',
                'callback' => 'reports_page',
                'load' => [
                    'status' => false,
                ],
            ],
            'automations' => [
                'page_title' => 'اتوماسیون',
                'menu_title' => 'اتوماسیون',
                'menu_slug' => 'tkm-automations',
                'callback' => 'automations_page',
                'load' => [
                    'status' => false,
                ],
            ],
            'departments' => [
                'page_title' => 'تیم و دپارتمان‌ها',
                'menu_title' => 'تیم و دپارتمان‌ها',
                'menu_slug' => 'tkm-departments',
                'callback' => 'departments_page',
                'load' => [
                    'status' => false,
                ]
            ],
            // Kept so old links keep working; both redirect into the inbox.
            'my_departments' => [
                'page_title' => 'صف من',
                'menu_title' => 'صف من',
                'menu_slug' => 'tkm-my-departments',
                'callback' => 'tickets_page',
                'load' => [
                    'status' => false,
                ]
            ],
            'edit-ticket' => [
                'page_title' => 'تیکت',
                'menu_title' => 'تیکت',
                'menu_slug' => 'tkm-edit-ticket',
                'callback' => 'tickets_page',
                'load' => [
                    'status' => false,
                ],
            ],
        ];
        parent::__construct();

        // Compose form is processed before output so it can redirect (Post/Redirect/Get).
        add_action('admin_init', [$this, 'handle_post']);
        add_action('admin_init', ['TKM_Admin_Department_Manager', 'boot']);
        add_action('admin_head', [$this, 'hide_redirect_pages']);
    }

    /** The two legacy entries stay registered (old links) but out of the sidebar. */
    public function hide_redirect_pages()
    {
        echo '<style>#adminmenu a[href*="page=tkm-edit-ticket"],#adminmenu a[href*="page=tkm-my-departments"]{display:none!important}</style>';
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
        include TKM_VIEWS_PATH . 'admin/inbox/app.php';
    }

    public function tkm_analysis()
    {
        include TKM_VIEWS_PATH . 'admin/analysis/analysis.php';
    }

    public function reports_page()
    {
        include TKM_VIEWS_PATH . 'admin/reports/app.php';
    }

    public function automations_page()
    {
        include TKM_VIEWS_PATH . 'admin/automations/app.php';
    }

    public function departments_page()
    {
        $manager = new TKM_Admin_Department_Manager();
        $manager->page();
    }

    /* ───────────────────────── Compose (staff → customers) ───────────────────────── */

    public function handle_post()
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['publish'], $_GET['page'])) {
            return;
        }

        if (sanitize_key(wp_unslash($_GET['page'])) !== 'tkm-new-ticket') {
            return;
        }

        if (!isset($_POST['ticket_nonce']) || !wp_verify_nonce($_POST['ticket_nonce'], 'ticket_send')) {
            wp_die('نشست شما منقضی شده است. صفحه را دوباره بارگذاری کنید.', '', ['back_link' => true]);
        }

        if (!current_user_can('tkm_view')) {
            wp_die('شما اجازه انجام این کار را ندارید.', '', ['response' => 403]);
        }

        $ids = $this->create_ticket(get_current_user_id(), wp_unslash($_POST));
        if ($ids) {
            wp_safe_redirect(count($ids) === 1 ? \Ticketchi\Support\Links::admin_ticket($ids[0]) : tkm_admin_url('tkm-tickets'));
            exit;
        }
        // Validation failed: keep the submitted values on the page (no redirect).
    }

    public function new_ticket_page()
    {
        $is_edit = false;
        include TKM_VIEWS_PATH . 'admin/ticket/new.php';
    }

    /**
     * One ticket per recipient.
     *
     * @return int[] Inserted ticket ids (empty on validation error).
     */
    public function create_ticket($creator_id, $data)
    {
        $users = !empty($data['user-id']) && is_array($data['user-id']) ? array_unique(array_filter(array_map('absint', $data['user-id']))) : [];
        $title = isset($data['ticket-title']) ? sanitize_text_field($data['ticket-title']) : '';
        $body = isset($data['tkm-content']) ? wp_kses_post($data['tkm-content']) : '';
        $department_id = isset($data['department_id']) ? absint($data['department_id']) : 0;

        if (!$users || $title === '' || trim(wp_strip_all_tags($body)) === '' || !$department_id) {
            tkm_flash('ارسال تیکت با خطا مواجه شد؛ لطفا موارد ضروری (*) را تکمیل نمایید.', 'danger');
            return [];
        }

        $file_value = $this->collect_files(null, 'tkm_ticket_files');
        $ticket_ids = [];

        foreach ($users as $user_id) {
            $id = \Ticketchi\Domain\Tickets::create([
                'title'         => $title,
                'body'          => $body,
                'status'        => isset($data['status']) ? $data['status'] : 'open',
                'priority'      => isset($data['priority']) ? $data['priority'] : 'medium',
                'creator_id'    => $creator_id,
                'user_id'       => $user_id,
                'from_admin'    => 1,
                'department_id' => $department_id,
                'file'          => $file_value,
                'note'          => isset($data['note']) ? $data['note'] : '',
            ], $creator_id, 'staff');

            if (!is_wp_error($id)) {
                $ticket_ids[] = $id;
                if (isset($data['send-copy'])) {
                    do_action('tkm_submit_ticket', $id);
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
     * Existing files (minus removed ones) + newly uploaded ones, as the JSON
     * value stored in the «file» column (or null).
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
}
