<?php

namespace Ticketchi\Admin;

defined('ABSPATH') || exit('NO Access');

/**
 * Every Ticketchi setting, grouped for the settings app.
 *
 * Keys and value shapes are the ones the plugin has always stored in the
 * `tkm_settings` option, so existing sites keep their configuration:
 * switches are '1' / '0', repeaters are lists of associative arrays.
 *
 * Field: id, type, label, desc, default, options, unit, placeholder,
 *        show_if => [field, value], fields (repeater), item_label (repeater)
 * Types: switch, text, textarea, number, color, select, combo, days, time,
 *        repeater, note
 */
final class SettingsSchema
{
    public static function sections()
    {
        $placeholders = '<code>{{ticket_id}}</code> شناسه · <code>{{title}}</code> عنوان · <code>{{department}}</code> دپارتمان · <code>{{status}}</code> وضعیت · <code>{{priority}}</code> اهمیت · <code>{{date}}</code> تاریخ';

        return [
            [
                'id' => 'general', 'title' => 'عمومی', 'icon' => 'sliders',
                'desc' => 'رفتار کلی سیستم تیکت برای مشتری‌ها.',
                'groups' => [
                    ['title' => 'پیش از ثبت تیکت', 'fields' => [
                        ['id' => 'new-ticket-alert', 'type' => 'switch', 'label' => 'نمایش پیغام بالای فرم ثبت تیکت', 'desc' => 'مثلاً برای یادآوری مطالعه سوالات متداول یا اعلام ساعات پاسخ‌گویی.', 'default' => '0'],
                        ['id' => 'alert-message-ticket', 'type' => 'textarea', 'label' => 'متن پیغام', 'default' => 'کاربر گرامی قبل از ثبت تیکت حتما سوالات متداول را مطالعه بفرمایید', 'show_if' => ['new-ticket-alert', '1']],
                    ]],
                    ['title' => 'پاسخ خودکار', 'fields' => [
                        ['id' => 'auto_reply', 'type' => 'switch', 'label' => 'ارسال پاسخ خودکار پس از ثبت تیکت', 'desc' => 'مشتری بلافاصله پیامی دریافت می‌کند تا بداند درخواستش ثبت شده است.', 'default' => '0'],
                        ['id' => 'auto_reply_text', 'type' => 'textarea', 'label' => 'متن پاسخ خودکار', 'placeholder' => 'سلام، تیکت شما ثبت شد و همکاران ما به‌زودی پاسخ می‌دهند.', 'show_if' => ['auto_reply', '1']],
                    ]],
                    ['title' => 'رضایت و بستن', 'fields' => [
                        ['id' => 'rating_ticket', 'type' => 'switch', 'label' => 'امتیازدهی مشتری به پاسخ‌ها', 'desc' => 'پس از پاسخ پشتیبانی، مشتری می‌تواند از ۱ تا ۵ ستاره امتیاز بدهد. نتیجه در گزارش‌ها دیده می‌شود.', 'default' => '1'],
                        ['id' => 'rating_ticket_title', 'type' => 'text', 'label' => 'عنوان کادر امتیازدهی', 'default' => 'لطفا امتیاز تیکت را ثبت نمایید', 'show_if' => ['rating_ticket', '1']],
                        ['id' => 'close_auto_ticket', 'type' => 'switch', 'label' => 'بستن خودکار تیکت‌های پاسخ‌داده‌شده', 'desc' => 'تیکت‌هایی که یک هفته پس از پاسخ پشتیبانی بی‌جواب مانده‌اند، خودکار بسته می‌شوند.', 'default' => '0'],
                    ]],
                ],
            ],
            [
                'id' => 'brand', 'title' => 'ظاهر و برند', 'icon' => 'sparkles',
                'desc' => 'رنگ برند در پنل مشتری و پنل مدیریت اعمال می‌شود.',
                'preview' => 'brand',
                'groups' => [
                    ['title' => 'رنگ‌ها', 'fields' => [
                        ['id' => 'primary_color', 'type' => 'color', 'label' => 'رنگ اصلی', 'desc' => 'دکمه‌ها، لینک‌ها و حالت فعال.', 'default' => '#2A48CD'],
                        ['id' => 'secondary_color', 'type' => 'color', 'label' => 'رنگ هاور', 'desc' => 'رنگ دکمه اصلی هنگام اشاره ماوس. خالی بگذارید تا خودکار ساخته شود.', 'default' => ''],
                        ['id' => 'text_color', 'type' => 'color', 'label' => 'رنگ متن روی رنگ اصلی', 'default' => '#ffffff'],
                    ]],
                    ['title' => 'عنوان پنل مشتری', 'fields' => [
                        ['id' => 'title_dashboard', 'type' => 'text', 'label' => 'عنوان', 'default' => 'داشبورد تیکت‌ها'],
                        ['id' => 'description_dashboard', 'type' => 'text', 'label' => 'توضیح کوتاه', 'default' => 'مدیریت تیکت‌های پشتیبانی شما'],
                    ]],
                ],
            ],
            [
                'id' => 'form', 'title' => 'فرم ثبت تیکت', 'icon' => 'edit',
                'desc' => 'برچسب فیلدها و گزینه‌های فرم ثبت تیکت در پنل مشتری.',
                'groups' => [
                    ['title' => 'برچسب فیلدها', 'columns' => 2, 'fields' => [
                        ['id' => 'department_label', 'type' => 'text', 'label' => 'دپارتمان', 'default' => 'دپارتمان'],
                        ['id' => 'type_label', 'type' => 'text', 'label' => 'نوع تیکت', 'default' => 'نوع تیکت'],
                        ['id' => 'title_label', 'type' => 'text', 'label' => 'عنوان تیکت', 'default' => 'عنوان تیکت'],
                        ['id' => 'product_label', 'type' => 'text', 'label' => 'محصول', 'default' => 'محصول'],
                        ['id' => 'priority_label', 'type' => 'text', 'label' => 'اهمیت', 'default' => 'اهمیت تیکت'],
                        ['id' => 'dec_label', 'type' => 'text', 'label' => 'توضیحات', 'default' => 'توضیحات'],
                        ['id' => 'file_label', 'type' => 'text', 'label' => 'پیوست فایل', 'default' => 'آپلود فایل'],
                        ['id' => 'voice_label', 'type' => 'text', 'label' => 'پیام صوتی', 'default' => 'ارسال صدا'],
                    ]],
                    ['title' => 'محصولات', 'fields' => [
                        ['id' => 'product_setting', 'type' => 'switch', 'label' => 'انتخاب محصول ووکامرس', 'desc' => 'مشتری می‌تواند تیکت را به یکی از محصولاتی که خریده وصل کند.', 'default' => '0'],
                        ['id' => 'edd_setting', 'type' => 'switch', 'label' => 'انتخاب محصول Easy Digital Downloads', 'default' => '0'],
                    ]],
                ],
            ],
            [
                'id' => 'upload', 'title' => 'پیوست فایل', 'icon' => 'paperclip',
                'desc' => 'محدودیت‌های آپلود در تیکت‌ها و پاسخ‌ها (مشتری و پشتیبانی).',
                'groups' => [
                    ['title' => 'محدودیت‌ها', 'fields' => [
                        ['id' => 'upload_max_size', 'type' => 'number', 'label' => 'حداکثر حجم هر فایل', 'unit' => 'مگابایت', 'min' => 1, 'max' => 1024, 'default' => '5'],
                        ['id' => 'upload_allowed_ext', 'type' => 'text', 'label' => 'پسوندهای مجاز', 'desc' => 'با ویرگول جدا کنید؛ مثلا jpg,png,pdf,zip', 'ltr' => true, 'default' => 'jpg,jpeg,png,gif,webp,pdf,zip,rar,doc,docx,xls,xlsx,txt,mp4,mp3'],
                        ['id' => 'upload_multiple', 'type' => 'switch', 'label' => 'آپلود چند فایل هم‌زمان', 'default' => '0'],
                        ['id' => 'upload_max_files', 'type' => 'number', 'label' => 'حداکثر تعداد فایل', 'unit' => 'فایل', 'min' => 1, 'max' => 20, 'default' => '5', 'show_if' => ['upload_multiple', '1']],
                    ]],
                ],
            ],
            [
                'id' => 'canned', 'title' => 'پاسخ‌های آماده', 'icon' => 'reply',
                'desc' => 'متن‌های پرتکرار که کارشناسان با یک کلیک در پاسخ درج می‌کنند.',
                'groups' => [
                    ['fields' => [
                        ['id' => 'save_message', 'type' => 'repeater', 'item_label' => 'پاسخ آماده', 'title_field' => 'save_message_title', 'empty' => 'هنوز پاسخ آماده‌ای ندارید.', 'fields' => [
                            ['id' => 'save_message_title', 'type' => 'text', 'label' => 'عنوان کوتاه', 'placeholder' => 'مثلا: خوش‌آمدگویی'],
                            ['id' => 'message_save', 'type' => 'textarea', 'label' => 'متن پاسخ'],
                        ]],
                    ]],
                ],
            ],
            [
                'id' => 'faq', 'title' => 'سوالات متداول', 'icon' => 'help',
                'desc' => 'پیش از نمایش فرم، سوالات پرتکرار به مشتری نشان داده می‌شود تا شاید بدون تیکت به جواب برسد.',
                'groups' => [
                    ['fields' => [
                        ['id' => 'faq_swicher', 'type' => 'switch', 'label' => 'نمایش سوالات متداول پیش از فرم ثبت تیکت', 'default' => '0'],
                        ['id' => 'faqs', 'type' => 'repeater', 'item_label' => 'سوال', 'title_field' => 'faqs-title', 'empty' => 'هنوز سوالی اضافه نکرده‌اید.', 'fields' => [
                            ['id' => 'faqs-title', 'type' => 'text', 'label' => 'سوال'],
                            ['id' => 'faqs-body', 'type' => 'textarea', 'label' => 'پاسخ'],
                        ]],
                    ]],
                ],
            ],
            [
                'id' => 'statuses', 'title' => 'وضعیت‌ها', 'icon' => 'flag',
                'desc' => 'رنگ وضعیت‌های پیش‌فرض و وضعیت‌های اختصاصی شما.',
                'groups' => [
                    ['title' => 'وضعیت‌های پیش‌فرض', 'columns' => 2, 'fields' => [
                        ['id' => 'open-color', 'type' => 'color', 'label' => 'باز', 'default' => '#4bcc58'],
                        ['id' => 'close-color', 'type' => 'color', 'label' => 'پاسخ داده شده', 'default' => '#3150d8'],
                        ['id' => 'answerd-color', 'type' => 'color', 'label' => 'بسته شده', 'default' => '#ff2121'],
                        ['id' => 'finish-color', 'type' => 'color', 'label' => 'پایان یافته', 'default' => '#000000'],
                    ]],
                    ['title' => 'وضعیت‌های اختصاصی', 'fields' => [
                        ['id' => 'statues', 'type' => 'repeater', 'item_label' => 'وضعیت', 'title_field' => 'status-title', 'empty' => 'وضعیت اختصاصی ندارید؛ مثلا «در انتظار بررسی فنی».', 'fields' => [
                            ['id' => 'status-title', 'type' => 'text', 'label' => 'نام وضعیت'],
                            ['id' => 'status-slug', 'type' => 'text', 'label' => 'نامک (انگلیسی)', 'ltr' => true, 'placeholder' => 'pending-review', 'desc' => 'فقط حروف انگلیسی، عدد و خط تیره. پس از استفاده تغییرش ندهید.'],
                            ['id' => 'color-status', 'type' => 'color', 'label' => 'رنگ', 'default' => '#f59e0b'],
                        ]],
                    ]],
                ],
            ],
            [
                'id' => 'team', 'title' => 'تیم و تخصیص', 'icon' => 'users',
                'desc' => 'کارشناسان هر دپارتمان در صفحه «تیم و دپارتمان‌ها» تعیین می‌شوند.',
                'groups' => [
                    ['fields' => [
                        ['id' => 'assign_mode', 'type' => 'select', 'label' => 'تخصیص خودکار تیکت‌های جدید', 'default' => 'manual', 'options' => [
                            'manual'       => 'دستی — مدیر یا کارشناس تیکت را برمی‌دارد',
                            'round_robin'  => 'نوبتی بین کارشناسان دپارتمان',
                            'least_loaded' => 'به کارشناسی که کمترین تیکت باز را دارد',
                        ]],
                        ['id' => 'agent_scope', 'type' => 'select', 'label' => 'کارشناسان کدام تیکت‌ها را ببینند؟', 'desc' => 'مدیران سایت و مدیران فروشگاه همیشه همه تیکت‌ها را می‌بینند.', 'default' => 'departments', 'options' => [
                            'departments' => 'فقط دپارتمان‌های خودشان + تیکت‌های سپرده‌شده به آن‌ها',
                            'all'         => 'همه تیکت‌ها',
                        ]],
                    ]],
                ],
            ],
            [
                'id' => 'sla', 'title' => 'SLA و زمان پاسخ', 'icon' => 'clock',
                'desc' => 'تعهد زمانی شما به مشتری؛ تیکت‌های نزدیک به مهلت در صندوق مشخص می‌شوند.',
                'groups' => [
                    ['fields' => [
                        ['id' => 'sla_enable', 'type' => 'switch', 'label' => 'فعال‌سازی SLA', 'desc' => 'برای هر تیکت مهلت اولین پاسخ و مهلت حل بر اساس اولویت تعیین می‌شود.', 'default' => '1'],
                    ]],
                    ['title' => 'مهلت اولین پاسخ', 'columns' => 3, 'show_if' => ['sla_enable', '1'], 'fields' => [
                        ['id' => 'sla_first_high', 'type' => 'number', 'label' => 'اولویت زیاد', 'unit' => 'ساعت', 'min' => 1, 'max' => 720, 'default' => '2'],
                        ['id' => 'sla_first_medium', 'type' => 'number', 'label' => 'اولویت متوسط', 'unit' => 'ساعت', 'min' => 1, 'max' => 720, 'default' => '8'],
                        ['id' => 'sla_first_low', 'type' => 'number', 'label' => 'اولویت کم', 'unit' => 'ساعت', 'min' => 1, 'max' => 720, 'default' => '24'],
                    ]],
                    ['title' => 'مهلت حل تیکت', 'columns' => 3, 'show_if' => ['sla_enable', '1'], 'fields' => [
                        ['id' => 'sla_resolve_high', 'type' => 'number', 'label' => 'اولویت زیاد', 'unit' => 'ساعت', 'min' => 1, 'max' => 2160, 'default' => '24'],
                        ['id' => 'sla_resolve_medium', 'type' => 'number', 'label' => 'اولویت متوسط', 'unit' => 'ساعت', 'min' => 1, 'max' => 2160, 'default' => '48'],
                        ['id' => 'sla_resolve_low', 'type' => 'number', 'label' => 'اولویت کم', 'unit' => 'ساعت', 'min' => 1, 'max' => 2160, 'default' => '96'],
                    ]],
                    ['title' => 'ساعات کاری', 'show_if' => ['sla_enable', '1'], 'fields' => [
                        ['id' => 'sla_business_hours', 'type' => 'switch', 'label' => 'مهلت‌ها فقط در ساعات کاری شمرده شوند', 'default' => '0'],
                        ['id' => 'sla_work_days', 'type' => 'days', 'label' => 'روزهای کاری', 'default' => ['6', '0', '1', '2', '3'], 'show_if' => ['sla_business_hours', '1']],
                        ['id' => 'sla_work_start', 'type' => 'time', 'label' => 'شروع', 'default' => '09:00', 'show_if' => ['sla_business_hours', '1']],
                        ['id' => 'sla_work_end', 'type' => 'time', 'label' => 'پایان', 'default' => '17:00', 'show_if' => ['sla_business_hours', '1']],
                    ]],
                ],
            ],
            [
                'id' => 'notify', 'title' => 'اعلان‌ها', 'icon' => 'bell',
                'desc' => 'ایمیل‌ها با رنگ برند و به‌صورت راست‌چین ارسال می‌شوند. اعلان‌های درون پنل همیشه فعال‌اند.',
                'groups' => [
                    ['title' => 'ایمیل به مشتری', 'fields' => [
                        ['id' => 'notify_customer_created', 'type' => 'switch', 'label' => 'تایید ثبت تیکت', 'default' => '1'],
                        ['id' => 'notify_customer_reply', 'type' => 'switch', 'label' => 'پاسخ جدید پشتیبانی', 'default' => '1'],
                    ]],
                    ['title' => 'ایمیل به تیم', 'fields' => [
                        ['id' => 'notify_agent_email', 'type' => 'switch', 'label' => 'تیکت جدید، پاسخ مشتری و تیکت‌های سپرده‌شده', 'default' => '1'],
                        ['id' => 'notify_sla_email', 'type' => 'switch', 'label' => 'هشدار عبور از مهلت SLA', 'default' => '1'],
                    ]],
                    ['title' => 'فرستنده', 'columns' => 2, 'fields' => [
                        ['id' => 'email_from_name', 'type' => 'text', 'label' => 'نام فرستنده', 'placeholder' => get_bloginfo('name'), 'default' => ''],
                        ['id' => 'email_from_address', 'type' => 'text', 'label' => 'ایمیل فرستنده', 'ltr' => true, 'placeholder' => get_option('admin_email'), 'default' => ''],
                    ]],
                ],
            ],
            [
                'id' => 'sms', 'title' => 'پیامک', 'icon' => 'message',
                'desc' => 'اتصال به سامانه پیامکی برای اطلاع‌رسانی به مشتری.',
                'groups' => [
                    ['title' => 'سامانه', 'fields' => [
                        ['id' => 'sms-service', 'type' => 'select', 'label' => 'سامانه پیامکی', 'default' => 'melipayamak', 'options' => ['melipayamak' => 'ملی پیامک', 'kavenegar' => 'کاوه‌نگار']],
                        ['id' => 'sms-username', 'type' => 'text', 'label' => 'نام کاربری', 'ltr' => true, 'show_if' => ['sms-service', 'melipayamak'], 'default' => ''],
                        ['id' => 'sms-password', 'type' => 'text', 'secret' => true, 'label' => 'رمز عبور', 'ltr' => true, 'show_if' => ['sms-service', 'melipayamak'], 'default' => ''],
                        ['id' => 'sms-api', 'type' => 'text', 'secret' => true, 'label' => 'کلید API', 'ltr' => true, 'show_if' => ['sms-service', 'kavenegar'], 'default' => ''],
                        ['id' => 'phone-service-key-user', 'type' => 'combo', 'label' => 'فیلد شماره موبایل کاربر', 'desc' => 'کلید متای کاربر که شماره موبایل در آن ذخیره شده؛ در ووکامرس معمولا billing_phone.', 'ltr' => true, 'options' => 'user_meta', 'default' => 'billing_phone'],
                    ]],
                    ['title' => 'پیامک ثبت تیکت', 'fields' => [
                        ['id' => 'user_create_sms', 'type' => 'switch', 'label' => 'ارسال پیامک به مشتری هنگام ثبت تیکت', 'default' => '0'],
                        ['id' => 'user_create_sms_pattern_code', 'type' => 'text', 'label' => 'کد الگو', 'ltr' => true, 'show_if' => ['user_create_sms', '1'], 'default' => ''],
                        ['id' => 'user_create_pattern', 'type' => 'textarea', 'label' => 'متن الگو', 'desc' => 'متغیرها: ' . $placeholders, 'show_if' => ['user_create_sms', '1'], 'default' => ''],
                    ]],
                    ['title' => 'پیامک پاسخ', 'fields' => [
                        ['id' => 'sms_on_reply', 'type' => 'switch', 'label' => 'ارسال پیامک به مشتری وقتی پشتیبانی پاسخ می‌دهد', 'default' => '0'],
                        ['id' => 'sms_reply_pattern_code', 'type' => 'text', 'label' => 'کد / نام الگو', 'desc' => 'مقادیر الگو به ترتیب: شناسه تیکت، عنوان تیکت', 'ltr' => true, 'show_if' => ['sms_on_reply', '1'], 'default' => ''],
                    ]],
                ],
            ],
            [
                'id' => 'kb', 'title' => 'پایگاه دانش', 'icon' => 'book',
                'desc' => 'مقاله‌های راهنما از منوی «پایگاه دانش» در پیشخوان وردپرس نوشته می‌شوند.',
                'groups' => [
                    ['fields' => [
                        ['id' => 'kb_enable', 'type' => 'switch', 'label' => 'پایگاه دانش و مرکز راهنما', 'default' => '1'],
                        ['id' => 'kb_suggest', 'type' => 'switch', 'label' => 'پیشنهاد مقاله هنگام نوشتن تیکت', 'desc' => 'با تایپ عنوان، مقاله‌های مرتبط به مشتری نمایش داده می‌شود.', 'default' => '1', 'show_if' => ['kb_enable', '1']],
                        ['id' => 'kb_slug', 'type' => 'text', 'label' => 'نامک آدرس مقاله‌ها', 'ltr' => true, 'desc' => 'مثلا example.com/help/نام-مقاله', 'default' => 'help', 'show_if' => ['kb_enable', '1']],
                    ]],
                ],
            ],
            [
                'id' => 'integrations', 'title' => 'شورت‌کدها', 'icon' => 'hash',
                'desc' => 'افزودن پنل تیکت و مرکز راهنما به هر صفحه‌ای از سایت.',
                'groups' => [
                    ['fields' => [
                        ['type' => 'note', 'html' => '<p><code class="tkm-copy" data-copy="[tickets]">[tickets]</code> پنل کامل تیکت مشتری (فهرست، ثبت، گفتگو). در ووکامرس به‌طور خودکار در «حساب کاربری › تیکت‌ها» هم هست.</p><p><code class="tkm-copy" data-copy="[ticketchi_kb]">[ticketchi_kb]</code> مرکز راهنما با جستجو و دسته‌بندی مقاله‌ها.</p>'],
                    ]],
                ],
            ],
        ];
    }

