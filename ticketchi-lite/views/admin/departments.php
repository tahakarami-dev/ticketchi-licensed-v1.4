<?php
/**
 * Departments: list + add / edit form.
 *
 * @var object[]    $departments
 * @var object|null $editing
 */
defined('ABSPATH') || exit;

use TicketchiLite\Departments;
use TicketchiLite\Promo;

$ticketchi_lite_kses = ticketchi_lite_allowed_html();
$ticketchi_lite_counts = Departments::ticket_counts();

$ticketchi_lite_parents = [];
$ticketchi_lite_children = [];
foreach ($departments as $ticketchi_lite_dep) {
    if ((int) $ticketchi_lite_dep->parent && Departments::find($ticketchi_lite_dep->parent)) {
        $ticketchi_lite_children[(int) $ticketchi_lite_dep->parent][] = $ticketchi_lite_dep;
    } else {
        $ticketchi_lite_parents[] = $ticketchi_lite_dep;
    }
}

$ticketchi_lite_row = function ($department, $is_child) use ($ticketchi_lite_counts, $ticketchi_lite_kses) {
    $c = isset($ticketchi_lite_counts[(int) $department->ID]) ? $ticketchi_lite_counts[(int) $department->ID] : ['total' => 0, 'open' => 0];
    $edit = ticketchi_lite_admin_url('ticketchi-lite-departments', ['action' => 'edit', 'id' => $department->ID]);
    $delete = wp_nonce_url(ticketchi_lite_admin_url('ticketchi-lite-departments', ['action' => 'delete', 'id' => $department->ID]), 'ticketchi_lite_delete_department');
    ?>
    <div class="tkm-dept <?php echo $is_child ? 'tkm-dept--child' : ''; ?>">
        <span class="tkm-dept__icon"><?php echo wp_kses(ticketchi_lite_icon($is_child ? 'tag' : 'building', 18), $ticketchi_lite_kses); ?></span>
        <div class="tkm-dept__main">
            <a class="tkm-dept__name" href="<?php echo esc_url($edit); ?>"><?php echo esc_html($department->name); ?></a>
            <?php if (trim((string) $department->description) !== '') : ?>
                <span class="tkm-dept__desc tkm-truncate"><?php echo esc_html($department->description); ?></span>
            <?php endif; ?>
        </div>
        <a class="tkm-dept__count" href="<?php echo esc_url(ticketchi_lite_admin_url('ticketchi-lite') . '#/department/' . (int) $department->ID); ?>" title="مشاهده تیکت‌ها">
            <strong class="tkm-num"><?php echo esc_html(ticketchi_lite_fa($c['total'])); ?></strong> تیکت
            <?php if ($c['open']) : ?><span class="tkm-badge tkm-badge--plain" style="--c: var(--tkm-success)"><?php echo esc_html(ticketchi_lite_fa($c['open'])); ?> باز</span><?php endif; ?>
        </a>
        <span class="tkm-dept__pos tkm-num" title="ترتیب نمایش"><?php echo esc_html(ticketchi_lite_fa($department->position)); ?></span>
        <div class="tkm-dept__actions">
            <a class="tkm-btn tkm-btn--ghost tkm-btn--sm tkm-btn--icon" href="<?php echo esc_url($edit); ?>" aria-label="ویرایش" title="ویرایش"><?php echo wp_kses(ticketchi_lite_icon('edit', 15), $ticketchi_lite_kses); ?></a>
            <a class="tkm-btn tkm-btn--ghost tkm-btn--sm tkm-btn--icon tkm-danger-btn" href="<?php echo esc_url($delete); ?>" data-confirm="<?php echo esc_attr('دپارتمان «' . $department->name . '» حذف شود؟ زیرمجموعه‌های آن به سطح اول منتقل می‌شوند.'); ?>" aria-label="حذف" title="حذف"><?php echo wp_kses(ticketchi_lite_icon('trash', 15), $ticketchi_lite_kses); ?></a>
        </div>
    </div>
    <?php
};

include TICKETCHI_LITE_PATH . 'views/admin/partials/header.php';
?>
<div class="tkm-head">
    <div class="tkm-head__text">
        <?php if ($editing) : ?>
            <a class="tkm-back" href="<?php echo esc_url(ticketchi_lite_admin_url('ticketchi-lite-departments')); ?>"><?php echo wp_kses(ticketchi_lite_icon('arrow-right', 15), $ticketchi_lite_kses); ?> بازگشت</a>
        <?php endif; ?>
        <h1 class="tkm-head__title"><?php echo $editing ? esc_html('ویرایش «' . $editing->name . '»') : 'دپارتمان‌ها'; ?></h1>
        <p class="tkm-head__desc">مشتری هنگام ثبت تیکت، دپارتمان و در صورت وجود زیرمجموعه آن را انتخاب می‌کند.</p>
    </div>
</div>

