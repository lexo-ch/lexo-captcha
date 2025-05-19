<?php

namespace LEXO\Captcha\Core\Services;

use LEXO\Captcha\Core;
use LEXO\Captcha\Core\Updater;
use LEXO\Captcha\Core\Pages\StatisticsPage;

final class CoreService
{
    private function __construct()
    {
        //
    }

    public static function can_manage_plugin() {
        if (!is_user_logged_in()) {
            return false;
        }

        return current_user_can(Core::BASE_CAPABILITY);
    }

    public static function action($name) {
        return trailingslashit(Core::$domain) . $name;
    }

    public static function filter($name) {
        return trailingslashit(Core::$domain) . $name;
    }

    public static function addAdminLocalizedScripts()
    {
        $vars = [
            'plugin_name'       => Core::$plugin_name,
            'plugin_slug'       => Core::$plugin_slug,
            'plugin_version'    => Core::$version,
            'min_php_version'   => Core::$min_php_version,
            'min_wp_version'    => Core::$min_wp_version,
            'text_domain'       => Core::$domain
        ];

        $vars = apply_filters(
            self::filter('admin_localized_script'),
            $vars,
        );

        wp_localize_script(
            trailingslashit(Core::$domain) . 'admin-' . Core::$domain . '.js',
            Core::$domain . 'AdminLocalized',
            $vars,
        );
    }

    public static function add_pages() {
        StatisticsPage::add_page();

        self::add_plugin_action(
            Updater::update_check_url(),
            self::__('Update Check'),
        );

        self::add_plugin_action(
            StatisticsPage::url(),
            self::__('Statistics'),
        );
    }

    public static function add_plugin_action(string $url, string $title) {
        ob_start();

        ?>

        <a href="<?= esc_attr($url) ?>">
            <?= esc_html($title) ?>
        </a>

        <?php

        $action_link = ob_get_clean();

        add_filter(
            'plugin_action_links_' . Core::$basename,
            function($links) use ($action_link) {
                $links[] = $action_link;

                return $links;
            },
        );
    }

    public static function slug($name) {
        return Core::$plugin_slug . "-{$name}";
    }

    public static function __($text) {
        return __($text, Core::$domain);
    }
}
