# معماری تیکت‌چی ۲.۰

## لایه‌ها

```
                ┌──────────────────────────────────────────────────────────┐
  UI            │ پنل مشتری (views/front + AJAX v1)   پنل مدیریت (اپ‌های JS) │
                └───────────────┬───────────────────────────┬──────────────┘
                                │ admin-ajax (v1)           │ REST  ticketchi/v1
                ┌───────────────▼───────────────────────────▼──────────────┐
  Domain        │ Ticketchi\Domain\Tickets  ← تنها مسیر نوشتن               │
                │ create · reply · update · set_tags · delete               │
                └───────────────┬──────────────────────────────────────────┘
                                │ رویدادها (do_action)
        ┌──────────────┬────────┴─────┬───────────────┬──────────────────┐
        ▼              ▼              ▼               ▼                  ▼
   Sla (5)      Assignment (10)  Notifier (20)   Engine (30)       Activity log
   مهلت‌ها        تخصیص خودکار      ایمیل/پیامک/زنگوله  قوانین اتوماسیون   تاریخچه تیکت
```

- **یک مسیر نوشتن:** همه تغییرات — از پنل مشتری، صندوق، اتوماسیون، کرون یا کد قدیمی (`TKM_Ticket_Manager`، `TKM_Reply_Manager`) — از `Ticketchi\Domain\Tickets` عبور می‌کنند؛ بنابراین SLA، تاریخچه، اعلان و اتوماسیون هیچ‌وقت جا نمی‌مانند.
- **رویدادها** (برای توسعه‌دهنده‌ها هم قابل استفاده‌اند):

| رویداد | آرگومان‌ها |
|---|---|
| `ticketchi/ticket_created` | `$ticket_id, $actor_id, $source` |
| `ticketchi/reply_added` | `$ticket_id, $reply_id, $actor_id, $is_staff, $is_internal` |
| `ticketchi/ticket_updated` | `$ticket_id, $changes (field => [old, new]), $actor_id` |
| `ticketchi/ticket_assigned` | `$ticket_id, $new_assignee_id, $old_assignee_id, $actor_id` |
| `ticketchi/sla_breached` | `$ticket_id, $kind (first|resolve)` |
| `ticketchi/ticket_rated` | `$ticket_id, $rating, $user_id` |

- **زمان‌ها** همه به UTC (`gmdate`) ذخیره و در نمایش به وقت سایت/شمسی تبدیل می‌شوند.

## پوشه‌ها

```
src/
  autoload.php            بارگذار PSR-4 برای Ticketchi\
  Plugin.php              بوت، فعال‌سازی، غیرفعال‌سازی
  Support/                Clock، Settings، Capabilities (نقش و محدوده دسترسی)، Links
  Database/Migrations.php ارتقای خودکار جدول‌ها (tkm_db_version)
  Domain/                 Tickets، TicketQuery (نماها/فیلتر/جستجو)، Tags، Activity، Customers، Presenter
  Sla/Sla.php             مهلت‌ها با ساعات کاری + کرون بررسی نقض
  Team/Assignment.php     دستی / نوبتی / کم‌کارترین
  Notifications/          Mailer (قالب RTL)، Notifier (زنگوله + ایمیل + پیامک)
  Automation/Engine.php   قوانین «وقتی/اگر/آنگاه» + کرون رویدادهای زمانی
  Reports/Reports.php     شاخص‌ها، سری روزانه، کارشناسان، نقشه حرارتی، CSV
  Kb/KnowledgeBase.php    نوع نوشته مقاله، جستجو، شورت‌کد [ticketchi_kb]
  Rest/Api.php            ticketchi/v1
  Admin/Screens.php       بارگذاری اپ‌ها، حالت تمام‌صفحه و هدایت پیوندهای قدیمی
  Admin/SettingsSchema.php همه تنظیمات (بخش‌ها، پیش‌فرض‌ها، اعتبارسنجی) روی گزینه tkm_settings
assets/admin/js/
  tkm-kit.js              کلاینت REST، قالب‌بندی، زنگوله، Ctrl+K (همه صفحات)
  tkm-inbox.js            صندوق پشتیبانی
  tkm-automations.js      سازنده قوانین
  tkm-reports.js          گزارش‌ها (SVG دستی، بدون کتابخانه)
  tkm-team.js             برچسب‌ها و حجم کار تیم
  tkm-settings.js         اپ تنظیمات
```

