<?php

namespace LEXO\Captcha\Core\Pages;

use LEXO\Captcha\Core;
use LEXO\Captcha\Core\Services\CoreService;

class Page {
    private function __construct()
    {
        //
    }

    public static function base_slug() {
        return 'base';
    }

    public static function base_capability() {
        return Core::BASE_CAPABILITY;
    }

    public static function filter($name) {
        return CoreService::filter(
            trailingslashit(static::base_slug() . '-page') . $name,
        );
    }

    public static function slug() {
        return CoreService::slug(static::base_slug());
    }

    public static function parent_slug() {
        return apply_filters(
            static::filter('parent-slug'),
            'options-general.php',
        );
    }

    public static function url() {
        $path = static::parent_slug();

        if (strpos($path, '.php') === false) {
            $path = 'admin.php';
        }

        return esc_url(
            add_query_arg(
                'page',
                static::slug(),
                admin_url($path)
            )
        );
    }

    public static function title() {
        return CoreService::__('Page Title');
    }

    public static function capability() {
        return apply_filters(
            static::filter('capability'),
            static::base_capability(),
        );
    }

    public static function add_page() {
        add_submenu_page(
            static::parent_slug(),
            static::title(),
            static::title(),
            static::capability(),
            static::slug(),
            [static::class, 'content'],
        );
    }

    public static function content() {
        //
    }
}

?>
