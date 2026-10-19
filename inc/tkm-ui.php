<?php

/**
 * Ticketchi UI helpers shared by the front panel and the admin panel.
 */

defined('ABSPATH') || exit('NO Access');

/**
 * Inline SVG icon (stroke icons, 24px grid).
 */
function tkm_icon($name, $size = 18, $class = '')
{
    static $paths = [
        'plus'          => '<path d="M12 5v14M5 12h14"/>',
        'inbox'         => '<path d="M22 12h-6l-2 3h-4l-2-3H2"/><path d="M5.45 5.11 2 12v6a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2v-6l-3.45-6.89A2 2 0 0 0 16.76 4H7.24a2 2 0 0 0-1.79 1.11z"/>',
        'ticket'        => '<path d="M2 9a3 3 0 0 1 0 6v2a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2v-2a3 3 0 0 1 0-6V7a2 2 0 0 0-2-2H4a2 2 0 0 0-2 2Z"/><path d="M13 5v2M13 17v2M13 11v2"/>',
        'message'       => '<path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/>',
        'messages'      => '<path d="M14 9a2 2 0 0 1-2 2H6l-4 4V4a2 2 0 0 1 2-2h8a2 2 0 0 1 2 2z"/><path d="M18 9h2a2 2 0 0 1 2 2v11l-4-4h-6a2 2 0 0 1-2-2v-1"/>',
        'check'         => '<path d="M20 6 9 17l-5-5"/>',
        'check-circle'  => '<circle cx="12" cy="12" r="10"/><path d="m9 12 2 2 4-4"/>',
        'clock'         => '<circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/>',
        'x'             => '<path d="M18 6 6 18M6 6l12 12"/>',
        'x-circle'      => '<circle cx="12" cy="12" r="10"/><path d="m15 9-6 6M9 9l6 6"/>',
        'search'        => '<circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/>',
        'filter'        => '<path d="M22 3H2l8 9.46V19l4 2v-8.54L22 3z"/>',
        'sliders'       => '<path d="M4 21v-7M4 10V3M12 21v-9M12 8V3M20 21v-5M20 12V3M1 14h6M9 8h6M17 16h6"/>',
        'chevron-left'  => '<path d="m15 18-6-6 6-6"/>',
        'chevron-right' => '<path d="m9 18 6-6-6-6"/>',
        'chevron-down'  => '<path d="m6 9 6 6 6-6"/>',
        'arrow-right'   => '<path d="M5 12h14M12 5l7 7-7 7"/>',
        'arrow-left'    => '<path d="M19 12H5M12 19l-7-7 7-7"/>',
        'arrow-up-left' => '<path d="M17 17 7 7M7 17V7h10"/>',
        'paperclip'     => '<path d="m21.44 11.05-9.19 9.19a6 6 0 0 1-8.49-8.49l8.57-8.57A4 4 0 1 1 18 8.84l-8.59 8.57a2 2 0 0 1-2.83-2.83l8.49-8.48"/>',
        'upload'        => '<path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><path d="m17 8-5-5-5 5M12 3v12"/>',
        'mic'           => '<path d="M12 2a3 3 0 0 0-3 3v7a3 3 0 0 0 6 0V5a3 3 0 0 0-3-3Z"/><path d="M19 10v2a7 7 0 0 1-14 0v-2M12 19v3"/>',
        'square'        => '<rect x="6" y="6" width="12" height="12" rx="2"/>',
        'send'          => '<path d="m22 2-7 20-4-9-9-4Z"/><path d="M22 2 11 13"/>',
        'star'          => '<path d="m12 2 3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/>',
        'eye'           => '<path d="M2 12s3-7 10-7 10 7 10 7-3 7-10 7-10-7-10-7Z"/><circle cx="12" cy="12" r="3"/>',
        'file'          => '<path d="M14.5 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7.5L14.5 2z"/><path d="M14 2v6h6"/>',
        'download'      => '<path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><path d="m7 10 5 5 5-5M12 15V3"/>',
        'user'          => '<path d="M19 21v-2a4 4 0 0 0-4-4H9a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/>',
        'users'         => '<path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75"/>',
        'building'      => '<rect x="4" y="2" width="16" height="20" rx="2"/><path d="M9 22v-4h6v4M8 6h.01M16 6h.01M12 6h.01M12 10h.01M12 14h.01M16 10h.01M16 14h.01M8 10h.01M8 14h.01"/>',
        'layers'        => '<path d="m12.83 2.18a2 2 0 0 0-1.66 0L2.6 6.08a1 1 0 0 0 0 1.83l8.58 3.91a2 2 0 0 0 1.66 0l8.58-3.9a1 1 0 0 0 0-1.83Z"/><path d="m22 17.65-9.17 4.16a2 2 0 0 1-1.66 0L2 17.65M22 12.65l-9.17 4.16a2 2 0 0 1-1.66 0L2 12.65"/>',
        'settings'      => '<path d="M12.22 2h-.44a2 2 0 0 0-2 2v.18a2 2 0 0 1-1 1.73l-.43.25a2 2 0 0 1-2 0l-.15-.08a2 2 0 0 0-2.73.73l-.22.38a2 2 0 0 0 .73 2.73l.15.1a2 2 0 0 1 1 1.72v.51a2 2 0 0 1-1 1.74l-.15.09a2 2 0 0 0-.73 2.73l.22.38a2 2 0 0 0 2.73.73l.15-.08a2 2 0 0 1 2 0l.43.25a2 2 0 0 1 1 1.73V20a2 2 0 0 0 2 2h.44a2 2 0 0 0 2-2v-.18a2 2 0 0 1 1-1.73l.43-.25a2 2 0 0 1 2 0l.15.08a2 2 0 0 0 2.73-.73l.22-.39a2 2 0 0 0-.73-2.73l-.15-.08a2 2 0 0 1-1-1.74v-.5a2 2 0 0 1 1-1.74l.15-.09a2 2 0 0 0 .73-2.73l-.22-.38a2 2 0 0 0-2.73-.73l-.15.08a2 2 0 0 1-2 0l-.43-.25a2 2 0 0 1-1-1.73V4a2 2 0 0 0-2-2z"/><circle cx="12" cy="12" r="3"/>',
        'chart'         => '<path d="M3 3v18h18"/><path d="m19 9-5 5-4-4-3 3"/>',
        'bar-chart'     => '<path d="M12 20V10M18 20V4M6 20v-4"/>',
        'pie'           => '<path d="M21.21 15.89A10 10 0 1 1 8 2.83"/><path d="M22 12A10 10 0 0 0 12 2v10z"/>',
        'zap'           => '<path d="M13 2 3 14h9l-1 8 10-12h-9l1-8z"/>',
        'alert'         => '<path d="m21.73 18-8-14a2 2 0 0 0-3.48 0l-8 14A2 2 0 0 0 4 21h16a2 2 0 0 0 1.73-3Z"/><path d="M12 9v4M12 17h.01"/>',
        'info'          => '<circle cx="12" cy="12" r="10"/><path d="M12 16v-4M12 8h.01"/>',
        'help'          => '<circle cx="12" cy="12" r="10"/><path d="M9.09 9a3 3 0 0 1 5.83 1c0 2-3 3-3 3M12 17h.01"/>',
        'trash'         => '<path d="M3 6h18M19 6v14c0 1-1 2-2 2H7c-1 0-2-1-2-2V6M8 6V4c0-1 1-2 2-2h4c1 0 2 1 2 2v2"/>',
        'edit'          => '<path d="M12 20h9"/><path d="M16.5 3.5a2.12 2.12 0 0 1 3 3L7 19l-4 1 1-4Z"/>',
        'refresh'       => '<path d="M3 12a9 9 0 0 1 9-9 9.75 9.75 0 0 1 6.74 2.74L21 8"/><path d="M21 3v5h-5M21 12a9 9 0 0 1-9 9 9.75 9.75 0 0 1-6.74-2.74L3 16"/><path d="M8 16H3v5"/>',
        'moon'          => '<path d="M12 3a6 6 0 0 0 9 9 9 9 0 1 1-9-9Z"/>',
        'sun'           => '<circle cx="12" cy="12" r="4"/><path d="M12 2v2M12 20v2M4.93 4.93l1.41 1.41M17.66 17.66l1.41 1.41M2 12h2M20 12h2M6.34 17.66l-1.41 1.41M19.07 4.93l-1.41 1.41"/>',
        'calendar'      => '<rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/>',
        'flag'          => '<path d="M4 15s1-1 4-1 5 2 8 2 4-1 4-1V3s-1 1-4 1-5-2-8-2-4 1-4 1zM4 22v-7"/>',
        'tag'           => '<path d="M12.59 2.59A2 2 0 0 0 11.17 2H4a2 2 0 0 0-2 2v7.17a2 2 0 0 0 .59 1.42l8.7 8.7a2.43 2.43 0 0 0 3.42 0l6.58-6.58a2.43 2.43 0 0 0 0-3.42z"/><circle cx="7.5" cy="7.5" r=".5" fill="currentColor"/>',
        'box'           => '<path d="M21 8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16Z"/><path d="m3.3 7 8.7 5 8.7-5M12 22V12"/>',
        'lock'          => '<rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/>',
        'home'          => '<path d="m3 9 9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><path d="M9 22V12h6v10"/>',
        'external'      => '<path d="M15 3h6v6M10 14 21 3M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/>',
        'note'          => '<path d="M15.5 3H5a2 2 0 0 0-2 2v14c0 1.1.9 2 2 2h14a2 2 0 0 0 2-2V8.5L15.5 3Z"/><path d="M15 3v6h6"/>',
        'hash'          => '<path d="M4 9h16M4 15h16M10 3 8 21M16 3l-2 18"/>',
        'bot'           => '<path d="M12 8V4H8"/><rect x="4" y="8" width="16" height="12" rx="2"/><path d="M2 14h2M20 14h2M15 13v2M9 13v2"/>',
        'sparkles'      => '<path d="m12 3-1.9 5.8a2 2 0 0 1-1.29 1.29L3 12l5.8 1.9a2 2 0 0 1 1.29 1.29L12 21l1.9-5.8a2 2 0 0 1 1.29-1.29L21 12l-5.8-1.9a2 2 0 0 1-1.29-1.29Z"/>',
        'more'          => '<circle cx="12" cy="12" r="1"/><circle cx="19" cy="12" r="1"/><circle cx="5" cy="12" r="1"/>',
        'trending-up'   => '<path d="m22 7-8.5 8.5-5-5L2 17"/><path d="M16 7h6v6"/>',
        'grid'          => '<rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/>',
        'list'          => '<path d="M8 6h13M8 12h13M8 18h13M3 6h.01M3 12h.01M3 18h.01"/>',
        'reply'         => '<path d="m9 17-5-5 5-5"/><path d="M20 18v-2a4 4 0 0 0-4-4H4"/>',
    ];

    $path = isset($paths[$name]) ? $paths[$name] : $paths['info'];
    $size = (int) $size;

    return '<svg class="tkm-icon ' . esc_attr($class) . '" width="' . $size . '" height="' . $size . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">' . $path . '</svg>';
}

