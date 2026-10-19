<?php
defined('ABSPATH') || exit('NO Access');

if (!is_user_logged_in()) {
    include TKM_VIEWS_PATH . 'front/partials/login-required.php';
    return;
}

$ticket_id      = isset($_GET['ticket-id']) ? absint($_GET['ticket-id']) : 0;
$ticket_manager = new TKM_Ticket_Manager();
$ticket         = $ticket_id ? $ticket_manager->get_info_ticket($ticket_id) : null;

if (!$ticket || !tkm_user_can_access_ticket($ticket)) : ?>
    <div class="tkm-ui tkm-app">
        <div class="tkm-card tkm-empty">
            <div class="tkm-empty__art"><?php echo tkm_icon('search', 30); ?></div>
            <h3 class="tkm-empty__title">تیکت پیدا نشد</h3>
            <p class="tkm-empty__text">این تیکت وجود ندارد یا شما به آن دسترسی ندارید.</p>
            <a class="tkm-btn tkm-btn--primary" href="<?php echo esc_url(tkm_url('list')); ?>"><?php echo tkm_icon('arrow-right', 16); ?> بازگشت به تیکت‌ها</a>
        </div>
    </div>
<?php
    return;
endif;

$department_manager = new TKM_Front_Department_Manager();
$department         = $department_manager->get_department($ticket->department_id);
$parent_department  = ($department && $department->parent) ? $department_manager->get_department($department->parent) : null;

$replies = (new TKM_Reply_Manager($ticket_id))->get_replys();

$product_info = (tkm_settings('product_setting') && $ticket->product) ? get_product_name_and_link_by_id($ticket->product) : null;
$edd_title    = (tkm_settings('edd_setting') && $ticket->edd_product) ? get_the_title($ticket->edd_product) : '';

$can_rate   = tkm_settings('rating_ticket') && (int) $ticket->creator_id === get_current_user_id();
$has_rated  = $can_rate && $ticket_manager->has_user_rated_ticket($ticket_id);
$rating     = $has_rated ? $ticket_manager->get_ticket_rating($ticket_id) : 0;
$is_final   = $ticket->status === 'finish';
$is_closed  = $ticket->status === 'closed';
$opener_is_support = (int) $ticket->from_admin === 1;

