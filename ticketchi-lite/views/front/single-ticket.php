<?php
/**
 * Customer panel — one conversation.
 */
defined('ABSPATH') || exit;

use TicketchiLite\Departments;
use TicketchiLite\Tickets;

$ticketchi_lite_kses = ticketchi_lite_allowed_html();
// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only.
$ticketchi_lite_id = isset($_GET['ticket-id']) ? absint($_GET['ticket-id']) : 0;
$ticket = $ticketchi_lite_id ? Tickets::find($ticketchi_lite_id) : null;

if (!$ticket || $ticket->status === 'trash' || !ticketchi_lite_user_can_access_ticket($ticket)) : ?>
    <div class="tkm-ui tkm-app">
        <div class="tkm-card tkm-empty">
            <div class="tkm-empty__art"><?php echo wp_kses(ticketchi_lite_icon('search', 30), $ticketchi_lite_kses); ?></div>
            <h3 class="tkm-empty__title">تیکت پیدا نشد</h3>
            <p class="tkm-empty__text">این تیکت وجود ندارد یا شما به آن دسترسی ندارید.</p>
            <a class="tkm-btn tkm-btn--primary" href="<?php echo esc_url(ticketchi_lite_url('list')); ?>"><?php echo wp_kses(ticketchi_lite_icon('arrow-right', 16), $ticketchi_lite_kses); ?> بازگشت به تیکت‌ها</a>
        </div>
    </div>
    <?php
    return;
endif;

