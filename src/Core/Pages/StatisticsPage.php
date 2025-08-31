<?php

namespace LEXO\Captcha\Core\Pages;

use LEXO\Captcha\Core\PluginService;

use const LEXO\Captcha\{
    DOMAIN
};

final class StatisticsPage
{
    public static function getDateFormat(): string
    {
        $date_format = 'd.m.Y H:i:s';
        return apply_filters(DOMAIN . '/statistics-page/date-format', $date_format);
    }

    public static function maybeFormatDate(string $date): string
    {
        $strtotime = strtotime($date);

        if ($strtotime === false) {
            return $date;
        }

        return date(self::getDateFormat(), $strtotime);
    }

    public static function content(): void
    {
        $statistics = json_decode(get_option(
            'lexo_captcha_statistics',
            '[]',
        ));

        if (!is_array($statistics)) {
            $statistics = [];
        }

        $caught_spam = count($statistics);

        $total_evaluations = get_option('lexo_captcha_evaluations', $caught_spam); ?>

        <div class="wrap">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>

            <p>
                <?php echo esc_html(__('Caught Spam:', 'lexocaptcha')); ?>
                <b><?php echo esc_html($caught_spam); ?></b>
            </p>

            <p>
                <?php echo esc_html(__('Total Evaluations:', 'lexocaptcha')); ?>
                <b><?php echo esc_html($total_evaluations); ?></b>
            </p>

            <?php if ($caught_spam && $total_evaluations) { ?>
                <p>
                    <?php echo esc_html(__('Spam Quota:', 'lexocaptcha')); ?>
                    <b><?php echo esc_html(number_format(($caught_spam / $total_evaluations) * 100, 3)); ?>%</b>
                </p>
            <?php }

            if (!empty($statistics)) { ?>
                <h1><?php echo esc_html(__('Spam Log', 'lexocaptcha')); ?></h1>

                <?php foreach ($statistics as $statistic_entry) { ?>
                    <hr>

                    <h2>
                        <?php echo esc_html($statistic_entry->ip ?? '???'); ?>
                        (<i><?php echo esc_html(self::maybeFormatDate($statistic_entry->date)); ?><i>)
                    </h2>

                    <p>
                        <?php echo esc_html(__('Reason:', 'lexocaptcha')); ?>
                        <b><?php echo esc_html(PluginService::describeReason($statistic_entry->reason)); ?></b>
                    </p>

                    <p>
                        <?php echo esc_html(__('Evaluation Timestamp:', 'lexocaptcha')); ?>
                        <b><?php echo esc_html($statistic_entry->timestamp); ?></b>
                    </p>

                    <p>
                        <?php echo esc_html(__('User-Agent:', 'lexocaptcha')); ?>
                        <b><?php echo esc_html($statistic_entry->user_agent ?? '-'); ?></b>
                    </p>

                    <p>
                        <?php echo esc_html(__('Referer:', 'lexocaptcha')); ?>
                        <b><?php echo esc_html($statistic_entry->referer ?? '-'); ?></b>
                    </p>

                    <p>
                        <?php echo esc_html(__('First Client Interaction Timestamp:', 'lexocaptcha')) ?>
                        <b><?php echo esc_html($statistic_entry->interaction_timestamp ?? '-'); ?></b>
                    </p>

                    <p>
                        <?php echo esc_html(__('Token Given by Client:', 'lexocaptcha')); ?>
                        <b><?php echo esc_html($statistic_entry->given_token ?? '-'); ?></b>
                    </p>

                    <p>
                        <?php echo esc_html(__('Expected Token:', 'lexocaptcha')); ?>
                        <b><?php echo esc_html($statistic_entry->expected_token ?? '-'); ?></b>
                    </p>

                    <p>
                        <?php echo esc_html(__('Token Generation Timestamp:', 'lexocaptcha')); ?>
                        <b><?php echo esc_html($statistic_entry->token_generation_timestamp ?? '-'); ?></b>
                    </p>

                    <?php
                    if (
                        isset($statistic_entry->additional_data)
                        && !empty($statistic_entry->additional_data)
                    ) { ?>
                        <h3>
                            <?php echo esc_html(__('Additional Data', 'lexocaptcha')); ?>
                        </h3>

                        <?php foreach ($statistic_entry->additional_data as $data_key => $data_value) { ?>
                            <p>
                                <?php echo esc_html($data_key); ?>:
                                <b><?php echo esc_html($data_value ?? '-'); ?></b>
                            </p>
                        <?php }
                    }
                }
            } ?>
        </div>
    <?php }
}
