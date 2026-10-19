<?php
defined('ABSPATH') || exit('NO Access');

if (!is_user_logged_in()) {
    include TKM_VIEWS_PATH . 'front/partials/login-required.php';
    return;
}

$department_manage  = new TKM_Front_Department_Manager();
$parent_departments = $department_manage->get_parent_department();
$ticket_manager     = new TKM_Ticket_Manager();
$current_user_id    = get_current_user_id();

$faqs     = tkm_settings('faq_swicher') ? tkm_settings('faqs') : [];
$faqs     = is_array($faqs) ? array_values(array_filter($faqs, function ($faq) {
    return !empty($faq['faqs-title']);
})) : [];
$has_faqs = !empty($faqs);

$label = function ($key, $default) {
    $value = trim((string) tkm_settings($key));
    return $value !== '' ? $value : $default;
};

$wc_products = [];
if (tkm_settings('product_setting')) {
    $wc_products = $ticket_manager->get_user_purchased_products($current_user_id);
}

$edd_products = [];
if (tkm_settings('edd_setting') && function_exists('edd_get_users_purchases')) {
    $payments = edd_get_users_purchases($current_user_id, -1);
    if ($payments) {
        foreach ($payments as $payment) {
            $cart_items = function_exists('edd_get_payment_meta_cart_details') ? edd_get_payment_meta_cart_details($payment->ID) : [];
            foreach ((array) $cart_items as $item) {
                if (!empty($item['id'])) {
                    $edd_products[(int) $item['id']] = get_the_title($item['id']);
                }
            }
        }
    }
}

