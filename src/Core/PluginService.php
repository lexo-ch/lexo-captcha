<?php

namespace LEXO\Captcha\Core;

use LEXO\Captcha\Core\PluginUpdater;
use LEXO\Captcha\Core\Loader\Loader;
use LEXO\Captcha\Core\Abstracts\Singleton;
use LEXO\Captcha\Core\Pages\StatisticsPage;

use const LEXO\Captcha\{
    ASSETS,
    PLUGIN_NAME,
    PLUGIN_SLUG,
    VERSION,
    MIN_PHP_VERSION,
    MIN_WP_VERSION,
    DOMAIN,
    BASENAME,
    CACHE_KEY,
    UPDATE_PATH,
    STATISTICS_PAGE_SLUG
};

class PluginService extends Singleton
{
    private static string $namespace        = 'custom-plugin-namespace';
    protected static $instance              = null;
    private static int $submit_cooldown     = 15000;
    private const CHECK_UPDATE              = 'check-update-' . PLUGIN_SLUG;
    private const MANAGE_PLUGIN_CAP         = 'manage_options';
    private const STATISTICS_PARENT_SLUG    = 'options-general.php';

    public function updater(): PluginUpdater
    {
        return (new PluginUpdater())
            ->setBasename(BASENAME)
            ->setSlug(PLUGIN_SLUG)
            ->setVersion(VERSION)
            ->setRemotePath(UPDATE_PATH)
            ->setCacheKey(CACHE_KEY)
            ->setCacheExpiration(HOUR_IN_SECONDS)
            ->setCache(true);
    }

    public function setNamespace(string $namespace): void
    {
        self::$namespace = $namespace;
    }

    public function registerNamespace()
    {
        $config = require_once trailingslashit(ASSETS) . 'config/config.php';

        $loader = Loader::getInstance();

        $loader->registerNamespace(self::$namespace, $config);

        add_action('admin_post_' . self::CHECK_UPDATE, [$this, 'checkForUpdateManually']);
    }

    public function checkForUpdateManually(): void
    {
        if (!wp_verify_nonce($_REQUEST['nonce'], self::CHECK_UPDATE)) {
            wp_die(__('Security check failed.', 'lexocaptcha'));
        }

        $plugin_service = PluginService::getInstance();

        if (!$plugin_service->updater()->hasNewUpdate()) {
            set_transient(
                DOMAIN . '_no_updates_notice',
                sprintf(
                    __('Plugin %s is up to date.', 'lexocaptcha'),
                    PLUGIN_NAME
                ),
                HOUR_IN_SECONDS
            );
        } else {
            delete_transient(CACHE_KEY);
        }

        wp_safe_redirect(admin_url('plugins.php'));

        exit;
    }

    public function addFrontLocalizedScripts(): void
    {
        self::$submit_cooldown = apply_filters(
            self::$namespace . '/captcha/submit-cooldown',
            self::$submit_cooldown,
        );

        $vars = [
            'plugin_name'       => PLUGIN_NAME,
            'plugin_slug'       => PLUGIN_SLUG,
            'plugin_version'    => VERSION,
            'min_php_version'   => MIN_PHP_VERSION,
            'min_wp_version'    => MIN_WP_VERSION,
            'text_domain'       => DOMAIN,
            'ajax_url'          => admin_url('admin-ajax.php'),
            'submit_cooldown'   => self::$submit_cooldown,
        ];

        $vars = apply_filters(
            self::$namespace . '/loader/front-localized-script',
            $vars
        );

        wp_localize_script(trailingslashit(self::$namespace) . 'front.js', DOMAIN . 'FrontLocalized', $vars);
    }

    public function addPluginLinks(): void
    {
        add_filter(
            'plugin_action_links_' . BASENAME,
            [$this, 'setPluginLinks']
        );
    }

    public function setPluginLinks($links): array
    {
        $update_check_url = self::getManualUpdateCheckLink();
        $update_check_link = "<a href='{$update_check_url}'>" . __('Update check', 'lexocaptcha') . '</a>';

        $statistics_url = self::getStatisticsLink();
        $statistics_url = "<a href='{$statistics_url}'>" . __('Statistics', 'lexocaptcha') . '</a>';

        array_push(
            $links,
            $update_check_link,
            $statistics_url
        );

        return $links;
    }

