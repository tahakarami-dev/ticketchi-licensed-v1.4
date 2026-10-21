<?php

namespace TicketchiLite;

defined('ABSPATH') || exit;

/**
 * Database schema. The tables are the ones Ticketchi Pro uses, so an upgrade
 * to Pro keeps every ticket; Pro adds its extra columns and tables itself.
 */
final class Install
{
    const DB_OPTION = 'ticketchi_lite_db_version';
    const DB_VERSION = '1.0.0';

    public static function activate()
    {
        self::create_tables();
        Settings::ensure_defaults();
        update_option(self::DB_OPTION, self::DB_VERSION, false);
        // The WooCommerce endpoint is registered on init; flush after it exists.
        update_option('ticketchi_lite_flush_rewrite', 1, false);
    }

    public static function deactivate()
    {
        flush_rewrite_rules(false);
    }

    public static function maybe_upgrade()
    {
        if (get_option(self::DB_OPTION) !== self::DB_VERSION) {
            self::activate();
        }
    }

    public static function create_tables()
    {
        global $wpdb;
        $p = $wpdb->prefix;
        $charset = $wpdb->get_charset_collate();

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        dbDelta("CREATE TABLE {$p}tkm_departments (
  ID bigint(20) NOT NULL AUTO_INCREMENT,
  name varchar(128) NOT NULL,
  parent bigint(20) NOT NULL DEFAULT '0',
  position int(11) NOT NULL DEFAULT '1',
  description varchar(512) DEFAULT NULL,
  PRIMARY KEY  (ID),
  KEY parent (parent)
) $charset;");

        dbDelta("CREATE TABLE {$p}tkm_tickets (
  ID bigint(20) NOT NULL AUTO_INCREMENT,
  title varchar(256) NOT NULL,
  body text NOT NULL,
  creator_id bigint(20) DEFAULT NULL,
  user_id bigint(20) DEFAULT NULL,
  user_name varchar(64) DEFAULT NULL,
  user_email varchar(128) DEFAULT NULL,
  user_phone varchar(16) DEFAULT NULL,
  from_admin tinyint(1) DEFAULT NULL,
  department_id bigint(20) NOT NULL,
  status varchar(64) NOT NULL,
  priority varchar(32) NOT NULL,
  create_date timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  reply_date varchar(19) DEFAULT NULL,
  voice varchar(512) DEFAULT NULL,
  file text DEFAULT NULL,
  note varchar(512) DEFAULT NULL,
  product bigint(20) DEFAULT NULL,
  edd_product bigint(20) DEFAULT NULL,
  first_response_at datetime DEFAULT NULL,
  resolved_at datetime DEFAULT NULL,
  updated_at datetime DEFAULT NULL,
  PRIMARY KEY  (ID),
  KEY creator_id (creator_id),
  KEY user_id (user_id),
  KEY department_id (department_id),
  KEY status (status)
) $charset;");

        dbDelta("CREATE TABLE {$p}tkm_replies (
  ID bigint(20) NOT NULL AUTO_INCREMENT,
  ticket_id bigint(20) NOT NULL,
  creator_id bigint(20) DEFAULT NULL,
  from_admin tinyint(1) DEFAULT NULL,
  is_internal tinyint(1) NOT NULL DEFAULT '0',
  body text NOT NULL,
  create_date timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  voice varchar(512) DEFAULT NULL,
  file text DEFAULT NULL,
  PRIMARY KEY  (ID),
  KEY ticket_id (ticket_id)
) $charset;");
    }
}