<div class="tkm-ws tkm-ws--reverse">
    <div class="tkm-ws__main">
        <section class="tkm-card">
            <div class="tkm-card__head">
                <div class="tkm-card__title"><?php echo wp_kses(ticketchi_lite_icon('layers', 18), $ticketchi_lite_kses); ?> همه دپارتمان‌ها <span class="tkm-badge tkm-badge--plain tkm-badge--outline tkm-num"><?php echo esc_html(ticketchi_lite_fa(count($departments))); ?></span></div>
            </div>
            <?php if ($departments) : ?>
                <div class="tkm-depts">
                    <?php foreach ($ticketchi_lite_parents as $ticketchi_lite_parent) : ?>
                        <div class="tkm-dept-group">
                            <?php
                            $ticketchi_lite_row($ticketchi_lite_parent, false);
                            foreach (isset($ticketchi_lite_children[(int) $ticketchi_lite_parent->ID]) ? $ticketchi_lite_children[(int) $ticketchi_lite_parent->ID] : [] as $ticketchi_lite_child) {
                                $ticketchi_lite_row($ticketchi_lite_child, true);
                            }
                            ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else : ?>
                <div class="tkm-empty">
                    <div class="tkm-empty__art"><?php echo wp_kses(ticketchi_lite_icon('building', 30), $ticketchi_lite_kses); ?></div>
                    <h3 class="tkm-empty__title">هنوز دپارتمانی ندارید</h3>
                    <p class="tkm-empty__text">اولین دپارتمان را از فرم کنار بسازید تا مشتری‌ها بتوانند تیکت ثبت کنند.</p>
                </div>
            <?php endif; ?>
        </section>

        <?php echo wp_kses(Promo::teaser('departments', 'کارشناس اختصاصی برای هر دپارتمان', 'در نسخه حرفه‌ای برای هر دپارتمان کارشناس تعیین کنید؛ تیکت‌ها خودکار بین آن‌ها تقسیم می‌شوند و هر کارشناس فقط صف خودش را می‌بیند.'), $ticketchi_lite_kses); ?>
    </div>

    <aside class="tkm-ws__side">
        <form class="tkm-card" method="post" action="<?php echo esc_url(ticketchi_lite_admin_url('ticketchi-lite-departments')); ?>">
            <?php wp_nonce_field('ticketchi_lite_department', 'ticketchi_lite_department_nonce'); ?>
            <?php if ($editing) : ?>
                <input type="hidden" name="department_id" value="<?php echo esc_attr($editing->ID); ?>">
            <?php endif; ?>
            <div class="tkm-card__head">
                <div class="tkm-card__title"><?php echo wp_kses(ticketchi_lite_icon($editing ? 'edit' : 'plus', 18), $ticketchi_lite_kses); ?> <?php echo $editing ? 'ویرایش دپارتمان' : 'دپارتمان جدید'; ?></div>
            </div>
            <div class="tkm-card__body tkm-stack">
                <div class="tkm-field">
                    <label class="tkm-label" for="tkm-dept-name">عنوان <span class="tkm-req">*</span></label>
                    <input class="tkm-input" id="tkm-dept-name" name="name" type="text" required value="<?php echo esc_attr($editing ? $editing->name : ''); ?>" placeholder="مثلا: پشتیبانی فنی">
                </div>
                <div class="tkm-field">
                    <label class="tkm-label" for="tkm-dept-parent">زیرمجموعه‌ی</label>
                    <select class="tkm-select" id="tkm-dept-parent" name="parent">
                        <option value="0">— سطح اول (بدون والد) —</option>
                        <?php foreach ($ticketchi_lite_parents as $ticketchi_lite_option) :
                            if ($editing && (int) $editing->ID === (int) $ticketchi_lite_option->ID) {
                                continue;
                            } ?>
                            <option value="<?php echo esc_attr($ticketchi_lite_option->ID); ?>" <?php selected($editing ? (int) $editing->parent : 0, (int) $ticketchi_lite_option->ID); ?>><?php echo esc_html($ticketchi_lite_option->name); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="tkm-field">
                    <label class="tkm-label" for="tkm-dept-position">ترتیب نمایش</label>
                    <input class="tkm-input tkm-input--short" id="tkm-dept-position" name="position" type="number" min="0" step="1" value="<?php echo esc_attr($editing ? $editing->position : 1); ?>">
                </div>
                <div class="tkm-field">
                    <label class="tkm-label" for="tkm-dept-desc">توضیح کوتاه</label>
                    <textarea class="tkm-textarea" id="tkm-dept-desc" name="description" rows="3" placeholder="هنگام انتخاب این دپارتمان به مشتری نمایش داده می‌شود"><?php echo esc_textarea($editing ? $editing->description : ''); ?></textarea>
                </div>
            </div>
            <div class="tkm-card__foot tkm-row">
                <button class="tkm-btn tkm-btn--primary tkm-grow" type="submit"><?php echo wp_kses(ticketchi_lite_icon('check', 16), $ticketchi_lite_kses); ?> <?php echo $editing ? 'ذخیره تغییرات' : 'افزودن دپارتمان'; ?></button>
                <?php if ($editing) : ?>
                    <a class="tkm-btn tkm-btn--ghost" href="<?php echo esc_url(ticketchi_lite_admin_url('ticketchi-lite-departments')); ?>">انصراف</a>
                <?php endif; ?>
            </div>
        </form>
    </aside>
</div>
<?php include TICKETCHI_LITE_PATH . 'views/admin/partials/footer.php'; ?>