    public static function getManualUpdateCheckLink(): string
    {
        return esc_url(
            add_query_arg(
                [
                    'action' => self::CHECK_UPDATE,
                    'nonce' => wp_create_nonce(self::CHECK_UPDATE)
                ],
                admin_url('admin-post.php')
            )
        );
    }

    public static function getStatisticsLink(): string
    {
        $path = self::getStatisticsPageParentSlug();

        if (strpos($path, '.php') === false) {
            $path = 'admin.php';
        }

        return esc_url(
            add_query_arg(
                'page',
                STATISTICS_PAGE_SLUG,
                admin_url($path)
            )
        );
    }

    public static function getStatisticsPageParentSlug(): string
    {
        $slug = self::STATISTICS_PARENT_SLUG;

        $slug = apply_filters(self::$namespace . '/statistics-page/parent-slug', $slug);

        return $slug;
    }

    public static function getManagePluginCap(): string
    {
        $capability = self::MANAGE_PLUGIN_CAP;

        $capability = apply_filters(self::$namespace . '/statistics-page/capability', $capability);

        return $capability;
    }

    public function addStatisticsPage(): void
    {
        add_submenu_page(
            self::getStatisticsPageParentSlug(),
            sprintf(
                __('%s Statistics', 'lexocaptcha'),
                PLUGIN_NAME
            ),
            PLUGIN_NAME,
            self::getManagePluginCap(),
            STATISTICS_PAGE_SLUG,
            [StatisticsPage::class, 'content']
        );
    }

    public function noUpdatesNotice(): void
    {
        $message = get_transient(DOMAIN . '_no_updates_notice');
        delete_transient(DOMAIN . '_no_updates_notice');

        if (!$message) {
            return;
        }

        wp_admin_notice(
            $message,
            [
                'type'        => 'success',
                'dismissible' => true,
                'attributes'  => [
                    'data-slug'   => PLUGIN_SLUG,
                    'data-action' => 'no-updates'
                ]
            ]
        );
    }

    public function updateSuccessNotice(): void
    {
        $message = get_transient(DOMAIN . '_update_success_notice');
        delete_transient(DOMAIN . '_update_success_notice');

        if (!$message) {
            return;
        }

        wp_admin_notice(
            $message,
            [
                'type'        => 'success',
                'dismissible' => true,
                'attributes'  => [
                    'data-slug'   => PLUGIN_SLUG,
                    'data-action' => 'updated'
                ]
            ]
        );
    }

    private static function validReferer(): bool
    {
        if (!isset($_SERVER['SERVER_NAME']) || empty($_SERVER['SERVER_NAME'])) {
            return false;
        }

        if (!isset($_SERVER['HTTP_REFERER']) || empty($_SERVER['HTTP_REFERER'])) {
            return false;
        }

        preg_match('/[^.]+\.[^.]+$/m', sanitize_text_field($_SERVER['SERVER_NAME']), $server_host);

        if (empty($server_host[0])) {
            return false;
        }

        $parsed = parse_url(esc_url_raw($_SERVER['HTTP_REFERER']));

        if ($parsed === false || !isset($parsed['host'])) {
            return false;
        }

        preg_match('/[^.]+\.[^.]+$/m', $parsed['host'], $client_host);

        if (empty($client_host[0])) {
            return false;
        }

        if ($server_host[0] !== $client_host[0]) {
            return false;
        }

        return true;
    }

    public static function requestToken(): void
    {
        if (!self::validReferer()) {
            if (isset($_SESSION['LEXO_CAPTCHA_TOKEN'])) {
                unset($_SESSION['LEXO_CAPTCHA_TOKEN']);
            }

            if (isset($_SESSION['LEXO_CAPTCHA_TOKEN_GENERATION_TIMESTAMP'])) {
                unset($_SESSION['LEXO_CAPTCHA_TOKEN_GENERATION_TIMESTAMP']);
            }

            wp_send_json_error([
                'message' => 'Invalid request origin'
            ]);
        }

        $_SESSION['LEXO_CAPTCHA_TOKEN_GENERATION_TIMESTAMP'] = self::getTimestamp();

        $_SESSION['LEXO_CAPTCHA_TOKEN'] = hash('SHA256', $_SESSION['LEXO_CAPTCHA_TOKEN_GENERATION_TIMESTAMP']);

        wp_send_json_success([
            'token' => $_SESSION['LEXO_CAPTCHA_TOKEN']
        ]);
    }