/* ───────────────────────── URLs ───────────────────────── */

/**
 * Builds a front-end URL that respects where the panel is rendered:
 * the [tickets] shortcode page or the WooCommerce "my account" endpoint.
 *
 * @param string   $type list|new|single
 * @param int|null $ticket_id
 */
function tkm_url($type = 'list', $ticket_id = null)
{
    $use_shortcode = !empty($GLOBALS['tkm_render_context']) && $GLOBALS['tkm_render_context'] === 'shortcode';

    if (!$use_shortcode && !function_exists('wc_get_endpoint_url')) {
        $use_shortcode = true;
    }

    if ($use_shortcode) {
        switch ($type) {
            case 'new':
                return TKM_Shortcode_Url::new();
            case 'single':
                return TKM_Shortcode_Url::single($ticket_id);
            default:
                return TKM_Shortcode_Url::base();
        }
    }

    switch ($type) {
        case 'new':
            return TKM_Ticket_Url::new();
        case 'single':
            return TKM_Ticket_Url::single($ticket_id);
        default:
            return TKM_Ticket_Url::all();
    }
}

function tkm_admin_url($page, $args = [])
{
    return add_query_arg(array_merge(['page' => $page], $args), admin_url('admin.php'));
}

/* ───────────────────────── Access ───────────────────────── */

