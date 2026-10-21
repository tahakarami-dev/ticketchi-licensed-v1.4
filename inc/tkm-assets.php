<?php

defined('ABSPATH') || exit('NO Access');

class TKM_ASSETS
{
    const SHARED = 'assets/shared/';

    public function __construct()
    {
        add_action('wp_enqueue_scripts', [__CLASS__, 'register_front'], 5);
        add_action('wp_enqueue_scripts', [$this, 'front_assets'], 999);
        add_action('admin_enqueue_scripts', [$this, 'admin_assets']);
        add_filter('admin_body_class', [$this, 'admin_body_class']);
    }

    public function admin_body_class($classes)
    {
        return self::is_plugin_admin_page() ? $classes . ' tkm-admin-page ' : $classes;
    }

    public static function is_plugin_admin_page()
    {
        return is_admin() && isset($_GET['page']) && strpos(sanitize_key(wp_unslash($_GET['page'])), 'tkm-') === 0;
    }

    /**
     * Brand tokens coming from the "استایل" settings tab.
     */
    public static function brand_css()
    {
        $vars = [];
        $primary = self::color(tkm_settings('primary_color'));
        $hover = self::color(tkm_settings('secondary_color'));
        $on_primary = self::color(tkm_settings('text_color'));

        if ($primary) {
            $vars[] = '--tkm-primary:' . $primary;
        }
        // The old default secondary color was a translucent tint; only use opaque values for hover.
        if ($hover && strlen($hover) === 7 && strtolower($hover) !== strtolower((string) $primary)) {
            $vars[] = '--tkm-primary-hover:' . $hover;
        }
        if ($on_primary) {
            $vars[] = '--tkm-on-primary:' . $on_primary;
        }

        return $vars ? '.tkm-ui{' . implode(';', $vars) . '}' : '';
    }

    private static function color($value)
    {
        $value = is_string($value) ? trim($value) : '';
        return preg_match('/^#(?:[0-9a-fA-F]{3}|[0-9a-fA-F]{6}|[0-9a-fA-F]{8})$/', $value) ? $value : null;
    }

    /**
     * Idempotent: block themes render shortcodes / endpoints before
     * wp_enqueue_scripts fires, so views may need the handles earlier.
     */
    public static function register_front()
    {
        if (wp_style_is('tkm-front', 'registered')) {
            return;
        }

        wp_register_style('tkm-ui', TKM_URL . self::SHARED . 'css/tkm-ui.css', [], TKM_VER);
        wp_register_style('tkm-front', TKM_FRONT_ASSETS . 'css/tkm-front.css', ['tkm-ui'], TKM_VER);
        wp_register_script('tkm-front', TKM_FRONT_ASSETS . 'js/tkm-front.js', ['jquery'], TKM_VER, true);
        wp_add_inline_style('tkm-front', self::brand_css());

        wp_localize_script('tkm-front', 'TKM_DATA_AJAX', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce'    => wp_create_nonce('tkm_ajax_nonce'),
            'kb'       => \Ticketchi\Kb\KnowledgeBase::enabled() && \Ticketchi\Support\Settings::bool('kb_suggest', true) ? esc_url_raw(rest_url('ticketchi/v1/kb/search')) : '',
            'upload'   => [
                'multiple'    => TKM_Upload_File::is_multiple(),
                'max_files'   => TKM_Upload_File::max_files(),
                'max_size'    => TKM_Upload_File::max_size_bytes(),
                'allowed_ext' => TKM_Upload_File::allowed_extensions(),
            ],
        ]);
    }

    /**
     * Only load on pages that actually render the panel. Views also call
     * TKM_ASSETS::enqueue_front() as a fallback (page builders, widgets).
     */
    public function front_assets()
    {
        if ($this->page_has_panel()) {
            self::enqueue_front();
        }
    }

    private function page_has_panel()
    {
        if (function_exists('is_wc_endpoint_url') && is_wc_endpoint_url('tickets')) {
            return true;
        }

        $post = get_post();
        return $post && (has_shortcode((string) $post->post_content, 'tickets') || has_shortcode((string) $post->post_content, 'ticketchi'));
    }

    public static function enqueue_front()
    {
        self::register_front();
        wp_enqueue_style('tkm-front');
        wp_enqueue_script('tkm-front');
    }

    public function admin_assets()
    {
        if (!self::is_plugin_admin_page()) {
            return;
        }

        wp_enqueue_style('tkm-ui', TKM_URL . self::SHARED . 'css/tkm-ui.css', [], TKM_VER);
        wp_enqueue_style('tkm-select2', TKM_ADMIN_ASSETS . 'css/select2.min.css', [], '4.1.0');
        wp_enqueue_style('tkm-admin', TKM_ADMIN_ASSETS . 'css/tkm-admin.css', ['tkm-ui', 'tkm-select2'], TKM_VER);
        wp_add_inline_style('tkm-admin', self::brand_css());

        wp_enqueue_script('tkm-select2', TKM_ADMIN_ASSETS . 'js/select2.min.js', ['jquery'], '4.1.0', true);
        wp_enqueue_script('tkm-main', TKM_ADMIN_ASSETS . 'js/tkm-admin.js', ['jquery', 'tkm-select2'], TKM_VER, true);
        wp_localize_script('tkm-main', 'TKM_DATA', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce'    => wp_create_nonce('tkm_admin_nonce'),
        ]);
    }
}
