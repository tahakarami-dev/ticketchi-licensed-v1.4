<?php

namespace Ticketchi\Notifications;

use Ticketchi\Domain\Tickets;
use Ticketchi\Support\Clock;
use Ticketchi\Support\Links;
use Ticketchi\Support\Settings;
use Ticketchi\Team\Assignment;

defined('ABSPATH') || exit('NO Access');

/**
 * Routes domain events to the right people over in-app, email and SMS.
 * Every channel is opt-out per event in «تنظیمات › اعلان‌ها».
 */
final class Notifier
{
    public static function register()
    {
        add_action('ticketchi/ticket_created', [__CLASS__, 'ticket_created'], 20, 3);
        add_action('ticketchi/reply_added', [__CLASS__, 'reply_added'], 20, 5);
        add_action('ticketchi/ticket_assigned', [__CLASS__, 'ticket_assigned'], 20, 4);
        add_action('ticketchi/sla_breached', [__CLASS__, 'sla_breached'], 20, 2);
    }

    /* ───────────── In-app store ───────────── */

    public static function table()
    {
        global $wpdb;
        return $wpdb->prefix . 'tkm_notifications';
    }

    public static function push($user_id, $ticket_id, $type, $message)
    {
        global $wpdb;
        if (!$user_id || !get_userdata($user_id)) {
            return 0;
        }
        $wpdb->insert(self::table(), [
            'user_id'    => (int) $user_id,
            'ticket_id'  => $ticket_id ? (int) $ticket_id : null,
            'type'       => substr($type, 0, 32),
            'message'    => mb_substr($message, 0, 255),
            'is_read'    => 0,
            'created_at' => Clock::now(),
        ], ['%d', '%d', '%s', '%s', '%d', '%s']);
        return (int) $wpdb->insert_id;
    }

    public static function for_user($user_id, $limit = 20)
    {
        global $wpdb;
        return $wpdb->get_results($wpdb->prepare('SELECT * FROM ' . self::table() . ' WHERE user_id = %d ORDER BY ID DESC LIMIT %d', (int) $user_id, (int) $limit));
    }

    public static function unread_count($user_id)
    {
        global $wpdb;
        return (int) $wpdb->get_var($wpdb->prepare('SELECT COUNT(*) FROM ' . self::table() . ' WHERE user_id = %d AND is_read = 0', (int) $user_id));
    }

    public static function mark_read($user_id, $id = null)
    {
        global $wpdb;
        $where = ['user_id' => (int) $user_id];
        if ($id) {
            $where['ID'] = (int) $id;
        }
        return $wpdb->update(self::table(), ['is_read' => 1], $where);
    }

    /* ───────────── Event handlers ───────────── */

    public static function ticket_created($ticket_id, $actor_id, $source)
    {
        $ticket = Tickets::find($ticket_id);
        if (!$ticket) {
            return;
        }
        $label = tkm_ticket_number($ticket->ID) . ' — ' . $ticket->title;

        if ((int) $ticket->from_admin === 1) {
            // Staff opened a ticket for a customer.
            if (Settings::bool('notify_customer_reply', true)) {
                self::email_customer($ticket, 'تیکت جدید از طرف پشتیبانی', 'تیکت جدیدی برای شما ثبت شد', '<p>تیم پشتیبانی برای شما پیام جدیدی با عنوان «' . esc_html($ticket->title) . '» ارسال کرده است.</p>' . Mailer::quote($ticket->body));
            }
            return;
        }

        // Assigned tickets are announced by ticket_assigned instead.
        if (!$ticket->assignee_id) {
            foreach (Assignment::department_agents($ticket->department_id) as $agent) {
                if ((int) $agent === (int) $actor_id) {
                    continue;
                }
                self::push($agent, $ticket->ID, 'new_ticket', 'تیکت جدید: ' . $label);
                if (Settings::bool('notify_agent_email', true)) {
                    self::email_staff($agent, $ticket, 'تیکت جدید: ' . $ticket->title, 'تیکت جدیدی در دپارتمان شما ثبت شد', '<p>' . esc_html(tkm_user_name(Tickets::customer_id($ticket))) . ' تیکت جدیدی ثبت کرده است.</p>' . Mailer::quote($ticket->body));
                }
            }
        }

        if (Settings::bool('notify_customer_created', true)) {
            self::email_customer($ticket, 'تیکت شما ثبت شد — ' . tkm_ticket_number($ticket->ID), 'درخواست شما دریافت شد', '<p>تیکت «' . esc_html($ticket->title) . '» با شناسه ' . esc_html(tkm_ticket_number($ticket->ID)) . ' ثبت شد. پاسخ کارشناسان از طریق ایمیل به شما اطلاع داده می‌شود.</p>');
        }
    }

