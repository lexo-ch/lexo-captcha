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
    private const TOKEN_NONCE_FIELD         = 'token_nonce';
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

    private static function normalizeHost(?string $host): ?string
    {
        if (!is_string($host) || $host === '') {
            return null;
        }

        $host = strtolower(trim($host));

        if ($host === '') {
            return null;
        }

        if (strpos($host, '://') !== false) {
            $parsed = wp_parse_url($host, PHP_URL_HOST);

            if (!is_string($parsed) || $parsed === '') {
                return null;
            }

            $host = strtolower($parsed);
        }

        $host = preg_replace('/:\\d+\\z/', '', $host);

        if (!is_string($host) || $host === '') {
            return null;
        }

        if (strpos($host, 'www.') === 0) {
            $host = substr($host, 4);
        }

        return $host ?: null;
    }

    private static function getAllowedHosts(): array
    {
        $site_host = self::normalizeHost(wp_parse_url(home_url(), PHP_URL_HOST));

        $hosts = apply_filters(
            self::$namespace . '/captcha/allowed-hosts',
            $site_host ? [$site_host] : []
        );

        if (!is_array($hosts)) {
            $hosts = $site_host ? [$site_host] : [];
        }

        $normalized = [];

        foreach ($hosts as $host) {
            $normalized_host = self::normalizeHost(is_string($host) ? $host : null);

            if ($normalized_host) {
                $normalized[] = $normalized_host;
            }
        }

        return array_values(array_unique($normalized));
    }

    private static function isAllowedHost(?string $host): bool
    {
        $normalized_host = self::normalizeHost($host);

        if (!$normalized_host) {
            return false;
        }

        return in_array($normalized_host, self::getAllowedHosts(), true);
    }

    private static function getRequestTokenNonceAction(): string
    {
        return self::$namespace . '/request-token';
    }

    private static function validOrigin(): bool
    {
        if (empty($_SERVER['HTTP_ORIGIN'])) {
            return false;
        }

        $origin = sanitize_text_field(wp_unslash($_SERVER['HTTP_ORIGIN']));

        $origin_host = wp_parse_url($origin, PHP_URL_HOST);

        if (!$origin_host) {
            return false;
        }

        return self::isAllowedHost($origin_host);
    }

    private static function validReferer(): bool
    {
        $referer = wp_get_raw_referer();

        if (!$referer) {
            return false;
        }

        $referer_host = wp_parse_url($referer, PHP_URL_HOST);

        if (!$referer_host) {
            return false;
        }

        return self::isAllowedHost($referer_host);
    }

    private static function validTokenRequest(): bool
    {
        $nonce_valid = check_ajax_referer(
            self::getRequestTokenNonceAction(),
            self::TOKEN_NONCE_FIELD,
            false
        );

        if (!$nonce_valid) {
            return false;
        }

        if (!self::validOrigin() && !self::validReferer()) {
            return false;
        }

        return true;
    }

    private static function clearTokenSession(): void
    {
        unset($_SESSION['LEXO_CAPTCHA_TOKEN']);
        unset($_SESSION['LEXO_CAPTCHA_TOKEN_GENERATION_TIMESTAMP']);
        unset($_SESSION['LEXO_CAPTCHA_TOKEN_USER_AGENT']);
        unset($_SESSION['LEXO_CAPTCHA_TOKEN_IP']);
    }

    private static function getClientIp(): ?string
    {
        $ip = $_SERVER['REMOTE_ADDR'] ?? null;

        $ip = apply_filters(self::$namespace . '/captcha/client-ip', $ip);

        if (!is_string($ip)) {
            return null;
        }

        $ip = trim($ip);

        if ($ip === '') {
            return null;
        }

        $validated = filter_var($ip, FILTER_VALIDATE_IP);

        if ($validated === false) {
            return null;
        }

        return $validated;
    }

    public static function requestNonce(): void
    {
        if ('POST' !== ($_SERVER['REQUEST_METHOD'] ?? '')) {
            wp_send_json_error(
                [
                    'message' => 'Invalid request method',
                ],
                405
            );
        }

        if (!self::validOrigin() && !self::validReferer()) {
            wp_send_json_error(
                [
                    'message' => 'Invalid request context',
                ],
                403
            );
        }

        wp_send_json_success([
            'nonce' => wp_create_nonce(self::getRequestTokenNonceAction()),
        ]);
    }

    public static function requestToken(): void
    {
        if ('POST' !== ($_SERVER['REQUEST_METHOD'] ?? '')) {
            self::clearTokenSession();

            wp_send_json_error(
                [
                    'message' => 'Invalid request method',
                ],
                405
            );
        }

        if (!self::validTokenRequest()) {
            self::clearTokenSession();

            wp_send_json_error(
                [
                    'message' => 'Invalid request context',
                ],
                403
            );
        }

        $timestamp = self::getTimestamp();

        self::clearTokenSession();

        $_SESSION['LEXO_CAPTCHA_TOKEN_GENERATION_TIMESTAMP'] = $timestamp;

        $token = wp_generate_password(64, false);

        $_SESSION['LEXO_CAPTCHA_TOKEN'] = $token;

        if (!empty($_SERVER['HTTP_USER_AGENT'])) {
            $_SESSION['LEXO_CAPTCHA_TOKEN_USER_AGENT'] = sanitize_text_field(
                wp_unslash($_SERVER['HTTP_USER_AGENT'])
            );
        }

        $client_ip = self::getClientIp();

        if ($client_ip) {
            $_SESSION['LEXO_CAPTCHA_TOKEN_IP'] = $client_ip;
        }

        wp_send_json_success([
            'token'      => $token,
            'next_nonce' => wp_create_nonce(self::getRequestTokenNonceAction()),
        ]);
    }

    public static function addAjaxRoutes(): void
    {
        add_action(
            'wp_ajax_lexo_captcha_request_nonce',
            [self::class, 'requestNonce'],
        );

        add_action(
            'wp_ajax_nopriv_lexo_captcha_request_nonce',
            [self::class, 'requestNonce'],
        );

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
        ?float $token_generation_timestamp,
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

        $decoded = json_decode(stripslashes($data), true);

        $timestamp = self::getTimestamp();

        $evaluations = get_option(
            'lexo_captcha_evaluations',
            0,
        );

        update_option(
            'lexo_captcha_evaluations',
            $evaluations + 1,
        );

        if (!is_array($decoded)) {
            self::appendStatistics(
                'invalid-payload',
                $timestamp,
                null,
                null,
                $_SESSION['LEXO_CAPTCHA_TOKEN'] ?? null,
                $_SESSION['LEXO_CAPTCHA_TOKEN_GENERATION_TIMESTAMP'] ?? null,
                [
                    'json_error' => json_last_error_msg(),
                ]
            );

            return false;
        }

        $data = $decoded;

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

        if (!array_key_exists('interacted', $data)) {
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

        if (!is_numeric($data['interacted'])) {
            self::appendStatistics(
                'interaction-data-invalid',
                $timestamp,
                null,
                $data['token'] ?? null,
                $_SESSION['LEXO_CAPTCHA_TOKEN'] ?? null,
                $_SESSION['LEXO_CAPTCHA_TOKEN_GENERATION_TIMESTAMP'] ?? null,
            );

            return false;
        }

        $interaction = (float) $data['interacted'];

        if (!array_key_exists('token', $data)) {
            self::appendStatistics(
                'token-missing',
                $timestamp,
                $interaction,
                null,
                $_SESSION['LEXO_CAPTCHA_TOKEN'] ?? null,
                $_SESSION['LEXO_CAPTCHA_TOKEN_GENERATION_TIMESTAMP'] ?? null,
            );

            return false;
        }

        if (!is_string($data['token']) || $data['token'] === '') {
            self::appendStatistics(
                'token-invalid',
                $timestamp,
                $interaction,
                is_scalar($data['token']) ? (string) $data['token'] : null,
                $_SESSION['LEXO_CAPTCHA_TOKEN'] ?? null,
                $_SESSION['LEXO_CAPTCHA_TOKEN_GENERATION_TIMESTAMP'] ?? null,
            );

            return false;
        }

        if (empty($_SESSION['LEXO_CAPTCHA_TOKEN']) || !isset($_SESSION['LEXO_CAPTCHA_TOKEN_GENERATION_TIMESTAMP'])) {
            self::appendStatistics(
                'no-token-requested',
                $timestamp,
                $interaction,
                $data['token'],
                $_SESSION['LEXO_CAPTCHA_TOKEN'] ?? null,
                $_SESSION['LEXO_CAPTCHA_TOKEN_GENERATION_TIMESTAMP'] ?? null,
            );

            return false;
        }

        $captcha_token = (string) $_SESSION['LEXO_CAPTCHA_TOKEN'];
        $captcha_token_generation_timestamp = (float) $_SESSION['LEXO_CAPTCHA_TOKEN_GENERATION_TIMESTAMP'];
        $captcha_token_user_agent = $_SESSION['LEXO_CAPTCHA_TOKEN_USER_AGENT'] ?? null;
        $captcha_token_ip = $_SESSION['LEXO_CAPTCHA_TOKEN_IP'] ?? null;

        self::clearTokenSession();

        if (!hash_equals($captcha_token, $data['token'])) {
            self::appendStatistics(
                'invalid-token',
                $timestamp,
                $interaction,
                $data['token'],
                $captcha_token,
                $captcha_token_generation_timestamp,
            );

            return false;
        }

        $current_user_agent = !empty($_SERVER['HTTP_USER_AGENT'])
            ? sanitize_text_field(wp_unslash($_SERVER['HTTP_USER_AGENT']))
            : null;

        if ($captcha_token_user_agent && $current_user_agent !== $captcha_token_user_agent) {
            self::appendStatistics(
                'token-user-agent-mismatch',
                $timestamp,
                $interaction,
                $data['token'],
                $captcha_token,
                $captcha_token_generation_timestamp,
                [
                    'expected_user_agent' => $captcha_token_user_agent,
                    'received_user_agent' => $current_user_agent,
                ]
            );

            return false;
        }

        $current_ip = self::getClientIp();

        if ($captcha_token_ip && $current_ip !== $captcha_token_ip) {
            self::appendStatistics(
                'token-ip-mismatch',
                $timestamp,
                $interaction,
                $data['token'],
                $captcha_token,
                $captcha_token_generation_timestamp,
                [
                    'expected_ip' => $captcha_token_ip,
                    'received_ip' => $current_ip,
                ]
            );

            return false;
        }

        $timestamp_tolerance = apply_filters(
            self::$namespace . '/captcha/client-timestamp-tolerance',
            300000,
        );

        if ($interaction > $timestamp + $timestamp_tolerance) {
            self::appendStatistics(
                'interaction-data-future',
                $timestamp,
                $interaction,
                $data['token'],
                $captcha_token,
                $captcha_token_generation_timestamp,
            );

            return false;
        }

        if ($timestamp - $captcha_token_generation_timestamp < self::$submit_cooldown) {
            self::appendStatistics(
                'early-submit',
                $timestamp,
                $interaction,
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

        $max_interaction_age = apply_filters(
            self::$namespace . '/captcha/max-interaction-age',
            3600000,
        );

        if ($timestamp - $interaction > $max_interaction_age) {
            self::appendStatistics(
                'interaction-data-expired',
                $timestamp,
                $interaction,
                $data['token'],
                $captcha_token,
                $captcha_token_generation_timestamp,
                [
                    'interaction_age' => $timestamp - $interaction,
                    'interaction_max_age' => $max_interaction_age,
                ],
            );

            return false;
        }

        $max_token_age = apply_filters(
            self::$namespace . '/captcha/max-token-age',
            3600000,
        );

        if ($timestamp - $captcha_token_generation_timestamp > $max_token_age) {
            self::appendStatistics(
                'expired-token',
                $timestamp,
                $interaction,
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
            case 'token-invalid':
                return __('Token invalid.', 'lexocaptcha');
            case 'token-user-agent-mismatch':
                return __('Token does not match the requesting browser.', 'lexocaptcha');
            case 'token-ip-mismatch':
                return __('Token does not match the requesting IP address.', 'lexocaptcha');
            case 'interaction-data-missing':
                return __('Interaction data missing.', 'lexocaptcha');
            case 'interaction-data-invalid':
                return __('Interaction data invalid.', 'lexocaptcha');
            case 'invalid-referer-host':
                return __('Missing referer or invalid referer host.', 'lexocaptcha');
            case 'invalid-payload':
                return __('Captcha payload is malformed.', 'lexocaptcha');
        }

        return $reason;
    }
}
