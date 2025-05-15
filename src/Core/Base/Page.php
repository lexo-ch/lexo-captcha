<?php

namespace LEXO\Captcha\Core\Base;

use LEXO\Captcha\Core;
use LEXO\Captcha\Core\Plugin\PluginService;

class Page {
    private function __construct()
    {
        //
    }

    const BASE_SLUG = 'base';

    const BASE_CAPABILITY = Core::BASE_CAPABILITY;

    public static function filter($name) {
        return PluginService::filter(
            trailingslashit(self::BASE_SLUG . '-page') . $name,
        );
    }

    public static function slug() {
        return PluginService::slug(self::BASE_SLUG);
    }

    public static function parent_slug() {
        return apply_filters(
            self::filter('parent-slug'),
            'options-general.php',
        );
    }

    public static function link() {
        $path = self::parent_slug();

        if (strpos($path, '.php') === false) {
            $path = 'admin.php';
        }

        return esc_url(
            add_query_arg(
                'page',
                self::slug(),
                admin_url($path)
            )
        );
    }

    public static function title() {
        return PluginService::__('Page Title');
    }

    public static function capability() {
        return apply_filters(
            self::filter('capability'),
            self::BASE_CAPABILITY,
        );
    }

    public static function add_page() {
        add_submenu_page(
            self::parent_slug(),
            self::title(),
            self::title(),
            self::capability(),
            self::slug(),
            [self::class, 'content'],
        );
    }

    public static function content() {
        //
    }
}

?>
