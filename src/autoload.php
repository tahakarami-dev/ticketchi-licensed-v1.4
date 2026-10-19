<?php

/**
 * PSR-4 autoloader for the Ticketchi\ namespace (src/).
 *
 * Kept separate from vendor/composer so the shipped classmap (which contains
 * the ionCube-encoded classes) never has to be regenerated.
 */

defined('ABSPATH') || exit('NO Access');

spl_autoload_register(function ($class) {
    $prefix = 'Ticketchi\\';
    if (strncmp($class, $prefix, strlen($prefix)) !== 0) {
        return;
    }

    $file = __DIR__ . '/' . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
    if (is_readable($file)) {
        require $file;
    }
});
