<?php

namespace TicketchiLite;

defined('ABSPATH') || exit;

/**
 * Attachments are a Ticketchi Pro feature. Lite only displays files that
 * already exist on tickets (for example after downgrading from Pro).
 */
final class Uploads
{
    /** Stored «file» column value → list of URLs (JSON list or a single legacy URL). */
    public static function decode($value)
    {
        if (empty($value)) {
            return [];
        }
        $decoded = json_decode((string) $value, true);
        if (is_array($decoded)) {
            return array_values(array_filter($decoded, 'is_string'));
        }
        return [(string) $value];
    }
}
