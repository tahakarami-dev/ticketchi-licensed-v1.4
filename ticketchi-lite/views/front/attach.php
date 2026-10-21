<?php
/**
 * Single attachment picker shared by the new-ticket and reply forms.
 *
 * @var string $ticketchi_lite_attach_id unique element id
 */
defined('ABSPATH') || exit;
?>
<div class="tkm-attach" data-attach>
    <label class="tkm-dropzone" data-dropzone>
        <input type="file" name="file" id="<?php echo esc_attr($ticketchi_lite_attach_id); ?>" accept="<?php echo esc_attr(\TicketchiLite\Uploads::accept_attr()); ?>" data-file-input>
        <span class="tkm-dropzone__icon"><?php echo wp_kses(ticketchi_lite_icon('upload', 20), ticketchi_lite_allowed_html()); ?></span>
        <span class="tkm-grow">
            <span class="tkm-dropzone__title">پیوست فایل — فایل را اینجا رها کنید یا کلیک کنید</span>
            <span class="tkm-dropzone__hint"><?php echo esc_html(\TicketchiLite\Uploads::hint()); ?></span>
        </span>
    </label>
    <div class="tkm-attach__bar">
        <div class="tkm-filelist" data-file-list></div>
    </div>
</div>
