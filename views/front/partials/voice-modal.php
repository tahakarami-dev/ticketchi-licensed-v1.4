<?php defined('ABSPATH') || exit('NO Access'); ?>
<div class="tkm-modal" data-voice-modal hidden role="dialog" aria-modal="true" aria-labelledby="tkm-voice-title">
    <div class="tkm-modal__backdrop" data-voice-close></div>
    <div class="tkm-modal__dialog">
        <button type="button" class="tkm-btn tkm-btn--ghost tkm-btn--icon tkm-btn--sm tkm-modal__close" data-voice-close aria-label="بستن"><?php echo tkm_icon('x', 18); ?></button>
        <h3 class="tkm-modal__title" id="tkm-voice-title">ضبط پیام صوتی</h3>
        <p class="tkm-modal__text">روی دکمه بزنید و صحبت کنید. پس از پایان، پیام به تیکت پیوست می‌شود.</p>

        <div class="tkm-recorder" data-recorder>
            <button type="button" class="tkm-recorder__orb" data-rec-toggle aria-label="شروع ضبط">
                <span data-rec-icon-start><?php echo tkm_icon('mic', 34); ?></span>
                <span data-rec-icon-stop hidden><?php echo tkm_icon('square', 30); ?></span>
            </button>
            <div class="tkm-recorder__time" data-rec-time>00:00</div>
            <div class="tkm-recorder__status" data-rec-status>آماده ضبط</div>
            <audio controls data-rec-playback hidden></audio>
            <div class="tkm-recorder__actions" data-rec-actions hidden>
                <button type="button" class="tkm-btn" data-rec-discard><?php echo tkm_icon('trash', 16); ?> حذف و ضبط دوباره</button>
                <button type="button" class="tkm-btn tkm-btn--primary" data-rec-attach><?php echo tkm_icon('check', 16); ?> پیوست به پیام</button>
            </div>
        </div>
    </div>
</div>