$ticketchi_lite_dep = Departments::find($ticket->department_id);
$ticketchi_lite_parent = ($ticketchi_lite_dep && $ticketchi_lite_dep->parent) ? Departments::find($ticketchi_lite_dep->parent) : null;
$replies = Tickets::replies($ticket->ID);
$ticketchi_lite_final = $ticket->status === 'finish';
$ticketchi_lite_attach_id = 'tkm-reply-file';
?>
<div class="tkm-ui tkm-app" data-view="single">

    <header class="tkm-pagehead tkm-pagehead--ticket">
        <a class="tkm-back" href="<?php echo esc_url(ticketchi_lite_url('list')); ?>"><?php echo wp_kses(ticketchi_lite_icon('arrow-right', 16), $ticketchi_lite_kses); ?> همه تیکت‌ها</a>
        <div class="tkm-pagehead__row">
            <h2 class="tkm-pagehead__title"><?php echo esc_html($ticket->title); ?></h2>
            <span data-status-badge><?php echo wp_kses(ticketchi_lite_status_badge($ticket->status), $ticketchi_lite_kses); ?></span>
        </div>
        <div class="tkm-pagehead__meta">
            <span class="tkm-num"><?php echo wp_kses(ticketchi_lite_icon('hash', 14), $ticketchi_lite_kses); ?><?php echo esc_html(ticketchi_lite_fa($ticket->ID)); ?></span>
            <span><?php echo wp_kses(ticketchi_lite_icon('building', 14), $ticketchi_lite_kses); ?><?php echo esc_html($ticketchi_lite_dep ? $ticketchi_lite_dep->name : '—'); ?></span>
            <span><?php echo wp_kses(ticketchi_lite_icon('calendar', 14), $ticketchi_lite_kses); ?><?php echo esc_html(ticketchi_lite_date($ticket->create_date, false)); ?></span>
            <?php echo wp_kses(ticketchi_lite_priority_badge($ticket->priority), $ticketchi_lite_kses); ?>
        </div>
    </header>

    <div class="tkm-single">
        <div class="tkm-single__main">
            <section class="tkm-thread" aria-label="گفتگو">
                <?php
                $ticketchi_lite_msg_author = (int) $ticket->creator_id;
                $ticketchi_lite_msg_body = $ticket->body;
                $ticketchi_lite_msg_date = $ticket->create_date;
                $ticketchi_lite_msg_file = $ticket->file;
                $ticketchi_lite_msg_voice = $ticket->voice;
                $ticketchi_lite_msg_support = (int) $ticket->from_admin === 1;
                include TICKETCHI_LITE_PATH . 'views/front/message.php';
                ?>
                <div class="tkm-thread__divider"><span><?php echo esc_html(count($replies) ? ticketchi_lite_fa(count($replies)) . ' پاسخ' : 'پاسخ‌ها'); ?></span></div>
                <div class="tkm-thread__replies" data-replies>
                    <?php include TICKETCHI_LITE_PATH . 'views/front/replies.php'; ?>
                </div>
            </section>

            <?php if ($ticketchi_lite_final) : ?>
                <div class="tkm-alert tkm-alert--success">
                    <?php echo wp_kses(ticketchi_lite_icon('check-circle', 18), $ticketchi_lite_kses); ?>
                    <div>این تیکت پایان یافته است. اگر سوال دیگری دارید، یک <a class="tkm-link" href="<?php echo esc_url(ticketchi_lite_url('new')); ?>">تیکت جدید</a> ثبت کنید.</div>
                </div>
            <?php else : ?>
                <form id="reply-submit" class="tkm-card tkm-composer" method="post" enctype="multipart/form-data" novalidate>
                    <input type="hidden" name="ticket_id" value="<?php echo esc_attr($ticket->ID); ?>" id="ticket_id">
                    <?php if ($ticket->status === 'closed') : ?>
                        <div class="tkm-composer__note"><?php echo wp_kses(ticketchi_lite_icon('info', 16), $ticketchi_lite_kses); ?> این تیکت بسته شده است؛ با ارسال پاسخ، دوباره باز می‌شود.</div>
                    <?php endif; ?>
                    <div class="tkm-composer__box">
                        <?php echo wp_kses(ticketchi_lite_avatar(get_current_user_id()), $ticketchi_lite_kses); ?>
                        <label class="tkm-sr" for="body">متن پاسخ</label>
                        <textarea class="tkm-composer__input" id="body" name="body" rows="3" placeholder="پاسخ خود را بنویسید…" data-autogrow></textarea>
                    </div>
                    <?php include TICKETCHI_LITE_PATH . 'views/front/attach.php'; ?>
                    <div class="tkm-composer__foot">
                        <label class="tkm-switch">
                            <input type="checkbox" name="status" id="status" value="closed">
                            <span class="tkm-switch__track"></span>
                            مشکلم حل شد، تیکت بسته شود
                        </label>
                        <button class="tkm-btn tkm-btn--primary" type="submit" data-submit>
                            <span class="tkm-spinner"></span>
                            <span class="tkm-btn__icon"><?php echo wp_kses(ticketchi_lite_icon('send', 16), $ticketchi_lite_kses); ?></span>
                            ارسال پاسخ
                        </button>
                    </div>
                </form>
            <?php endif; ?>
        </div>

        <aside class="tkm-single__side">
            <section class="tkm-card">
                <div class="tkm-card__head"><div class="tkm-card__title"><?php echo wp_kses(ticketchi_lite_icon('info', 18), $ticketchi_lite_kses); ?> جزئیات تیکت</div></div>
                <dl class="tkm-kv">
                    <div><dt>شناسه</dt><dd class="tkm-num"><?php echo esc_html(ticketchi_lite_ticket_number($ticket->ID)); ?></dd></div>
                    <div><dt>وضعیت</dt><dd data-status-badge><?php echo wp_kses(ticketchi_lite_status_badge($ticket->status), $ticketchi_lite_kses); ?></dd></div>
                    <div><dt>اولویت</dt><dd><?php echo wp_kses(ticketchi_lite_priority_badge($ticket->priority), $ticketchi_lite_kses); ?></dd></div>
                    <div><dt>دپارتمان</dt><dd><?php echo esc_html(($ticketchi_lite_parent ? $ticketchi_lite_parent->name . ' / ' : '') . ($ticketchi_lite_dep ? $ticketchi_lite_dep->name : '—')); ?></dd></div>
                    <div><dt>ایجاد کننده</dt><dd class="tkm-row tkm-kv__user"><?php echo wp_kses(ticketchi_lite_avatar($ticket->creator_id, 'sm'), $ticketchi_lite_kses); ?><?php echo esc_html(ticketchi_lite_user_name($ticket->creator_id)); ?></dd></div>
                    <div><dt>تاریخ ایجاد</dt><dd><?php echo esc_html(ticketchi_lite_date($ticket->create_date)); ?></dd></div>
                    <div><dt>آخرین بروزرسانی</dt><dd><?php echo esc_html(ticketchi_lite_date($ticket->reply_date)); ?></dd></div>
                </dl>
            </section>
        </aside>
    </div>
</div>
