<?php

namespace LEXO\Captcha;

use const LEXO\Captcha\{
    CACHE_KEY
};

class Deactivation
{
    public static function run(): void
    {
        delete_transient(CACHE_KEY);
    }
}
