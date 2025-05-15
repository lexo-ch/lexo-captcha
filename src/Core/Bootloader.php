<?php

namespace LEXO\Captcha\Core;

use LEXO\Captcha\Core;
use LEXO\Captcha\Core\Loader\Loader;
use LEXO\Captcha\Core\Plugin\PluginService;

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
            [Bootloader::class, 'init'],
            10,
        );

        add_action(
            'after_setup_theme',
            [Bootloader::class, 'onAfterSetupTheme'],
        );

        add_action(
            'admin_menu',
            [Bootloader::class, 'onAdminMenu'],
            100,
        );

        add_action(
            'admin_init',
            [Bootloader::class, 'onAdminInit'],
            10,
        );

        add_action(
            'admin_notices',
            [Bootloader::class, 'onAdminNotices'],
        );
    }

    public static function onAdminInit()
    {
        //
    }

    public static function init()
    {
        do_action(Core::$domain . '/init');

        Loader::add_actions();

        PluginService::registerNamespace();
    }

    public static function onAdminMenu()
    {
        PluginService::add_pages();
    }

    public static function onAdminNotices()
    {
        PluginService::noUpdatesNotice();

        PluginService::updateSuccessNotice();
    }

    public static function onAfterSetupTheme()
    {
        Bootloader::loadPluginTextdomain();

        PluginService::updater()->run();
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
