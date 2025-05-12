<?php

namespace LEXO\Captcha;

final class Deactivation
{
    public static function run()
    {
        delete_transient(Core::$cache_key);
    }
}
