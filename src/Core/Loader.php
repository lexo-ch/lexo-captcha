<?php

namespace LEXO\Captcha\Core;

use LEXO\Captcha\Core;
use LEXO\Captcha\Core\Services\CoreService;

final class Loader {
    private function __construct() {
        //
    }

    public static function setup() {
        add_action(
            'admin_enqueue_scripts',
            [self::class, 'load_admin_resources'],
        );

        add_action(
            'wp_enqueue_scripts',
            [self::class, 'load_front_resources'],
        );
    }

    public static function filter(string $name) {
        return CoreService::filter(
            "loader/{$name}",
        );
    }

    private static function resource(string $path) {
        return Core::$path . "dist/{$path}";
    }

    private static function resource_url(string $path) {
        return Core::$url . "dist/{$path}";
    }

    public static function load_admin_resources() {
        if (file_exists(self::resource('admin.js'))) {
            wp_enqueue_script(
                Core::$domain . "-admin-script",
                self::resource_url('admin.js'),
                [],
                md5_file(self::resource('admin.js')),
                [
                    'strategy'  => 'defer',
                ],
            );
        }

        if (file_exists(self::resource('admin.css'))) {
            wp_enqueue_style(
                Core::$domain . "-admin-styles",
                self::resource_url('admin.css'),
                [],
                md5_file(self::resource('admin.css')),
            );
        }
    }

    public static function load_front_resources() {
        if (file_exists(self::resource('front.js'))) {
            wp_enqueue_script(
                Core::$domain . "-front-script",
                self::resource_url('front.js'),
                [],
                md5_file(self::resource('front.js')),
                [
                    'strategy'  => 'defer',
                ],
            );

            wp_localize_script(
                Core::$domain . "-front-script",
                Core::$domain . "_globals",
                apply_filters(
                    self::filter('front-script-globals'),
                    [
                        'ajax_url' => admin_url('admin-ajax.php'),
                    ],
                ),
            );
        }

        if (file_exists(self::resource('front.css'))) {
            wp_enqueue_style(
                Core::$domain . "-front-styles",
                self::resource_url('front.css'),
                [],
                md5_file(self::resource('front.css')),
            );
        }
    }
}
