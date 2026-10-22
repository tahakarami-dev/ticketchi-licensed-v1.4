<?php
/**
 * Template helpers shared by the customer panel and the admin screens.
 */

defined('ABSPATH') || exit;

/**
 * Inline SVG icon (stroke icons on a 24px grid). Echo through wp_kses() with
 * ticketchi_lite_allowed_html().
 */
function ticketchi_lite_icon($name, $size = 18, $class = '')
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
        'search'        => '<circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/>',
        'sliders'       => '<path d="M4 21v-7M4 10V3M12 21v-9M12 8V3M20 21v-5M20 12V3M1 14h6M9 8h6M17 16h6"/>',
        'chevron-left'  => '<path d="m15 18-6-6 6-6"/>',
        'chevron-right' => '<path d="m9 18 6-6-6-6"/>',
        'chevron-down'  => '<path d="m6 9 6 6 6-6"/>',
        'arrow-right'   => '<path d="M5 12h14M12 5l7 7-7 7"/>',
        'arrow-left'    => '<path d="M19 12H5M12 19l-7-7 7-7"/>',
        'paperclip'     => '<path d="m21.44 11.05-9.19 9.19a6 6 0 0 1-8.49-8.49l8.57-8.57A4 4 0 1 1 18 8.84l-8.59 8.57a2 2 0 0 1-2.83-2.83l8.49-8.48"/>',
        'upload'        => '<path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><path d="m17 8-5-5-5 5M12 3v12"/>',
        'mic'           => '<path d="M12 2a3 3 0 0 0-3 3v7a3 3 0 0 0 6 0V5a3 3 0 0 0-3-3Z"/><path d="M19 10v2a7 7 0 0 1-14 0v-2M12 19v3"/>',
        'send'          => '<path d="m22 2-7 20-4-9-9-4Z"/><path d="M22 2 11 13"/>',
        'star'          => '<path d="m12 2 3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/>',
        'file'          => '<path d="M14.5 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7.5L14.5 2z"/><path d="M14 2v6h6"/>',
        'user'          => '<path d="M19 21v-2a4 4 0 0 0-4-4H9a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/>',
        'users'         => '<path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75"/>',
        'building'      => '<rect x="4" y="2" width="16" height="20" rx="2"/><path d="M9 22v-4h6v4M8 6h.01M16 6h.01M12 6h.01M12 10h.01M12 14h.01M16 10h.01M16 14h.01M8 10h.01M8 14h.01"/>',
        'layers'        => '<path d="m12.83 2.18a2 2 0 0 0-1.66 0L2.6 6.08a1 1 0 0 0 0 1.83l8.58 3.91a2 2 0 0 0 1.66 0l8.58-3.9a1 1 0 0 0 0-1.83Z"/><path d="m22 17.65-9.17 4.16a2 2 0 0 1-1.66 0L2 17.65M22 12.65l-9.17 4.16a2 2 0 0 1-1.66 0L2 12.65"/>',
        'settings'      => '<path d="M12.22 2h-.44a2 2 0 0 0-2 2v.18a2 2 0 0 1-1 1.73l-.43.25a2 2 0 0 1-2 0l-.15-.08a2 2 0 0 0-2.73.73l-.22.38a2 2 0 0 0 .73 2.73l.15.1a2 2 0 0 1 1 1.72v.51a2 2 0 0 1-1 1.74l-.15.09a2 2 0 0 0-.73 2.73l.22.38a2 2 0 0 0 2.73.73l.15-.08a2 2 0 0 1 2 0l.43.25a2 2 0 0 1 1 1.73V20a2 2 0 0 0 2 2h.44a2 2 0 0 0 2-2v-.18a2 2 0 0 1 1-1.73l.43-.25a2 2 0 0 1 2 0l.15.08a2 2 0 0 0 2.73-.73l.22-.39a2 2 0 0 0-.73-2.73l-.15-.08a2 2 0 0 1-1-1.74v-.5a2 2 0 0 1 1-1.74l.15-.09a2 2 0 0 0 .73-2.73l-.22-.38a2 2 0 0 0-2.73-.73l-.15.08a2 2 0 0 1-2 0l-.43-.25a2 2 0 0 1-1-1.73V4a2 2 0 0 0-2-2z"/><circle cx="12" cy="12" r="3"/>',
        'bar-chart'     => '<path d="M12 20V10M18 20V4M6 20v-4"/>',
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
        'lock'          => '<rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/>',
        'home'          => '<path d="m3 9 9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><path d="M9 22V12h6v10"/>',
        'external'      => '<path d="M15 3h6v6M10 14 21 3M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/>',
        'hash'          => '<path d="M4 9h16M4 15h16M10 3 8 21M16 3l-2 18"/>',
        'sparkles'      => '<path d="m12 3-1.9 5.8a2 2 0 0 1-1.29 1.29L3 12l5.8 1.9a2 2 0 0 1 1.29 1.29L12 21l1.9-5.8a2 2 0 0 1 1.29-1.29L21 12l-5.8-1.9a2 2 0 0 1-1.29-1.29Z"/>',
        'crown'         => '<path d="m2 4 3 12h14l3-12-6 7-4-7-4 7-6-7zM5 20h14"/>',
        'grid'          => '<rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/>',
        'bell'          => '<path d="M6 8a6 6 0 0 1 12 0c0 7 3 9 3 9H3s3-2 3-9"/><path d="M10.3 21a1.94 1.94 0 0 0 3.4 0"/>',
        'user-check'    => '<path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="m16 11 2 2 4-4"/>',
        'book'          => '<path d="M4 19.5v-15A2.5 2.5 0 0 1 6.5 2H20v20H6.5a2.5 2.5 0 0 1 0-5H20"/>',
        'reply'         => '<path d="m9 17-5-5 5-5"/><path d="M20 18v-2a4 4 0 0 0-4-4H4"/>',
        'shopping-bag'  => '<path d="M6 2 3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4Z"/><path d="M3 6h18"/><path d="M16 10a4 4 0 0 1-8 0"/>',
    ];

    $path = isset($paths[$name]) ? $paths[$name] : $paths['info'];
    $size = (int) $size;

    return '<svg class="tkm-icon ' . esc_attr($class) . '" width="' . $size . '" height="' . $size . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">' . $path . '</svg>';
}

