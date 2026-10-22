<?php
/**
 * Customer panel — new ticket form.
 */
defined('ABSPATH') || exit;

use TicketchiLite\Departments;
use TicketchiLite\Settings;

$ticketchi_lite_kses = ticketchi_lite_allowed_html();
$ticketchi_lite_parents = Departments::all();
?>
<div class="tkm-ui tkm-app" data-view="new">

    <header class="tkm-pagehead">
        <a class="tkm-back" href="<?php echo esc_url(ticketchi_lite_url('list')); ?>"><?php echo wp_kses(ticketchi_lite_icon('arrow-right', 16), $ticketchi_lite_kses); ?> همه تیکت‌ها</a>
        <h2 class="tkm-pagehead__title">ثبت تیکت جدید</h2>
        <p class="tkm-pagehead__desc">مشکل یا سوال خود را با جزئیات بنویسید تا سریع‌تر به نتیجه برسیم.</p>
    </header>

    <?php if (Settings::bool('new-ticket-alert') && trim((string) Settings::get('alert-message-ticket')) !== '') : ?>
        <div class="tkm-alert tkm-alert--warning tkm-mb">
            <?php echo wp_kses(ticketchi_lite_icon('info', 18), $ticketchi_lite_kses); ?>
            <div><?php echo esc_html(Settings::get('alert-message-ticket')); ?></div>
        </div>
    <?php endif; ?>

    <?php if ($ticketchi_lite_parents) : ?>
        <form id="tkm-submit-ticket" class="tkm-card tkm-form-card" method="post" enctype="multipart/form-data" novalidate data-redirect="<?php echo esc_url(ticketchi_lite_url('list')); ?>">

            <div class="tkm-form-section">
                <div class="tkm-form-section__head">
                    <span class="tkm-step">۱</span>
                    <div>
                        <h3>دپارتمان</h3>
                        <p>درخواست شما به کدام بخش مربوط است؟</p>
                    </div>
                </div>

                <div class="tkm-tiles" role="radiogroup" aria-label="دپارتمان">
                    <?php foreach ($ticketchi_lite_parents as $ticketchi_lite_dep) : ?>
                        <label class="tkm-tile">
                            <input type="radio" name="tkm-parent-department" value="<?php echo esc_attr($ticketchi_lite_dep->ID); ?>" data-description="<?php echo esc_attr(trim((string) $ticketchi_lite_dep->description)); ?>">
                            <span class="tkm-tile__box">
                                <span class="tkm-tile__icon"><?php echo wp_kses(ticketchi_lite_icon('building', 18), $ticketchi_lite_kses); ?></span>
                                <span class="tkm-tile__name"><?php echo esc_html($ticketchi_lite_dep->name); ?></span>
                                <span class="tkm-tile__check"><?php echo wp_kses(ticketchi_lite_icon('check', 14), $ticketchi_lite_kses); ?></span>
                            </span>
                        </label>
                    <?php endforeach; ?>
                </div>

                <div class="tkm-alert tkm-alert--info" data-dep-note hidden>
                    <?php echo wp_kses(ticketchi_lite_icon('info', 18), $ticketchi_lite_kses); ?>
                    <div data-dep-note-text></div>
                </div>
            </div>

            <div class="tkm-form-section">
                <div class="tkm-form-section__head">
                    <span class="tkm-step">۲</span>
                    <div>
                        <h3>جزئیات درخواست</h3>
                        <p>هرچه دقیق‌تر بنویسید، پاسخ سریع‌تر و دقیق‌تری می‌گیرید.</p>
                    </div>
                </div>

                <div class="tkm-grid-2">
                    <div class="tkm-field tkm-col-span">
                        <label class="tkm-label" for="title-ticket">عنوان تیکت</label>
                        <input class="tkm-input" type="text" id="title-ticket" name="title_ticket" maxlength="250" placeholder="مثلا: مشکل در پرداخت سفارش" autocomplete="off">
                    </div>

                    <div class="tkm-field tkm-col-span">
                        <label class="tkm-label" for="ticket-content">توضیحات <span class="tkm-req">*</span></label>
                        <textarea class="tkm-textarea" id="ticket-content" name="content" rows="7" placeholder="مشکل را شرح دهید؛ مراحلی که انجام دادید، پیام خطا و هر جزئیاتی که کمک می‌کند…" data-autogrow></textarea>
                    </div>

                </div>
            </div>

            <div class="tkm-form-actions">
                <p class="tkm-hint"><?php echo wp_kses(ticketchi_lite_icon('lock', 14), $ticketchi_lite_kses); ?> اطلاعات شما فقط برای تیم پشتیبانی قابل مشاهده است.</p>
                <button type="submit" class="tkm-btn tkm-btn--primary tkm-btn--lg" data-submit>
                    <span class="tkm-spinner"></span>
                    <span class="tkm-btn__icon"><?php echo wp_kses(ticketchi_lite_icon('send', 18), $ticketchi_lite_kses); ?></span>
                    ارسال تیکت
                </button>
            </div>
        </form>
    <?php else : ?>
        <div class="tkm-card tkm-empty">
            <div class="tkm-empty__art"><?php echo wp_kses(ticketchi_lite_icon('building', 30), $ticketchi_lite_kses); ?></div>
            <h3 class="tkm-empty__title">هنوز دپارتمانی تعریف نشده است</h3>
            <p class="tkm-empty__text">در حال حاضر امکان ثبت تیکت وجود ندارد. لطفا بعدا دوباره تلاش کنید.</p>
        </div>
    <?php endif; ?>
</div>
