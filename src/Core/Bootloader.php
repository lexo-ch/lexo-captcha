<?php

namespace LEXO\Captcha\Core;

use LEXO\Captcha\Core;
use LEXO\Captcha\Core\Loader;
use LEXO\Captcha\Core\Services\CaptchaService;
use LEXO\Captcha\Core\Updater;
use LEXO\Captcha\Core\Services\CoreService;

final class Bootloader {
    private function __construct() {
        //
    }

    public static function run(): void {
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
            'admin_notices',
            [self::class, 'admin_notices'],
        );
    }

    public static function init(): void {
        do_action(CoreService::action('init'));

        Loader::setup();

        CaptchaService::add_ajax_routes();

        CaptchaService::pass_submit_cooldown_to_frontend();
    }

    public static function admin_menu(): void {
        CoreService::add_pages();
    }

    public static function admin_notices(): void {
        Updater::no_updates_notice();

        Updater::update_success_notice();
    }

    public static function after_setup_theme(): void {
        load_plugin_textdomain(
            Core::$domain,
            false,
            trailingslashit(trailingslashit(basename(Core::$path)) . Core::$locales),
        );

        Updater::setup();
    }
}
