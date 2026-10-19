<?php
/**
 * Seeds demo data for the local test site.
 * Run with: wp eval-file tests/seed.php
 */

global $wpdb;
$p = $wpdb->prefix;

foreach (['tkm_tickets', 'tkm_replies', 'tkm_departments', 'tkm_users', 'tkm_ratings', 'tkm_tags', 'tkm_ticket_tags', 'tkm_activity', 'tkm_notifications', 'tkm_automations'] as $t) {
    $wpdb->query("DELETE FROM {$p}{$t}");
}

function tkm_seed_user($login, $name, $role)
{
    $user = get_user_by('login', $login);
    if ($user) {
        return $user->ID;
    }
    $id = wp_insert_user([
        'user_login'   => $login,
        'user_pass'    => $login,
        'user_email'   => $login . '@example.com',
        'display_name' => $name,
        'role'         => $role,
    ]);
    update_user_meta($id, 'billing_phone', '0912' . rand(1000000, 9999999));
    return $id;
}

$admin    = 1;
wp_update_user(['ID' => $admin, 'display_name' => 'طاها کرمی']);
$ali      = tkm_seed_user('ali', 'علی رضایی', 'customer');
$maryam   = tkm_seed_user('maryam', 'مریم احمدی', 'customer');
$reza     = tkm_seed_user('reza', 'رضا محمدی', 'customer');
$sara     = tkm_seed_user('sara', 'سارا کریمی', 'editor');
// Support agent with the plugin role and no department: sees only tickets assigned to them.
$nima     = tkm_seed_user('nima', 'نیما صادقی', 'tkm_agent');
$customers = [$ali, $maryam, $reza];

$departments = [
    'پشتیبانی فنی' => ['نصب و راه‌اندازی' => 'لطفا نسخه وردپرس و افزونه را در متن تیکت ذکر کنید.', 'گزارش باگ' => ''],
    'مالی'         => ['پرداخت و فاکتور' => 'برای پیگیری پرداخت، کد رهگیری را ارسال کنید.', 'بازگشت وجه' => ''],
    'فروش'         => ['مشاوره پیش از خرید' => ''],
];

$children = [];
$pos = 1;
foreach ($departments as $parent => $kids) {
    $wpdb->insert("{$p}tkm_departments", ['name' => $parent, 'parent' => 0, 'position' => $pos++, 'description' => '']);
    $parent_id = $wpdb->insert_id;
    $cpos = 1;
    foreach ($kids as $kid => $desc) {
        $wpdb->insert("{$p}tkm_departments", ['name' => $kid, 'parent' => $parent_id, 'position' => $cpos++, 'description' => $desc]);
        $children[] = $wpdb->insert_id;
    }
    $wpdb->insert("{$p}tkm_users", ['department_id' => $parent_id, 'user_id' => $sara]);
}
$wpdb->insert("{$p}tkm_users", ['department_id' => $children[0], 'user_id' => $sara]);
$wpdb->insert("{$p}tkm_users", ['department_id' => $children[1], 'user_id' => $admin]);

$titles = [
    'خطا در فعال‌سازی لایسنس',
    'صفحه پرداخت لود نمی‌شود',
    'درخواست بازگشت وجه سفارش',
    'سوال درباره نسخه حرفه‌ای',
    'مشکل در آپلود فایل',
    'ایمیل اطلاع‌رسانی ارسال نمی‌شود',
    'تداخل با قالب سایت',
    'فاکتور رسمی برای شرکت',
    'نمایش نادرست تاریخ شمسی',
    'افزودن قابلیت چندزبانه',
    'کندی پنل مدیریت',
    'به‌روزرسانی ناموفق افزونه',
    'سوال درباره تخفیف سازمانی',
    'خطای ۵۰۰ بعد از نصب',
];
$statuses   = ['open', 'answerd', 'closed', 'finish', 'open', 'answerd', 'open', 'pending'];
$priorities = ['low', 'medium', 'high'];
$bodies = [
    '<p>سلام، وقت بخیر. بعد از نصب نسخه جدید با این مشکل مواجه شدم. لطفا راهنمایی کنید.</p>',
    '<p>سلام. این مشکل از دیروز شروع شده و هنوز برطرف نشده است. تصویر خطا را پیوست کردم.</p>',
    '<p>درود. ممنون می‌شوم در اسرع وقت بررسی کنید چون سایت در حالت فروش است.</p>',
];

