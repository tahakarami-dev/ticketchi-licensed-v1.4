<?php
/**
 * PSR-4 style autoloader for the TicketchiLite\ namespace (includes/).
 */

defined('ABSPATH') || exit;

spl_autoload_register(function ($class) {
    $prefix = 'TicketchiLite\\';
    if (strncmp($class, $prefix, strlen($prefix)) !== 0) {
        return;
    }

    $file = __DIR__ . '/' . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
    if (is_readable($file)) {
        require $file;
    }
});
