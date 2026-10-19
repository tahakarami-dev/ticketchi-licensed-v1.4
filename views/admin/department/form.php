<?php
/**
 * Department add / edit form.
 *
 * @var object|null $showdepartment
 * @var int[]       $answerable
 * @var array       $departments
 */
defined('ABSPATH') || exit('NO Access');

$is_edit = !empty($showdepartment);
?>
<form class="tkm-card" method="post" action="<?php echo esc_url(tkm_admin_url('tkm-departments')); ?>">
    <?php wp_nonce_field('add_department', 'add_department_nonce', false); ?>
    <?php if ($is_edit) : ?>
        <input type="hidden" name="department_id" value="<?php echo esc_attr($showdepartment->ID); ?>">
    <?php endif; ?>

    <div class="tkm-card__head">
        <div class="tkm-card__title"><?php echo tkm_icon($is_edit ? 'edit' : 'plus', 18); ?> <?php echo $is_edit ? 'ویرایش دپارتمان' : 'دپارتمان جدید'; ?></div>
    </div>
    <div class="tkm-card__body tkm-stack">
        <div class="tkm-field">
            <label class="tkm-label" for="tkm-dept-name">عنوان <span class="tkm-req">*</span></label>
            <input class="tkm-input" id="tkm-dept-name" name="name" type="text" required value="<?php echo esc_attr($is_edit ? $showdepartment->name : ''); ?>" placeholder="مثلا: پشتیبانی فنی">
        </div>
        <div class="tkm-field">
            <label class="tkm-label" for="tkm-dept-parent">زیرمجموعه‌ی</label>
            <select class="tkm-select" id="tkm-dept-parent" name="parent">
                <option value="0">— سطح اول (بدون والد) —</option>
                <?php foreach ($departments as $department) :
                    if ($department->parent || ($is_edit && (int) $showdepartment->ID === (int) $department->ID)) {
                        continue;
                    } ?>
                    <option value="<?php echo esc_attr($department->ID); ?>" <?php selected($is_edit ? (int) $showdepartment->parent : 0, (int) $department->ID); ?>><?php echo esc_html($department->name); ?></option>
                <?php endforeach; ?>
            </select>
            <span class="tkm-hint">کاربران ابتدا دپارتمان سطح اول و سپس زیرمجموعه را انتخاب می‌کنند.</span>
        </div>
        <div class="tkm-field">
            <label class="tkm-label" for="department-answerabel">کارشناسان پاسخگو</label>
            <select class="tkm-select tkm-user-search" name="department-answerabel[]" id="department-answerabel" multiple data-placeholder="جستجوی کاربر…">
                <?php foreach ((array) $answerable as $user_id) :
                    if (!get_userdata($user_id)) continue; ?>
                    <option value="<?php echo esc_attr($user_id); ?>" selected><?php echo esc_html(tkm_user_name($user_id)); ?></option>
                <?php endforeach; ?>
            </select>
            <span class="tkm-hint">کارشناسان، تیکت‌های این دپارتمان (و زیرمجموعه‌هایش) را در «صف من» می‌بینند.</span>
        </div>
        <div class="tkm-field">
            <label class="tkm-label" for="tkm-dept-position">ترتیب نمایش</label>
            <input class="tkm-input tkm-input--short" id="tkm-dept-position" name="position" type="number" min="0" step="1" value="<?php echo esc_attr($is_edit ? $showdepartment->position : 1); ?>">
        </div>
        <div class="tkm-field">
            <label class="tkm-label" for="tkm-dept-desc">توضیح کوتاه</label>
            <textarea class="tkm-textarea" id="tkm-dept-desc" name="description" rows="3" placeholder="هنگام انتخاب این دپارتمان به کاربر نمایش داده می‌شود"><?php echo esc_textarea($is_edit ? $showdepartment->description : ''); ?></textarea>
        </div>
    </div>
    <div class="tkm-card__foot tkm-row">
        <button class="tkm-btn tkm-btn--primary tkm-grow" type="submit" name="submit" id="submit"><?php echo tkm_icon('check', 16); ?> <?php echo $is_edit ? 'ذخیره تغییرات' : 'افزودن دپارتمان'; ?></button>
        <?php if ($is_edit) : ?>
            <a class="tkm-btn tkm-btn--ghost" href="<?php echo esc_url(tkm_admin_url('tkm-departments')); ?>">انصراف</a>
        <?php endif; ?>
    </div>
</form>
