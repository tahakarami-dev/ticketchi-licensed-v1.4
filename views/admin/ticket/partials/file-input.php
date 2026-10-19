<?php
/**
 * @var string $tkm_file_name  input name (without [])
 * @var string $tkm_file_label
 */
defined('ABSPATH') || exit('NO Access');
?>
<label class="tkm-dropzone tkm-dropzone--sm" data-admin-dropzone>
    <input type="file" name="<?php echo esc_attr($tkm_file_name); ?>[]" id="<?php echo esc_attr($tkm_file_name); ?>" <?php echo TKM_Upload_File::is_multiple() ? 'multiple' : ''; ?> accept="<?php echo esc_attr(tkm_get_accept_attr()); ?>">
    <span class="tkm-dropzone__icon"><?php echo tkm_icon('paperclip', 18); ?></span>
    <span class="tkm-grow">
        <span class="tkm-dropzone__title" data-dropzone-title><?php echo esc_html($tkm_file_label); ?></span>
        <span class="tkm-dropzone__hint"><?php echo esc_html(tkm_upload_hint_text()); ?></span>
    </span>
</label>