    public static function addAjaxRoutes(): void
    {
        add_action(
            'wp_ajax_lexo_captcha_request_token',
            [self::class, 'requestToken'],
        );

        add_action(
            'wp_ajax_nopriv_lexo_captcha_request_token',
            [self::class, 'requestToken'],
        );
    }

    private static function getTimestamp(): float
    {
        return floor(microtime(true) * 1000);
    }

    private static function appendStatistics(
        string $reason,
        float $timestamp,
        ?float $interaction,
        ?string $given_token,
        ?string $expected_token,
        float $token_generation_timestamp,
        array $additional_data = []
    ): void {
        $statistics = json_decode(get_option(
            'lexo_captcha_statistics',
            '[]',
        ));

        if (!is_array($statistics)) {
            $statistics = [];
        }

        $original_timezone = date_default_timezone_get();

        date_default_timezone_set('Europe/Zurich');

        array_unshift(
            $statistics,
            [
                'ip'                         => sanitize_text_field($_SERVER['REMOTE_ADDR']) ?? null,
                'user_agent'                 => sanitize_text_field($_SERVER['HTTP_USER_AGENT']) ?? null,
                'referer'                    => esc_url_raw($_SERVER['HTTP_REFERER']) ?? null,
                'date'                       => date('Y-m-d H:i:s'),
                'reason'                     => $reason,
                'timestamp'                  => $timestamp,
                'interaction_timestamp'      => $interaction,
                'given_token'                => $given_token,
                'expected_token'             => $expected_token,
                'token_generation_timestamp' => $token_generation_timestamp,
                'additional_data'            => $additional_data,
            ],
        );

        date_default_timezone_set($original_timezone);

        update_option(
            'lexo_captcha_statistics',
            json_encode($statistics),
        );
    }

