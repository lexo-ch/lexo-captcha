<?php

namespace LEXO\Captcha\Core\Plugin;

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
        if (!CaptchaService::valid_referer()) {
            if (isset($_SESSION['LEXO_CAPTCHA_TOKEN'])) {
                unset($_SESSION['LEXO_CAPTCHA_TOKEN']);
            }

            if (isset($_SESSION['LEXO_CAPTCHA_TOKEN_GENERATION_TIMESTAMP'])) {
                unset($_SESSION['LEXO_CAPTCHA_TOKEN_GENERATION_TIMESTAMP']);
            }

            exit;
        }

        $_SESSION['LEXO_CAPTCHA_TOKEN_GENERATION_TIMESTAMP'] = CaptchaService::get_timestamp();

        echo $_SESSION['LEXO_CAPTCHA_TOKEN'] = hash('SHA256', $_SESSION['LEXO_CAPTCHA_TOKEN_GENERATION_TIMESTAMP']);

        exit;
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

    public static function evaluate_data($data) {
        $data = json_decode(stripslashes($data), true);

        $timestamp = CaptchaService::get_timestamp();

        $evaluations = get_option(
            'lexo_captcha_evaluations',
            0,
        );

        update_option(
            'lexo_captcha_evaluations',
            $evaluations + 1,
        );

        if (!CaptchaService::valid_referer()) {
            CaptchaService::append_statistics(
                'Missing or invalid referer host.',
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
            CaptchaService::append_statistics(
                'Missing interaction data',
                $timestamp,
                null,
                $data['token'] ?? null,
                $_SESSION['LEXO_CAPTCHA_TOKEN'] ?? null,
                $_SESSION['LEXO_CAPTCHA_TOKEN_GENERATION_TIMESTAMP'] ?? null,
            );

            return false;
        }

        if (!isset($data['token'])) {
            CaptchaService::append_statistics(
                'Missing token',
                $timestamp,
                $data['interacted'],
                null,
                $_SESSION['LEXO_CAPTCHA_TOKEN'] ?? null,
                $_SESSION['LEXO_CAPTCHA_TOKEN_GENERATION_TIMESTAMP'] ?? null,
            );

            return false;
        }

        if (empty($_SESSION['LEXO_CAPTCHA_TOKEN']) || !isset($_SESSION['LEXO_CAPTCHA_TOKEN_GENERATION_TIMESTAMP'])) {
            CaptchaService::append_statistics(
                'No token requested',
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
            CaptchaService::append_statistics(
                'Invalid token',
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
            PluginService::filter('client-timestamp-tolerance'),
            300000,
        );

        if ($data['interacted'] > $timestamp + $timestamp_tolerance) {
            CaptchaService::append_statistics(
                'Interaction data from the future, likely faked',
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
            PluginService::filter('submit-cooldown'),
            15000,
        );
        
        // Form sent within 15 seconds since token generation.
        if ($timestamp - $captcha_token_generation_timestamp < $submit_cooldown) {
            CaptchaService::append_statistics(
                'Submit too early',
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
            PluginService::filter('max-interaction-age'),
            3600000,
        );

        // First interacted over an hour ago.
        if ($timestamp - $data['interacted'] > $max_interaction_age) {
            CaptchaService::append_statistics(
                'Interaction data too old',
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
            PluginService::filter('max-token-age'),
            3600000,
        );

        // Token generated over an hour ago.
        if ($timestamp - $captcha_token_generation_timestamp > $max_token_age) {
            CaptchaService::append_statistics(
                'Expired token',
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
}