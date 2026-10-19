# دیزاین سیستم تیکت‌چی

یک دیزاین سیستم مشترک برای **پنل کاربری** (اندپوینت حساب کاربری ووکامرس + شورت‌کد `[tickets]`) و **پنل مدیریت**.

| فایل | نقش |
|---|---|
| `assets/shared/css/tkm-ui.css` | توکن‌ها + کامپوننت‌های پایه (مشترک) |
| `assets/front/css/tkm-front.css` | چیدمان پنل کاربری + محافظت در برابر استایل قالب‌ها |
| `assets/admin/css/tkm-admin.css` | شل SaaS پنل مدیریت، جدول‌ها، نمودارها، اسکین Codestar / select2 / ویرایشگر |
| `assets/shared/fonts/vazirmatn-variable.woff2` | فونت وزیرمتن (متغیر، لایسنس OFL) |
| `inc/tkm-ui.php` | هلپرهای PHP: آیکون، بج، آواتار، تاریخ شمسی، URL، دسترسی، پیام فلش |

## اصول

1. **ایزوله**: همه چیز زیر کلاس `.tkm-ui` است؛ هیچ استایلی به قالب سایت یا بقیه wp-admin نشت نمی‌کند.
2. **توکن‌محور**: رنگ، فاصله، شعاع و سایه فقط از متغیرهای CSS خوانده می‌شوند. حالت تاریک = بازتعریف همان توکن‌ها.
3. **راست‌چین واقعی**: `direction: rtl` و ویژگی‌های منطقی (`inline-start`) — مستقل از زبان پیشخوان وردپرس.
4. **بدون وابستگی سنگین**: بدون Tailwind، SweetAlert یا کتابخانه نمودار؛ آیکون‌ها SVG درون‌خطی و آواتارها حرف اول نام (بدون درخواست به Gravatar).
5. **ریسپانسیو با Container Query** در پنل کاربری تا در ستون باریک «حساب کاربری» هم درست بنشیند.

## توکن‌ها

```css
.tkm-ui {
  --tkm-primary        /* رنگ برند — از تنظیمات «استایل > رنگ اصلی» */
  --tkm-on-primary     /* رنگ متن روی برند — «رنگ متن» */
  --tkm-primary-hover  /* «رنگ ثانویه» (اگر رنگ کامل و غیرشفاف باشد) */
  --tkm-primary-soft   /* تینت ملایم برند برای پس‌زمینه‌ها */

  --tkm-bg --tkm-surface --tkm-surface-2 --tkm-surface-3
  --tkm-border --tkm-border-strong
  --tkm-text --tkm-text-2 --tkm-text-3

  --tkm-success --tkm-warning --tkm-danger --tkm-info
  --tkm-p-low --tkm-p-medium --tkm-p-high   /* رنگ اولویت‌ها */

  --tkm-r-xs 6 · --tkm-r-sm 10 · --tkm-r 14 · --tkm-r-lg 20
  --tkm-shadow-xs · --tkm-shadow-sm · --tkm-shadow · --tkm-shadow-lg
  --tkm-fs-xs 12 · sm 13 · base 14 · md 15 · lg 18 · xl 22 · 2xl 28
}
.tkm-ui[data-theme="dark"] { /* همان توکن‌ها برای حالت تاریک */ }
```

رنگ وضعیت‌ها (باز، پاسخ داده، … و وضعیت‌های سفارشی) همچنان از تنظیمات «وضعیت‌ها» می‌آیند و با متغیر `--c` به بج‌ها داده می‌شوند.

## کامپوننت‌ها

| کلاس | توضیح |
|---|---|
| `.tkm-btn` + `--primary` `--soft` `--ghost` `--danger` · `--sm` `--lg` `--icon` `--block` | دکمه‌ها؛ `.is-loading` اسپینر نشان می‌دهد |
| `.tkm-card` › `__head` `__title` `__sub` `__body` `__foot` | کارت |
| `.tkm-badge` (با `--c`) | بج وضعیت — `tkm_status_badge($slug)` |
| `.tkm-priority--low/medium/high` | نشانگر اولویت — `tkm_priority_badge($p)` |
| `.tkm-avatar` `--sm` `--lg` | آواتار حرف اول — `tkm_avatar($user_id)` |
| `.tkm-field` `.tkm-label` `.tkm-hint` `.tkm-input` `.tkm-select` `.tkm-textarea` | فرم |
| `.tkm-segmented` | انتخاب گزینه‌ای (رادیو) |
| `.tkm-switch` | سوییچ |
| `.tkm-dropzone` `.tkm-filelist` `.tkm-file` | آپلود و چیپ فایل‌ها — `tkm_files_html($value)` |
| `.tkm-alert` `--success` `--warning` `--danger` | پیام درون‌صفحه |
| `.tkm-empty` | حالت خالی |
| `.tkm-modal` · `.tkm-toast` | دیالوگ و اعلان شناور (`TKM_UI.toast()` / `TKM_ADMIN.toast()`) |
| `.tkm-stars` | امتیازدهی ستاره‌ای |
| `.tkm-recorder` | ضبط پیام صوتی |

## هلپرهای PHP (`inc/tkm-ui.php`)

```php
tkm_icon('inbox', 18);                 // آیکون SVG
tkm_url('single', $ticket_id);         // لینک درست در شورت‌کد یا حساب کاربری
tkm_admin_url('tkm-edit-ticket', ['id' => 5]);
tkm_user_can_access_ticket($ticket);   // سازنده، گیرنده یا کارشناس
tkm_is_staff($user_id);                // مدیر، مدیر فروشگاه یا کارشناس دپارتمان
tkm_date($datetime);                   // «۸ مهر ۱۴۰۵ — ۲۲:۴۷» (شمسی، منطقه زمانی سایت)
tkm_time_ago($datetime);               // «۳ ساعت پیش»
tkm_content($body);                    // خروجی امن متن تیکت/پاسخ
tkm_flash('ذخیره شد');                 // پیام فلش پنل مدیریت (بعد از ریدایرکت)
```

## نمودارهای داشبورد

SVG خالص، بدون کتابخانه: ستون‌ها حداکثر ۲۴px با گوشه ۴px، خطوط راهنمای یک‌پیکسلی، تولتیپ هنگام هاور، و «نمایش جدول داده‌ها» برای دسترسی‌پذیری. محور زمان راست‌به‌چپ است (قدیمی‌ترین روز سمت راست).
