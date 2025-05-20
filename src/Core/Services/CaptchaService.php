<?php

namespace LEXO\Captcha\Core\Services;

final class CaptchaService {
    private function __construct()
    {
        //
    }

    public static function valid_referer() {
        if (empty($_SERVER['SERVER_NAME'])) {
            return false;
        }

        if (empty($_SERVER['HTTP_REFERER'])) {
            return false;
        }

        preg_match('/[^.]+\.[^.]+$/m', $_SERVER['SERVER_NAME'], $server_host);

        $server_host = $server_host[0];

        preg_match('/[^.]+\.[^.]+$/m', parse_url($_SERVER['HTTP_REFERER'])['host'], $client_host);

        $client_host = $client_host[0];

        if ($server_host !== $client_host) {
            return false;
        }

        return true;
    }

    public static function request_token() {
        if (!self::valid_referer()) {
            if (isset($_SESSION['LEXO_CAPTCHA_TOKEN'])) {
                unset($_SESSION['LEXO_CAPTCHA_TOKEN']);
            }

            if (isset($_SESSION['LEXO_CAPTCHA_TOKEN_GENERATION_TIMESTAMP'])) {
                unset($_SESSION['LEXO_CAPTCHA_TOKEN_GENERATION_TIMESTAMP']);
            }

            exit;
        }

        $_SESSION['LEXO_CAPTCHA_TOKEN_GENERATION_TIMESTAMP'] = self::get_timestamp();

        echo $_SESSION['LEXO_CAPTCHA_TOKEN'] = hash('SHA256', $_SESSION['LEXO_CAPTCHA_TOKEN_GENERATION_TIMESTAMP']);

        exit;
    }

    public static function add_ajax_routes() {
        add_action(
            'wp_ajax_lexo_captcha_request_token',
            [self::class, 'request_token'],
        );

        add_action(
            'wp_ajax_nopriv_lexo_captcha_request_token',
            [self::class, 'request_token'],
        ); // for guest users
    }

    public static function get_timestamp() {
        return floor(microtime(true) * 1000);
    }

    public static function append_statistics($reason, $timestamp, $interaction, $given_token, $expected_token, $token_generation_timestamp, $additional_data = []) {
        $statistics = json_decode(get_option(
            'lexo_captcha_statistics',
            '[]',
        ));

        if (!is_array($statistics)) {
            $statistics = [];
        }

        date_default_timezone_set('Europe/Zurich');

        array_unshift(
            $statistics,
            [
                'ip' => $_SERVER['REMOTE_ADDR'] ?? null,
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null,
                'referer' => $_SERVER['HTTP_REFERER'] ?? null,
                'date' => date('Y/m/d H:i:s'),
                'reason' => $reason,
                'timestamp' => $timestamp,
                'interaction_timestamp' => $interaction,
                'given_token' => $given_token,
                'expected_token' => $expected_token,
                'token_generation_timestamp' => $token_generation_timestamp,
                'additional_data' => $additional_data,
            ],
        );

        update_option(
            'lexo_captcha_statistics',
            json_encode($statistics),
        );
    }

    public static function evaluate_data(?string $data = null) {
        if (empty($data)) {
            $data = $_POST['lexo_captcha_data'];
        }

        $data = json_decode(stripslashes($data), true);

        $timestamp = self::get_timestamp();

        $evaluations = get_option(
            'lexo_captcha_evaluations',
            0,
        );

        update_option(
            'lexo_captcha_evaluations',
            $evaluations + 1,
        );

        if (!self::valid_referer()) {
            self::append_statistics(
                'invalid-referer-host',
                $timestamp,
                $data['interacted'] ?? null,
                $data['token'] ?? null,
                $_SESSION['LEXO_CAPTCHA_TOKEN'] ?? null,
                $_SESSION['LEXO_CAPTCHA_TOKEN_GENERATION_TIMESTAMP'] ?? null,
                [
                    'server_name' => $_SERVER['SERVER_NAME'] ?? null,
                ]
            );

            return false;
        } 

        if (!isset($data['interacted'])) {
            self::append_statistics(
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
            self::append_statistics(
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
            self::append_statistics(
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
            self::append_statistics(
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
            CoreService::filter('client-timestamp-tolerance'),
            300000,
        );

        if ($data['interacted'] > $timestamp + $timestamp_tolerance) {
            self::append_statistics(
                'interaction-data-future',
                $timestamp,
                $data['interacted'],
                $data['token'],
                $captcha_token,
                $captcha_token_generation_timestamp,
            );

            return false;
        }

        // 15000ms = 15s

        $submit_cooldown = apply_filters(
            CoreService::filter('submit-cooldown'),
            15000,
        );
        
        // Form sent within 15 seconds since token generation.
        if ($timestamp - $captcha_token_generation_timestamp < $submit_cooldown) {
            self::append_statistics(
                'early-submit',
                $timestamp,
                $data['interacted'],
                $data['token'],
                $captcha_token,
                $captcha_token_generation_timestamp,
                [
                    'remaining_time' => $timestamp - $captcha_token_generation_timestamp,
                    'submit_cooldown' => $submit_cooldown,
                ],
            );

            return false;
        }

        // 3600000ms = 1h

        $max_interaction_age = apply_filters(
            CoreService::filter('max-interaction-age'),
            3600000,
        );

        // First interacted over an hour ago.
        if ($timestamp - $data['interacted'] > $max_interaction_age) {
            self::append_statistics(
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
            CoreService::filter('max-token-age'),
            3600000,
        );

        // Token generated over an hour ago.
        if ($timestamp - $captcha_token_generation_timestamp > $max_token_age) {
            self::append_statistics(
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

    public static function describe_reason($reason) {
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