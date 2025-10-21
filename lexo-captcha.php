<?php

/**
 * Plugin Name:       LEXO Captcha
 * Plugin URI:        https://github.com/lexo-ch/lexo-captcha/
 * Description:       LEXO Captcha solution.
 * Version:           2.0.5
 * Requires at least: 6.4
 * Requires PHP:      7.4.1
 * Author:            LEXO GmbH
 * Author URI:        https://www.lexo.ch
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       lexocaptcha
 * Domain Path:       /languages
 * Update URI:        lexo-captcha
 */

namespace LEXO\Captcha;

use Exception;
use LEXO\Captcha\Activation;
use LEXO\Captcha\Deactivation;
use LEXO\Captcha\Uninstalling;
use LEXO\Captcha\Core\Bootloader;

// Prevent direct access
!defined('WPINC')
    && die;

// Define Main plugin file
!defined('LEXO\Captcha\FILE')
    && define('LEXO\Captcha\FILE', __FILE__);

// Define plugin name
!defined('LEXO\Captcha\PLUGIN_NAME')
    && define('LEXO\Captcha\PLUGIN_NAME', get_file_data(FILE, [
        'Plugin Name' => 'Plugin Name'
    ])['Plugin Name']);

// Define plugin slug
!defined('LEXO\Captcha\PLUGIN_SLUG')
    && define('LEXO\Captcha\PLUGIN_SLUG', get_file_data(FILE, [
        'Update URI' => 'Update URI'
    ])['Update URI']);

// Define Basename
!defined('LEXO\Captcha\BASENAME')
    && define('LEXO\Captcha\BASENAME', plugin_basename(FILE));

// Define internal path
!defined('LEXO\Captcha\PATH')
    && define('LEXO\Captcha\PATH', plugin_dir_path(FILE));

// Define assets path
!defined('LEXO\Captcha\ASSETS')
    && define('LEXO\Captcha\ASSETS', trailingslashit(PATH) . 'assets');

// Define internal url
!defined('LEXO\Captcha\URL')
    && define('LEXO\Captcha\URL', plugin_dir_url(FILE));

// Define internal version
!defined('LEXO\Captcha\VERSION')
    && define('LEXO\Captcha\VERSION', get_file_data(FILE, [
        'Version' => 'Version'
    ])['Version']);

// Define min PHP version
!defined('LEXO\Captcha\MIN_PHP_VERSION')
    && define('LEXO\Captcha\MIN_PHP_VERSION', get_file_data(FILE, [
        'Requires PHP' => 'Requires PHP'
    ])['Requires PHP']);

// Define min WP version
!defined('LEXO\Captcha\MIN_WP_VERSION')
    && define('LEXO\Captcha\MIN_WP_VERSION', get_file_data(FILE, [
        'Requires at least' => 'Requires at least'
    ])['Requires at least']);

// Define Text domain
!defined('LEXO\Captcha\DOMAIN')
    && define('LEXO\Captcha\DOMAIN', get_file_data(FILE, [
        'Text Domain' => 'Text Domain'
    ])['Text Domain']);

// Define locales folder (with all translations)
!defined('LEXO\Captcha\LOCALES')
    && define('LEXO\Captcha\LOCALES', 'languages');

!defined('LEXO\Captcha\CACHE_KEY')
    && define('LEXO\Captcha\CACHE_KEY', DOMAIN . '_cache_key_update');

!defined('LEXO\Captcha\UPDATE_PATH')
    && define('LEXO\Captcha\UPDATE_PATH', 'https://wprepo.lexo.ch/public/lexo-captcha/info.json');

!defined('LEXO\Captcha\STATISTICS_PAGE_SLUG')
    && define('LEXO\Captcha\STATISTICS_PAGE_SLUG', DOMAIN . '-statistics');

if (!file_exists($composer = PATH . '/vendor/autoload.php')) {
    wp_die('Error locating autoloader in LEXO Captcha.
        Please run a following command:<pre>composer install</pre>', 'lexocaptcha');
}

require $composer;

register_activation_hook(FILE, function () {
    (new Activation())->run();
});

register_deactivation_hook(FILE, function () {
    (new Deactivation())->run();
});

if (!function_exists('lexo_captcha_uninstall')) {
    function lexo_captcha_uninstall()
    {
        (new Uninstalling())->run();
    }
}
register_uninstall_hook(FILE, __NAMESPACE__ . '\lexo_captcha_uninstall');

try {
    Bootloader::run();
} catch (Exception $e) {
    require_once(ABSPATH . 'wp-admin/includes/plugin.php');

    deactivate_plugins(FILE);

    wp_die($e->getMessage());
}
