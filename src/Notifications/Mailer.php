<?php

namespace Ticketchi\Notifications;

use Ticketchi\Support\Settings;

defined('ABSPATH') || exit('NO Access');

/**
 * Branded, RTL, inline-styled HTML emails (works in Gmail/Outlook).
 */
final class Mailer
{
    public static function send($to, $subject, $headline, $body_html, $button_url = '', $button_label = 'مشاهده تیکت')
    {
        if (!is_email($to)) {
            return false;
        }

        $brand = sanitize_hex_color(Settings::get('primary_color', '#2A48CD')) ?: '#2A48CD';
        $site = wp_specialchars_decode(get_bloginfo('name'), ENT_QUOTES);
        $from_name = Settings::get('email_from_name', $site);

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
            $headers[] = 'From: ' . $from_name . ' <' . $from_email . '>';
        }

        return wp_mail($to, $subject, $html, $headers);
    }

    /** Short quote of a message body for emails. */
    public static function quote($html)
    {
        $text = wp_trim_words(wp_strip_all_tags((string) $html), 60, '…');
        return $text === '' ? '' : '<div style="margin-top:12px;padding:14px 16px;background:#f6f7f9;border-radius:10px;border-right:3px solid #d5d9e0;color:#475467">' . esc_html($text) . '</div>';
    }
}
