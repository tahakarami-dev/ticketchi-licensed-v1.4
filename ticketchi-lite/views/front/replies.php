<?php
/**
 * Conversation replies. Also rendered by the reply AJAX handler.
 *
 * @var object[] $replies
 * @var object   $ticket
 */
defined('ABSPATH') || exit;

foreach ((array) $replies as $ticketchi_lite_reply) {
    $ticketchi_lite_msg_author = (int) $ticketchi_lite_reply->creator_id;
    $ticketchi_lite_msg_body = $ticketchi_lite_reply->body;
    $ticketchi_lite_msg_date = $ticketchi_lite_reply->create_date;
    $ticketchi_lite_msg_file = $ticketchi_lite_reply->file;
    $ticketchi_lite_msg_voice = $ticketchi_lite_reply->voice;
    $ticketchi_lite_msg_support = \TicketchiLite\Tickets::is_staff_reply($ticketchi_lite_reply, $ticket);
    include TICKETCHI_LITE_PATH . 'views/front/message.php';
}