    /** Flat id => field map (repeaters included, their sub-fields are not). */
    public static function fields()
    {
        $out = [];
        foreach (self::sections() as $section) {
            foreach ($section['groups'] as $group) {
                foreach ($group['fields'] as $field) {
                    if (!empty($field['id'])) {
                        $out[$field['id']] = $field;
                    }
                }
            }
        }
        return $out;
    }

    public static function defaults()
    {
        $out = [];
        foreach (self::fields() as $id => $field) {
            if (array_key_exists('default', $field)) {
                $out[$id] = $field['default'];
            }
        }
        return $out;
    }

    /** Current values, with defaults for anything never saved. */
    public static function values()
    {
        $saved = get_option('tkm_settings', []);
        $saved = is_array($saved) ? $saved : [];
        $values = [];
        foreach (self::fields() as $id => $field) {
            if ($field['type'] === 'note') {
                continue;
            }
            $value = array_key_exists($id, $saved) ? $saved[$id] : (isset($field['default']) ? $field['default'] : '');
            if ($field['type'] === 'switch') {
                $value = !empty($value) && $value !== '0' ? '1' : '0';
            } elseif ($field['type'] === 'repeater' || $field['type'] === 'days') {
                $value = is_array($value) ? array_values($value) : [];
            } elseif (is_array($value)) {
                $value = '';
            }
            $values[$id] = $value;
        }
        return $values;
    }

