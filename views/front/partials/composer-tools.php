<?php
/**
 * Attachments + voice note controls shared by the new-ticket and reply forms.
 *
 * @var string $tkm_tools_id Unique prefix for element ids.
 */
defined('ABSPATH') || exit('NO Access');

$tkm_multiple = TKM_Upload_File::is_multiple();
?>
<div class="tkm-attach" data-attach>
    <label class="tkm-dropzone" data-dropzone>
        <input type="file" name="file[]" id="<?php echo esc_attr($tkm_tools_id); ?>-files" <?php echo $tkm_multiple ? 'multiple' : ''; ?> accept="<?php echo esc_attr(tkm_get_accept_attr()); ?>" data-file-input>
        <span class="tkm-dropzone__icon"><?php echo tkm_icon('upload', 20); ?></span>
        <span class="tkm-grow">
            <span class="tkm-dropzone__title"><?php echo esc_html(trim(tkm_settings('file_label') ?: 'آپلود فایل')); ?> — فایل را اینجا رها کنید یا کلیک کنید</span>
            <span class="tkm-dropzone__hint"><?php echo esc_html(tkm_upload_hint_text()); ?></span>
        </span>
    </label>

    <div class="tkm-attach__bar">
        <div class="tkm-filelist" data-file-list></div>
        <div class="tkm-voice-chip" data-voice-chip hidden>
            <?php echo tkm_icon('mic', 14); ?> <span data-voice-label>پیام صوتی</span>
            <button type="button" data-voice-remove aria-label="حذف پیام صوتی"><?php echo tkm_icon('x', 14); ?></button>
        </div>
        <button type="button" class="tkm-btn tkm-btn--soft tkm-btn--sm tkm-attach__voice" data-voice-open>
            <?php echo tkm_icon('mic', 16); ?> <?php echo esc_html(trim(tkm_settings('voice_label') ?: 'ارسال صدا')); ?>
        </button>
    </div>
    <input type="hidden" name="audioData" data-voice-input value="">
</div>
