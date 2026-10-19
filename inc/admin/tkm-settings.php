<?php

function tkm_user_meta_key()
{
  global $wpdb;
  $fields = $wpdb->get_col("SELECT DISTINCT meta_key FROM {$wpdb->usermeta} ");
  $array = [];

  foreach ($fields as $field) {
    $array[$field] = $field;
  }


  return $array;
}

if (class_exists('CSF')) {

  $prefix = 'tkm_settings';

  // Create options
  CSF::createOptions($prefix, array(
    'menu_title' => 'تیکت چی',
    'menu_slug'  => 'tkm-settings',
    'menu_hidden' => true,
    'show_sub_menu' => false,
    'framework_title' => 'تیکت چی',
    'theme' => 'light',
    'footer_text' => '',
    'class' => 'ticket_master',
    'sticky_header' => false,
    'show_search' => false
  ));


  // Create a section
  CSF::createSection($prefix, array(
    'title'  => 'عمومی',
    'fields' => array(

      // add field
      array(
        'id'      => 'new-ticket-alert',
        'type'    => 'switcher',
        'title'   => 'پیغام ارسال تیکت',
        'label'   => 'نمایش پیغام قبل از ثبت تیکت',
        'default' => false
      ),
      array(
        'id'      => 'alert-message-ticket',
        'type'    => 'textarea',
        'title'   => 'متن پیغام',
        'default' => 'کاربر گرامی قبل از ثبت تیکت حتما سوالات متداول را مطالعه بفرمایید',
        'dependency' => array('new-ticket-alert', '==', 'true')
      ),
      array(
        'id'      => 'close_auto_ticket',
        'type'    => 'switcher',
        'title'   => 'فعال سازی',
        'label'   => 'بستن خودکار تیکت ها به صورت هفتگی ',
        'default' => false
      ),
      array(
        'id'      => 'rating_ticket',
        'type'    => 'switcher',
        'title'   => 'فعال سازی',
        'label'   => 'امتیاز دهی به تیکت ',
        'default' => true
      ),
      array(
        'id'    => 'rating_ticket_title',
        'type'  => 'text',
        'title' => 'عنوان امتیاز دهی به تیکت ',
        'default' => 'لطفا امتیاز تیکت را ثبت نمایید',
        'dependency' => array('rating_ticket', '==', 'true')

      ),


    )
  ));

  CSF::createSection($prefix, array(
    'title'  => 'استایل',
    'id'     => 'tkm_style',
    'fields' => array(
      array(
        'id'      => 'primary_color',
        'type'    => 'color',
        'title'   => 'رنگ اصلی',
        'desc'    => 'رنگ برند در پنل کاربری و پنل مدیریت (دکمه‌ها، لینک‌ها و حالت فعال).',
        'default' => '#2A48CD',


      ),
      array(
        'id'      => 'secondary_color',
        'type'    => 'color',
        'title'   => 'رنگ ثانویه',
        'desc'    => 'رنگ حالت هاور دکمه‌های اصلی (اختیاری).',
        'default' => '#2947cc8a',
      ),
      array(
        'id'      => 'text_color',
        'type'    => 'color',
        'title'   => 'رنگ متن',
        'desc'    => 'رنگ متن روی دکمه‌ها و سطوح رنگ اصلی.',
        'default' => '#ffffff',
      ),
    )
  ));

  CSF::createSection($prefix, array(
    'title'  => 'فیلدها',
    'fields' => array(

      array(
        'id'      => 'title_dashboard',
        'type'    => 'text',
        'title'   => 'عنوان داشبورد',
        'default' => 'داشبورد تیکت‌ها',
      ),

      array(
        'id'      => 'description_dashboard',
        'type'    => 'text',
        'title'   => 'توضیحات داشبورد',
        'default' => 'مدیریت تیکت‌های پشتیبانی شما',
      ),
      array(
        'id'      => 'department_label',
        'type'    => 'text',
        'title'   => 'فیلد دپارتمان',
        'default' => 'دپارتمان',
      ),
      array(
        'id'      => 'type_label',
        'type'    => 'text',
        'title'   => 'فیلد نوع تیکت',
        'default' => 'نوع تیکت',
      ),
      array(
        'id'      => 'title_label',
        'type'    => 'text',
        'title'   => 'فیلد عنوان تیکت',
        'default' => ' عنوان تیکت',
      ),
      array(
        'id'      => 'product_label',
        'type'    => 'text',
        'title'   => 'فیلد محصول  ',
        'default' => ' محصول ',
      ),
      array(
        'id'      => 'priority_label',
        'type'    => 'text',
        'title'   => 'فیلد اهمیت تیکت  ',
        'default' => ' اهمیت تیکت ',
      ),
      array(
        'id'      => 'dec_label',
        'type'    => 'text',
        'title'   => 'فیلد  توضیحات  ',
        'default' => '  توضیحات ',
      ),
      array(
        'id'      => 'file_label',
        'type'    => 'text',
        'title'   => 'فیلد  آپلود فایل   ',
        'default' => '  آپلود فایل  ',
      ),
      array(
        'id'      => 'voice_label',
        'type'    => 'text',
        'title'   => 'فیلد  ارسال پیام صوتی   ',
        'default' => '  ارسال صدا  ',
      ),

    ),
  ));

  CSF::createSection($prefix, array(
    'title'  => 'مدیریت آپلود',
    'fields' => array(
      array(
        'type'    => 'subheading',
        'content' => 'در این بخش می‌توانید محدودیت‌های آپلود فایل در تیکت‌ها و پاسخ‌ها را مدیریت کنید.',
      ),
      array(
        'id'      => 'upload_max_size',
        'type'    => 'number',
        'title'   => 'حداکثر حجم هر فایل (مگابایت)',
        'desc'    => 'حداکثر حجم مجاز برای هر فایل آپلودی بر حسب مگابایت.',
        'default' => 5,
        'unit'    => 'MB',
      ),
      array(
        'id'      => 'upload_allowed_ext',
        'type'    => 'text',
        'title'   => 'پسوندهای مجاز',
        'desc'    => 'پسوندهای مجاز را با کاما (,) از هم جدا کنید. مثال: jpg,png,pdf,zip',
        'default' => 'jpg,jpeg,png,gif,webp,pdf,zip,rar,doc,docx,xls,xlsx,txt,mp4,mp3',
      ),
      array(
        'id'      => 'upload_multiple',
        'type'    => 'switcher',
        'title'   => 'آپلود چند فایل',
        'label'   => 'امکان آپلود همزمان چند فایل در هر تیکت / پاسخ',
        'default' => false,
      ),
      array(
        'id'         => 'upload_max_files',
        'type'       => 'number',
        'title'      => 'حداکثر تعداد فایل',
        'desc'       => 'حداکثر تعداد فایل قابل آپلود در حالت چند فایلی.',
        'default'    => 5,
        'dependency' => array('upload_multiple', '==', 'true'),
      ),
    ),
  ));

  CSF::createSection($prefix, array(
    'title'  => 'پاسخ های آماده',
    'fields' => array(
      array(
        'id'     => 'save_message',
        'type'   => 'repeater',
        'title'  => 'پاسخ های آماده ',
        'fields' => array(

          array(
            'id'    => 'save_message_title',
            'type'  => 'text',
            'title' => 'شماره پاسخ'
          ),
          array(
            'id'    => 'message_save',
            'type'  => 'textarea',
            'title' => ' متن پاسخ آماده'
          ),

        ),
      ),



    )
  ));

  CSF::createSection($prefix, array(
    'title'  => 'سوالات متداول',
    'fields' => array(

      // add field
      array(
        'id'      => 'faq_swicher',
        'type'    => 'switcher',
        'title'   => 'سوالات متداول ',
        'label'   => 'آیا سوالات متداول قبل از ثبت تیکت نمایش داده شود ',
        'default' => false
      ),
      array(
        'id'     => 'faqs',
        'type'   => 'repeater',
        'title'  => 'سوال جدید',
        'fields' => array(

          array(
            'id'    => 'faqs-title',
            'type'  => 'text',
            'title' => 'عنوان سوال'
          ),
          array(
            'id'    => 'faqs-body',
            'type'  => 'textarea',
            'title' => 'توضیح سوال'
          ),

        ),
      ),

    )
  ));

  CSF::createSection($prefix, array(
    'title'  => ' وضعیت ها',
    'fields' => array(

      // add field
      array(
        'id'      => 'open-color',
        'type'    => 'color',
        'title'   => 'رنگ وضعیت باز',
        'label'   => 'رنگ نمایش وضعیت ',
        'default' => '#4bcc58'
      ),
      array(
        'id'      => 'close-color',
        'type'    => 'color',
        'title'   => 'رنگ وضعیت پاسخ داده شده',
        'label'   => 'رنگ نمایش وضعیت ',
        'default' => '#3150d8'
      ),
      array(
        'id'      => 'answerd-color',
        'type'    => 'color',
        'title'   => 'رنگ وضعیت بسته شده ',
        'label'   => 'رنگ نمایش وضعیت ',
        'default' => '#ff2121'
      ),
      array(
        'id'      => 'finish-color',
        'type'    => 'color',
        'title'   => 'رنگ وضعیت پایان یافته',
        'label'   => 'رنگ نمایش وضعیت ',
        'default' => '#000000'
      ),
      array(
        'id'      => 'trash-color',
        'type'    => 'color',
        'title'   => 'رنگ وضعیت زباله دان ',
        'label'   => 'رنگ نمایش وضعیت ',
        'default' => '#000000'
      ),
      array(
        'id'     => 'statues',
        'type'   => 'repeater',
        'title'  => 'وضعیت جدید ',
        'fields' => array(

          array(
            'id'    => 'status-title',
            'type'  => 'text',
            'title' => 'نام وضعیت '
          ),
          array(
            'id'    => 'status-slug',
            'type'  => 'text',
            'title' => 'نامک  '
          ),
          array(
            'id'    => 'color-status',
            'type'  => 'color',
            'title' => 'رنگ وضعیت '
          ),

        ),
      ),





    )
  ));

  CSF::createSection($prefix, array(
    'title' => 'پیامک',
    'id' => 'sms_section'



  ));

  CSF::createSection($prefix, array(
    'parent' => 'sms_section',
    'title'  => 'تنظیمات سامانه',
    'fields' => array(

      // add field
      array(
        'id'    => 'sms-service',
        'type'  => 'select',
        'title' => 'سامانه های پیامکی',
        'options' => array(
          'melipayamak' => "ملی پیامک",
          'kavenegar'   => "کاوه‌نگار",


        ),
      ),

      array(
        'id'    => 'phone-service-key-user',
        'type'  => 'select',
        'title' => '  کلید شماره موبایل ',
        'options' => 'tkm_user_meta_key'


      ),

      array(
        'id'    => 'sms-username',
        'type'  => 'text',
        'title' => 'نام کابری سامانه  ',
        'dependency' => array('sms-service', '==', 'melipayamak'),


      ),
      array(
        'id'    => 'sms-password',
        'type'  => 'text',
        'title' => ' رمز عبور سامانه ',
        'dependency' => array('sms-service', '==', 'melipayamak'),


      ),
      array(
        'id'    => 'sms-api',
        'type'  => 'text',
        'title' => ' کد api ',
        'dependency' => array('sms-service', '==', 'kavenegar'),


      ),
    )
  ));

  CSF::createSection($prefix, array(
    'parent' => 'sms_section',
    'title' => 'ارسال تیکت',
    'id' => 'sms_ticket_section',
    'fields' => array(



      array(
        'id'      => 'user_create_sms',
        'type'    => 'switcher',
        'title'   => 'فعال سازی',
        'label'   => ' ارسال پیامک هنگام ایجاد تیکت',
        'default' => false
      ),
      array(
        'id'      => 'user_create_sms_pattern_code',
        'type'    => 'text',
        'title'   => 'کد الگو',
        'default' => false
      ),
      array(
        'id'      => 'user_create_pattern',
        'type'    => 'textarea',
        'title'   => 'الگو',
        'default' => false
      ),
      array(
        'type'    => 'content',
        'content' => '<p>شناسه تیکت : {{ticket_id}}</p>' .
          '<p>عنوان تیکت : {{title}}</p>' .
          '<p>دپارتمان تیکت : {{department}}</p>' .
          '<p> وضعیت تیکت : {{status}}</p>' .
          '<p>اهمیت تیکت : {{priority}}</p>' .
          '<p>تاریخ تیکت : {{date}}</p>',


      ),

    )
  ));

  CSF::createSection($prefix, array(
    'title'  => 'تیم و تخصیص',
    'fields' => array(
      array(
        'type'    => 'subheading',
        'content' => 'کارشناسان هر دپارتمان از صفحه «تیم و دپارتمان‌ها» تعیین می‌شوند. کاربرانی با نقش «کارشناس پشتیبانی» فقط به صندوق پشتیبانی دسترسی دارند.',
      ),
      array(
        'id'      => 'assign_mode',
        'type'    => 'select',
        'title'   => 'تخصیص خودکار تیکت‌های جدید',
        'options' => array(
          'manual'       => 'دستی (بدون تخصیص خودکار)',
          'round_robin'  => 'نوبتی بین کارشناسان دپارتمان (Round robin)',
          'least_loaded' => 'به کارشناسی که کمترین تیکت باز را دارد',
        ),
        'default' => 'manual',
      ),
      array(
        'id'      => 'agent_scope',
        'type'    => 'select',
        'title'   => 'کارشناسان کدام تیکت‌ها را ببینند؟',
        'options' => array(
          'departments' => 'فقط تیکت‌های دپارتمان‌های خودشان و تیکت‌هایی که به آن‌ها سپرده شده',
          'all'         => 'همه تیکت‌ها',
        ),
        'default' => 'departments',
        'desc'    => 'مدیران سایت و مدیران فروشگاه همیشه همه تیکت‌ها را می‌بینند.',
      ),
    ),
  ));

  CSF::createSection($prefix, array(
    'title'  => 'SLA و زمان پاسخ',
    'fields' => array(
      array(
        'id'      => 'sla_enable',
        'type'    => 'switcher',
        'title'   => 'فعال‌سازی SLA',
        'label'   => 'برای هر تیکت مهلت اولین پاسخ و مهلت حل تعیین شود',
        'default' => true,
      ),
      array('type' => 'subheading', 'content' => 'مهلت اولین پاسخ (ساعت)', 'dependency' => array('sla_enable', '==', 'true')),
      array('id' => 'sla_first_high', 'type' => 'number', 'title' => 'اولویت زیاد', 'default' => 2, 'unit' => 'ساعت', 'dependency' => array('sla_enable', '==', 'true')),
      array('id' => 'sla_first_medium', 'type' => 'number', 'title' => 'اولویت متوسط', 'default' => 8, 'unit' => 'ساعت', 'dependency' => array('sla_enable', '==', 'true')),
      array('id' => 'sla_first_low', 'type' => 'number', 'title' => 'اولویت کم', 'default' => 24, 'unit' => 'ساعت', 'dependency' => array('sla_enable', '==', 'true')),
      array('type' => 'subheading', 'content' => 'مهلت حل تیکت (ساعت)', 'dependency' => array('sla_enable', '==', 'true')),
      array('id' => 'sla_resolve_high', 'type' => 'number', 'title' => 'اولویت زیاد', 'default' => 24, 'unit' => 'ساعت', 'dependency' => array('sla_enable', '==', 'true')),
      array('id' => 'sla_resolve_medium', 'type' => 'number', 'title' => 'اولویت متوسط', 'default' => 48, 'unit' => 'ساعت', 'dependency' => array('sla_enable', '==', 'true')),
      array('id' => 'sla_resolve_low', 'type' => 'number', 'title' => 'اولویت کم', 'default' => 96, 'unit' => 'ساعت', 'dependency' => array('sla_enable', '==', 'true')),
      array(
        'id'         => 'sla_business_hours',
        'type'       => 'switcher',
        'title'      => 'ساعات کاری',
        'label'      => 'مهلت‌ها فقط در ساعات کاری شمرده شوند',
        'default'    => false,
        'dependency' => array('sla_enable', '==', 'true'),
      ),
      array(
        'id'         => 'sla_work_days',
        'type'       => 'checkbox',
        'title'      => 'روزهای کاری',
        'inline'     => true,
        'options'    => array('6' => 'شنبه', '0' => 'یکشنبه', '1' => 'دوشنبه', '2' => 'سه‌شنبه', '3' => 'چهارشنبه', '4' => 'پنجشنبه', '5' => 'جمعه'),
        'default'    => array('6', '0', '1', '2', '3'),
        'dependency' => array('sla_enable|sla_business_hours', '==|==', 'true|true'),
      ),
      array('id' => 'sla_work_start', 'type' => 'text', 'title' => 'شروع ساعت کاری', 'default' => '09:00', 'desc' => 'به شکل ۲۴ ساعته، مثلا 09:00', 'dependency' => array('sla_enable|sla_business_hours', '==|==', 'true|true')),
      array('id' => 'sla_work_end', 'type' => 'text', 'title' => 'پایان ساعت کاری', 'default' => '17:00', 'dependency' => array('sla_enable|sla_business_hours', '==|==', 'true|true')),
    ),
  ));

  CSF::createSection($prefix, array(
    'title'  => 'اعلان‌ها',
    'fields' => array(
      array('type' => 'subheading', 'content' => 'ایمیل‌ها با قالب برند (رنگ اصلی) و به‌صورت راست‌چین ارسال می‌شوند.'),
      array('id' => 'notify_customer_created', 'type' => 'switcher', 'title' => 'تایید ثبت تیکت', 'label' => 'ایمیل تایید به مشتری پس از ثبت تیکت', 'default' => true),
      array('id' => 'notify_customer_reply', 'type' => 'switcher', 'title' => 'پاسخ کارشناس', 'label' => 'ایمیل به مشتری وقتی کارشناس پاسخ می‌دهد', 'default' => true),
      array('id' => 'notify_agent_email', 'type' => 'switcher', 'title' => 'اعلان کارشناسان', 'label' => 'ایمیل به کارشناس برای تیکت جدید، پاسخ مشتری و تیکت‌های سپرده‌شده', 'default' => true),
      array('id' => 'notify_sla_email', 'type' => 'switcher', 'title' => 'هشدار SLA', 'label' => 'ایمیل هشدار هنگام عبور از مهلت', 'default' => true),
      array('id' => 'email_from_name', 'type' => 'text', 'title' => 'نام فرستنده', 'desc' => 'خالی = نام سایت'),
      array('id' => 'email_from_address', 'type' => 'text', 'title' => 'ایمیل فرستنده', 'desc' => 'خالی = پیش‌فرض وردپرس'),
      array('type' => 'subheading', 'content' => 'پیامک پاسخ (از سامانه انتخاب‌شده در بخش «پیامک»)'),
      array('id' => 'sms_on_reply', 'type' => 'switcher', 'title' => 'پیامک پاسخ', 'label' => 'ارسال پیامک به مشتری وقتی کارشناس پاسخ می‌دهد', 'default' => false),
      array('id' => 'sms_reply_pattern_code', 'type' => 'text', 'title' => 'کد / نام الگوی پیامک پاسخ', 'desc' => 'مقادیر الگو به ترتیب: شناسه تیکت، عنوان تیکت', 'dependency' => array('sms_on_reply', '==', 'true')),
    ),
  ));

  CSF::createSection($prefix, array(
    'title'  => 'پایگاه دانش',
    'fields' => array(
      array('id' => 'kb_enable', 'type' => 'switcher', 'title' => 'پیشنهاد مقاله', 'label' => 'هنگام نوشتن تیکت، مقاله‌های مرتبط به مشتری پیشنهاد شود', 'default' => true),
      array('id' => 'kb_slug', 'type' => 'text', 'title' => 'نامک آدرس مقاله‌ها', 'default' => 'help', 'desc' => 'مثلا example.com/help/نام-مقاله — پس از تغییر، یک‌بار «تنظیمات › پیوندهای یکتا» را ذخیره کنید.'),
      array('type' => 'content', 'content' => '<p>مقاله‌ها را از منوی «پایگاه دانش» در پیشخوان بنویسید. برای نمایش مرکز راهنما در یک صفحه از شورت‌کد <code>[ticketchi_kb]</code> استفاده کنید.</p>'),
    ),
  ));

  CSF::createSection($prefix, array(
    'title'  => 'فروشگاه',
    'fields' => array(
      array(
        'id'      => 'product_setting',
        'type'    => 'switcher',
        'title'   => ' پشتیبانی محصولات ووکامرس ',
        'label'   => '  آیا می‌خواهید کاربر برای محصولات ووکامرس خریداری شده تیکت ثبت نماید  ',
        'default' => false
      ),
      array(
        'id'      => 'edd_setting',
        'type'    => 'switcher',
        'title'   => ' پشتیبانی محصولات  Easy Digital Download ',
        'label'   => '  آیا می‌خواهید کاربر برای محصولات Easy Digital Download خریداری شده تیکت ثبت نماید  ',
        'default' => false
      ),
    )
  ));

  CSF::createSection($prefix, array(
    'title'  => 'پاسخ خودکار ',
    'fields' => array(

      // add field
      array(
        'id'      => 'auto_reply',
        'type'    => 'switcher',
        'title'   => ' پاسخ خودکار ',
        'label'   => 'این پاسخ بعد از ثبت تیکت توسط کاربر نمایش داده میشود ',
        'default' => false
      ),
      array(
        'id'      => 'auto_reply_text',
        'type'    => 'textarea',
        'title'   => ' متن پاسخ خودکار ',
        'placeholder'   => ' این متن در پاسخ خودکار نمایش داده خواهد شد ',
        'dependency' => array('auto_reply', '==', 'true')

      ),
    )
  ));

  CSF::createSection($prefix, array(
    'title'  => 'شورت کد',
    'fields' => array(

      // add field
      array(
        'id'      => 'shortcode',
        'type'    => 'switcher',
        'title'   => 'شورت کد',
        'label'   => 'با فعال سازی قابلیت شورت کد، امکان استفاده از آن در صفحات دلخواه شما فراهم می‌شود',
        'default' => false
      ),
      array(
        'id'      => 'shortcode_info',
        'type'    => 'content',
        'content' => '<h3>شورت کد در دسترس: <span style="color:#2a48cd">[tickets]</span></h3>
        <p>برای نمایش لیست تیکت‌ها در هر کجای سایت، این کد را در ویرایشگر متن یا HTML صفحه مورد نظرتان قرار دهید.</p>
'
      ),
    )
  ));
}

/**
 * Wrap the Codestar settings screen in the Ticketchi admin shell.
 */
function tkm_settings_is_screen()
{
  return is_admin() && isset($_GET['page']) && $_GET['page'] === 'tkm-settings';
}

add_action('csf_options_before', function () {
  if (!tkm_settings_is_screen()) {
    return;
  }
  include TKM_VIEWS_PATH . 'admin/partials/header.php';
  $tkm_head_title = 'تنظیمات';
  $tkm_head_desc = 'ظاهر، فرم‌ها، پیامک، پاسخ‌های آماده و سایر قابلیت‌های تیکت‌چی';
  include TKM_VIEWS_PATH . 'admin/partials/page-head.php';
  echo '<div class="tkm-settings">';
});

add_action('csf_options_after', function () {
  if (!tkm_settings_is_screen()) {
    return;
  }
  echo '</div>';
  include TKM_VIEWS_PATH . 'admin/partials/footer.php';
});
