<?php
/**
 * «نسخه حرفه‌ای» — what Ticketchi Pro adds, with a Lite / Pro comparison.
 */
defined('ABSPATH') || exit;

use TicketchiLite\Promo;

$ticketchi_lite_kses = ticketchi_lite_allowed_html();
include TICKETCHI_LITE_PATH . 'views/admin/partials/header.php';
?>
<div class="tkm-pro">
    <section class="tkm-pro-hero">
        <div class="tkm-pro-hero__text">
            <span class="tkm-pro-badge"><?php echo wp_kses(ticketchi_lite_icon('crown', 15), $ticketchi_lite_kses); ?> تیکت‌چی حرفه‌ای</span>
            <h1>پشتیبانی تیمی، سریع و قابل اندازه‌گیری</h1>
            <p>نسخه لایت برای شروع عالی است. وقتی تیکت‌ها زیاد شدند و چند نفر پاسخ می‌دهند، نسخه حرفه‌ای تیکت‌چی کار را تقسیم می‌کند، مهلت پاسخ را زیر نظر می‌گیرد، کارهای تکراری را خودکار انجام می‌دهد و با گزارش‌ها نشان می‌دهد پشتیبانی شما چطور کار می‌کند.</p>
            <div class="tkm-pro-hero__actions">
                <a class="tkm-btn tkm-btn--primary tkm-btn--lg" href="<?php echo esc_url(Promo::url('pro-page-hero')); ?>" target="_blank" rel="noopener"><?php echo wp_kses(ticketchi_lite_icon('crown', 18), $ticketchi_lite_kses); ?> خرید نسخه حرفه‌ای</a>
                <a class="tkm-btn tkm-btn--lg" href="#tkm-pro-compare">مقایسه نسخه‌ها</a>
            </div>
            <ul class="tkm-pro-trust">
                <li><?php echo wp_kses(ticketchi_lite_icon('check-circle', 16), $ticketchi_lite_kses); ?> همه تیکت‌ها و تنظیمات فعلی حفظ می‌شوند</li>
                <li><?php echo wp_kses(ticketchi_lite_icon('check-circle', 16), $ticketchi_lite_kses); ?> نصب در یک دقیقه، بدون تنظیم دوباره</li>
                <li><?php echo wp_kses(ticketchi_lite_icon('check-circle', 16), $ticketchi_lite_kses); ?> پشتیبانی و بروزرسانی از طرف سازنده</li>
            </ul>
        </div>
        <div class="tkm-pro-hero__art" aria-hidden="true">
            <div class="tkm-pro-mock">
                <div class="tkm-pro-mock__row"><span class="tkm-pro-mock__dot" style="--c:#16a34a"></span><span class="tkm-pro-mock__bar" style="width:70%"></span><span class="tkm-pro-mock__chip">SLA ۲ ساعت</span></div>
                <div class="tkm-pro-mock__row"><span class="tkm-pro-mock__dot" style="--c:#f59e0b"></span><span class="tkm-pro-mock__bar" style="width:55%"></span><span class="tkm-pro-mock__chip">سپرده به علی</span></div>
                <div class="tkm-pro-mock__row"><span class="tkm-pro-mock__dot" style="--c:#ef4444"></span><span class="tkm-pro-mock__bar" style="width:80%"></span><span class="tkm-pro-mock__chip">VIP</span></div>
                <div class="tkm-pro-mock__chart"><i style="height:40%"></i><i style="height:65%"></i><i style="height:50%"></i><i style="height:85%"></i><i style="height:70%"></i><i style="height:95%"></i><i style="height:60%"></i></div>
            </div>
        </div>
    </section>

    <section class="tkm-pro-grid" aria-label="امکانات نسخه حرفه‌ای">
        <?php foreach (Promo::features() as $ticketchi_lite_feature) : ?>
            <article class="tkm-card tkm-pro-feature" id="pro-<?php echo esc_attr($ticketchi_lite_feature['key']); ?>">
                <span class="tkm-pro-feature__icon"><?php echo wp_kses(ticketchi_lite_icon($ticketchi_lite_feature['icon'], 20), $ticketchi_lite_kses); ?></span>
                <h3><?php echo esc_html($ticketchi_lite_feature['title']); ?></h3>
                <p><?php echo esc_html($ticketchi_lite_feature['text']); ?></p>
            </article>
        <?php endforeach; ?>
    </section>

    <section class="tkm-card tkm-pro-compare" id="tkm-pro-compare">
        <div class="tkm-card__head"><div class="tkm-card__title"><?php echo wp_kses(ticketchi_lite_icon('layers', 18), $ticketchi_lite_kses); ?> مقایسه لایت و حرفه‌ای</div></div>
        <table class="tkm-table">
            <thead><tr><th>امکان</th><th>لایت (رایگان)</th><th>حرفه‌ای</th></tr></thead>
            <tbody>
                <?php foreach (Promo::comparison() as $ticketchi_lite_row) : ?>
                    <tr>
                        <td><?php echo esc_html($ticketchi_lite_row[0]); ?></td>
                        <?php foreach ([$ticketchi_lite_row[1], $ticketchi_lite_row[2]] as $ticketchi_lite_cell) : ?>
                            <td>
                                <?php if ($ticketchi_lite_cell === true) : ?>
                                    <span class="tkm-pro-yes"><?php echo wp_kses(ticketchi_lite_icon('check', 16), $ticketchi_lite_kses); ?></span>
                                <?php elseif ($ticketchi_lite_cell === false) : ?>
                                    <span class="tkm-pro-no">—</span>
                                <?php else : ?>
                                    <?php echo esc_html($ticketchi_lite_cell); ?>
                                <?php endif; ?>
                            </td>
                        <?php endforeach; ?>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </section>

    <section class="tkm-card tkm-pro-steps">
        <div class="tkm-card__head"><div class="tkm-card__title"><?php echo wp_kses(ticketchi_lite_icon('refresh', 18), $ticketchi_lite_kses); ?> ارتقا در سه قدم</div></div>
        <ol>
            <li>نسخه حرفه‌ای را از راست‌چین تهیه و نصب کنید.</li>
            <li>نسخه حرفه‌ای را فعال کنید؛ تیکت‌ها، دپارتمان‌ها و تنظیمات خودکار منتقل می‌شوند.</li>
            <li>تیکت‌چی لایت را غیرفعال و حذف کنید.</li>
        </ol>
    </section>

    <section class="tkm-pro-final">
        <h2>تیم پشتیبانی شما سزاوار ابزار بهتری است</h2>
        <a class="tkm-btn tkm-btn--primary tkm-btn--lg" href="<?php echo esc_url(Promo::url('pro-page-footer')); ?>" target="_blank" rel="noopener"><?php echo wp_kses(ticketchi_lite_icon('crown', 18), $ticketchi_lite_kses); ?> مشاهده و خرید تیکت‌چی حرفه‌ای</a>
    </section>
</div>
<?php include TICKETCHI_LITE_PATH . 'views/admin/partials/footer.php'; ?>