    /** Adds defaults for keys a site has never saved (fresh installs, new settings). */
    public static function ensure_defaults()
    {
        $saved = get_option('tkm_settings', []);
        $saved = is_array($saved) ? $saved : [];
        $merged = $saved + self::defaults();
        if ($merged !== $saved) {
            update_option('tkm_settings', $merged);
        }
    }

    /**
     * Validates and stores submitted values. Unknown keys are ignored, keys
     * not submitted keep their stored value.
     *
     * @return array|\WP_Error saved values
     */
    public static function save(array $input)
    {
        $fields = self::fields();
        $saved = get_option('tkm_settings', []);
        $saved = is_array($saved) ? $saved : [];
        $errors = [];

        foreach ($input as $id => $value) {
            if (!isset($fields[$id]) || $fields[$id]['type'] === 'note') {
                continue;
            }
            $clean = self::sanitize($fields[$id], $value, $errors);
            if ($clean !== null) {
                $saved[$id] = $clean;
            }
        }

        if ($errors) {
            return new \WP_Error('tkm_settings', implode(' ', $errors), ['fields' => array_keys($errors)]);
        }

        update_option('tkm_settings', $saved);
        if (isset($input['kb_slug'])) {
            update_option('tkm_flush_rewrite', 1);
        }
        return self::values();
    }

