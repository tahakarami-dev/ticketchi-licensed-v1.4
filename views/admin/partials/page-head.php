<?php
/**
 * Page heading used by every admin screen.
 *
 * @var string $tkm_head_title
 * @var string $tkm_head_desc    optional
 * @var string $tkm_head_actions optional, raw HTML
 * @var string $tkm_head_back    optional URL
 */
defined('ABSPATH') || exit('NO Access');
?>
<div class="tkm-head">
    <div class="tkm-head__text">
        <?php if (!empty($tkm_head_back)) : ?>
            <a class="tkm-back" href="<?php echo esc_url($tkm_head_back); ?>"><?php echo tkm_icon('arrow-right', 15); ?> بازگشت</a>
        <?php endif; ?>
        <h1 class="tkm-head__title"><?php echo wp_kses_post($tkm_head_title); ?></h1>
        <?php if (!empty($tkm_head_desc)) : ?>
            <p class="tkm-head__desc"><?php echo wp_kses_post($tkm_head_desc); ?></p>
        <?php endif; ?>
    </div>
    <?php if (!empty($tkm_head_actions)) : ?>
        <div class="tkm-head__actions"><?php echo $tkm_head_actions; ?></div>
    <?php endif; ?>
</div>
<?php
$tkm_head_title = $tkm_head_desc = $tkm_head_actions = $tkm_head_back = '';
