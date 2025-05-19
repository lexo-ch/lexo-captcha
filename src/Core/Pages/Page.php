<?php

namespace LEXO\Captcha\Core\Pages;

use LEXO\Captcha\Core;
use LEXO\Captcha\Core\Services\CoreService;

class Page {
    private function __construct()
    {
        //
    }

    const BASE_SLUG = 'base';

    const BASE_CAPABILITY = Core::BASE_CAPABILITY;

    public static function filter($name) {
        return CoreService::filter(
            trailingslashit(self::BASE_SLUG . '-page') . $name,
        );
    }

    public static function slug() {
        return CoreService::slug(self::BASE_SLUG);
    }

    public static function parent_slug() {
        return apply_filters(
            self::filter('parent-slug'),
            'options-general.php',
        );
    }

    public static function url() {
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
        return CoreService::__('Page Title');
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