$tkm_tools_id = 'tkm-new';
?>
<div class="tkm-ui tkm-app" data-view="new">

    <header class="tkm-pagehead">
        <a class="tkm-back" href="<?php echo esc_url(tkm_url('list')); ?>"><?php echo tkm_icon('arrow-right', 16); ?> همه تیکت‌ها</a>
        <h2 class="tkm-pagehead__title">ثبت تیکت جدید</h2>
        <p class="tkm-pagehead__desc">مشکل یا سوال خود را با جزئیات بنویسید تا سریع‌تر به نتیجه برسیم.</p>
    </header>

    <?php if (tkm_settings('new-ticket-alert') && trim((string) tkm_settings('alert-message-ticket')) !== '') : ?>
        <div class="tkm-alert tkm-alert--warning tkm-mb">
            <?php echo tkm_icon('info', 18); ?>
            <div><?php echo esc_html(tkm_settings('alert-message-ticket')); ?></div>
        </div>
    <?php endif; ?>

    <?php if ($has_faqs) : ?>
        <section class="tkm-card tkm-faq" data-faq>
            <div class="tkm-card__head">
                <div class="tkm-card__title"><?php echo tkm_icon('help', 18); ?> شاید جواب سوالتان اینجا باشد</div>
                <span class="tkm-card__sub">سوالات متداول</span>
            </div>
            <div class="tkm-faq__list">
                <?php foreach ($faqs as $i => $faq) : ?>
                    <details class="tkm-faq__item" <?php echo $i === 0 ? 'open' : ''; ?>>
                        <summary><span><?php echo esc_html($faq['faqs-title']); ?></span><?php echo tkm_icon('chevron-down', 18, 'tkm-faq__chev'); ?></summary>
                        <div class="tkm-faq__body"><?php echo wp_kses_post(wpautop(trim((string) $faq['faqs-body']))); ?></div>
                    </details>
                <?php endforeach; ?>
            </div>
            <div class="tkm-card__foot tkm-faq__foot">
                <span class="tkm-muted">جواب سوالتان را پیدا نکردید؟</span>
                <button type="button" class="tkm-btn tkm-btn--primary" data-faq-continue><?php echo tkm_icon('message', 16); ?> ثبت تیکت</button>
            </div>
        </section>
    <?php endif; ?>

    <?php if (count($parent_departments) > 0) : ?>
        <form id="tkm-submit-ticket" class="tkm-card tkm-form-card" method="post" enctype="multipart/form-data" novalidate
            data-redirect="<?php echo esc_url(tkm_url('list')); ?>" <?php echo $has_faqs ? 'hidden' : ''; ?>>

            <div class="tkm-form-section">
                <div class="tkm-form-section__head">
                    <span class="tkm-step">۱</span>
                    <div>
                        <h3><?php echo esc_html($label('department_label', 'دپارتمان')); ?></h3>
                        <p>درخواست شما به کدام بخش مربوط است؟</p>
                    </div>
                </div>

                <div class="tkm-tiles" role="radiogroup" aria-label="<?php echo esc_attr($label('department_label', 'دپارتمان')); ?>">
                    <?php foreach ($parent_departments as $item) :
                        $children = $department_manage->get_child_department($item->ID);
                    ?>
                        <label class="tkm-tile">
                            <input type="radio" name="tkm-parent-department" value="<?php echo esc_attr($item->ID); ?>" data-has-children="<?php echo $children ? '1' : '0'; ?>" data-description="<?php echo esc_attr(trim((string) $item->description)); ?>">
                            <span class="tkm-tile__box">
                                <span class="tkm-tile__icon"><?php echo tkm_icon('building', 18); ?></span>
                                <span class="tkm-tile__name"><?php echo esc_html($item->name); ?></span>
                                <span class="tkm-tile__check"><?php echo tkm_icon('check', 14); ?></span>
                            </span>
                        </label>
                    <?php endforeach; ?>
                </div>

                <div class="tkm-field tkm-subdeps" data-subdeps hidden>
                    <span class="tkm-label"><?php echo esc_html($label('type_label', 'نوع تیکت')); ?> <span class="tkm-req">*</span></span>
                    <?php foreach ($parent_departments as $parent_department) :
                        $children = $department_manage->get_child_department($parent_department->ID);
                        if (!$children) {
                            continue;
                        }
                    ?>
                        <div class="tkm-chips" data-parent="<?php echo esc_attr($parent_department->ID); ?>" hidden>
                            <?php foreach ($children as $child) : ?>
                                <label class="tkm-chip">
                                    <input type="radio" name="child-department" value="<?php echo esc_attr($child->ID); ?>" data-description="<?php echo esc_attr(trim((string) $child->description)); ?>">
                                    <span><?php echo esc_html($child->name); ?></span>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    <?php endforeach; ?>
                </div>

                <div class="tkm-alert tkm-alert--info" data-dep-note hidden>
                    <?php echo tkm_icon('info', 18); ?>
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
                        <label class="tkm-label" for="title-ticket"><?php echo esc_html($label('title_label', 'عنوان تیکت')); ?></label>
                        <input class="tkm-input" type="text" id="title-ticket" name="title_ticket" maxlength="250" placeholder="مثلا: خطا در فعال‌سازی لایسنس" autocomplete="off">
                    </div>

                    <?php if (tkm_settings('product_setting')) : ?>
                        <div class="tkm-field">
                            <label class="tkm-label" for="products"><?php echo esc_html($label('product_label', 'محصول')); ?></label>
                            <?php if ($wc_products) : ?>
                                <select name="user_purchased_products" class="tkm-select" id="products">
                                    <option value="">انتخاب محصول (اختیاری)</option>
                                    <?php foreach ($wc_products as $product_id => $product_name) : ?>
                                        <option value="<?php echo esc_attr($product_id); ?>"><?php echo esc_html($product_name); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            <?php else : ?>
                                <div class="tkm-inline-note"><?php echo tkm_icon('box', 16); ?> شما هنوز محصولی خریداری نکرده‌اید.</div>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>

                    <?php if (tkm_settings('edd_setting')) : ?>
                        <div class="tkm-field">
                            <label class="tkm-label" for="edd-products">محصول دیجیتال</label>
                            <?php if ($edd_products) : ?>
                                <select class="tkm-select" name="edd_purchased_products" id="edd-products">
                                    <option value="">انتخاب محصول دیجیتال (اختیاری)</option>
                                    <?php foreach ($edd_products as $product_id => $product_title) : ?>
                                        <option value="<?php echo esc_attr($product_id); ?>"><?php echo esc_html($product_title); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            <?php else : ?>
                                <div class="tkm-inline-note"><?php echo tkm_icon('box', 16); ?> شما هنوز محصول دیجیتالی خریداری نکرده‌اید.</div>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>

                    <div class="tkm-field tkm-col-span">
                        <span class="tkm-label"><?php echo esc_html($label('priority_label', 'اهمیت تیکت')); ?></span>
                        <div class="tkm-segmented tkm-segmented--priority" role="radiogroup">
                            <label><input type="radio" name="priority" value="low"><span><?php echo tkm_priority_badge('low'); ?></span></label>
                            <label><input type="radio" name="priority" value="medium" checked><span><?php echo tkm_priority_badge('medium'); ?></span></label>
                            <label><input type="radio" name="priority" value="high"><span><?php echo tkm_priority_badge('high'); ?></span></label>
                        </div>
                    </div>

                    <div class="tkm-field tkm-col-span">
                        <label class="tkm-label" for="ticket-content"><?php echo esc_html($label('dec_label', 'توضیحات')); ?> <span class="tkm-req">*</span></label>
                        <textarea class="tkm-textarea" id="ticket-content" name="content" rows="7" placeholder="مشکل را شرح دهید؛ مراحلی که انجام دادید، پیام خطا و هر جزئیاتی که کمک می‌کند…" data-autogrow></textarea>
                    </div>

                    <div class="tkm-field tkm-col-span">
                        <?php include TKM_VIEWS_PATH . 'front/partials/composer-tools.php'; ?>
                    </div>
                </div>
            </div>

            <div class="tkm-form-actions">
                <p class="tkm-hint"><?php echo tkm_icon('lock', 14); ?> اطلاعات شما فقط برای تیم پشتیبانی قابل مشاهده است.</p>
                <button type="submit" class="tkm-btn tkm-btn--primary tkm-btn--lg" data-submit>
                    <span class="tkm-spinner"></span>
                    <span class="tkm-btn__icon"><?php echo tkm_icon('send', 18); ?></span>
                    ارسال تیکت
                </button>
            </div>
        </form>
    <?php else : ?>
        <div class="tkm-card tkm-empty">
            <div class="tkm-empty__art"><?php echo tkm_icon('building', 30); ?></div>
            <h3 class="tkm-empty__title">هنوز دپارتمانی تعریف نشده است</h3>
            <p class="tkm-empty__text">در حال حاضر امکان ثبت تیکت وجود ندارد. لطفا بعدا دوباره تلاش کنید.</p>
        </div>
    <?php endif; ?>

    <?php include TKM_VIEWS_PATH . 'front/partials/voice-modal.php'; ?>
</div>
