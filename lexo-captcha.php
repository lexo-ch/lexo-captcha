<?php

/**
 * Plugin Name:       LEXO Captcha
 * Plugin URI:        https://github.com/lexo-ch/lexo-captcha/
 * Description:       LEXO Captcha solution.
 * Version:           1.0.0
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
if (!defined('WPINC')) {
    die;
}

final class Core {
    public const FILE = __FILE__;

    public static string $plugin_name;

    public static string $plugin_slug;

    public static string $basename;

    public static string $path;

    public static string $url;

    public static string $version;

    public static string $min_php_version;

    public static string $min_wp_version;

    public static string $domain;

    public static string $locales;

    public static string $field_name;

    public static string $cache_key;

    public static string $update_path;

    public static string $original_name_addition;
}

$file_data = get_file_data(__FILE__, [
    'Plugin Name' => 'Plugin Name',
    'Update URI' => 'Update URI',
    'Version' => 'Version',
    'Requires PHP' => 'Requires PHP',
    'Requires at least' => 'Requires at least',
    'Text Domain' => 'Text Domain',
]);

Core::$plugin_name = $file_data['Plugin Name'];

Core::$plugin_slug = $file_data['Update URI'];

Core::$basename = plugin_basename(__FILE__);

Core::$path = plugin_dir_path(__FILE__);

Core::$url = plugin_dir_url(__FILE__);

Core::$version = $file_data['Version'];

Core::$min_php_version = $file_data['Requires PHP'];

Core::$min_wp_version = $file_data['Requires at least'];

Core::$domain = $file_data['Text Domain'];

Core::$locales = 'languages';

Core::$field_name = 'lexo_captcha_setting';

Core::$cache_key = Core::$domain . '_cache_key_update';

Core::$update_path = 'https://wprepo.lexo.ch/public/lexo-captcha/info.json';

Core::$original_name_addition = '---lexocaptcha-original---';

$composer = Core::$path . '/vendor/autoload.php';

if (!file_exists($composer)) {
    ob_start();

    ?>

    Error locating autoloader in LEXO Captcha.

    Please run a following command: <pre>composer install</pre>

    <?php

    wp_die(trim(ob_get_clean()), 'lexocaptcha');
}

require $composer;

register_activation_hook(__FILE__, [Activation::class, 'run']);

register_deactivation_hook(__FILE__, [Deactivation::class, 'run']);

register_uninstall_hook(__FILE__, [Uninstalling::class, 'run']);

try {
    Bootloader::run();
} catch (Exception $e) {
    require_once(ABSPATH . 'wp-admin/includes/plugin.php');

    deactivate_plugins(__FILE__);

    wp_die($e->getMessage());
}
