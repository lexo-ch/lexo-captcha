<?php

namespace LEXO\Captcha;

final class Deactivation
{
    private function __construct()
    {
        //
    }

    public static function run()
    {
        delete_transient(Core::$cache_key);
    }
}
