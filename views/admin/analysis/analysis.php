<?php
defined('ABSPATH') || exit('NO Access');

$ticket_analysis = new TKM_Analysis();
$days      = 30;
$kpi       = $ticket_analysis->kpis($days);
$daily     = $ticket_analysis->daily_counts($days);
$by_status = $ticket_analysis->status_counts();
$by_prio   = $ticket_analysis->priority_counts();
$by_dept   = $ticket_analysis->department_counts(6);
$recent    = $ticket_analysis->recent_tickets(6);
$ratings   = $ticket_analysis->rating_distribution();
$employees = $ticket_analysis->get_employee_ticket_stats();
$statuses  = array_filter(tkm_get_status(), function ($s) {
    return $s['slug'] !== 'trash';
});

$jalali = function ($ymd, $format = 'j F') {
    $ts = strtotime($ymd . ' 12:00:00');
    return class_exists('\Morilog\Jalali\Jalalian') ? tkm_fa(\Morilog\Jalali\Jalalian::forge($ts)->format($format)) : tkm_fa(date_i18n('j M', $ts));
};

// KPI delta vs the previous period.
$delta = null;
if ($kpi['created_prev'] > 0) {
    $delta = (int) round(($kpi['created'] - $kpi['created_prev']) / $kpi['created_prev'] * 100);
}

/* ── Daily column chart geometry ── */
$chart_w = 720;
$chart_h = 220;
$pad_l = 34;   // y labels (left side in the SVG's LTR coordinate space)
$pad_b = 26;
$pad_t = 12;
$plot_w = $chart_w - $pad_l - 8;
$plot_h = $chart_h - $pad_b - $pad_t;
$max = max(1, max($daily));
$step = $max <= 4 ? 1 : (int) ceil($max / 4);
$y_max = $step * (int) ceil($max / $step);
$slot = $plot_w / count($daily);
$bar_w = min(24, max(4, $slot - 6));

$current_user = wp_get_current_user();

include TKM_VIEWS_PATH . 'admin/partials/header.php';

$tkm_head_title = 'سلام ' . esc_html($current_user->display_name) . '، خوش آمدید';
$tkm_head_desc = 'نمای کلی پشتیبانی در ' . tkm_fa($days) . ' روز اخیر';
$tkm_head_actions = '<a class="tkm-btn" href="' . esc_url(tkm_admin_url('tkm-tickets', ['status' => 'open'])) . '">' . tkm_icon('inbox', 16) . ' تیکت‌های باز</a>';
include TKM_VIEWS_PATH . 'admin/partials/page-head.php';
?>

