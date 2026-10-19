<?php
/**
 * @var array                         $departments
 * @var TKM_Admin_Department_Manager  $this
 * @var TKM_Answerable_Mnager         $answerable_manager
 */
defined('ABSPATH') || exit('NO Access');

$counts = $this->ticket_counts();
$parents = array_filter($departments, function ($d) {
    return !(int) $d->parent;
});
$children = [];
foreach ($departments as $d) {
    if ((int) $d->parent) {
        $children[(int) $d->parent][] = $d;
    }
}
// Departments whose parent no longer exists are shown as top-level.
foreach ($children as $parent_id => $list) {
    $exists = false;
    foreach ($parents as $p) {
        if ((int) $p->ID === $parent_id) {
            $exists = true;
        }
    }
    if (!$exists) {
        $parents = array_merge($parents, $list);
        unset($children[$parent_id]);
    }
}

$staff_html = function ($department_id) use ($answerable_manager) {
    $ids = $answerable_manager->get_by_department($department_id);
    if (!$ids) {
        return '<span class="tkm-muted tkm-small">بدون کارشناس</span>';
    }
    $html = '<span class="tkm-avatars">';
    foreach (array_slice($ids, 0, 4) as $id) {
        $html .= tkm_avatar($id, 'sm');
    }
    if (count($ids) > 4) {
        $html .= '<span class="tkm-avatar tkm-avatar--sm tkm-avatar--more">+' . tkm_fa(count($ids) - 4) . '</span>';
    }
    return $html . '</span>';
};

$row = function ($department, $is_child = false) use ($counts, $staff_html) {
    $c = isset($counts[(int) $department->ID]) ? $counts[(int) $department->ID] : ['total' => 0, 'open' => 0];
    $edit = tkm_admin_url('tkm-departments', ['action' => 'edit', 'id' => $department->ID]);
    $delete = wp_nonce_url(tkm_admin_url('tkm-departments', ['action' => 'delete', 'id' => $department->ID]), 'delete_department', 'delete_department_nonce');
    ob_start(); ?>
    <div class="tkm-dept <?php echo $is_child ? 'tkm-dept--child' : ''; ?>">
        <span class="tkm-dept__icon"><?php echo tkm_icon($is_child ? 'tag' : 'building', 18); ?></span>
        <div class="tkm-dept__main">
            <a class="tkm-dept__name" href="<?php echo esc_url($edit); ?>"><?php echo esc_html($department->name); ?></a>
            <?php if (trim((string) $department->description) !== '') : ?>
                <span class="tkm-dept__desc tkm-truncate"><?php echo esc_html($department->description); ?></span>
            <?php endif; ?>
        </div>
        <div class="tkm-dept__staff"><?php echo $staff_html($department->ID); ?></div>
        <a class="tkm-dept__count" href="<?php echo esc_url(tkm_admin_url('tkm-tickets', ['department_id' => $department->ID])); ?>" title="مشاهده تیکت‌ها">
            <strong class="tkm-num"><?php echo esc_html(tkm_fa($c['total'])); ?></strong> تیکت
            <?php if ($c['open']) : ?><span class="tkm-badge tkm-badge--plain" style="--c: var(--tkm-success)"><?php echo esc_html(tkm_fa($c['open'])); ?> باز</span><?php endif; ?>
        </a>
        <span class="tkm-dept__pos tkm-num" title="ترتیب نمایش"><?php echo esc_html(tkm_fa($department->position)); ?></span>
        <div class="tkm-dept__actions">
            <a class="tkm-btn tkm-btn--ghost tkm-btn--sm tkm-btn--icon" href="<?php echo esc_url($edit); ?>" aria-label="ویرایش" title="ویرایش"><?php echo tkm_icon('edit', 15); ?></a>
            <a class="tkm-btn tkm-btn--ghost tkm-btn--sm tkm-btn--icon tkm-danger-btn" href="<?php echo esc_url($delete); ?>" data-confirm="دپارتمان «<?php echo esc_attr($department->name); ?>» حذف شود؟ زیرمجموعه‌های آن به سطح اول منتقل می‌شوند." aria-label="حذف" title="حذف"><?php echo tkm_icon('trash', 15); ?></a>
        </div>
    </div>
<?php
    return ob_get_clean();
};

include TKM_VIEWS_PATH . 'admin/partials/header.php';

$tkm_head_title = 'دپارتمان‌ها';
$tkm_head_desc = 'ساختار تیم پشتیبانی، زیرمجموعه‌ها و کارشناسان پاسخگو';
include TKM_VIEWS_PATH . 'admin/partials/page-head.php';
?>
<div class="tkm-ws tkm-ws--reverse">
    <div class="tkm-ws__main">
        <section class="tkm-card">
            <div class="tkm-card__head">
                <div class="tkm-card__title"><?php echo tkm_icon('layers', 18); ?> همه دپارتمان‌ها <span class="tkm-badge tkm-badge--plain tkm-badge--outline tkm-num"><?php echo esc_html(tkm_fa(count($departments))); ?></span></div>
            </div>
            <?php if ($departments) : ?>
                <div class="tkm-depts">
                    <?php foreach ($parents as $parent) : ?>
                        <div class="tkm-dept-group">
                            <?php echo $row($parent); ?>
                            <?php foreach (isset($children[(int) $parent->ID]) ? $children[(int) $parent->ID] : [] as $child) : ?>
                                <?php echo $row($child, true); ?>
                            <?php endforeach; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else : ?>
                <div class="tkm-empty">
                    <div class="tkm-empty__art"><?php echo tkm_icon('building', 30); ?></div>
                    <h3 class="tkm-empty__title">هنوز دپارتمانی ندارید</h3>
                    <p class="tkm-empty__text">اولین دپارتمان را از فرم کنار بسازید تا کاربران بتوانند تیکت ثبت کنند.</p>
                </div>
            <?php endif; ?>
        </section>

        <section class="tkm-card" id="tkm-team-agents">
            <header class="tkm-card__head"><div><h2 class="tkm-card__title">کارشناسان و حجم کار</h2><p class="tkm-card__sub">تیکت‌های باز سپرده‌شده به هر کارشناس</p></div></header>
            <div class="tkm-card__body" data-agents><span class="tkm-spinner"></span></div>
        </section>

        <section class="tkm-card" id="tkm-tag-manager">
            <header class="tkm-card__head"><div><h2 class="tkm-card__title">برچسب‌ها</h2><p class="tkm-card__sub">برای دسته‌بندی تیکت‌ها، فیلتر صندوق و قوانین اتوماسیون</p></div></header>
            <div class="tkm-card__body">
                <form class="tkm-tagm__new" data-tag-new>
                    <input type="color" class="tkm-tagm__color" name="color" value="#6366f1" aria-label="رنگ برچسب">
                    <input type="text" class="tkm-input" name="name" maxlength="64" placeholder="نام برچسب جدید، مثلاً: VIP" aria-label="نام برچسب" required>
                    <button type="submit" class="tkm-btn tkm-btn--primary"><?php echo tkm_icon('plus', 16); ?> افزودن</button>
                </form>
                <div class="tkm-tagm__list" data-tags><span class="tkm-spinner"></span></div>
            </div>
        </section>
    </div>

    <aside class="tkm-ws__side">
        <?php
        $showdepartment = null;
        $answerable = [];
        include TKM_VIEWS_PATH . 'admin/department/form.php';
        ?>
    </aside>
</div>
<?php include TKM_VIEWS_PATH . 'admin/partials/footer.php'; ?>
