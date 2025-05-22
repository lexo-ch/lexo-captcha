<?php

namespace LEXO\Captcha\Core\Services;

use LEXO\Captcha\Core;
use LEXO\Captcha\Core\Updater;
use LEXO\Captcha\Core\Pages\StatisticsPage;

final class CoreService {
    private function __construct() {
        //
    }

    public static function can_manage_plugin(): bool {
        if (!is_user_logged_in()) {
            return false;
        }

        return current_user_can(Core::BASE_CAPABILITY);
    }

    public static function action(string $name): string {
        return trailingslashit(Core::$domain) . $name;
    }

    public static function filter(string $name): string {
        return trailingslashit(Core::$domain) . $name;
    }

    public static function add_pages(): void {
        StatisticsPage::add_page();

        self::add_plugin_action(
            Updater::update_check_url(),
            __('Update Check', 'lexocaptcha'),
        );

        self::add_plugin_action(
            StatisticsPage::url(),
            __('Statistics', 'lexocaptcha'),
        );
    }

    public static function add_plugin_action(string $url, string $title): void {
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

    public static function slug(string $name): string {
        return Core::$plugin_slug . "-{$name}";
    }
}