<section class="tkm-kpis">
    <article class="tkm-kpi">
        <div class="tkm-kpi__top"><span class="tkm-kpi__label">تیکت‌های جدید</span><span class="tkm-kpi__icon"><?php echo tkm_icon('ticket', 18); ?></span></div>
        <div class="tkm-kpi__value"><?php echo esc_html(tkm_fa($kpi['created'])); ?></div>
        <div class="tkm-kpi__foot">
            <?php if ($delta !== null) : ?>
                <span class="tkm-delta <?php echo $delta > 0 ? 'is-up' : ($delta < 0 ? 'is-down' : ''); ?>"><?php echo tkm_icon($delta >= 0 ? 'trending-up' : 'chart', 13); ?><?php echo esc_html(($delta > 0 ? '+' : '') . tkm_fa($delta) . '٪'); ?></span>
                <span>نسبت به ۳۰ روز قبل</span>
            <?php else : ?>
                <span>از مجموع <?php echo esc_html(tkm_fa($kpi['total'])); ?> تیکت</span>
            <?php endif; ?>
        </div>
    </article>

    <article class="tkm-kpi">
        <div class="tkm-kpi__top"><span class="tkm-kpi__label">در انتظار پاسخ</span><span class="tkm-kpi__icon" style="--c: var(--tkm-warning)"><?php echo tkm_icon('clock', 18); ?></span></div>
        <div class="tkm-kpi__value"><?php echo esc_html(tkm_fa($kpi['open'])); ?></div>
        <div class="tkm-kpi__foot">
            <?php if ($kpi['high_open']) : ?>
                <a class="tkm-delta is-alert" href="<?php echo esc_url(tkm_admin_url('tkm-tickets', ['status' => 'open', 'priority' => 'high'])); ?>"><?php echo tkm_icon('alert', 13); ?><?php echo esc_html(tkm_fa($kpi['high_open'])); ?> فوری</a>
                <span>با اولویت زیاد</span>
            <?php else : ?>
                <span>هیچ تیکت فوری در صف نیست</span>
            <?php endif; ?>
        </div>
    </article>

    <article class="tkm-kpi">
        <div class="tkm-kpi__top"><span class="tkm-kpi__label">میانگین زمان اولین پاسخ</span><span class="tkm-kpi__icon" style="--c: var(--tkm-info)"><?php echo tkm_icon('zap', 18); ?></span></div>
        <div class="tkm-kpi__value tkm-kpi__value--text"><?php echo esc_html($kpi['first_response'] === null ? '—' : TKM_Analysis::duration($kpi['first_response'])); ?></div>
        <div class="tkm-kpi__foot"><span><?php echo esc_html(tkm_fa($kpi['replies'])); ?> پاسخ در ۳۰ روز اخیر</span></div>
    </article>

    <article class="tkm-kpi">
        <div class="tkm-kpi__top"><span class="tkm-kpi__label">رضایت کاربران</span><span class="tkm-kpi__icon" style="--c: #f5a524"><?php echo tkm_icon('star', 18); ?></span></div>
        <div class="tkm-kpi__value"><?php echo $kpi['rating'] === null ? '—' : esc_html(tkm_fa($kpi['rating'])) . '<small> / ۵</small>'; ?></div>
        <div class="tkm-kpi__foot"><span class="tkm-delta"><?php echo esc_html(TKM_Analysis::satisfaction_label($kpi['rating'])); ?></span><span>از <?php echo esc_html(tkm_fa($kpi['rating_count'])); ?> امتیاز</span></div>
    </article>
</section>

