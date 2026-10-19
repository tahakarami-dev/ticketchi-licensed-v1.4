<?php
defined('ABSPATH') || exit('NO Access');

$page          = 'tkm-tickets';
$department_id = isset($_GET['department_id']) ? absint($_GET['department_id']) : 0;
$priority      = isset($_GET['priority']) ? sanitize_key($_GET['priority']) : '';
$creator_id    = isset($_GET['creator_id']) ? absint($_GET['creator_id']) : 0;
$search        = isset($_GET['search']) ? sanitize_text_field(wp_unslash($_GET['search'])) : '';
$status        = isset($_GET['status']) ? sanitize_key($_GET['status']) : '';

$department_manager = new TKM_Admin_Department_Manager();
$parent_departments = $department_manager->get_parent_department();
$statuses = tkm_get_status();

$this->tickets_list->prepare_items();
$counts = $this->tickets_list->status_counts();
$all_count = array_sum($counts) - (isset($counts['trash']) ? $counts['trash'] : 0);
$has_filters = $department_id || $priority || $creator_id || $search !== '';

include TKM_VIEWS_PATH . 'admin/partials/header.php';

$tkm_head_title = 'تیکت‌ها';
$tkm_head_desc = $search !== ''
    ? 'نتایج جستجو برای «' . esc_html($search) . '» — ' . tkm_fa($this->tickets_list->get_total_items()) . ' مورد'
    : 'همه درخواست‌های پشتیبانی در یک صف';
$tkm_head_actions = '<a class="tkm-btn tkm-btn--primary" href="' . esc_url(tkm_admin_url('tkm-new-ticket')) . '">' . tkm_icon('send', 16) . ' ارسال تیکت به کاربر</a>';
include TKM_VIEWS_PATH . 'admin/partials/page-head.php';
?>

<nav class="tkm-tabs" aria-label="فیلتر وضعیت">
    <a class="tkm-tab <?php echo $status === '' ? 'is-active' : ''; ?>" href="<?php echo esc_url(tkm_admin_url($page)); ?>">
        همه <span class="tkm-tab__count tkm-num"><?php echo esc_html(tkm_fa($all_count)); ?></span>
    </a>
    <?php foreach ($statuses as $item) :
        $count = isset($counts[$item['slug']]) ? $counts[$item['slug']] : 0; ?>
        <a class="tkm-tab <?php echo $status === $item['slug'] ? 'is-active' : ''; ?> <?php echo $item['slug'] === 'trash' ? 'tkm-tab--trash' : ''; ?>" href="<?php echo esc_url(tkm_admin_url($page, ['status' => $item['slug']])); ?>" style="--c: <?php echo esc_attr($item['color'] ?: '#8a94a6'); ?>">
            <?php if ($item['slug'] === 'trash') : ?><?php echo tkm_icon('trash', 14); ?><?php else : ?><i class="tkm-swatch"></i><?php endif; ?>
            <?php echo esc_html(trim($item['name'])); ?>
            <span class="tkm-tab__count tkm-num"><?php echo esc_html(tkm_fa($count)); ?></span>
        </a>
    <?php endforeach; ?>
</nav>

<div class="tkm-card tkm-list-panel">
    <form class="tkm-filters" method="get">
        <input type="hidden" name="page" value="<?php echo esc_attr($page); ?>">
        <?php if ($status !== '') : ?><input type="hidden" name="status" value="<?php echo esc_attr($status); ?>"><?php endif; ?>

        <div class="tkm-input-icon tkm-filters__search">
            <?php echo tkm_icon('search', 16); ?>
            <input type="search" class="tkm-input" name="search" value="<?php echo esc_attr($search); ?>" placeholder="جستجو در عنوان، متن یا شناسه (#۱۲)…" aria-label="جستجو">
        </div>

        <select class="tkm-select" name="department_id" aria-label="دپارتمان">
            <option value="">همه دپارتمان‌ها</option>
            <?php foreach ($parent_departments as $parent) :
                $children = $department_manager->get_child_department($parent->ID); ?>
                <option value="<?php echo esc_attr($parent->ID); ?>" <?php selected($department_id, $parent->ID); ?>><?php echo esc_html($parent->name); ?></option>
                <?php foreach ($children as $child) : ?>
                    <option value="<?php echo esc_attr($child->ID); ?>" <?php selected($department_id, $child->ID); ?>>&nbsp;&nbsp;— <?php echo esc_html($child->name); ?></option>
                <?php endforeach; ?>
            <?php endforeach; ?>
        </select>

        <select class="tkm-select" name="priority" aria-label="اولویت">
            <option value="">همه اولویت‌ها</option>
            <option value="high" <?php selected($priority, 'high'); ?>>اولویت زیاد</option>
            <option value="medium" <?php selected($priority, 'medium'); ?>>اولویت متوسط</option>
            <option value="low" <?php selected($priority, 'low'); ?>>اولویت کم</option>
        </select>

        <select class="tkm-select tkm-user-search" id="ticket-creator" name="creator_id" data-placeholder="کاربر…" aria-label="کاربر">
            <option value="">همه کاربران</option>
            <?php if ($creator_id && get_userdata($creator_id)) : ?>
                <option value="<?php echo esc_attr($creator_id); ?>" selected><?php echo esc_html(tkm_user_name($creator_id)); ?></option>
            <?php endif; ?>
        </select>

        <button type="submit" class="tkm-btn"><?php echo tkm_icon('filter', 15); ?> اعمال</button>
        <?php if ($has_filters) : ?>
            <a class="tkm-btn tkm-btn--ghost" href="<?php echo esc_url(tkm_admin_url($page, $status !== '' ? ['status' => $status] : [])); ?>"><?php echo tkm_icon('x', 15); ?> پاک کردن</a>
        <?php endif; ?>
    </form>

    <form method="post" class="tkm-wptable">
        <?php $this->tickets_list->display(); ?>
    </form>
</div>

<?php include TKM_VIEWS_PATH . 'admin/partials/footer.php'; ?>
