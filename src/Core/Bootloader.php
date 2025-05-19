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
            [self::class, 'onAfterSetupTheme'],
        );

        add_action(
            'admin_menu',
            [self::class, 'onAdminMenu'],
            100,
        );

        add_action(
            'admin_init',
            [self::class, 'onAdminInit'],
            10,
        );

        add_action(
            'admin_notices',
            [self::class, 'onAdminNotices'],
        );
    }

    public static function onAdminInit()
    {
        //
    }

    public static function init()
    {
        do_action(Core::$domain . '/init');

        Loader::setup();

        CoreService::registerNamespace();
    }

    public static function onAdminMenu()
    {
        CoreService::add_pages();
    }

    public static function onAdminNotices()
    {
        CoreService::noUpdatesNotice();

        CoreService::updateSuccessNotice();
    }

    public static function onAfterSetupTheme()
    {
        self::loadPluginTextdomain();

        Updater::setup();
    }

    public static function loadPluginTextdomain()
    {
        load_plugin_textdomain(
            Core::$domain,
            false,
            trailingslashit(trailingslashit(basename(Core::$path)) . Core::$locales),
        );
    }
}
