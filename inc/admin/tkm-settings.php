<?php
/**
 * Settings are defined in src/Admin/SettingsSchema.php and edited in the
 * settings app (views/admin/settings/app.php). Values live in the same
 * `tkm_settings` option as before.
 */
defined('ABSPATH') || exit('NO Access');

/** User meta keys offered for the «mobile number» field (public keys only). */
function tkm_user_meta_key()
{
    global $wpdb;
    $keys = $wpdb->get_col("SELECT DISTINCT meta_key FROM {$wpdb->usermeta} WHERE meta_key NOT LIKE '\\_%' AND meta_key NOT LIKE '%capabilities' AND meta_key NOT LIKE '%user_level' AND meta_key NOT LIKE 'session_tokens' ORDER BY meta_key LIMIT 300");
    return array_combine($keys, $keys) ?: [];
}
