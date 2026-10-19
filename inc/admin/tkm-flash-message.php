<?php

defined('ABSPATH') || exit('NO Access');

/**
 * Admin flash messages. Backed by a per-user transient (the old $_SESSION
 * storage never worked because no session was ever started).
 */
class TKM_Flash_Message
{
    const ERROR = 1;
    const SUCCESS = 2;
    const WARNING = 3;
    const INFO = 4;

    public static function add_message($message, $type = self::SUCCESS)
    {
        tkm_flash($message, self::get_type($type));
    }

    public static function show_message()
    {
        tkm_flash_render();
    }

    public static function get_type($type)
    {
        switch ($type) {
            case self::SUCCESS:
                return 'success';
            case self::ERROR:
                return 'danger';
            case self::WARNING:
                return 'warning';
            default:
                return 'info';
        }
    }

    public static function empty_session()
    {
        delete_transient('tkm_flash_' . get_current_user_id());
    }
}