/**
 * Staff = site managers or anyone assigned to a department.
 */
function tkm_is_staff($user_id = null)
{
    $user_id = $user_id ? (int) $user_id : get_current_user_id();
    return $user_id ? user_can($user_id, 'tkm_view') : false;
}

/**
 * Can the user read / reply to this ticket from the customer panel?
 */
function tkm_user_can_access_ticket($ticket, $user_id = null)
{
    if (!$ticket) {
        return false;
    }

    $user_id = $user_id ? (int) $user_id : get_current_user_id();
    if (!$user_id) {
        return false;
    }

    if ((int) $ticket->creator_id === $user_id || (int) $ticket->user_id === $user_id) {
        return true;
    }

    return tkm_is_staff($user_id);
}

/* ───────────────────────── Formatting ───────────────────────── */

function tkm_fa($value)
{
    return convert_to_persian_numbers((string) $value);
}

function tkm_ticket_number($ticket_id)
{
    return '#' . tkm_fa((int) $ticket_id);
}

function tkm_date($datetime, $with_time = true)
{
    $ts = is_numeric($datetime) ? (int) $datetime : strtotime((string) $datetime);
    if (!$ts) {
        return '—';
    }

    // Solar Hijri dates via the bundled morilog/jalali, in the site's timezone.
    if (class_exists('\\Morilog\\Jalali\\Jalalian')) {
        $date = \Morilog\Jalali\Jalalian::forge($ts, wp_timezone());
        return tkm_fa($date->format($with_time ? 'j F Y — H:i' : 'j F Y'));
    }

    $format = get_option('date_format') . ($with_time ? ' — ' . get_option('time_format') : '');
    return tkm_fa(wp_date($format, $ts));
}