    public static function evaluateData(?string $data = null): bool
    {
        if (empty($data)) {
            $data = sanitize_text_field($_POST['lexo_captcha_data']);
        }

        $data = json_decode(stripslashes($data), true);

        $timestamp = self::getTimestamp();

        $evaluations = get_option(
            'lexo_captcha_evaluations',
            0,
        );

        update_option(
            'lexo_captcha_evaluations',
            $evaluations + 1,
        );

        if (!self::validReferer()) {
            self::appendStatistics(
                'invalid-referer-host',
                $timestamp,
                $data['interacted'] ?? null,
                $data['token'] ?? null,
                $_SESSION['LEXO_CAPTCHA_TOKEN'] ?? null,
                $_SESSION['LEXO_CAPTCHA_TOKEN_GENERATION_TIMESTAMP'] ?? null,
                [
                    'server_name' => sanitize_text_field($_SERVER['SERVER_NAME']) ?? null,
                ]
            );

            return false;
        }

        if (!isset($data['interacted'])) {
            self::appendStatistics(
                'interaction-data-missing',
                $timestamp,
                null,
                $data['token'] ?? null,
                $_SESSION['LEXO_CAPTCHA_TOKEN'] ?? null,
                $_SESSION['LEXO_CAPTCHA_TOKEN_GENERATION_TIMESTAMP'] ?? null,
            );

            return false;
        }

        if (!isset($data['token'])) {
            self::appendStatistics(
                'token-missing',
                $timestamp,
                $data['interacted'],
                null,
                $_SESSION['LEXO_CAPTCHA_TOKEN'] ?? null,
                $_SESSION['LEXO_CAPTCHA_TOKEN_GENERATION_TIMESTAMP'] ?? null,
            );

            return false;
        }

        if (empty($_SESSION['LEXO_CAPTCHA_TOKEN']) || !isset($_SESSION['LEXO_CAPTCHA_TOKEN_GENERATION_TIMESTAMP'])) {
            self::appendStatistics(
                'no-token-requested',
                $timestamp,
                $data['interacted'],
                $data['token'],
                $_SESSION['LEXO_CAPTCHA_TOKEN'] ?? null,
                $_SESSION['LEXO_CAPTCHA_TOKEN_GENERATION_TIMESTAMP'] ?? null,
            );

            return false;
        }

        $captcha_token = $_SESSION['LEXO_CAPTCHA_TOKEN'];
        $captcha_token_generation_timestamp = $_SESSION['LEXO_CAPTCHA_TOKEN_GENERATION_TIMESTAMP'];

        unset($_SESSION['LEXO_CAPTCHA_TOKEN']);
        unset($_SESSION['LEXO_CAPTCHA_TOKEN_GENERATION_TIMESTAMP']);

        if ($data['token'] !== $captcha_token) {
            self::appendStatistics(
                'invalid-token',
                $timestamp,
                $data['interacted'],
                $data['token'],
                $captcha_token,
                $captcha_token_generation_timestamp,
            );

            return false;
        }

        // client timestamp tolerance = 300000ms = 300s

        $timestamp_tolerance = apply_filters(
            self::$namespace . '/captcha/client-timestamp-tolerance',
            300000,
        );

        if ($data['interacted'] > $timestamp + $timestamp_tolerance) {
            self::appendStatistics(
                'interaction-data-future',
                $timestamp,
                $data['interacted'],
                $data['token'],
                $captcha_token,
                $captcha_token_generation_timestamp,
            );

            return false;
        }

        // Form sent within 15 seconds since token generation.
        if ($timestamp - $captcha_token_generation_timestamp < self::$submit_cooldown) {
            self::appendStatistics(
                'early-submit',
                $timestamp,
                $data['interacted'],
                $data['token'],
                $captcha_token,
                $captcha_token_generation_timestamp,
                [
                    'remaining_time' => $timestamp - $captcha_token_generation_timestamp,
                    'submit_cooldown' => self::$submit_cooldown,
                ],
            );

            return false;
        }

        // 3600000ms = 1h

        $max_interaction_age = apply_filters(
            self::$namespace . '/captcha/max-interaction-age',
            3600000,
        );

        // First interacted over an hour ago.
        if ($timestamp - $data['interacted'] > $max_interaction_age) {
            self::appendStatistics(
                'interaction-data-expired',
                $timestamp,
                $data['interacted'],
                $data['token'],
                $captcha_token,
                $captcha_token_generation_timestamp,
                [
                    'interaction_age' => $timestamp - $data['interacted'],
                    'interaction_max_age' => $max_interaction_age,
                ],
            );

            return false;
        }

        $max_token_age = apply_filters(
            self::$namespace . '/captcha/max-token-age',
            3600000,
        );

        // Token generated over an hour ago.
        if ($timestamp - $captcha_token_generation_timestamp > $max_token_age) {
            self::appendStatistics(
                'expired-token',
                $timestamp,
                $data['interacted'],
                $data['token'],
                $captcha_token,
                $captcha_token_generation_timestamp,
                [
                    'token_age' => $timestamp - $captcha_token_generation_timestamp,
                    'token_max_age' => $max_token_age,
                ],
            );

            return false;
        }

        return true;
    }

    public static function describeReason(string $reason): string
    {
        switch ($reason) {
            case 'expired-token':
                return __('Expired token.', 'lexocaptcha');
            case 'interaction-data-expired':
                return __('Interaction data expired.', 'lexocaptcha');
            case 'early-submit':
                return __('Submitted too early.', 'lexocaptcha');
            case 'interaction-data-future':
                return __('Interaction data from the future, likely faked.', 'lexocaptcha');
            case 'invalid-token':
                return __('Invalid token.', 'lexocaptcha');
            case 'no-token-requested':
                return __('No token requested.', 'lexocaptcha');
            case 'token-missing':
                return __('Token missing.', 'lexocaptcha');
            case 'interaction-data-missing':
                return __('Interaction data missing.', 'lexocaptcha');
            case 'invalid-referer-host':
                return __('Missing referer or invalid referer host.', 'lexocaptcha');
        }

        return $reason;
    }
}
