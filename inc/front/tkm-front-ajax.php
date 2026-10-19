<?php
defined('ABSPATH') || exit('NO Access');

class TKM_Front_AJAX
{

    public function __construct()
    {
        add_action('wp_ajax_tkm-submit-ticket', [$this, 'submit_ticket']);
        add_action('wp_ajax_nopriv_tkm-submit-ticket', [$this, 'submit_ticket']);
        add_action('wp_ajax_tkm-submit-reply', [$this, 'reply_ticket']);
        add_action('wp_ajax_nopriv_tkm-submit-reply', [$this, 'reply_ticket']);
        add_action('wp_ajax_tkm_submit_rating', [$this, 'submit_rating']);
        add_action('wp_ajax_nopriv_tkm_submit_rating', [$this, 'submit_rating']);
    }

    private function post($key, $default = '')
    {
        return isset($_POST[$key]) ? wp_unslash($_POST[$key]) : $default;
    }

    /**
     * Nonce + login guard shared by every front endpoint.
     */
    private function guard()
    {
        if (!wp_verify_nonce($this->post('nonce'), 'tkm_ajax_nonce')) {
            $this->make_response(['success' => false, 'result' => 'درخواست نامعتبر است. صفحه را دوباره بارگذاری کنید.']);
        }

        if (!is_user_logged_in()) {
            $this->make_response(['success' => false, 'result' => 'برای ادامه ابتدا وارد حساب کاربری خود شوید.']);
        }
    }

    public function submit_ticket()
    {
        $this->guard();

        $department_id = absint($this->post('child-department'));
        $department = $department_id ? (new TKM_Front_Department_Manager())->get_department($department_id) : null;

        if ($department_id && !$department) {
            $this->make_response(['success' => false, 'result' => ['دپارتمان انتخاب‌شده معتبر نیست.']]);
        }

        $file = isset($_FILES['file']) ? $_FILES['file'] : null;
        $upload_result = null;

        if ($file) {
            $uploader = new TKM_Upload_File($file);
            $upload_result = $uploader->upload();

            if (!$upload_result['success']) {
                $this->make_response(['success' => false, 'result' => $upload_result['message']]);
            }
        }

        $priority = sanitize_key($this->post('priority'));
        $title = sanitize_text_field($this->post('title_ticket'));

        $ticket_data = [
            'title'         => $title !== '' ? $title : 'بدون عنوان',
            'body'          => wp_kses_post($this->post('content')),
            'creator_id'    => get_current_user_id(),
            'user_id'       => null,
            'status'        => 'open',
            'priority'      => in_array($priority, ['low', 'medium', 'high'], true) ? $priority : 'medium',
            'product'       => absint($this->post('user_purchased_products')) ?: null,
            'edd_product'   => absint($this->post('edd_purchased_products')) ?: null,
            'department_id' => $department_id,
            'file'          => null,
            'voice'         => null,
        ];

        if ($upload_result && !empty($upload_result['urls'])) {
            $ticket_data['file'] = tkm_files_encode(array_map('esc_url_raw', $upload_result['urls']));
        }

        $voice_data = $this->post('audioData');

        if ($voice_data) {
            $voice_upload_result = (new TKM_Upload_Voice($voice_data))->upload();

            if (!$voice_upload_result['success']) {
                $this->make_response(['success' => false, 'result' => $voice_upload_result['message']]);
            }

            $ticket_data['voice'] = esc_url_raw($voice_upload_result['url']);
        }

        $ticket_manager = new TKM_Ticket_Manager();
        $ticket = $ticket_manager->insert($ticket_data);

        if (isset($ticket['ticket_id']) && $ticket['ticket_id']) {
            do_action('tkm_submit_ticket', $ticket['ticket_id']);
            $this->make_response([
                'success'   => true,
                'result'    => TKM_Ticket_Url::all(),
                'ticket_id' => (int) $ticket['ticket_id'],
            ]);
        }

        $this->make_response(['success' => false, 'result' => $ticket ? $ticket : 'ثبت تیکت با خطا مواجه شد.']);
    }

    public function make_response($result)
    {
        if (is_array($result)) {
            wp_send_json($result);
        } else {
            wp_die($result);
        }
    }

