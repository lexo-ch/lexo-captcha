<?php

namespace LEXO\Captcha;

use LEXO\Captcha\Core\Plugin\PluginService;

final class Activation
{
    private function __construct()
    {
        //
    }

    public static function run()
    {
        if (get_option(Core::$field_name) === false) {
            add_option(Core::$field_name, PluginService::getInitSettings());
        }
    }
}
