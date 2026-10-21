<?php

namespace Ticketchi\Database;

defined('ABSPATH') || exit('NO Access');

/**
 * Versioned, additive schema migrations. They never drop data and run
 * automatically after a plugin update (no re-activation needed).
 */
final class Migrations
{
    const OPTION = 'tkm_db_version';
    const VERSION = '2.0.1';

    public static function maybe_run()
    {
        if (get_option(self::OPTION) !== self::VERSION) {
            self::run();
        }
    }

    public static function run()
    {
        global $wpdb;

        // v1 tables (unchanged structure, created if missing).
        \TKM_DB::create_table();

        $p = $wpdb->prefix;
        $charset = $wpdb->get_charset_collate();

        self::add_columns("{$p}tkm_tickets", [
            'assignee_id'        => 'bigint(20) DEFAULT NULL',
            'first_response_due' => 'datetime DEFAULT NULL',
            'resolution_due'     => 'datetime DEFAULT NULL',
            'first_response_at'  => 'datetime DEFAULT NULL',
            'resolved_at'        => 'datetime DEFAULT NULL',
            'sla_notified'       => 'tinyint(1) NOT NULL DEFAULT 0',
            'updated_at'         => 'datetime DEFAULT NULL',
        ]);
        self::add_index("{$p}tkm_tickets", 'assignee_id', 'assignee_id');

        self::add_columns("{$p}tkm_replies", [
            'from_admin'  => 'tinyint(1) DEFAULT NULL',
            'is_internal' => 'tinyint(1) NOT NULL DEFAULT 0',
        ]);

        $tables = [
            "CREATE TABLE IF NOT EXISTS {$p}tkm_tags (
                ID bigint(20) NOT NULL AUTO_INCREMENT,
                name varchar(64) NOT NULL,
                color varchar(16) NOT NULL DEFAULT '#6366f1',
                PRIMARY KEY (ID)
            ) $charset;",
            "CREATE TABLE IF NOT EXISTS {$p}tkm_ticket_tags (
                ticket_id bigint(20) NOT NULL,
                tag_id bigint(20) NOT NULL,
                PRIMARY KEY (ticket_id, tag_id),
                KEY tag_id (tag_id)
            ) $charset;",
            "CREATE TABLE IF NOT EXISTS {$p}tkm_activity (
                ID bigint(20) NOT NULL AUTO_INCREMENT,
                ticket_id bigint(20) NOT NULL,
                user_id bigint(20) DEFAULT NULL,
                type varchar(32) NOT NULL,
                data text DEFAULT NULL,
                created_at datetime NOT NULL,
                PRIMARY KEY (ID),
                KEY ticket_id (ticket_id)
            ) $charset;",
            "CREATE TABLE IF NOT EXISTS {$p}tkm_notifications (
                ID bigint(20) NOT NULL AUTO_INCREMENT,
                user_id bigint(20) NOT NULL,
                ticket_id bigint(20) DEFAULT NULL,
                type varchar(32) NOT NULL,
                message varchar(255) NOT NULL,
                is_read tinyint(1) NOT NULL DEFAULT 0,
                created_at datetime NOT NULL,
                PRIMARY KEY (ID),
                KEY user_id (user_id)
            ) $charset;",
            "CREATE TABLE IF NOT EXISTS {$p}tkm_automations (
                ID bigint(20) NOT NULL AUTO_INCREMENT,
                name varchar(128) NOT NULL,
                trigger_event varchar(32) NOT NULL,
                conditions text DEFAULT NULL,
                actions text DEFAULT NULL,
                is_active tinyint(1) NOT NULL DEFAULT 1,
                position int(11) NOT NULL DEFAULT 0,
                run_count bigint(20) NOT NULL DEFAULT 0,
                created_at datetime NOT NULL,
                PRIMARY KEY (ID)
            ) $charset;",
        ];

        foreach ($tables as $sql) {
            $wpdb->query($sql);
        }

        // Backfill: legacy replies written by staff in wp-admin already have from_admin=1;
        // tickets without updated_at take their last activity date.
        $wpdb->query("UPDATE {$p}tkm_tickets SET updated_at = COALESCE(reply_date, create_date) WHERE updated_at IS NULL");

        // Sites upgrading from Ticketchi Lite keep their settings (same keys).
        $lite = get_option('ticketchi_lite_settings');
        if (is_array($lite) && $lite) {
            $current = get_option('tkm_settings', []);
            update_option('tkm_settings', (is_array($current) ? $current : []) + $lite);
        }

        \Ticketchi\Support\Capabilities::install();
        \Ticketchi\Admin\SettingsSchema::ensure_defaults();

        update_option(self::OPTION, self::VERSION, false);
    }

    private static function columns($table)
    {
        global $wpdb;
        $cols = $wpdb->get_col("SHOW COLUMNS FROM {$table}", 0);
        return array_map('strtolower', (array) $cols);
    }

    private static function add_columns($table, array $columns)
    {
        global $wpdb;
        $existing = self::columns($table);
        foreach ($columns as $name => $definition) {
            if (!in_array(strtolower($name), $existing, true)) {
                $wpdb->query("ALTER TABLE {$table} ADD COLUMN {$name} {$definition}");
            }
        }
    }

    private static function add_index($table, $name, $column)
    {
        global $wpdb;
        $suppress = $wpdb->suppress_errors(true);
        $exists = $wpdb->get_results("SHOW INDEX FROM {$table} WHERE Key_name = '{$name}'");
        if (!$exists) {
            $wpdb->query("ALTER TABLE {$table} ADD INDEX {$name} ({$column})");
        }
        $wpdb->suppress_errors($suppress);
    }
}