$tkm_tools_id = 'tkm-reply';
?>
<div class="tkm-ui tkm-app" data-view="single">

    <header class="tkm-pagehead tkm-pagehead--ticket">
        <a class="tkm-back" href="<?php echo esc_url(tkm_url('list')); ?>"><?php echo tkm_icon('arrow-right', 16); ?> همه تیکت‌ها</a>
        <div class="tkm-pagehead__row">
            <h2 class="tkm-pagehead__title"><?php echo esc_html($ticket->title); ?></h2>
            <span data-status-badge><?php echo tkm_status_badge($ticket->status); ?></span>
        </div>
        <div class="tkm-pagehead__meta">
            <span class="tkm-num"><?php echo tkm_icon('hash', 14); ?><?php echo esc_html(tkm_fa($ticket->ID)); ?></span>
            <span><?php echo tkm_icon('building', 14); ?><?php echo esc_html($department ? $department->name : '—'); ?></span>
            <span><?php echo tkm_icon('calendar', 14); ?><?php echo esc_html(tkm_date($ticket->create_date, false)); ?></span>
            <?php echo tkm_priority_badge($ticket->priority); ?>
        </div>
    </header>

    <div class="tkm-single">
        <div class="tkm-single__main">
            <section class="tkm-thread" aria-label="گفتگو">
                <?php
                $tkm_msg_author  = (int) $ticket->creator_id;
                $tkm_msg_body    = $ticket->body;
                $tkm_msg_date    = $ticket->create_date;
                $tkm_msg_files   = tkm_files_html($ticket->file);
                $tkm_msg_voice   = tkm_voice_html($ticket->voice);
                $tkm_msg_support = $opener_is_support;
                $tkm_msg_bot     = false;
                include TKM_VIEWS_PATH . 'front/partials/message.php';
                ?>
                <div class="tkm-thread__divider"><span><?php echo esc_html(count($replies) ? tkm_fa(count($replies)) . ' پاسخ' : 'پاسخ‌ها'); ?></span></div>
                <div class="tkm-thread__replies" data-replies>
                    <?php include TKM_VIEWS_PATH . 'front/reply.php'; ?>
                </div>
            </section>

            <?php if ($is_final) : ?>
                <div class="tkm-alert tkm-alert--success">
                    <?php echo tkm_icon('check-circle', 18); ?>
                    <div>این تیکت پایان یافته است. اگر سوال دیگری دارید، یک <a class="tkm-link" href="<?php echo esc_url(tkm_url('new')); ?>">تیکت جدید</a> ثبت کنید.</div>
                </div>
            <?php else : ?>
                <form id="reply-submit" class="tkm-card tkm-composer" method="post" enctype="multipart/form-data" novalidate>
                    <input type="hidden" name="ticket_id" value="<?php echo esc_attr($ticket->ID); ?>" id="ticket_id">
                    <?php if ($is_closed) : ?>
                        <div class="tkm-composer__note"><?php echo tkm_icon('info', 16); ?> این تیکت بسته شده است؛ با ارسال پاسخ، دوباره باز می‌شود.</div>
                    <?php endif; ?>
                    <div class="tkm-composer__box">
                        <?php echo tkm_avatar(get_current_user_id()); ?>
                        <label class="tkm-sr" for="body">متن پاسخ</label>
                        <textarea class="tkm-composer__input" id="body" name="body" rows="3" placeholder="پاسخ خود را بنویسید…" data-autogrow></textarea>
                    </div>
                    <?php include TKM_VIEWS_PATH . 'front/partials/composer-tools.php'; ?>
                    <div class="tkm-composer__foot">
                        <label class="tkm-switch">
                            <input type="checkbox" name="status" id="status" value="closed">
                            <span class="tkm-switch__track"></span>
                            مشکلم حل شد، تیکت بسته شود
                        </label>
                        <button class="tkm-btn tkm-btn--primary" type="submit" data-submit>
                            <span class="tkm-spinner"></span>
                            <span class="tkm-btn__icon"><?php echo tkm_icon('send', 16); ?></span>
                            ارسال پاسخ
                        </button>
                    </div>
                </form>
            <?php endif; ?>
        </div>

        <aside class="tkm-single__side">
            <section class="tkm-card">
                <div class="tkm-card__head"><div class="tkm-card__title"><?php echo tkm_icon('info', 18); ?> جزئیات تیکت</div></div>
                <dl class="tkm-kv">
                    <div><dt>شناسه</dt><dd class="tkm-num"><?php echo esc_html(tkm_ticket_number($ticket->ID)); ?></dd></div>
                    <div><dt>وضعیت</dt><dd data-status-badge><?php echo tkm_status_badge($ticket->status); ?></dd></div>
                    <div><dt>اولویت</dt><dd><?php echo tkm_priority_badge($ticket->priority); ?></dd></div>
                    <div><dt>دپارتمان</dt><dd><?php echo esc_html(($parent_department ? $parent_department->name . ' / ' : '') . ($department ? $department->name : '—')); ?></dd></div>
                    <?php if ($product_info) : ?>
                        <div><dt>محصول</dt><dd><?php if ($product_info['link']) : ?><a class="tkm-link" href="<?php echo esc_url($product_info['link']); ?>" target="_blank" rel="noopener"><?php echo esc_html($product_info['name']); ?></a><?php else : echo esc_html($product_info['name']); endif; ?></dd></div>
                    <?php endif; ?>
                    <?php if ($edd_title) : ?>
                        <div><dt>محصول دیجیتال</dt><dd><?php echo esc_html($edd_title); ?></dd></div>
                    <?php endif; ?>
                    <div><dt>ایجاد کننده</dt><dd class="tkm-row tkm-kv__user"><?php echo tkm_avatar($ticket->creator_id, 'sm'); ?><?php echo esc_html(tkm_user_name($ticket->creator_id)); ?></dd></div>
                    <div><dt>تاریخ ایجاد</dt><dd><?php echo esc_html(tkm_date($ticket->create_date)); ?></dd></div>
                    <div><dt>آخرین بروزرسانی</dt><dd><?php echo esc_html(tkm_date($ticket->reply_date)); ?></dd></div>
                </dl>
            </section>

            <?php if ($can_rate) : ?>
                <section class="tkm-card tkm-rate" data-rating-card>
                    <div class="tkm-card__body">
                        <?php if ($has_rated) : ?>
                            <h4 class="tkm-rate__title">امتیاز شما به این تیکت</h4>
                            <div class="tkm-stars tkm-stars--static" aria-label="<?php echo esc_attr(tkm_fa($rating) . ' از ۵'); ?>">
                                <?php for ($i = 1; $i <= 5; $i++) : ?>
                                    <span class="<?php echo $i <= $rating ? 'is-on' : ''; ?>"><?php echo tkm_icon('star', 22); ?></span>
                                <?php endfor; ?>
                            </div>
                            <p class="tkm-hint">ممنون که به بهبود پشتیبانی کمک کردید.</p>
                        <?php else : ?>
                            <h4 class="tkm-rate__title"><?php echo esc_html(tkm_settings('rating_ticket_title') ?: 'لطفا امتیاز تیکت را ثبت نمایید'); ?></h4>
                            <form class="tkm-rate__form" data-rating-form data-ticket="<?php echo esc_attr($ticket->ID); ?>">
                                <div class="tkm-stars" role="radiogroup" aria-label="امتیاز">
                                    <?php for ($i = 5; $i >= 1; $i--) : ?>
                                        <input type="radio" name="rating" id="tkm-star-<?php echo $i; ?>" value="<?php echo $i; ?>">
                                        <label for="tkm-star-<?php echo $i; ?>" title="<?php echo esc_attr(tkm_fa($i) . ' ستاره'); ?>"><?php echo tkm_icon('star', 26); ?></label>
                                    <?php endfor; ?>
                                </div>
                                <button type="submit" class="tkm-btn tkm-btn--soft tkm-btn--block" id="tkm-submit-rating" data-submit>
                                    <span class="tkm-spinner"></span>
                                    ثبت امتیاز
                                </button>
                            </form>
                        <?php endif; ?>
                    </div>
                </section>
            <?php endif; ?>
        </aside>
    </div>

    <?php if (!$is_final) {
        include TKM_VIEWS_PATH . 'front/partials/voice-modal.php';
    } ?>
</div>
