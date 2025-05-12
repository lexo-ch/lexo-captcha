<?php

namespace LEXO\Captcha\Core\Plugin;

use LEXO\Captcha\Core;
use LEXO\Captcha\Core\Traits\Helpers;
use LEXO\Captcha\Core\Updater\PluginUpdater;

final class PluginService
{
    use Helpers;

    private function __construct()
    {
        //
    }

    public static string $namespace = 'custom-plugin-namespace';

    public static string $check_update;

    public static string $manage_plugin_capability = 'administrator';

    public static string $setting_parent_slug = 'options-general.php';

    public static string $setting_page_slug;

    public static bool $can_manage_plugin = false;

    public static int $temp_disable_period = 0;

    public static array $plugin_settings = [];

    public static $settingsPage;

    public static function registerNamespace()
    {
        if (is_user_logged_in()) {
            PluginService::$can_manage_plugin = current_user_can(PluginService::getManagePluginCap());
        }

        PluginService::$plugin_settings = PluginService::getPluginSettings();

        PluginService::$settingsPage = new SettingsPage();

        PluginService::$temp_disable_period = PluginService::getTemporaryDisablePeriod();

        add_action(
            'admin_post_' . PluginService::$check_update,
            [PluginService::class, 'checkForUpdateManually'],
        );

        add_action(
            'admin_post_toggle_webp_converter',
            [PluginService::class, 'handleToggleCaptchaonverter'],
        );
    }

    public static function handleSaveSettings()
    {
        add_action(
            'admin_post_save_' . Core::$field_name,
            [PluginService::class, 'saveSettings'],
        );
    }

    public static function saveSettings()
    {
        if (!current_user_can(PluginService::getManagePluginCap())) {
            wp_die(__('This user doesn\'t have permission to run this plugin.', 'lexocaptcha'));
        }

        check_admin_referer(Core::$field_name);

        $settings = PluginService::$plugin_settings;

        update_option(Core::$field_name, $settings);

        set_transient(
            Core::$domain . '_update_success_notice',
            sprintf(
                __('The settings for %s have been successfully saved.', 'lexocaptcha'),
                Core::$field_name
            ),
            HOUR_IN_SECONDS
        );

        wp_safe_redirect(PluginService::getOptionsLink());

        exit;
    }

    public static function getManagePluginCap()
    {
        $capability = PluginService::$manage_plugin_capability;

        $capability = apply_filters(PluginService::$namespace . '/options-page/capability', $capability);

        return $capability;
    }

    public static function getDisableMsgDateFormat(): string
    {
        $date_format = 'd.m.Y H:i:s';
        return apply_filters(PluginService::$namespace . '/dashboard-widget/date-format', $date_format);
    }

    public static function getTemporaryDisablePeriod(): int
    {
        $default_period = 60; // minutes

        $filtered_period = apply_filters(PluginService::$namespace . '/temporary-disable-period', $default_period);

        if (!is_numeric($filtered_period) || $filtered_period <= 0) {
            return 0;
        }

        return (int) $filtered_period * 60; // Convert minutes to seconds
    }

    public static function getSettingsPageParentSlug()
    {
        $slug = PluginService::$setting_parent_slug;

        $slug = apply_filters(PluginService::$namespace . '/options-page/parent-slug', $slug);

        return $slug;
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

        $vars = apply_filters(PluginService::$namespace . '/admin_localized_script', $vars);

        wp_localize_script(trailingslashit(PluginService::$namespace) . 'admin-' . Core::$domain . '.js', Core::$domain . 'AdminLocalized', $vars);
    }

    public static function addSettingsLink()
    {
        add_filter(
            'plugin_action_links_' . Core::$basename,
            [PluginService::class, 'setSettingsLink'],
        );
    }

    public static function getOptionsLink()
    {
        $path = PluginService::getSettingsPageParentSlug();

        if (strpos($path, '.php') === false) {
            $path = 'admin.php';
        }

        return esc_url(
            add_query_arg(
                'page',
                PluginService::$setting_page_slug,
                admin_url($path)
            )
        );
    }

    public static function setSettingsLink($links)
    {
        $url = PluginService::getOptionsLink();

        $settings_link = "<a href='{$url}'>" . __('Settings', 'lexocaptcha') . '</a>';

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
            wp_die(__('Security check failed.', 'lexocaptcha'));
        }

