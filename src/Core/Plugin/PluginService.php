<?php

namespace LEXO\Captcha\Core\Plugin;

use LEXO\Captcha\Core;
use LEXO\Captcha\Core\Updater\PluginUpdater;

final class PluginService
{
    private function __construct()
    {
        //
    }

    public static string $check_update;

    public static bool $can_manage_plugin = false;

    public static function registerNamespace()
    {
        if (is_user_logged_in()) {
            PluginService::$can_manage_plugin = current_user_can(Core::BASE_CAPABILITY);
        }

        add_action(
            'admin_post_' . PluginService::$check_update,
            [PluginService::class, 'checkForUpdateManually'],
        );

        add_action(
            'admin_post_toggle_lexo_captcha',
            [PluginService::class, 'handleToggleCaptchaonverter'],
        );
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
            PluginService::filter('admin_localized_script'),
            $vars,
        );

        wp_localize_script(
            trailingslashit(Core::$domain) . 'admin-' . Core::$domain . '.js',
            Core::$domain . 'AdminLocalized',
            $vars,
        );
    }

    public static function add_statistics_link($links)
    {
        $url = StatisticsPage::link();

        $settings_link = "<a href='{$url}'>" . PluginService::__('Statistics') . '</a>';

        array_push(
            $links,
            $settings_link
        );

        return $links;
    }

    public static function updater()
    {
        return (new PluginUpdater())
            ->setBasename(Core::$basename)
            ->setSlug(Core::$plugin_slug)
            ->setVersion(Core::$version)
            ->setRemotePath(Core::$update_path)
            ->setCacheKey(Core::$cache_key)
            ->setCacheExpiration(HOUR_IN_SECONDS)
            ->setCache(true);
    }

    public static function checkForUpdateManually()
    {
        if (!wp_verify_nonce($_REQUEST['nonce'], PluginService::$check_update)) {
            wp_die(PluginService::__('Security check failed.'));
        }

        if (!PluginService::updater()->hasNewUpdate()) {
            set_transient(
                Core::$domain . '_no_updates_notice',
                sprintf(
                    PluginService::__('Plugin %s is up to date.'),
                    Core::$plugin_name
                ),
                HOUR_IN_SECONDS,
            );
        }
        else {
            delete_transient(Core::$cache_key);
            
            wp_safe_redirect(admin_url('plugins.php'));
        }

        exit;
    }

    public static function nextAutoUpdateCheck()
    {
        $expiration_datetime = get_option('_transient_timeout_' . Core::$cache_key);

        if (!$expiration_datetime) {
            return false;
        }

        return wp_date(
            get_option('date_format') . ' ' . get_option('time_format'),
            $expiration_datetime,
        );
    }

    public static function noUpdatesNotice()
    {
        $message = get_transient(Core::$domain . '_no_updates_notice');

        delete_transient(Core::$domain . '_no_updates_notice');

        if (!$message) {
            return false;
        }

        wp_admin_notice(
            $message,
            [
                'type'        => 'success',
                'dismissible' => true,
                'attributes'  => [
                    'data-slug'   => Core::$plugin_slug,
                    'data-action' => 'no-updates'
                ]
            ]
        );
    }

    public static function updateSuccessNotice()
    {
        $message = get_transient(Core::$domain . '_update_success_notice');

        delete_transient(Core::$domain . '_update_success_notice');

        if (!$message) {
            return false;
        }

        wp_admin_notice(
            $message,
            [
                'type'        => 'success',
                'dismissible' => true,
                'attributes'  => [
                    'data-slug'   => Core::$plugin_slug,
                    'data-action' => 'updated'
                ]
            ]
        );
    }

    public static function add_pages() {
        StatisticsPage::add_page();

        add_filter(
            'plugin_action_links_' . Core::$basename,
            [PluginService::class, 'add_statistics_link'],
        );
    }

    public static function slug($name) {
        return Core::$plugin_slug . "-{$name}";
    }

    public static function getManualUpdateCheckLink(): string
    {
        return esc_url(
            add_query_arg(
                [
                    'action' => PluginService::$check_update,
                    'nonce' => wp_create_nonce(PluginService::$check_update)
                ],
                admin_url('admin-post.php')
            )
        );
    }

    public static function __($text) {
        return __($text, Core::$domain);
    }
}

PluginService::$check_update = 'check-update-' . Core::$plugin_slug;