    private static function sanitize(array $field, $value, array &$errors)
    {
        $id = $field['id'];
        switch ($field['type']) {
            case 'switch':
                return !empty($value) && $value !== '0' && $value !== 'false' ? '1' : '0';
            case 'number':
                $n = is_numeric(\tkm_en_digits((string) $value)) ? (int) \tkm_en_digits((string) $value) : null;
                if ($n === null) {
                    $errors[$id] = '«' . $field['label'] . '» باید عدد باشد.';
                    return null;
                }
                $n = max(isset($field['min']) ? $field['min'] : 0, min(isset($field['max']) ? $field['max'] : PHP_INT_MAX, $n));
                return (string) $n;
            case 'color':
                $value = trim((string) $value);
                if ($value === '') {
                    return '';
                }
                if (!preg_match('/^#([0-9a-f]{3}|[0-9a-f]{6}|[0-9a-f]{8})$/i', $value)) {
                    $errors[$id] = 'رنگ «' . $field['label'] . '» معتبر نیست.';
                    return null;
                }
                return $value;
            case 'select':
                return isset($field['options'][(string) $value]) ? (string) $value : (isset($field['default']) ? $field['default'] : '');
            case 'days':
                return array_values(array_intersect(array_map('strval', (array) $value), ['0', '1', '2', '3', '4', '5', '6']));
            case 'time':
                $value = \tkm_en_digits(trim((string) $value));
                if (!preg_match('/^([01]?\d|2[0-3]):[0-5]\d$/', $value)) {
                    $errors[$id] = 'ساعت «' . $field['label'] . '» را به شکل ۲۴ ساعته وارد کنید (مثلا 09:00).';
                    return null;
                }
                return strlen($value) === 4 ? '0' . $value : $value;
            case 'textarea':
                return sanitize_textarea_field((string) $value);
            case 'repeater':
                $rows = [];
                $slugs = [];
                foreach ((array) $value as $row) {
                    if (!is_array($row)) {
                        continue;
                    }
                    $item = [];
                    $empty = true;
                    foreach ($field['fields'] as $sub) {
                        $v = isset($row[$sub['id']]) ? $row[$sub['id']] : '';
                        $v = self::sanitize($sub, $v, $errors);
                        $item[$sub['id']] = $v === null ? '' : $v;
                        if ($item[$sub['id']] !== '' && $sub['type'] !== 'color') {
                            $empty = false;
                        }
                    }
                    if ($empty) {
                        continue;
                    }
                    if ($id === 'statues') {
                        $slug = sanitize_title($item['status-slug'] !== '' ? $item['status-slug'] : $item['status-title']);
                        if ($slug === '' || in_array($slug, ['open', 'answerd', 'closed', 'finish', 'trash'], true) || in_array($slug, $slugs, true)) {
                            $errors['statues'] = 'نامک هر وضعیت باید یکتا باشد و با وضعیت‌های پیش‌فرض یکی نباشد.';
                        }
                        $item['status-slug'] = $slug;
                        $slugs[] = $slug;
                    }
                    $rows[] = $item;
                }
                return $rows;
            case 'combo':
            case 'text':
            default:
                $value = sanitize_text_field((string) $value);
                if ($id === 'email_from_address' && $value !== '' && !is_email($value)) {
                    $errors[$id] = 'ایمیل فرستنده معتبر نیست.';
                    return null;
                }
                if ($id === 'kb_slug') {
                    return sanitize_title($value) ?: 'help';
                }
                if ($id === 'upload_allowed_ext') {
                    return implode(',', array_filter(array_map(function ($e) {
                        return preg_replace('/[^a-z0-9]/', '', strtolower(trim($e)));
                    }, explode(',', str_replace('،', ',', $value)))));
                }
                return $value;
        }
    }
}
