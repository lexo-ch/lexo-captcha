<?php

namespace LEXO\Captcha\Core;

use LEXO\Captcha\Core\PluginService;
use LEXO\Captcha\Core\Abstracts\Singleton;

use const LEXO\Captcha\{
    DOMAIN,
    PATH,
    LOCALES
};

class Bootloader extends Singleton
{
    protected static $instance = null;

    public static function run(): void
    {
        add_action(
            'init',
            [self::class, 'onInit'],
            10,
        );

        add_action(
            'after_setup_theme',
            [self::class, 'onAfterSetupTheme'],
        );

        add_action(
            'admin_notices',
            [self::class, 'onAdminNotices'],
        );

        add_action(
            'admin_menu',
            [self::class, 'onAdminMenu'],
            200
        );

        add_action(
            DOMAIN . '/localize/front.js',
            [self::class, 'onFrontLexoCaptchaJsLoad']
        );
    }

    public static function onInit(): void
    {
        do_action(DOMAIN . '/init');

        if (session_status() === PHP_SESSION_NONE && !headers_sent()) {
            session_start();
        }

        $plugin_service = PluginService::getInstance();
        $plugin_service->setNamespace(DOMAIN);
        $plugin_service->registerNamespace();
        $plugin_service->addAjaxRoutes();
        $plugin_service->addPluginLinks();
    }

    public static function onAdminNotices(): void
    {
        $plugin_service = PluginService::getInstance();
        $plugin_service->noUpdatesNotice();
        $plugin_service->updateSuccessNotice();
    }

    public static function onAdminMenu(): void
    {
        $plugin_service = PluginService::getInstance();
        $plugin_service->addStatisticsPage();
    }

    public static function onAfterSetupTheme(): void
    {
        self::loadPluginTextdomain();
        PluginService::getInstance()->updater()->run();
    }

    public static function onFrontLexoCaptchaJsLoad(): void
    {
        PluginService::getInstance()->addFrontLocalizedScripts();
    }

    public static function loadPluginTextdomain(): void
    {
        load_plugin_textdomain(DOMAIN, false, trailingslashit(trailingslashit(basename(PATH)) . LOCALES));
    }
}
