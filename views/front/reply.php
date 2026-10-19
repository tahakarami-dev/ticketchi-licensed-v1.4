<?php
/**
 * Conversation replies. Also rendered by the tkm-submit-reply AJAX handler.
 *
 * @var array  $replies
 * @var object $ticket
 */
defined('ABSPATH') || exit('NO Access');

if (tkm_settings('auto_reply') && trim((string) tkm_settings('auto_reply_text')) !== '') {
    $tkm_msg_author  = 0;
    $tkm_msg_body    = tkm_settings('auto_reply_text');
    $tkm_msg_date    = '';
    $tkm_msg_files   = '';
    $tkm_msg_voice   = '';
    $tkm_msg_support = true;
    $tkm_msg_bot     = true;
    include TKM_VIEWS_PATH . 'front/partials/message.php';
}

foreach ((array) $replies as $reply) {
    $tkm_msg_author  = (int) $reply->creator_id;
    $tkm_msg_body    = $reply->body;
    $tkm_msg_date    = $reply->create_date;
    $tkm_msg_files   = tkm_files_html($reply->file);
    $tkm_msg_voice   = tkm_voice_html($reply->voice);
    $tkm_msg_support = (int) $reply->from_admin === 1 || ($tkm_msg_author && $tkm_msg_author !== (int) $ticket->creator_id && tkm_is_staff($tkm_msg_author));
    $tkm_msg_bot     = false;
    include TKM_VIEWS_PATH . 'front/partials/message.php';
}