    public static function reply_added($ticket_id, $reply_id, $actor_id, $staff, $internal)
    {
        $ticket = Tickets::find($ticket_id);
        if (!$ticket || $internal) {
            return;
        }

        global $wpdb;
        $reply = $wpdb->get_row($wpdb->prepare('SELECT * FROM ' . Tickets::replies_table() . ' WHERE ID = %d', (int) $reply_id));
        $label = tkm_ticket_number($ticket->ID) . ' — ' . $ticket->title;

        if ($staff) {
            if (Settings::bool('notify_customer_reply', true)) {
                self::email_customer($ticket, 'پاسخ جدید به تیکت ' . tkm_ticket_number($ticket->ID), 'پاسخ جدیدی برای شما ثبت شد', '<p>' . esc_html(tkm_user_name($actor_id)) . ' به تیکت «' . esc_html($ticket->title) . '» پاسخ داد:</p>' . Mailer::quote($reply ? $reply->body : ''));
            }
            if (Settings::bool('sms_on_reply', false)) {
                self::sms_customer($ticket, Settings::get('sms_reply_pattern_code', ''), [tkm_ticket_number($ticket->ID), $ticket->title]);
            }
            return;
        }

        // Customer replied.
        $recipients = $ticket->assignee_id ? [(int) $ticket->assignee_id] : Assignment::department_agents($ticket->department_id);
        foreach ($recipients as $agent) {
            if ((int) $agent === (int) $actor_id) {
                continue;
            }
            self::push($agent, $ticket->ID, 'customer_reply', 'پاسخ مشتری در ' . $label);
            if (Settings::bool('notify_agent_email', true)) {
                self::email_staff($agent, $ticket, 'پاسخ مشتری: ' . $ticket->title, 'مشتری پاسخ داد', '<p>' . esc_html(tkm_user_name($actor_id)) . ' به تیکت پاسخ داد:</p>' . Mailer::quote($reply ? $reply->body : ''));
            }
        }
    }

    public static function ticket_assigned($ticket_id, $assignee_id, $previous_id, $actor_id)
    {
        if (!$assignee_id || (int) $assignee_id === (int) $actor_id) {
            return;
        }
        $ticket = Tickets::find($ticket_id);
        if (!$ticket) {
            return;
        }
        self::push($assignee_id, $ticket->ID, 'assigned', 'تیکت ' . tkm_ticket_number($ticket->ID) . ' به شما سپرده شد: ' . $ticket->title);
        if (Settings::bool('notify_agent_email', true)) {
            self::email_staff($assignee_id, $ticket, 'تیکت به شما سپرده شد: ' . $ticket->title, 'تیکت جدیدی به شما سپرده شد', '<p>' . esc_html($actor_id ? tkm_user_name($actor_id) : 'تخصیص خودکار') . ' تیکت «' . esc_html($ticket->title) . '» را به شما سپرد.</p>' . Mailer::quote($ticket->body));
        }
    }

    public static function sla_breached($ticket_id, $kind)
    {
        $ticket = Tickets::find($ticket_id);
        if (!$ticket) {
            return;
        }
        $what = $kind === 'resolution' ? 'حل' : 'اولین پاسخ';
        $recipients = $ticket->assignee_id ? [(int) $ticket->assignee_id] : array_map('intval', get_users(['role' => 'administrator', 'fields' => 'ID', 'number' => 10]));

        foreach ($recipients as $user_id) {
            self::push($user_id, $ticket->ID, 'sla', 'مهلت ' . $what . ' تیکت ' . tkm_ticket_number($ticket->ID) . ' گذشت');
            if (Settings::bool('notify_sla_email', true)) {
                self::email_staff($user_id, $ticket, 'هشدار SLA: ' . $ticket->title, 'مهلت ' . $what . ' گذشت', '<p>مهلت ' . esc_html($what) . ' تیکت «' . esc_html($ticket->title) . '» به پایان رسیده است. لطفا هرچه سریع‌تر رسیدگی کنید.</p>');
            }
        }
    }

    /* ───────────── Channels ───────────── */

    private static function email_customer($ticket, $subject, $headline, $body)
    {
        $user = get_userdata(Tickets::customer_id($ticket));
        if ($user) {
            Mailer::send($user->user_email, $subject, $headline, $body, Links::customer_ticket($ticket->ID));
        }
    }

    private static function email_staff($user_id, $ticket, $subject, $headline, $body)
    {
        $user = get_userdata($user_id);
        if ($user) {
            Mailer::send($user->user_email, $subject, $headline, $body, Links::admin_ticket($ticket->ID), 'باز کردن در صندوق پشتیبانی');
        }
    }

    private static function sms_customer($ticket, $pattern, array $values)
    {
        $service = Settings::get('sms-service', '');
        $class = 'TKM_' . ucfirst((string) $service);
        $meta_key = Settings::get('phone-service-key-user', '');
        if (!$service || !$meta_key || !class_exists($class)) {
            return;
        }
        $phone = get_user_meta(Tickets::customer_id($ticket), $meta_key, true);
        if (is_scalar($phone) && $phone !== '') {
            (new $class((string) $phone, $values, $pattern))->send();
        }
    }
}
