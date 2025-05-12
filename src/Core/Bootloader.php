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
            [Bootloader::class, 'onInit'],
            10,
        );

        add_action(
            Core::$domain . '/localize/admin-lexocaptcha.js',
            [Bootloader::class, 'onAdminWebpcJsLoad'],
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

        Loader::run();
    }

    public static function onAdminInit()
    {
        PluginService::updateMissingSettings();

        PluginService::handleSaveSettings();
    }

    public static function onInit()
    {
        do_action(Core::$domain . '/init');

        PluginService::registerNamespace();

        PluginService::addSettingsLink();
    }

    public static function onAdminMenu()
    {
        PluginService::addSettingsPage();
    }

    public static function onAdminWebpcJsLoad()
    {
        PluginService::addAdminLocalizedScripts();
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
