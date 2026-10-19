<?php
/**
 * @var array    $parent_departments
 * @var object   $department_manager
 * @var int      $tkm_selected_department
 */
defined('ABSPATH') || exit('NO Access');
?>
<select class="tkm-select" name="department_id" id="tkm-department" required>
    <option value="">انتخاب دپارتمان…</option>
    <?php foreach ($parent_departments as $parent) :
        $children = $department_manager->get_child_department($parent->ID); ?>
        <?php if ($children) : ?>
            <optgroup label="<?php echo esc_attr($parent->name); ?>">
                <option value="<?php echo esc_attr($parent->ID); ?>" <?php selected($tkm_selected_department, $parent->ID); ?>><?php echo esc_html($parent->name); ?> (عمومی)</option>
                <?php foreach ($children as $child) : ?>
                    <option value="<?php echo esc_attr($child->ID); ?>" <?php selected($tkm_selected_department, $child->ID); ?>><?php echo esc_html($child->name); ?></option>
                <?php endforeach; ?>
            </optgroup>
        <?php else : ?>
            <option value="<?php echo esc_attr($parent->ID); ?>" <?php selected($tkm_selected_department, $parent->ID); ?>><?php echo esc_html($parent->name); ?></option>
        <?php endif; ?>
    <?php endforeach; ?>
</select>
