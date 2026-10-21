<?php
/**
 * One message bubble in the conversation.
 *
 * @var int    $ticketchi_lite_msg_author
 * @var string $ticketchi_lite_msg_body
 * @var string $ticketchi_lite_msg_date
 * @var string $ticketchi_lite_msg_file   stored «file» value
 * @var string $ticketchi_lite_msg_voice  audio URL (messages recorded in Ticketchi Pro)
 * @var bool   $ticketchi_lite_msg_support
 */
defined('ABSPATH') || exit;

$ticketchi_lite_kses = ticketchi_lite_allowed_html();
$ticketchi_lite_msg_mine = (int) $ticketchi_lite_msg_author === get_current_user_id();
$ticketchi_lite_msg_class = 'tkm-msg' . ($ticketchi_lite_msg_mine ? ' tkm-msg--mine' : '') . ($ticketchi_lite_msg_support ? ' tkm-msg--support' : '');
?>
<article class="<?php echo esc_attr($ticketchi_lite_msg_class); ?>">
    <?php echo wp_kses(ticketchi_lite_avatar($ticketchi_lite_msg_author), $ticketchi_lite_kses); ?>
    <div class="tkm-msg__wrap">
        <header class="tkm-msg__head">
            <strong><?php echo esc_html($ticketchi_lite_msg_mine ? 'شما' : ticketchi_lite_user_name($ticketchi_lite_msg_author)); ?></strong>
            <?php if ($ticketchi_lite_msg_support && !$ticketchi_lite_msg_mine) : ?>
                <span class="tkm-role"><?php echo wp_kses(ticketchi_lite_icon('check-circle', 12), $ticketchi_lite_kses); ?> پشتیبانی</span>
            <?php endif; ?>
            <time datetime="<?php echo esc_attr((string) \TicketchiLite\Clock::iso($ticketchi_lite_msg_date)); ?>" title="<?php echo esc_attr(ticketchi_lite_date($ticketchi_lite_msg_date)); ?>"><?php echo esc_html(ticketchi_lite_time_ago($ticketchi_lite_msg_date)); ?></time>
        </header>
        <div class="tkm-msg__bubble">
            <?php if (trim(wp_strip_all_tags((string) $ticketchi_lite_msg_body)) !== '') : ?>
                <div class="tkm-prose"><?php echo wp_kses_post(ticketchi_lite_content($ticketchi_lite_msg_body)); ?></div>
            <?php endif; ?>
            <?php if (!empty($ticketchi_lite_msg_voice)) : ?>
                <audio class="tkm-audio" controls preload="none" src="<?php echo esc_url($ticketchi_lite_msg_voice); ?>"></audio>
            <?php endif; ?>
            <?php echo wp_kses(ticketchi_lite_files_html($ticketchi_lite_msg_file), $ticketchi_lite_kses); ?>
        </div>
    </div>
</article>
