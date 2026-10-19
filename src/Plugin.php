<?php

namespace Ticketchi;

use Ticketchi\Support\Capabilities;

defined('ABSPATH') || exit('NO Access');

/**
 * v2 bootstrap. Wires the modules together; each one only talks to the
 * others through Domain\Tickets and the «ticketchi/*» events.
 *
 *   Domain        Tickets (write path), TicketQuery, Activity, Tags, Customers, Presenter
 *   Sla           due dates in business hours, breach cron
 *   Team          auto-assignment (round robin / least loaded)
 *   Notifications in-app + branded email + SMS
 *   Automation    if-this-then-that rules (event + time based)
 *   Reports       analytics + CSV
 *   Kb            help-center articles + suggestions
 *   Rest          ticketchi/v1 API for the admin app
 */
final class Plugin
{
    private static $booted = false;

    public static function boot()
    {
        if (self::$booted) {
            return;
        }
        self::$booted = true;

        require_once TKM_INC_PATH . 'sms-services/tkm-kavenegar.php';

        add_action('init', [Database\Migrations::class, 'maybe_run'], 1);

        Capabilities::register();
        Sla\Sla::register();
        Team\Assignment::register();
        Notifications\Notifier::register();
        Automation\Engine::register();
        Kb\KnowledgeBase::register();
        Rest\Api::register();
        Admin\Screens::register();

        add_action('admin_post_tkm_reports_csv', [__CLASS__, 'download_csv']);
    }

    public static function download_csv()
    {
        if (!Capabilities::can_manage() || !check_admin_referer('tkm_reports_csv')) {
            wp_die('اجازه دسترسی ندارید.', '', ['response' => 403]);
        }
        Reports\Reports::csv(
            isset($_GET['from']) ? sanitize_text_field(wp_unslash($_GET['from'])) : '',
            isset($_GET['to']) ? sanitize_text_field(wp_unslash($_GET['to'])) : ''
        );
    }

    /** Called on plugin activation. */
    public static function activate()
    {
        Database\Migrations::run();
        flush_rewrite_rules(false);
    }

    public static function deactivate()
    {
        wp_clear_scheduled_hook(Sla\Sla::CRON);
        wp_clear_scheduled_hook(Automation\Engine::CRON);
    }
}
