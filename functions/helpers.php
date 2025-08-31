<?php

use LEXO\Captcha\Core\PluginService;

if (!function_exists('lexo_captcha_evaluate_data')) {
    function lexo_captcha_evaluate_data(?string $data = null): bool
    {
        return PluginService::evaluateData($data);
    }

}
