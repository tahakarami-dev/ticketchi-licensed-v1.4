<?php
/**
 * [ticketchi_kb] help center.
 *
 * @var string        $q        current search
 * @var WP_Term[]     $cats     top-level categories
 * @var array[]       $results  search results (cards)
 */
defined('ABSPATH') || exit('NO Access');

use Ticketchi\Kb\KnowledgeBase;

$tkm_kb_new_ticket = add_query_arg('action', 'new', \Ticketchi\Support\Links::customer_list());
$tkm_kb_base = remove_query_arg(['kb', 'kb_cat']);
$tkm_kb_cat = isset($_GET['kb_cat']) ? absint($_GET['kb_cat']) : 0;
$tkm_kb_cats = is_wp_error($cats) ? [] : $cats;

$tkm_kb_list = [];
if ($q === '' && $tkm_kb_cat) {
    $tkm_kb_list = array_map([KnowledgeBase::class, 'card'], get_posts([
        'post_type'      => KnowledgeBase::TYPE,
        'post_status'    => 'publish',
        'posts_per_page' => 50,
        'tax_query'      => [['taxonomy' => KnowledgeBase::TAX, 'terms' => $tkm_kb_cat]],
    ]));
}
$tkm_kb_recent = $q === '' && !$tkm_kb_cat ? array_map([KnowledgeBase::class, 'card'], get_posts([
    'post_type'      => KnowledgeBase::TYPE,
    'post_status'    => 'publish',
    'posts_per_page' => 6,
])) : [];
?>
<div class="tkm-ui tkm-app tkm-kb" data-view="kb">
    <section class="tkm-kb__hero">
        <h2 class="tkm-kb__title">چطور می‌توانیم کمک کنیم؟</h2>
        <p class="tkm-kb__lead">پاسخ پرتکرارترین سوال‌ها را این‌جا پیدا کنید؛ اگر نبود، تیم پشتیبانی کنار شماست.</p>
        <form class="tkm-kb__search" method="get" action="<?php echo esc_url($tkm_kb_base); ?>" role="search">
            <?php echo tkm_icon('search', 20); ?>
            <input type="search" name="kb" value="<?php echo esc_attr($q); ?>" placeholder="مثلاً: فعال‌سازی لایسنس" aria-label="جستجو در راهنما" class="tkm-kb__input">
            <button type="submit" class="tkm-btn tkm-btn--primary">جستجو</button>
        </form>
    </section>

    <?php if ($q !== '') : ?>
        <section class="tkm-card tkm-kb__results">
            <header class="tkm-card__head"><div class="tkm-card__title"><?php echo esc_html(sprintf('نتایج «%s»', $q)); ?> <span class="tkm-badge tkm-badge--plain tkm-num"><?php echo esc_html(tkm_fa(count($results))); ?></span></div>
                <a class="tkm-btn tkm-btn--ghost tkm-btn--sm" href="<?php echo esc_url($tkm_kb_base); ?>">همه دسته‌ها</a></header>
            <?php if ($results) : ?>
                <ul class="tkm-kb__list">
                    <?php foreach ($results as $tkm_kb_a) : ?>
                        <li><a href="<?php echo esc_url($tkm_kb_a['url']); ?>"><strong><?php echo esc_html($tkm_kb_a['title']); ?></strong><span><?php echo esc_html($tkm_kb_a['excerpt']); ?></span><?php echo tkm_icon('chevron-left', 18); ?></a></li>
                    <?php endforeach; ?>
                </ul>
            <?php else : ?>
                <div class="tkm-empty">
                    <div class="tkm-empty__art"><?php echo tkm_icon('search', 28); ?></div>
                    <h3 class="tkm-empty__title">مقاله‌ای پیدا نشد</h3>
                    <p class="tkm-empty__text">عبارت دیگری را امتحان کنید یا مستقیم از تیم پشتیبانی بپرسید.</p>
                </div>
            <?php endif; ?>
        </section>
    <?php elseif ($tkm_kb_cat) :
        $tkm_kb_term = get_term($tkm_kb_cat, KnowledgeBase::TAX); ?>
        <section class="tkm-card tkm-kb__results">
            <header class="tkm-card__head"><div class="tkm-card__title"><?php echo esc_html($tkm_kb_term && !is_wp_error($tkm_kb_term) ? $tkm_kb_term->name : 'دسته'); ?></div>
                <a class="tkm-btn tkm-btn--ghost tkm-btn--sm" href="<?php echo esc_url($tkm_kb_base); ?>">همه دسته‌ها</a></header>
            <?php if ($tkm_kb_list) : ?>
                <ul class="tkm-kb__list">
                    <?php foreach ($tkm_kb_list as $tkm_kb_a) : ?>
                        <li><a href="<?php echo esc_url($tkm_kb_a['url']); ?>"><strong><?php echo esc_html($tkm_kb_a['title']); ?></strong><span><?php echo esc_html($tkm_kb_a['excerpt']); ?></span><?php echo tkm_icon('chevron-left', 18); ?></a></li>
                    <?php endforeach; ?>
                </ul>
            <?php else : ?>
                <p class="tkm-card__body tkm-muted">در این دسته هنوز مقاله‌ای منتشر نشده است.</p>
            <?php endif; ?>
        </section>
    <?php else : ?>
        <?php if ($tkm_kb_cats) : ?>
            <section class="tkm-kb__cats" aria-label="دسته‌های راهنما">
                <?php foreach ($tkm_kb_cats as $tkm_kb_c) : ?>
                    <a class="tkm-card tkm-kb__cat" href="<?php echo esc_url(add_query_arg('kb_cat', $tkm_kb_c->term_id, $tkm_kb_base)); ?>">
                        <span class="tkm-kb__cat-icon"><?php echo tkm_icon('book', 20); ?></span>
                        <strong><?php echo esc_html($tkm_kb_c->name); ?></strong>
                        <?php if ($tkm_kb_c->description) : ?><span class="tkm-kb__cat-desc"><?php echo esc_html($tkm_kb_c->description); ?></span><?php endif; ?>
                        <span class="tkm-kb__cat-count tkm-num"><?php echo esc_html(tkm_fa($tkm_kb_c->count)); ?> مقاله</span>
                    </a>
                <?php endforeach; ?>
            </section>
        <?php endif; ?>
        <?php if ($tkm_kb_recent) : ?>
            <section class="tkm-card tkm-kb__results">
                <header class="tkm-card__head"><div class="tkm-card__title">مقاله‌های تازه</div></header>
                <ul class="tkm-kb__list">
                    <?php foreach ($tkm_kb_recent as $tkm_kb_a) : ?>
                        <li><a href="<?php echo esc_url($tkm_kb_a['url']); ?>"><strong><?php echo esc_html($tkm_kb_a['title']); ?></strong><span><?php echo esc_html($tkm_kb_a['excerpt']); ?></span><?php echo tkm_icon('chevron-left', 18); ?></a></li>
                    <?php endforeach; ?>
                </ul>
            </section>
        <?php elseif (!$tkm_kb_cats) : ?>
            <div class="tkm-card tkm-empty">
                <div class="tkm-empty__art"><?php echo tkm_icon('book', 28); ?></div>
                <h3 class="tkm-empty__title">مرکز راهنما به‌زودی تکمیل می‌شود</h3>
                <p class="tkm-empty__text">تا آن زمان سوالتان را از طریق تیکت بپرسید.</p>
            </div>
        <?php endif; ?>
    <?php endif; ?>

    <section class="tkm-kb__cta">
        <div><strong>جوابتان را پیدا نکردید؟</strong><span>کارشناسان ما معمولاً در کمتر از چند ساعت پاسخ می‌دهند.</span></div>
        <a class="tkm-btn tkm-btn--primary" href="<?php echo esc_url($tkm_kb_new_ticket); ?>"><?php echo tkm_icon('plus', 16); ?> ثبت تیکت پشتیبانی</a>
    </section>
</div>
