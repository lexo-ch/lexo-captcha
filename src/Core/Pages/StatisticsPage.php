<?php

namespace LEXO\Captcha\Core\Pages;

use LEXO\Captcha\Core\Pages\Page;
use LEXO\Captcha\Core\Services\CaptchaService;

final class StatisticsPage extends Page
{
    public static function base_slug() {
        return 'statistics';
    }

    public static function title() {
        return __('LEXO Captcha Statistics', 'lexocaptcha');
    }

    public static function content() {
        $statistics = json_decode(get_option(
            'lexo_captcha_statistics',
            '[]',
        ));

        if (!is_array($statistics)) {
            $statistics = [];
        }

        $caught_spam = count($statistics);

        $total_evaluations = get_option('lexo_captcha_evaluations', $caught_spam);

        ?>

        <div class="wrap">
            <h1>
                <?= esc_html(__('Captcha Statistics', 'lexocaptcha')) ?>
            </h1>
            
            <p>
                <?= esc_html(__('Caught Spam:', 'lexocaptcha')) ?>
                
                <b>
                    <?= esc_html($caught_spam) ?>
                </b>
            </p>

            <p>
                <?= esc_html(__('Total Evaluations:', 'lexocaptcha')) ?>
                
                <b>
                    <?= esc_html($total_evaluations) ?>
                </b>
            </p>

            <?php

            if ($caught_spam && $total_evaluations) {
                ?>

                <p>
                    <?= esc_html(__('Spam Quota:', 'lexocaptcha')) ?>
                    
                    <b>
                        <?= esc_html(($caught_spam / $total_evaluations) * 100) ?>%
                    </b>
                </p>

                <?php
            }

            if (!empty($statistics)) {
                ?>

                <h1>
                    <?= esc_html(__('Spam Log', 'lexocaptcha')) ?>
                </h1>

                <?php

                foreach ($statistics as $statistic_entry) {
                    ?>

                    <hr>

                    <h2>
                        <?= esc_html($statistic_entry->ip ?? '???') ?>
                        
                        (<i>
                            <?= esc_html($statistic_entry->date) ?>
                        <i>)
                    </h2>

                    <p>
                        <?= esc_html(__('Reason:', 'lexocaptcha')) ?>
                        
                        <b>
                            <?= esc_html(CaptchaService::describe_reason($statistic_entry->reason)) ?>
                        </b>
                    </p>
                    
                    <p>
                        <?= esc_html(__('Evaluation Timestamp:', 'lexocaptcha')) ?>
                        
                        <b>
                            <?= esc_html($statistic_entry->timestamp) ?>
                        </b>
                    </p>
                    
                    <p>
                        <?= esc_html(__('User-Agent:', 'lexocaptcha')) ?>
                        
                        <b>
                            <?= esc_html($statistic_entry->user_agent ?? '-') ?>
                        </b>
                    </p>
                    
                    <p>
                        <?= esc_html(__('Referer:', 'lexocaptcha')) ?>
                        
                        <b>
                            <?= esc_html($statistic_entry->referer ?? '-') ?>
                        </b>
                    </p>
                    
                    <p>
                        <?= esc_html(__('First Client Interaction Timestamp:', 'lexocaptcha')) ?>
                        
                        <b>
                            <?= esc_html($statistic_entry->interaction_timestamp ?? '-') ?>
                        </b>
                    </p>
                    
                    <p>
                        <?= esc_html(__('Token Given by Client:', 'lexocaptcha')) ?>
                        
                        <b>
                            <?= esc_html($statistic_entry->given_token ?? '-') ?>
                        </b>
                    </p>
                    
                    <p>
                        <?= esc_html(__('Expected Token:', 'lexocaptcha')) ?>
                        
                        <b>
                            <?= esc_html($statistic_entry->expected_token ?? '-') ?>
                        </b>
                    </p>
                    
                    <p>
                        <?= esc_html(__('Token Generation Timestamp:', 'lexocaptcha')) ?>
                        
                        <b>
                            <?= esc_html($statistic_entry->token_generation_timestamp ?? '-') ?>
                        </b>
                    </p>

                    <?php

                    if (isset($statistic_entry->additional_data) && !empty($statistic_entry->additional_data)) {
                        ?>

                        <h3>
                            <?= esc_html(__('Additional Data', 'lexocaptcha')) ?>
                        </h3>

                        <?php

                        foreach ($statistic_entry->additional_data as $data_key => $data_value) {
                            ?>

                            <p>
                                <?= esc_html($data_key) ?>:
                                
                                <b>
                                    <?= esc_html($data_value ?? '-') ?>
                                </b>
                            </p>

                            <?php
                        }
                    }
                }
            }

            ?>
        </div>

        <?php
    }
}
