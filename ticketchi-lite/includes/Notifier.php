<?php

namespace TicketchiLite;

defined('ABSPATH') || exit;

/**
 * Branded RTL emails for new tickets and replies. Every message can be turned
 * off in «تنظیمات › ایمیل‌ها».
 */
final class Notifier
{
    public static function register()
    {
        add_action('ticketchi_lite_ticket_created', [__CLASS__, 'ticket_created'], 20, 2);
        add_action('ticketchi_lite_reply_added', [__CLASS__, 'reply_added'], 20, 4);
    }

    public static function ticket_created($ticket_id, $actor_id)
    {
        $ticket = Tickets::find($ticket_id);
        if (!$ticket) {
            return;
        }
        $number = ticketchi_lite_ticket_number($ticket->ID);

        if (Settings::bool('notify_customer_created')) {
            self::email_customer($ticket, 'تیکت شما ثبت شد — ' . $number, 'درخواست شما دریافت شد', '<p>تیکت «' . esc_html($ticket->title) . '» با شناسه ' . esc_html($number) . ' ثبت شد. پاسخ پشتیبانی از طریق ایمیل به شما اطلاع داده می‌شود.</p>');
        }
        if (Settings::bool('notify_agent_email')) {
            self::email_staff($ticket, 'تیکت جدید: ' . $ticket->title, 'تیکت جدیدی ثبت شد', '<p>' . esc_html(ticketchi_lite_user_name($actor_id)) . ' تیکت جدیدی ثبت کرده است.</p>' . self::quote($ticket->body));
        }
    }

    public static function reply_added($ticket_id, $reply_id, $actor_id, $staff)
    {
        global $wpdb;
        $ticket = Tickets::find($ticket_id);
        if (!$ticket) {
            return;
        }
        $reply = $wpdb->get_row($wpdb->prepare('SELECT * FROM %i WHERE ID = %d', Tickets::replies_table(), (int) $reply_id));
        $body = $reply ? $reply->body : '';

        if ($staff) {
            if (Settings::bool('notify_customer_reply')) {
                self::email_customer($ticket, 'پاسخ جدید به تیکت ' . ticketchi_lite_ticket_number($ticket->ID), 'پاسخ جدیدی برای شما ثبت شد', '<p>' . esc_html(ticketchi_lite_user_name($actor_id)) . ' به تیکت «' . esc_html($ticket->title) . '» پاسخ داد:</p>' . self::quote($body));
            }
            return;
        }

        if (Settings::bool('notify_agent_email')) {
            self::email_staff($ticket, 'پاسخ مشتری: ' . $ticket->title, 'مشتری پاسخ داد', '<p>' . esc_html(ticketchi_lite_user_name($actor_id)) . ' به تیکت پاسخ داد:</p>' . self::quote($body));
        }
    }

    private static function email_customer($ticket, $subject, $headline, $body)
    {
        $user = get_userdata(Tickets::customer_id($ticket));
        if ($user) {
            self::send($user->user_email, $subject, $headline, $body, ticketchi_lite_url('single', $ticket->ID), 'مشاهده تیکت');
        }
    }

    private static function email_staff($ticket, $subject, $headline, $body)
    {
        $to = Settings::get('notify_staff_address', '');
        $to = is_email($to) ? $to : get_option('admin_email');
        self::send($to, $subject, $headline, $body, Admin::ticket_url($ticket->ID), 'باز کردن در صندوق پشتیبانی');
    }

    /** Short quote of a message body. */
    private static function quote($html)
    {
        $text = wp_trim_words(wp_strip_all_tags((string) $html), 60, '…');
        return $text === '' ? '' : '<div style="margin-top:12px;padding:14px 16px;background:#f6f7f9;border-radius:10px;border-right:3px solid #d5d9e0;color:#475467">' . esc_html($text) . '</div>';
    }

    /** Inline-styled HTML email (works in Gmail / Outlook). */
    public static function send($to, $subject, $headline, $body_html, $button_url = '', $button_label = '')
    {
        if (!is_email($to)) {
            return false;
        }

        $brand = sanitize_hex_color(Settings::get('primary_color', '#2A48CD')) ?: '#2A48CD';
        $site = wp_specialchars_decode(get_bloginfo('name'), ENT_QUOTES);

        $button = $button_url ? '<p style="margin:28px 0 8px"><a href="' . esc_url($button_url) . '" style="display:inline-block;background:' . $brand . ';color:#ffffff;text-decoration:none;padding:12px 24px;border-radius:10px;font-weight:bold;font-size:14px">' . esc_html($button_label) . '</a></p>' : '';

        $html = '<!doctype html><html lang="fa" dir="rtl"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width"></head>'
            . '<body style="margin:0;padding:0;background:#f4f5f7;font-family:Tahoma,Vazirmatn,sans-serif;direction:rtl">'
            . '<table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background:#f4f5f7;padding:32px 12px"><tr><td align="center">'
            . '<table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="max-width:560px;background:#ffffff;border-radius:16px;overflow:hidden;border:1px solid #e7e9ee">'
            . '<tr><td style="background:' . $brand . ';padding:18px 28px;color:#ffffff;font-size:15px;font-weight:bold;text-align:right">' . esc_html($site) . '</td></tr>'
            . '<tr><td style="padding:28px;text-align:right;color:#0f1729;font-size:14px;line-height:2">'
            . '<h1 style="margin:0 0 12px;font-size:18px;color:#0f1729">' . esc_html($headline) . '</h1>'
            . $body_html . $button
            . '</td></tr>'
            . '<tr><td style="padding:16px 28px;background:#f9fafb;color:#8a94a6;font-size:12px;text-align:right;border-top:1px solid #e7e9ee">این ایمیل به‌صورت خودکار از سامانه پشتیبانی ' . esc_html($site) . ' ارسال شده است.</td></tr>'
            . '</table></td></tr></table></body></html>';

        $headers = ['Content-Type: text/html; charset=UTF-8'];
        $from_email = Settings::get('email_from_address', '');
        if (is_email($from_email)) {
            $from_name = Settings::get('email_from_name', '') ?: $site;
            $headers[] = 'From: ' . str_replace(['"', "\r", "\n"], '', $from_name) . ' <' . $from_email . '>';
        }

        return wp_mail($to, $subject, $html, $headers);
    }
}