        if (!PluginService::updater()->hasNewUpdate()) {
            set_transient(
                Core::$domain . '_no_updates_notice',
                sprintf(
                    __('Plugin %s is up to date.', 'lexocaptcha'),
                    Core::$plugin_name
                ),
                HOUR_IN_SECONDS,
            );

            wp_safe_redirect(PluginService::getOptionsLink());
        } else {
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

        return wp_date(get_option('date_format') . ' ' . get_option('time_format'), $expiration_datetime);
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

    public static function addSettingsPage()
    {
        add_submenu_page(
            PluginService::getSettingsPageParentSlug(),
            __('LEXO Captcha', 'lexocaptcha'),
            __('LEXO Captcha', 'lexocaptcha'),
            PluginService::getManagePluginCap(),
            PluginService::$setting_page_slug,
            function() {
                SettingsPage::getSettingsPageContent();
            }
        );
    }

    public static function getInitSettings(): array
    {
        return [
            'temporary_disable_timestamp' => 0
        ];
    }

    private static function mergeSettingsAndUpdateOption($currentSettings, $defaultSettings)
    {
        // Flag to track if there are any changes
        $isChanged = false;

        foreach ($defaultSettings as $key => $value) {
            if (!isset($currentSettings[$key])) {
                $currentSettings[$key] = $value;
                $isChanged = true; // Mark as changed
            } elseif (is_array($value)) {
                // Recursively merge sub-arrays and check for changes
                list($mergedSubArray, $subArrayChanged) = PluginService::mergeSettingsAndUpdateOption($currentSettings[$key], $value);
                $currentSettings[$key] = $mergedSubArray;
                if ($subArrayChanged) {
                    $isChanged = true; // Propagate change flag
                }
            }
        }

        if ($isChanged) {
            // Update the WordPress option only if there are changes
            update_option(Core::$field_name, $currentSettings);
        }

        // Return the merged settings and the change flag
        return array($currentSettings, $isChanged);
    }

    public static function updateMissingSettings()
    {
        PluginService::mergeSettingsAndUpdateOption(
            get_option(Core::$field_name),
            PluginService::getInitSettings()
        );
    }

    public static function getPluginSettings()
    {
        return wp_parse_args(get_option(Core::$field_name, []), PluginService::getInitSettings());
    }

    public static function getSettingsPageFields(): array
    {
        $settings = PluginService::$plugin_settings;

        if (!$settings) {
            return [];
        }

        return [];
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

    public static function isTemporarilyDisabled(): bool
    {
        $timestamp = PluginService::$plugin_settings['temporary_disable_timestamp'];

        $current_time = current_time('timestamp');

        if ($timestamp > 0 && ($timestamp + PluginService::$temp_disable_period) > $current_time) {
            return true;
        }

        // Reset and trigger temporary-disablement-has-ended action if time has expired
        if ($timestamp > 0 && ($timestamp + PluginService::$temp_disable_period) <= $current_time) {
            PluginService::enablePlugin();
        }

        return false;
    }

    public static function getDisableMessage(): string
    {
        return sprintf(
            __('The %s plugin (<b>image optimization</b>) is temporarily disabled until <b>%s</b>.', 'lexocaptcha'),
            Core::$plugin_name,
            date(
                PluginService::getDisableMsgDateFormat(),
                PluginService::$plugin_settings['temporary_disable_timestamp'] + PluginService::$temp_disable_period
            )
        );
    }

    private static function enablePlugin()
    {
        $was_disabled = PluginService::$plugin_settings['temporary_disable_timestamp'] > 0;
        PluginService::$plugin_settings['temporary_disable_timestamp'] = 0;
        update_option(Core::$field_name, PluginService::$plugin_settings);

        if ($was_disabled) {
            do_action(Core::$domain . '/temporary-disablement-has-ended');
        }
    }

    private static function disablePlugin()
    {
        PluginService::$plugin_settings['temporary_disable_timestamp'] = current_time('timestamp');
        update_option(Core::$field_name, PluginService::$plugin_settings);

        do_action(Core::$domain . '/plugin-temporarily-disabled');
    }

    public function addTemporaryDisableNotice()
    {
        if (!PluginService::isTemporarilyDisabled()) {
            return false;
        }

        $message = $this->getDisableMessage();

        wp_admin_notice(
            $message,
            [
                'type'        => 'info',
                'dismissible' => false,
                'attributes'  => [
                    'data-slug'   => Core::$plugin_slug,
                    'data-action' => 'temp-disable'
                ]
            ]
        );
    }

    private function infoDisableMessage(int $seconds): string
    {
        $time_display = PluginService::convertSecondsToHoursAndMinutes($seconds);

        return sprintf(
            __(
                'If you disable image optimization, uploaded images will not be automatically converted and optimized for the web in WebP format for <strong>%s</strong>. The function will then be automatically reactivated. You can enable image optimization at any time before this period expires.',
                'lexocaptcha'
            ),
            $time_display
        );
    }

    public function handleToggleCaptchaonverter()
    {
        if (!current_user_can(PluginService::getManagePluginCap())) {
            wp_die(__('This user doesn\'t have permission to run this plugin.', 'lexocaptcha'));
        }

        check_admin_referer('toggle_webp_converter');

        if (isset($_POST['disable_plugin'])) {
            PluginService::disablePlugin();
        } else {
            PluginService::enablePlugin();
        }

        wp_safe_redirect(admin_url());
        exit;
    }
}

PluginService::$namespace = Core::$domain;

PluginService::$check_update = 'check-update-' . Core::$plugin_slug;

PluginService::$setting_page_slug = 'settings-' . Core::$plugin_slug;