=== Ticketchi Lite ===
Contributors: tahakarami
Tags: support ticket, helpdesk, ticket system, persian, rtl
Requires at least: 6.2
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

A lightweight, right-to-left (Persian) support ticket system for WordPress: a customer ticket panel and a modern support inbox.

== Description ==

**Ticketchi Lite** adds a simple, good-looking helpdesk to Persian (RTL) WordPress sites.

Put the `[ticketchi]` shortcode on a page: logged-in customers open tickets, follow the conversation and close a ticket when their problem is solved. You answer everything from a full-screen, modern support inbox inside wp-admin.

= Features =

* Customer panel: open a ticket (department, title, description), ticket list with status overview, conversation and reply, close a solved ticket
* Support inbox: open / waiting for customer / closed / all / trash views, reply, «reply and close», change status, trash and delete
* Departments
* Email to the customer when support replies, and to the site admin for new tickets and customer replies
* Solar Hijri (Jalali) dates, bundled Vazirmatn font, fully right-to-left
* Works on restricted networks: the plugin makes **no requests to external servers** (no Gravatar, no CDN, no fonts from Google, no tracking)

= Ticketchi Pro =

Ticketchi Lite is intentionally small. For a real support team, [Ticketchi Pro](https://www.rtl-theme.com/ticketchi-wordpress-plugin/) adds:

* File attachments and voice messages
* WooCommerce «My account» tickets, product selection and customer orders
* Search, filters, sorting, ticket priority, tags and bulk actions
* Support agents, unlimited responders per department, sub-departments and automatic assignment
* SLA with business hours, automation rules, dashboard and reports
* Internal notes, canned replies, FAQ, 360° customer profile
* SMS notifications, knowledge base, brand colours, dark mode and much more

Upgrading keeps every ticket, department and setting.

= سیستم تیکت پشتیبانی فارسی =

تیکت‌چی لایت یک سیستم تیکت پشتیبانی سبک، فارسی و راست‌چین برای وردپرس است: پنل تیکت مشتری با شورت‌کد `[ticketchi]` و صندوق پشتیبانی مدرن در پیشخوان. افزونه هیچ درخواستی به سرورهای خارجی نمی‌فرستد و در شرایط اینترنت ملی هم بدون مشکل کار می‌کند. برای پیوست فایل، ووکامرس، کارشناسان پشتیبانی، SLA، اتوماسیون، گزارش‌ها و پیامک، [نسخه حرفه‌ای تیکت‌چی](https://www.rtl-theme.com/ticketchi-wordpress-plugin/) را ببینید.

== Installation ==

1. Install and activate the plugin from «Plugins › Add New», or upload the `ticketchi-lite` folder to `/wp-content/plugins/`.
2. Open «Ticketchi › Departments» and create at least one department.
3. Add the `[ticketchi]` shortcode to a page and link to it from your menu.

== Frequently Asked Questions ==

= Who can answer tickets? =

Site administrators (and WooCommerce shop managers). Support agents with their own role are part of Ticketchi Pro.

= Can customers attach files? =

Not in Ticketchi Lite. Attachments and voice messages are part of Ticketchi Pro.

= Does it work with WooCommerce? =

Yes, through the `[ticketchi]` shortcode on any page. The «My account › Support tickets» tab is part of Ticketchi Pro.

= Does the plugin connect to external servers? =

No. Fonts, icons and avatars are bundled, so the plugin also works on networks without access to foreign servers.

= What happens to my tickets if I upgrade to Ticketchi Pro? =

Everything is kept: tickets, replies, departments and settings. Activate Pro, then deactivate and delete Ticketchi Lite.

= Are my tickets deleted when I delete the plugin? =

No. Tickets stay in the database so nothing is lost when switching to Ticketchi Pro or reinstalling.

== Screenshots ==

1. Support inbox – conversation and reply.
2. Customer panel – ticket list with status overview.
3. Customer panel – opening a new ticket.
4. Customer panel – conversation.
5. Departments.
6. Settings.
7. What Ticketchi Pro adds.

== Changelog ==

= 1.0.0 =
* First release.

== Upgrade Notice ==

= 1.0.0 =
First release.
