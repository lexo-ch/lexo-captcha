<?php

namespace LEXO\Captcha\Core;

use LEXO\Captcha\Core;
use LEXO\Captcha\Core\Loader;
use LEXO\Captcha\Core\Updater;
use LEXO\Captcha\Core\Services\CoreService;

final class Bootloader
{
    private function __construct()
    {
        //
    }

    public static function run()
    {
        add_action(
            'init',
            [self::class, 'init'],
            10,
        );

        add_action(
            'after_setup_theme',
            [self::class, 'after_setup_theme'],
        );

        add_action(
            'admin_menu',
            [self::class, 'admin_menu'],
            100,
        );

        add_action(
            'admin_init',
            [self::class, 'admin_init'],
            10,
        );

        add_action(
            'admin_notices',
            [self::class, 'admin_notices'],
        );
    }

    public static function admin_init()
    {
        //
    }

    public static function init()
    {
        do_action(CoreService::action('init'));

        Loader::setup();
    }

    public static function admin_menu()
    {
        CoreService::add_pages();
    }

    public static function admin_notices()
    {
        Updater::no_updates_notice();

        Updater::update_success_notice();
    }

    public static function after_setup_theme()
    {
        load_plugin_textdomain(
            Core::$domain,
            false,
            trailingslashit(trailingslashit(basename(Core::$path)) . Core::$locales),
        );

        Updater::setup();
    }
}