## پایگاه داده

| جدول | کاربرد |
|---|---|
| `tkm_tickets` | + `assignee_id`، `first_response_due`، `resolution_due`، `first_response_at`، `resolved_at`، `sla_notified`، `updated_at` |
| `tkm_replies` | + `is_internal` (یادداشت داخلی) |
| `tkm_departments`، `tkm_users`، `tkm_ratings` | مانند قبل |
| `tkm_tags`، `tkm_ticket_tags` | برچسب‌ها |
| `tkm_activity` | تاریخچه هر تیکت |
| `tkm_notifications` | اعلان‌های درون‌برنامه‌ای |
| `tkm_automations` | قوانین اتوماسیون |

## دسترسی‌ها

| قابلیت | چه کسی |
|---|---|
| `tkm_view` | مدیران، مدیران فروشگاه، نقش «کارشناس پشتیبانی» (`tkm_agent`) و هر کاربری که در دپارتمانی پاسخ‌دهنده باشد |
| `tkm_manage` | مدیران سایت و مدیران فروشگاه — تنظیمات، دپارتمان‌ها، اتوماسیون، گزارش‌ها، حذف دائمی |

کارشناسان (به‌طور پیش‌فرض) فقط تیکت‌های دپارتمان‌های خودشان و زیرمجموعه‌ها را به‌علاوه تیکت‌هایی که به آن‌ها سپرده شده می‌بینند؛ این محدودیت هم در فهرست و هم در همه مسیرهای REST اعمال می‌شود. تنظیم: «تیم و تخصیص › کارشناسان کدام تیکت‌ها را ببینند؟».

## REST API — `wp-json/ticketchi/v1`

همه مسیرها (جز `kb/search`) به ورود و نانس `wp_rest` (هدر `X-WP-Nonce`) نیاز دارند. `PATCH/PUT/DELETE` از `X-HTTP-Method-Override` هم پشتیبانی می‌کنند.

| مسیر | توضیح |
|---|---|
| `GET /meta` | کاربر جاری، وضعیت‌ها، دپارتمان‌ها، کارشناسان، برچسب‌ها، پاسخ‌های آماده، شمارنده نماها |
| `GET /inbox` | `view, status, priority, department, assignee, tag, customer, search, sort, page, per_page` |
| `GET/PATCH/DELETE /tickets/{id}` | جزئیات کامل؛ ویرایش `status, priority, department_id, assignee_id, title, note, tags` |
| `POST /tickets/{id}/replies` | `body, internal, close, file[]` (multipart) |
| `POST /tickets/bulk` | `{ids, action: status|priority|assign|tag|trash|restore|delete, value}` |
| `GET /customers/{id}` | پروفایل ۳۶۰ |
| `GET /search?q=` | جستجوی سراسری (تیکت + مشتری) |
| `GET/POST /tags` · `PUT/DELETE /tags/{id}` | برچسب‌ها |
| `GET /notifications` · `POST /notifications/read` | اعلان‌ها |
| `GET/POST /automations` · `PUT/DELETE /automations/{id}` | قوانین (مدیر) |
| `GET /reports?from=Y-m-d&to=Y-m-d` | گزارش (مدیر) |
| `GET/POST /settings` | تنظیمات: ساختار بخش‌ها + مقادیر؛ ذخیره با اعتبارسنجی (مدیر) |
| `GET /kb/search?q=` | جستجوی عمومی مقاله‌ها |

## کرون‌ها

| رویداد | بازه | کار |
|---|---|---|
| `tkm_sla_check` | هر ۱۵ دقیقه | یافتن نقض SLA و اعلان |
| `tkm_automation_tick` | ساعتی | قوانین «منتظر پاسخ پشتیبانی/مشتری» |
| `tkm_auto_close` | روزانه | بستن خودکار تیکت‌های پاسخ‌داده (تنظیمات) |