/**
 * wp_kses() allow-list for the markup produced by these helpers (icons,
 * badges, avatars, attachments) on top of the regular post HTML.
 */
function ticketchi_lite_allowed_html()
{
    static $allowed = null;
    if ($allowed !== null) {
        return $allowed;
    }

    $svg = ['class' => true, 'width' => true, 'height' => true, 'viewbox' => true, 'fill' => true, 'stroke' => true, 'stroke-width' => true, 'stroke-linecap' => true, 'stroke-linejoin' => true, 'aria-hidden' => true, 'focusable' => true, 'xmlns' => true];
    $shape = ['d' => true, 'cx' => true, 'cy' => true, 'r' => true, 'x' => true, 'y' => true, 'width' => true, 'height' => true, 'rx' => true, 'fill' => true];
    $common = ['class' => true, 'style' => true, 'title' => true, 'aria-hidden' => true, 'aria-label' => true, 'data-status' => true];

    $allowed = wp_kses_allowed_html('post');
    $allowed['svg'] = $svg;
    $allowed['path'] = $shape;
    $allowed['circle'] = $shape;
    $allowed['rect'] = $shape;
    $allowed['span'] = $common;
    $allowed['i'] = $common;
    $allowed['img'] = ['class' => true, 'src' => true, 'alt' => true, 'title' => true, 'loading' => true, 'decoding' => true, 'width' => true, 'height' => true];
    $allowed['a'] = ['class' => true, 'href' => true, 'target' => true, 'rel' => true, 'download' => true, 'title' => true];
    $allowed['div'] = $common;

    return $allowed;
}

/** Echoes trusted helper markup (icons, badges, avatars) through wp_kses(). */
function ticketchi_lite_kses($html)
{
    return wp_kses($html, ticketchi_lite_allowed_html());
}

/* ───────────────────────── Access ───────────────────────── */

/** Support staff in Lite = site administrators and WooCommerce shop managers. */
function ticketchi_lite_is_staff($user_id = null)
{
    $user_id = $user_id ? (int) $user_id : get_current_user_id();
    return $user_id && (user_can($user_id, 'manage_options') || user_can($user_id, 'manage_woocommerce'));
}

/** Can the user read / reply to this ticket from the customer panel? */
function ticketchi_lite_user_can_access_ticket($ticket, $user_id = null)
{
    if (!$ticket) {
        return false;
    }
    $user_id = $user_id ? (int) $user_id : get_current_user_id();
    if (!$user_id) {
        return false;
    }
    return (int) $ticket->creator_id === $user_id || (int) $ticket->user_id === $user_id || ticketchi_lite_is_staff($user_id);
}

/* ───────────────────────── URLs ───────────────────────── */

/**
 * Front-end URL that respects where the panel is rendered: the shortcode page
 * or the WooCommerce «My account › Tickets» endpoint.
 *
 * @param string   $type list|new|single
 * @param int|null $ticket_id
 */
function ticketchi_lite_url($type = 'list', $ticket_id = null)
{
    $base = \TicketchiLite\Front::base_url();

    if ($type === 'new') {
        return add_query_arg(['action' => 'new'], $base);
    }
    if ($type === 'single') {
        return add_query_arg(['action' => 'single', 'ticket-id' => (int) $ticket_id], $base);
    }
    return $base;
}