    public function reply_ticket()
    {
        $this->guard();

        $user_id = get_current_user_id();
        $ticket_id = absint($this->post('ticket_id'));

        $ticket_manager = new TKM_Ticket_Manager();
        $ticket = $ticket_manager->get_info_ticket($ticket_id);

        if (!$ticket || !tkm_user_can_access_ticket($ticket, $user_id)) {
            $this->make_response(['success' => false, 'result' => 'تیکت مورد نظر یافت نشد.']);
        }

        if ($ticket->status == 'finish') {
            $this->make_response(['success' => false, 'result' => 'این تیکت پایان یافته و امکان پاسخ به آن وجود ندارد.']);
        }

        $data_reply = [
            'body'       => wp_kses_post($this->post('body')),
            'creator_id' => $user_id,
            'file'       => null,
            'voice'      => null,
        ];

        // Customers can only keep the ticket open or close it themselves.
        $status = $this->post('status') === 'closed' ? 'closed' : 'open';

        if (isset($_FILES['file'])) {
            $uploader = new TKM_Upload_File($_FILES['file']);
            $upload_result = $uploader->upload();
            if (!$upload_result['success']) {
                $this->make_response(['success' => false, 'result' => $upload_result['message']]);
            }
            if (!empty($upload_result['urls'])) {
                $data_reply['file'] = tkm_files_encode(array_map('esc_url_raw', $upload_result['urls']));
            }
        }

        $voice_data = $this->post('audioData');
        if ($voice_data) {
            $voice_upload_result = (new TKM_Upload_Voice($voice_data))->upload();
            if (!$voice_upload_result['success']) {
                $this->make_response(['success' => false, 'result' => $voice_upload_result['message']]);
            }
            $data_reply['voice'] = esc_url_raw($voice_upload_result['url']);
        }

        // A voice note or attachment alone is a valid reply.
        if (trim(wp_strip_all_tags($data_reply['body'])) === '' && ($data_reply['file'] || $data_reply['voice'])) {
            $data_reply['body'] = ' ';
        }

        $reply_manager = new TKM_Reply_Manager($ticket_id);
        $insert = $reply_manager->insert_reply($data_reply);

        if (is_numeric($insert) && $insert) {
            $ticket_manager->update_status($ticket_id, $status);
            $ticket_manager->update_reply_date($ticket_id);

            $ticket = $ticket_manager->get_info_ticket($ticket_id);
            $replies = $reply_manager->get_replys();
            ob_start();
            include TKM_VIEWS_PATH . 'front/reply.php';
            $replies_html = ob_get_clean();

            $this->make_response([
                'success'       => true,
                'result'        => 'پاسخ ثبت شد',
                'replies_html'  => $replies_html,
                'status_update' => get_status_html($status),
                'status_badge'  => tkm_status_badge($status),
                'status'        => $status,
            ]);
        }

        $this->make_response(['success' => false, 'result' => is_array($insert) ? implode(' ', $insert) : 'ثبت پاسخ با خطا مواجه شد.']);
    }

    public function submit_rating()
    {
        $this->guard();

        $ticket_id = absint($this->post('ticket_id'));
        $rating = (int) $this->post('rating');
        $user_id = get_current_user_id();

        $ticket = (new TKM_Ticket_Manager())->get_info_ticket($ticket_id);

        if (!$ticket || !tkm_user_can_access_ticket($ticket, $user_id) || $rating < 1 || $rating > 5) {
            wp_send_json_error(['message' => 'ورودی‌ها نامعتبر است.']);
        }

        global $wpdb;
        $table_name = $wpdb->prefix . 'tkm_ratings';

        $existing = $wpdb->get_var($wpdb->prepare(
            "SELECT ID FROM $table_name WHERE ticket_id = %d AND user_id = %d",
            $ticket_id,
            $user_id
        ));

        if ($existing) {
            wp_send_json_error(['message' => 'شما قبلا برای این تیکت امتیاز داده‌اید.']);
        }

        $result = $wpdb->insert(
            $table_name,
            [
                'ticket_id' => $ticket_id,
                'user_id'   => $user_id,
                'rating'    => $rating,
            ],
            ['%d', '%d', '%d']
        );

        if ($result) {
            wp_send_json_success(['message' => 'امتیاز با موفقیت ثبت شد.', 'rating' => $rating]);
        }

        wp_send_json_error(['message' => 'خطا در ثبت امتیاز.']);
    }
}