<section class="tkm-grid tkm-grid--dash">
    <article class="tkm-card tkm-span-2">
        <div class="tkm-card__head">
            <div>
                <div class="tkm-card__title">تیکت‌های ثبت‌شده در هر روز</div>
                <div class="tkm-card__sub"><?php echo esc_html($jalali(array_key_first($daily)) . ' تا ' . $jalali(array_key_last($daily))); ?></div>
            </div>
            <span class="tkm-badge tkm-badge--plain tkm-badge--outline tkm-num"><?php echo esc_html(tkm_fa(array_sum($daily))); ?> تیکت</span>
        </div>
        <div class="tkm-card__body">
            <figure class="tkm-chart" data-chart>
                <svg viewBox="0 0 <?php echo $chart_w; ?> <?php echo $chart_h; ?>" role="img" aria-label="نمودار ستونی تعداد تیکت‌های روزانه" direction="ltr">
                    <?php for ($v = 0; $v <= $y_max; $v += $step) :
                        $y = $pad_t + $plot_h - ($v / $y_max) * $plot_h; ?>
                        <line class="tkm-chart__grid" x1="<?php echo $pad_l; ?>" x2="<?php echo $chart_w - 8; ?>" y1="<?php echo round($y, 1); ?>" y2="<?php echo round($y, 1); ?>"></line>
                        <text class="tkm-chart__tick" x="<?php echo $pad_l - 8; ?>" y="<?php echo round($y + 4, 1); ?>" text-anchor="end"><?php echo esc_html(tkm_fa($v)); ?></text>
                    <?php endfor; ?>

                    <?php $i = 0;
                    foreach ($daily as $day => $count) :
                        // Time flows right-to-left (RTL reading order): oldest day on the right, today on the left.
                        $cx = $pad_l + $plot_w - ($i + 0.5) * $slot;
                        $h = ($count / $y_max) * $plot_h;
                        $x = $cx - $bar_w / 2;
                        $y = $pad_t + $plot_h - $h;
                        $r = min(4, $h, $bar_w / 2);
                        $i++;
                    ?>
                        <g class="tkm-chart__col" data-tip="<?php echo esc_attr($jalali($day, 'l j F')); ?>" data-value="<?php echo esc_attr(tkm_fa($count) . ' تیکت'); ?>">
                            <rect class="tkm-chart__hit" x="<?php echo round($cx - $slot / 2, 2); ?>" y="<?php echo $pad_t; ?>" width="<?php echo round($slot, 2); ?>" height="<?php echo $plot_h; ?>"></rect>
                            <?php if ($count > 0) : ?>
                                <path class="tkm-chart__bar" d="M<?php echo round($x, 2); ?>,<?php echo round($pad_t + $plot_h, 2); ?> V<?php echo round($y + $r, 2); ?> Q<?php echo round($x, 2); ?>,<?php echo round($y, 2); ?> <?php echo round($x + $r, 2); ?>,<?php echo round($y, 2); ?> H<?php echo round($x + $bar_w - $r, 2); ?> Q<?php echo round($x + $bar_w, 2); ?>,<?php echo round($y, 2); ?> <?php echo round($x + $bar_w, 2); ?>,<?php echo round($y + $r, 2); ?> V<?php echo round($pad_t + $plot_h, 2); ?> Z"></path>
                            <?php endif; ?>
                            <?php if (($i - 1) % 7 === 0) : ?>
                                <text class="tkm-chart__tick" x="<?php echo round($cx, 2); ?>" y="<?php echo $chart_h - 6; ?>" text-anchor="middle"><?php echo esc_html($jalali($day)); ?></text>
                            <?php endif; ?>
                        </g>
                    <?php endforeach; ?>
                    <line class="tkm-chart__axis" x1="<?php echo $pad_l; ?>" x2="<?php echo $chart_w - 8; ?>" y1="<?php echo $pad_t + $plot_h; ?>" y2="<?php echo $pad_t + $plot_h; ?>"></line>
                </svg>
                <div class="tkm-chart__tooltip" hidden></div>
            </figure>
            <details class="tkm-table-view">
                <summary><?php echo tkm_icon('list', 14); ?> نمایش جدول داده‌ها</summary>
                <table class="tkm-mini-table">
                    <thead><tr><th>تاریخ</th><th>تعداد تیکت</th></tr></thead>
                    <tbody>
                        <?php foreach (array_reverse($daily, true) as $day => $count) : ?>
                            <tr><td><?php echo esc_html($jalali($day, 'l j F Y')); ?></td><td class="tkm-num"><?php echo esc_html(tkm_fa($count)); ?></td></tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </details>
        </div>
    </article>

    <article class="tkm-card">
        <div class="tkm-card__head"><div><div class="tkm-card__title">توزیع وضعیت‌ها</div><div class="tkm-card__sub">همه تیکت‌ها</div></div></div>
        <div class="tkm-card__body">
            <?php $status_max = max(1, $by_status ? max($by_status) : 1); ?>
            <ul class="tkm-hbars">
                <?php foreach ($statuses as $status) :
                    $count = isset($by_status[$status['slug']]) ? $by_status[$status['slug']] : 0; ?>
                    <li>
                        <a class="tkm-hbar" href="<?php echo esc_url(tkm_admin_url('tkm-tickets', ['status' => $status['slug']])); ?>" title="<?php echo esc_attr(trim($status['name']) . ': ' . tkm_fa($count)); ?>">
                            <span class="tkm-hbar__label"><i class="tkm-swatch" style="--c: <?php echo esc_attr($status['color'] ?: '#8a94a6'); ?>"></i><?php echo esc_html(trim($status['name'])); ?></span>
                            <span class="tkm-hbar__value tkm-num"><?php echo esc_html(tkm_fa($count)); ?></span>
                            <span class="tkm-hbar__track"><span class="tkm-hbar__fill" style="--c: <?php echo esc_attr($status['color'] ?: '#8a94a6'); ?>; width: <?php echo $count ? max(2, round($count / $status_max * 100, 1)) : 0; ?>%"></span></span>
                        </a>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>
    </article>

    <article class="tkm-card">
        <div class="tkm-card__head"><div><div class="tkm-card__title">اولویت تیکت‌های فعال</div><div class="tkm-card__sub">باز، پاسخ داده‌شده و سایر وضعیت‌های جاری</div></div></div>
        <div class="tkm-card__body">
            <?php $prio_total = array_sum($by_prio);
            $prio_names = ['high' => 'زیاد', 'medium' => 'متوسط', 'low' => 'کم']; ?>
            <div class="tkm-stack-bar" role="img" aria-label="سهم اولویت‌ها">
                <?php foreach ($by_prio as $p => $count) :
                    if (!$count) continue; ?>
                    <span class="tkm-stack-bar__seg tkm-seg--<?php echo esc_attr($p); ?>" style="flex-grow: <?php echo (int) $count; ?>" title="<?php echo esc_attr($prio_names[$p] . ': ' . tkm_fa($count)); ?>"></span>
                <?php endforeach; ?>
                <?php if (!$prio_total) : ?><span class="tkm-stack-bar__empty"></span><?php endif; ?>
            </div>
            <ul class="tkm-legend">
                <?php foreach ($by_prio as $p => $count) : ?>
                    <li>
                        <a href="<?php echo esc_url(tkm_admin_url('tkm-tickets', ['priority' => $p])); ?>">
                            <i class="tkm-swatch tkm-seg--<?php echo esc_attr($p); ?>"></i>
                            <span>اولویت <?php echo esc_html($prio_names[$p]); ?></span>
                            <strong class="tkm-num"><?php echo esc_html(tkm_fa($count)); ?></strong>
                            <em><?php echo $prio_total ? esc_html(tkm_fa(round($count / $prio_total * 100)) . '٪') : '—'; ?></em>
                        </a>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>
    </article>

    <article class="tkm-card">
        <div class="tkm-card__head"><div><div class="tkm-card__title">پرکارترین دپارتمان‌ها</div><div class="tkm-card__sub">بر اساس تعداد تیکت</div></div></div>
        <div class="tkm-card__body">
            <?php if ($by_dept) :
                $dept_max = max(1, (int) $by_dept[0]->total); ?>
                <ul class="tkm-hbars">
                    <?php foreach ($by_dept as $dept) : ?>
                        <li>
                            <a class="tkm-hbar" href="<?php echo esc_url(tkm_admin_url('tkm-tickets', ['department_id' => $dept->ID])); ?>">
                                <span class="tkm-hbar__label"><?php echo esc_html($dept->name); ?></span>
                                <span class="tkm-hbar__value tkm-num"><?php echo esc_html(tkm_fa($dept->total)); ?><?php if ((int) $dept->open_count) : ?> <small>(<?php echo esc_html(tkm_fa($dept->open_count)); ?> باز)</small><?php endif; ?></span>
                                <span class="tkm-hbar__track"><span class="tkm-hbar__fill" style="width: <?php echo round($dept->total / $dept_max * 100, 1); ?>%"></span></span>
                            </a>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php else : ?>
                <p class="tkm-muted">هنوز داده‌ای ثبت نشده است.</p>
            <?php endif; ?>
        </div>
    </article>

    <article class="tkm-card">
        <div class="tkm-card__head"><div><div class="tkm-card__title">امتیاز کاربران</div><div class="tkm-card__sub">توزیع امتیازهای ثبت‌شده</div></div></div>
        <div class="tkm-card__body tkm-rating-summary">
            <div class="tkm-rating-summary__big">
                <strong><?php echo $kpi['rating'] === null ? '—' : esc_html(tkm_fa($kpi['rating'])); ?></strong>
                <div class="tkm-stars tkm-stars--static">
                    <?php for ($s = 1; $s <= 5; $s++) : ?>
                        <span class="<?php echo $kpi['rating'] !== null && $s <= round($kpi['rating']) ? 'is-on' : ''; ?>"><?php echo tkm_icon('star', 14); ?></span>
                    <?php endfor; ?>
                </div>
                <small><?php echo esc_html(tkm_fa($kpi['rating_count'])); ?> امتیاز</small>
            </div>
            <ul class="tkm-rating-rows">
                <?php $rating_max = max(1, max($ratings));
                foreach ($ratings as $star => $count) : ?>
                    <li title="<?php echo esc_attr(tkm_fa($star) . ' ستاره: ' . tkm_fa($count)); ?>">
                        <span class="tkm-num"><?php echo esc_html(tkm_fa($star)); ?></span>
                        <span class="tkm-hbar__track"><span class="tkm-hbar__fill tkm-hbar__fill--star" style="width: <?php echo round($count / $rating_max * 100, 1); ?>%"></span></span>
                        <span class="tkm-num tkm-muted"><?php echo esc_html(tkm_fa($count)); ?></span>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>
    </article>

    <article class="tkm-card tkm-span-2">
        <div class="tkm-card__head">
            <div><div class="tkm-card__title">عملکرد کارشناسان</div><div class="tkm-card__sub">کارمندان پاسخگوی دپارتمان‌ها</div></div>
            <a class="tkm-btn tkm-btn--ghost tkm-btn--sm" href="<?php echo esc_url(tkm_admin_url('tkm-departments')); ?>">مدیریت دپارتمان‌ها <?php echo tkm_icon('chevron-left', 14); ?></a>
        </div>
        <div class="tkm-table-wrap">
            <table class="tkm-table">
                <thead>
                    <tr><th>کارشناس</th><th>تیکت‌ها</th><th>باز</th><th>پاسخ داده‌شده</th><th>پاسخ‌های ارسالی</th><th>میانگین امتیاز</th></tr>
                </thead>
                <tbody>
                    <?php if ($employees) : ?>
                        <?php foreach ($employees as $employee) : ?>
                            <tr>
                                <td><span class="tkm-user-cell"><?php echo tkm_avatar($employee->user_id, 'sm'); ?><span class="tkm-user-cell__name"><?php echo esc_html(tkm_user_name($employee->user_id)); ?></span></span></td>
                                <td class="tkm-num"><?php echo esc_html(tkm_fa($employee->ticket_count)); ?></td>
                                <td class="tkm-num"><?php echo esc_html(tkm_fa($employee->open_ticket_count)); ?></td>
                                <td class="tkm-num"><?php echo esc_html(tkm_fa($employee->answered_ticket_count)); ?></td>
                                <td class="tkm-num"><?php echo esc_html(tkm_fa($employee->reply_count)); ?></td>
                                <td><?php if ($employee->avg_rating === null) : ?><span class="tkm-muted">—</span><?php else : ?><span class="tkm-rating-pill"><?php echo tkm_icon('star', 12); ?><?php echo esc_html(tkm_fa($employee->avg_rating)); ?></span><?php endif; ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else : ?>
                        <tr><td colspan="6"><div class="tkm-empty tkm-empty--compact"><p class="tkm-empty__text">هنوز کارشناسی به دپارتمان‌ها اختصاص داده نشده است.</p></div></td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </article>

    <article class="tkm-card">
        <div class="tkm-card__head">
            <div><div class="tkm-card__title">آخرین فعالیت‌ها</div><div class="tkm-card__sub">تیکت‌هایی که اخیرا به‌روز شده‌اند</div></div>
        </div>
        <ul class="tkm-activity">
            <?php foreach ($recent as $item) : ?>
                <li>
                    <a href="<?php echo esc_url(tkm_admin_url('tkm-edit-ticket', ['id' => $item->ID])); ?>">
                        <?php echo tkm_avatar((int) $item->from_admin === 1 && $item->user_id ? $item->user_id : $item->creator_id, 'sm'); ?>
                        <span class="tkm-grow">
                            <span class="tkm-activity__title tkm-truncate"><?php echo esc_html($item->title); ?></span>
                            <span class="tkm-activity__meta"><?php echo esc_html(tkm_ticket_number($item->ID) . ' · ' . tkm_time_ago($item->reply_date)); ?></span>
                        </span>
                        <?php echo tkm_status_badge($item->status); ?>
                    </a>
                </li>
            <?php endforeach; ?>
            <?php if (!$recent) : ?>
                <li class="tkm-empty tkm-empty--compact"><p class="tkm-empty__text">هنوز تیکتی ثبت نشده است.</p></li>
            <?php endif; ?>
        </ul>
    </article>
</section>

<?php include TKM_VIEWS_PATH . 'admin/partials/footer.php'; ?>