function ticketchi_lite_admin_url($page, $args = [])
{
    return add_query_arg(array_merge(['page' => $page], $args), admin_url('admin.php'));
}

/* ───────────────────────── Formatting ───────────────────────── */

function ticketchi_lite_fa($value)
{
    return strtr((string) $value, ['0' => '۰', '1' => '۱', '2' => '۲', '3' => '۳', '4' => '۴', '5' => '۵', '6' => '۶', '7' => '۷', '8' => '۸', '9' => '۹']);
}

/** Persian / Arabic-Indic digits → ASCII (form input). */
function ticketchi_lite_en_digits($value)
{
    return strtr((string) $value, [
        '۰' => '0', '۱' => '1', '۲' => '2', '۳' => '3', '۴' => '4', '۵' => '5', '۶' => '6', '۷' => '7', '۸' => '8', '۹' => '9',
        '٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4', '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9',
    ]);
}

function ticketchi_lite_ticket_number($ticket_id)
{
    return '#' . ticketchi_lite_fa((int) $ticket_id);
}

/** Solar Hijri date in the site timezone. Stored dates are UTC. */
function ticketchi_lite_date($datetime, $with_time = true)
{
    $ts = \TicketchiLite\Clock::ts($datetime);
    return $ts ? ticketchi_lite_fa(\TicketchiLite\Jalali::format($ts, $with_time)) : '—';
}

/** «۳ ساعت پیش» */
function ticketchi_lite_time_ago($datetime)
{
    $ts = \TicketchiLite\Clock::ts($datetime);
    if (!$ts) {
        return '—';
    }

    $diff = max(0, time() - $ts);
    $units = [[31536000, 'سال'], [2592000, 'ماه'], [604800, 'هفته'], [86400, 'روز'], [3600, 'ساعت'], [60, 'دقیقه']];
    foreach ($units as $unit) {
        if ($diff >= $unit[0]) {
            return ticketchi_lite_fa((int) floor($diff / $unit[0])) . ' ' . $unit[1] . ' پیش';
        }
    }
    return 'همین الان';
}

/** Ticket / reply body as safe HTML; plain text gets paragraphs. */
function ticketchi_lite_content($body)
{
    return wpautop(wp_kses_post((string) $body));
}

/* ───────────────────────── Components ───────────────────────── */

function ticketchi_lite_status_badge($status)
{
    $s = \TicketchiLite\Statuses::get($status);
    return '<span class="tkm-badge" data-status="' . esc_attr($s['slug']) . '" style="--c:' . esc_attr($s['color']) . '">' . esc_html($s['name']) . '</span>';
}

function ticketchi_lite_avatar($user_id, $size = '')
{
    return \TicketchiLite\Avatars::html($user_id, $size);
}

function ticketchi_lite_user_name($user_id, $fallback = 'کاربر حذف‌شده')
{
    $user = $user_id ? get_userdata($user_id) : false;
    return $user ? $user->display_name : $fallback;
}

/** Attachment chips for a stored «file» value (JSON list or a single URL). */
function ticketchi_lite_files_html($value)
{
    $files = \TicketchiLite\Uploads::decode($value);
    if (!$files) {
        return '';
    }

    $html = '<div class="tkm-filelist">';
    foreach ($files as $url) {
        $html .= '<a class="tkm-file" href="' . esc_url($url) . '" target="_blank" rel="noopener" download>' . ticketchi_lite_icon('paperclip', 14) . '<span>' . esc_html(wp_basename((string) wp_parse_url($url, PHP_URL_PATH))) . '</span></a>';
    }
    return $html . '</div>';
}

/* ───────────────────────── Admin flash messages ───────────────────────── */

function ticketchi_lite_flash($message, $type = 'success')
{
    $key = 'ticketchi_lite_flash_' . get_current_user_id();
    $messages = get_transient($key);
    $messages = is_array($messages) ? $messages : [];
    $messages[] = ['body' => $message, 'type' => $type];
    set_transient($key, $messages, 120);
}

function ticketchi_lite_flash_render()
{
    $key = 'ticketchi_lite_flash_' . get_current_user_id();
    $messages = get_transient($key);
    if (empty($messages) || !is_array($messages)) {
        return;
    }
    delete_transient($key);

    foreach ($messages as $message) {
        $type = in_array($message['type'], ['success', 'warning', 'danger', 'info'], true) ? $message['type'] : 'info';
        $icon = $type === 'success' ? 'check-circle' : ($type === 'info' ? 'info' : 'alert');
        echo '<div class="tkm-alert tkm-alert--' . esc_attr($type) . ' tkm-flash">' . wp_kses(ticketchi_lite_icon($icon), ticketchi_lite_allowed_html()) . '<div>' . wp_kses_post($message['body']) . '</div></div>';
    }
}
