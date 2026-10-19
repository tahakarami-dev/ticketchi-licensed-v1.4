<?php
/**
 * Test-only stand-in for the ionCube-encoded BASE_MENU class.
 *
 * The shipped plugin keeps the original encoded file. This stub only exists so
 * the plugin can boot inside the local test environment, where the ionCube
 * loader is not available. It mirrors the public contract TKM_MENU relies on.
 */

defined('ABSPATH') || exit('NO Access');

abstract class BASE_MENU
{
    public $page_title;
    public $menu_title;
    public $menu_slug;
    public $icon;
    public $capability = 'manage_options';
    public $has_sub_menu = false;
    public $sub_item = [];

    public function __construct()
    {
        add_action('admin_menu', [$this, 'register']);
    }

    public function register()
    {
        $parent = trim($this->menu_slug);
        add_menu_page($this->page_title, $this->menu_title, $this->capability, $parent, [$this, 'page'], $this->icon);

        if (!$this->has_sub_menu) {
            return;
        }

        foreach ($this->sub_item as $item) {
            $callback = !empty($item['callback']) ? [$this, $item['callback']] : '';
            $hook = add_submenu_page($parent, $item['page_title'], $item['menu_title'], $this->capability, $item['menu_slug'], $callback);

            if (!empty($item['load']['status']) && !empty($item['load']['callback_option'])) {
                add_action('load-' . $hook, [$this, $item['load']['callback_option']]);
            }
        }
    }

    abstract public function page();
}
