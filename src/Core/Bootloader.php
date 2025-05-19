<?php

namespace LEXO\Captcha\Core;

use LEXO\Captcha\Core;
use LEXO\Captcha\Core\Loader\Loader;
use LEXO\Captcha\Core\Plugin\PluginService;
use LEXO\Captcha\Core\Updater\Updater;

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
