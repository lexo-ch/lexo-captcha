<?php

namespace LEXO\Captcha\Core;

use stdClass;
use LEXO\Captcha\Core;
use LEXO\Captcha\Core\Services\CoreService;

final class Updater {
    const CACHE = true;

    public static function setup(): void {
        add_filter(
            'plugins_api',
            [self::class, 'info'],
            20,
            3,
        );

        add_filter(
            'site_transient_update_plugins',
            [self::class, 'update'],
        );

        add_action(
            'upgrader_process_complete',
            [self::class, 'purge'],
            10,
            2,
        );
    }

    public static function request() {
        $remote = get_transient(Core::$cache_key);

        if (empty($remote) || !self::CACHE) {
            $remote = self::get_remote_data();

            if ($remote === false) {
                return false;
            }

            set_transient(Core::$cache_key, $remote, HOUR_IN_SECONDS);
        }

        $remote = json_decode(wp_remote_retrieve_body($remote));

        return $remote;
    }

    public static function get_remote_data() {
        $remote_args = [
            'timeout' => 10,
            'headers' => [
                'Accept' => 'application/json',
            ],
        ];

        $remote = wp_remote_get(Core::$update_path, $remote_args);

        if (is_wp_error($remote)) {
            return false;
        }

        if (200 !== wp_remote_retrieve_response_code($remote)) {
            return false;
        }

        if (empty(wp_remote_retrieve_body($remote))) {
            return false;
        }

        return $remote;
    }

    public static function info($res, $action, $args) {
        if ($action !== 'plugin_information') {
            return $res;
        }

        if (Core::$plugin_slug !== $args->slug) {
            return $res;
        }

        $remote = self::request();

        if (empty($remote)) {
            return $res;
        }

        $sections = [
            'description'   => $remote->sections->description,
            'changelog'     => $remote->sections->changelog
        ];

        $sections = apply_filters(
            CoreService::filter('plugin_sections'),
            $sections,
        );

        $res = new stdClass();

        $res->name              = $remote->name;
        $res->slug              = $remote->slug;
        $res->version           = $remote->version;
        $res->tested            = $remote->tested;
        $res->requires          = $remote->requires;
        $res->author            = $remote->author;
        $res->author_profile    = $remote->author_profile;
        $res->download_link     = $remote->download_url;
        $res->trunk             = $remote->download_url;
        $res->requires_php      = $remote->requires_php;
        $res->donate_link       = $remote->donate_link;
        $res->sections          = $sections;

        // $res->last_updated   = $remote->last_updated;

        if (!empty($remote->banners)) {
            $res->banners = [
                'low'   => $remote->banners->low,
                'high'  => $remote->banners->high
            ];
        }


        // in case you want the screenshots tab, use the following HTML format for its content:
        // <ol><li><a href="IMG_URL" target="_blank"><img src="IMG_URL" alt="CAPTION" /></a><p>CAPTION</p></li></ol>
        if (!empty($remote->sections->screenshots)) {
            $res->sections['screenshots'] = $remote->sections->screenshots;
        }

        return $res;
    }

    public static function update($transient) {
        if (empty($transient->checked)) {
            return $transient;
        }

        $remote = self::request();

        if (empty($remote)) {
            return $transient;
        }

        if (!version_compare(Core::$version, $remote->version, '<')) {
            return $transient;
        }

        if (!version_compare($remote->requires, get_bloginfo('version'), '<')) {
            return $transient;
        }

        if (!version_compare($remote->requires_php, PHP_VERSION, '<')) {
            return $transient;
        }

        $res = new stdClass();

        $res->slug          = $remote->slug;
        $res->plugin        = Core::$basename;
        $res->new_version   = $remote->version;
        $res->tested        = $remote->tested;
        $res->package       = $remote->download_url;

        $transient->response[$res->plugin] = $res;

        return $transient;
    }

    public static function purge($upgrader, $options) {
        if (!self::CACHE) {
            return;
        }

        if ($options['action'] !== 'update') {
            return;
        }

        if ($options['type'] !== 'plugin') {
            return;
        }

        delete_transient(Core::$cache_key);
    }

    public static function has_new_update() {
        $remote = self::get_remote_data();

        if (empty($remote)) {
            return false;
        }

        $remote = json_decode(wp_remote_retrieve_body($remote));

        if (empty($remote)) {
            return false;
        }

        if (!version_compare(Core::$version, $remote->version, '<')) {
            return false;
        }

        if (!version_compare($remote->requires, get_bloginfo('version'), '<')) {
            return false;
        }

        if (!version_compare($remote->requires_php, PHP_VERSION, '<')) {
            return false;
        }

        return true;
    }

    public static function update_check_url() {
        return add_query_arg(
            [
                'action' => CoreService::$check_update,
                'nonce' => wp_create_nonce(CoreService::$check_update)
            ],
            admin_url('admin-post.php')
        );
    }
}
