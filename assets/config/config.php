<?php

use LEXO\Captcha\Core;

if (!defined('ABSPATH')) {
    exit; // Don't access directly
};

return [
    'priority'  => 90,
    'dist_path' => Core::$path . 'dist',
    'dist_uri'  => Core::$url . 'dist',
    'assets'    => [
        'front' => [
            'styles'    => [
                
            ],
            'scripts'   => [
                'lexo-captcha.js'
            ],
        ],
        'admin' => [
            'styles'    => [
                'admin-lexocaptcha.css',
            ],
            'scripts'   => [
                'admin-lexocaptcha.js',
            ],
        ],
        'editor' => [
            'styles'    => []
        ],
    ]
];
