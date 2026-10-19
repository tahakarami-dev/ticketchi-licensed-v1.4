<?php
/**
 * A single message bubble in the conversation.
 *
 * @var int    $tkm_msg_author
 * @var string $tkm_msg_body
 * @var string $tkm_msg_date
 * @var string $tkm_msg_files
 * @var string $tkm_msg_voice
 * @var bool   $tkm_msg_support
 * @var bool   $tkm_msg_bot
 */
defined('ABSPATH') || exit('NO Access');

$tkm_msg_mine = !$tkm_msg_bot && (int) $tkm_msg_author === get_current_user_id();
$tkm_msg_class = 'tkm-msg' . ($tkm_msg_mine ? ' tkm-msg--mine' : '') . ($tkm_msg_support ? ' tkm-msg--support' : '') . ($tkm_msg_bot ? ' tkm-msg--bot' : '');
$tkm_msg_has_text = trim(wp_strip_all_tags((string) $tkm_msg_body)) !== '';
?>
<article class="<?php echo esc_attr($tkm_msg_class); ?>">
    <?php if ($tkm_msg_bot) : ?>
        <span class="tkm-avatar tkm-avatar--bot" aria-hidden="true"><?php echo tkm_icon('bot', 18); ?></span>
    <?php else : ?>
        <?php echo tkm_avatar($tkm_msg_author); ?>
    <?php endif; ?>
    <div class="tkm-msg__wrap">
        <header class="tkm-msg__head">
            <strong><?php echo esc_html($tkm_msg_bot ? 'پاسخ خودکار' : ($tkm_msg_mine ? 'شما' : tkm_user_name($tkm_msg_author))); ?></strong>
            <?php if ($tkm_msg_support && !$tkm_msg_mine) : ?>
                <span class="tkm-role"><?php echo tkm_icon('check-circle', 12); ?> پشتیبانی</span>
            <?php endif; ?>
            <?php if ($tkm_msg_date) : ?>
                <time datetime="<?php echo esc_attr(mysql2date('c', $tkm_msg_date, false)); ?>" title="<?php echo esc_attr(tkm_date($tkm_msg_date)); ?>"><?php echo esc_html(tkm_time_ago($tkm_msg_date)); ?></time>
            <?php endif; ?>
        </header>
        <div class="tkm-msg__bubble">
            <?php if ($tkm_msg_has_text) : ?>
                <div class="tkm-prose"><?php echo tkm_content($tkm_msg_body); ?></div>
            <?php endif; ?>
            <?php echo $tkm_msg_voice; ?>
            <?php echo $tkm_msg_files; ?>
        </div>
    </div>
</article>