/**
 * Persian relative time ("۳ ساعت پیش"). Dates are written with PHP date(),
 * which WordPress pins to UTC, so compare against time().
 */
function tkm_time_ago($datetime)
{
    $ts = strtotime((string) $datetime);
    if (!$ts) {
        return '—';
    }

    $diff = max(0, time() - $ts);

    if ($diff < 60) {
        return 'همین الان';
    }

    $units = [
        [31536000, 'سال'],
        [2592000, 'ماه'],
        [604800, 'هفته'],
        [86400, 'روز'],
        [3600, 'ساعت'],
        [60, 'دقیقه'],
    ];

    foreach ($units as $unit) {
        if ($diff >= $unit[0]) {
            return tkm_fa((int) floor($diff / $unit[0])) . ' ' . $unit[1] . ' پیش';
        }
    }

    return 'همین الان';
}

/**
 * Renders rich ticket/reply content safely. Plain-text bodies get paragraphs.
 */
function tkm_content($body)
{
    return wpautop(wp_kses_post((string) $body));
}

/* ───────────────────────── Components ───────────────────────── */

function tkm_status_badge($status)
{
    $name = get_status_name($status);
    $name = $name ? trim($name) : (string) $status;

    return '<span class="tkm-badge" data-status="' . esc_attr($status) . '" style="--c:' . esc_attr(get_status_color($status) ?: '#8a94a6') . '">' . esc_html($name) . '</span>';
}

function tkm_priority_badge($priority)
{
    $priority = in_array($priority, ['low', 'medium', 'high'], true) ? $priority : 'medium';

    return '<span class="tkm-priority tkm-priority--' . esc_attr($priority) . '"><span class="tkm-priority__bars"><i></i><i></i><i></i></span>' . esc_html(get_priority_name($priority)) . '</span>';
}

/**
 * Initials avatar — no external (gravatar) requests, consistent color per user.
 */
function tkm_avatar($user_id, $size = '', $extra_class = '')
{
    $user = $user_id ? get_userdata($user_id) : false;
    $name = $user ? $user->display_name : 'کاربر';
    $initial = function_exists('mb_substr') ? mb_substr(trim($name), 0, 1) : substr(trim($name), 0, 1);
    $hue = $user_id ? (crc32((string) $user_id) % 360) : 220;
    $class = 'tkm-avatar' . ($size ? ' tkm-avatar--' . $size : '') . ($extra_class ? ' ' . $extra_class : '');

    return '<span class="' . esc_attr($class) . '" style="--h:' . (int) $hue . '" title="' . esc_attr($name) . '" aria-hidden="true">' . esc_html($initial) . '</span>';
}

function tkm_user_name($user_id, $fallback = 'کاربر حذف‌شده')
{
    $user = $user_id ? get_userdata($user_id) : false;
    return $user ? $user->display_name : $fallback;
}

/**
 * Attachment chips for a stored file value (JSON list or legacy single URL).
 */
function tkm_files_html($value)
{
    $files = tkm_get_files($value);
    if (empty($files)) {
        return '';
    }

    $html = '<div class="tkm-filelist">';
    foreach ($files as $url) {
        $html .= '<a class="tkm-file" href="' . esc_url($url) . '" target="_blank" rel="noopener" download>' . tkm_icon('paperclip', 14) . '<span>' . esc_html(tkm_get_file_name($url)) . '</span></a>';
    }
    return $html . '</div>';
}

function tkm_voice_html($url)
{
    if (empty($url)) {
        return '';
    }
    return '<audio class="tkm-audio" controls preload="none" src="' . esc_url($url) . '"></audio>';
}

/* ───────────────────────── Flash messages (admin) ───────────────────────── */

function tkm_flash($message, $type = 'success')
{
    $key = 'tkm_flash_' . get_current_user_id();
    $messages = get_transient($key);
    $messages = is_array($messages) ? $messages : [];
    $messages[] = ['body' => $message, 'type' => $type];
    set_transient($key, $messages, 120);
}

function tkm_flash_render()
{
    $key = 'tkm_flash_' . get_current_user_id();
    $messages = get_transient($key);
    if (empty($messages) || !is_array($messages)) {
        return;
    }
    delete_transient($key);

    foreach ($messages as $message) {
        $type = in_array($message['type'], ['success', 'warning', 'danger', 'info'], true) ? $message['type'] : 'info';
        $icon = $type === 'success' ? 'check-circle' : ($type === 'info' ? 'info' : 'alert');
        echo '<div class="tkm-alert tkm-alert--' . esc_attr($type) . ' tkm-flash">' . tkm_icon($icon) . '<div>' . wp_kses_post($message['body']) . '</div></div>';
    }
}
