<?php

defined('ABSPATH') || exit('NO Access');

class TKM_Reply_Manager
{

    private $wpdb;
    private $table;
    private $ticket_id;

    public function __construct($ticket_id)
    {
        global $wpdb;
        $this->wpdb = $wpdb;
        $this->table = $wpdb->prefix . 'tkm_replies';
        $this->ticket_id = $ticket_id;
    }

    public function insert_reply($data)
    {
        $errors = [];

        if (empty($data['body'])) {
            $errors[] = 'لطفا پاسخ تیکت را وارد نمایید';
        }

        if (count($errors) > 0) {
            return $errors;
        }

        $result = \Ticketchi\Domain\Tickets::reply($this->ticket_id, [
            'body'  => $data['body'],
            'file'  => !empty($data['file']) ? $data['file'] : null,
            'voice' => !empty($data['voice']) ? $data['voice'] : null,
        ], !empty($data['creator_id']) ? (int) $data['creator_id'] : 0);

        return is_wp_error($result) ? [$result->get_error_message()] : $result;
    }

    /**
     * @param bool $include_internal false for the customer panel (hides staff-only notes)
     */
    public function get_replys($include_internal = true)
    {
        return \Ticketchi\Domain\Tickets::replies($this->ticket_id, $include_internal);
    }

    public function delete_reply($reply_id)
    {
        return $this->wpdb->delete($this->table, ['ID' => $reply_id,], ['%d']);
    }

    public function delete_replies()
    {
        return  $this->wpdb->delete($this->table, ['ticket_id' => $this->ticket_id], ['%d']);
    }
}