foreach ($titles as $i => $title) {
    $days    = rand(0, 28);
    $created = date('Y-m-d H:i:s', strtotime("-{$days} days -" . rand(1, 20) . ' hours'));
    $reply   = date('Y-m-d H:i:s', strtotime($created . ' +' . rand(1, 30) . ' hours'));
    $creator = $customers[$i % 3];
    $from_admin = ($i % 6 === 5);

    $wpdb->insert("{$p}tkm_tickets", [
        'title'         => $title,
        'body'          => $bodies[$i % 3],
        'creator_id'    => $from_admin ? $admin : $creator,
        'user_id'       => $from_admin ? $creator : null,
        'from_admin'    => $from_admin ? 1 : null,
        'department_id' => $children[$i % count($children)],
        'status'        => $statuses[$i % count($statuses)],
        'priority'      => $priorities[$i % 3],
        'create_date'   => $created,
        'reply_date'    => $reply,
        'note'          => $i % 4 === 0 ? 'مشتری VIP — پیگیری فوری' : null,
        'updated_at'    => $reply,
        'assignee_id'   => $i === 1 ? $nima : ($i % 5 === 0 ? $sara : null),
    ]);
    $ticket_id = $wpdb->insert_id;

    $replies = rand(0, 4);
    for ($r = 0; $r < $replies; $r++) {
        $staff = $r % 2 === 0;
        $wpdb->insert("{$p}tkm_replies", [
            'ticket_id'   => $ticket_id,
            'creator_id'  => $staff ? ($r % 4 === 0 ? $sara : $admin) : $creator,
            'from_admin'  => $staff ? 1 : null,
            'body'        => $staff ? '<p>سلام، درخواست شما بررسی شد. لطفا کش سایت را پاک کرده و مجددا امتحان کنید.</p>' : '<p>ممنون، انجام دادم ولی هنوز مشکل پابرجاست.</p>',
            'create_date' => date('Y-m-d H:i:s', strtotime($created . ' +' . (($r + 1) * 3) . ' hours')),
        ]);
    }

    if (in_array($statuses[$i % count($statuses)], ['closed', 'finish', 'answerd'], true) && $i % 2 === 0) {
        $wpdb->insert("{$p}tkm_ratings", ['ticket_id' => $ticket_id, 'user_id' => $creator, 'rating' => rand(3, 5)]);
    }
}

// v2: tags, an automation rule and a couple of help articles.
$vip = \Ticketchi\Domain\Tags::find_or_create('VIP', '#8b5cf6');
$bug = \Ticketchi\Domain\Tags::find_or_create('باگ', '#ef4444');
\Ticketchi\Domain\Tags::add((int) $wpdb->get_var("SELECT MIN(ID) FROM {$p}tkm_tickets"), $vip);
\Ticketchi\Automation\Engine::save([
    'name'       => 'کلیدواژه فوری',
    'trigger'    => 'ticket_created',
    'conditions' => [['field' => 'keyword', 'value' => 'فوری']],
    'actions'    => [['type' => 'priority', 'value' => 'high'], ['type' => 'tag', 'value' => $bug]],
    'is_active'  => true,
]);
foreach (get_posts(['post_type' => 'tkm_article', 'numberposts' => -1, 'post_status' => 'any']) as $old) {
    wp_delete_post($old->ID, true);
}
foreach ([
    ['فعال‌سازی لایسنس افزونه', 'برای فعال‌سازی لایسنس، کد را از پنل کاربری کپی کرده و در تنظیمات افزونه وارد کنید.'],
    ['رفع خطای ۵۰۰ بعد از نصب', 'خطای ۵۰۰ معمولاً به دلیل نسخه PHP یا تداخل افزونه‌هاست.'],
] as $article) {
    wp_insert_post(['post_type' => 'tkm_article', 'post_status' => 'publish', 'post_title' => $article[0], 'post_content' => $article[1]]);
}
if (!get_page_by_path('help-center')) {
    wp_insert_post(['post_type' => 'page', 'post_status' => 'publish', 'post_title' => 'مرکز راهنما', 'post_name' => 'help-center', 'post_content' => '[ticketchi_kb]']);
}

$settings = get_option('tkm_settings', []);
$settings = array_merge(is_array($settings) ? $settings : [], [
    'new-ticket-alert'     => '1',
    'alert-message-ticket' => 'کاربر گرامی، قبل از ثبت تیکت حتما سوالات متداول را مطالعه بفرمایید.',
    'rating_ticket'        => '1',
    'rating_ticket_title'  => 'از پاسخ‌گویی ما راضی بودید؟',
    'faq_swicher'          => '1',
    'faqs' => [
        ['faqs-title' => 'چطور لایسنس را فعال کنم؟', 'faqs-body' => 'از منوی تنظیمات، بخش لایسنس، کد خرید را وارد کنید.'],
        ['faqs-title' => 'زمان پاسخ‌گویی چقدر است؟', 'faqs-body' => 'تیکت‌ها معمولا در کمتر از ۲۴ ساعت کاری پاسخ داده می‌شوند.'],
        ['faqs-title' => 'آیا امکان بازگشت وجه وجود دارد؟', 'faqs-body' => 'تا ۷ روز پس از خرید امکان بازگشت وجه وجود دارد.'],
    ],
    'save_message' => [
        ['save_message_title' => 'خوش‌آمدگویی', 'message_save' => 'سلام، وقت بخیر. درخواست شما دریافت شد و در حال بررسی است.'],
        ['save_message_title' => 'پاک کردن کش', 'message_save' => 'لطفا کش مرورگر و افزونه کش سایت را پاک کرده و مجددا بررسی کنید.'],
    ],
    'statues' => [
        ['status-title' => 'در انتظار بررسی', 'status-slug' => 'pending', 'color-status' => '#f59e0b'],
    ],
    'auto_reply'      => '1',
    'auto_reply_text' => 'تیکت شما ثبت شد. همکاران ما در اولین فرصت پاسخ خواهند داد.',
    'product_setting' => '1',
    'upload_multiple' => '1',
    'upload_max_files' => 3,
]);
update_option('tkm_settings', $settings);

if (!get_page_by_path('support')) {
    wp_insert_post(['post_type' => 'page', 'post_status' => 'publish', 'post_title' => 'پشتیبانی', 'post_name' => 'support', 'post_content' => '[tickets]']);
}

echo "Seeded: " . count($titles) . " tickets, users ali/maryam/reza/sara/nima (password = login)\n";
