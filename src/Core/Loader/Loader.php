<?php

namespace LEXO\Captcha\Core\Loader;

use LEXO\Captcha\Core\Loader\Manifest;

final class Loader
{
    public static ?string $hook = null;

    public static ?string $context = null;

    public static array $namespaces = [];

    public static function registerNamespace(string $namespace, array $data)
    {
        Loader::$namespaces[$namespace] = [
            'assets'   => $data['assets'],
            'priority' => $data['priority'] ?? 50,
            'manifest' => new Manifest(
                $data['dist_path'] . '/mix-manifest.json',
                $data['dist_uri'],
                $data['dist_path']
            ),
        ];
    }

    public static function run()
    {
        foreach (Loader::$namespaces as $namespace => $data) {
            add_action(Loader::$hook, function () use ($namespace, $data) {
                Loader::loadStyles(
                    $namespace,
                    $data['manifest'],
                    $data['assets'][self::$context]['styles']
                );

                Loader::loadScripts(
                    $namespace,
                    $data['manifest'],
                    $data['assets'][self::$context]['scripts']
                );
            }, $data['priority']);
        }
    }

    public static function loadStyles(string $namespace, Manifest $manifest, array $assets)
    {
        $load_styles = apply_filters("{$namespace}/load_styles", true);

        if (!$load_styles) {
            return;
        }

        foreach ($assets as $style) {
            $style = "css/{$style}";

            $basename = basename($style);
            $handler  = "{$namespace}/{$basename}";

            if (!apply_filters("{$namespace}/enqueue/{$basename}", true)) {
                continue;
            }

            $version = Loader::getVersionFromManifest($manifest, $style);

            wp_register_style(
                $handler,
                $manifest->getUri($style),
                [],
                $version
            );
            wp_enqueue_style($handler);

            add_filter('style_loader_src', function ($src, $handle) use ($handler, $version) {
                if ($handle !== $handler) {
                    return $src;
                }

                return add_query_arg(
                    [
                        'ver' => $version
                    ],
                    $src
                );
            }, PHP_INT_MAX, 2);

            add_filter('style_loader_tag', function ($html, $handle, $href, $media) use ($handler, $version) {
                if ($handle !== $handler) {
                    return $html;
                }

                ob_start();
                
                ?>

                <link
                    rel="preload"
                    href="<?= $href ?>"
                    as="style"
                    id="<?= esc_attr($handle) ?>"
                    media="<?= esc_attr($media) ?>"
                    onload="this.onload=null;this.rel='stylesheet'"
                >
                <noscript><?= trim($html) ?></noscript>

                <?php
                
                return ob_get_clean();
            }, PHP_INT_MAX, 4);
        }
    }

    public static function loadScripts(string $namespace, Manifest $manifest, array $assets)
    {
        $load_scripts = apply_filters("{$namespace}/load_scripts", true);

        if (!$load_scripts) {
            return;
        }

        foreach ($assets as $script) {
            $script = "js/{$script}";

            $basename = basename($script);

            $handler  = "{$namespace}/{$basename}";

            if (!apply_filters("{$namespace}/enqueue/{$basename}", true)) {
                continue;
            }

            $version = Loader::getVersionFromManifest($manifest, $script);

            wp_register_script(
                $handler,
                $manifest->getUri($script),
                [],
                $version,
                [
                    'strategy'  => 'defer',
                    'in_footer' => false
                ]
            );

            do_action("{$namespace}/localize/$basename");

            wp_enqueue_script($handler);

            add_filter('script_loader_src', function ($src, $handle) use ($handler, $version) {
                if ($handle !== $handler) {
                    return $src;
                }

                return add_query_arg(
                    [
                        'ver' => $version
                    ],
                    $src
                );
            }, PHP_INT_MAX, 2);
        }
    }

    public static function getUri(string $namespace, string $asset): string
    {
        return Loader::$namespaces[$namespace]['manifest']->getUri($asset);
    }

    public static function getPath(string $namespace, string $asset): string
    {
        return Loader::$namespaces[$namespace]['manifest']->getPath($asset);
    }

    public static function getVersionFromManifest(Manifest $manifest, string $file): string
    {
        return explode('?id=', $manifest->manifest["/{$file}"])[1] ?? 'generic-1.0.0';
    }
}

Loader::$hook = !is_admin() ? 'wp_enqueue_scripts' : 'admin_enqueue_scripts';

Loader::$context = !is_admin() ? 'front' : 'admin';

add_action(Loader::$hook, function() {
    Loader::run();
}, -1);
