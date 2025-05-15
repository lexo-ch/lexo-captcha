<?php

namespace LEXO\Captcha\Core\Plugin;

final class StatisticsPage
{
    private function __construct()
    {
        //
    }

    public static function add_page() {
        add_submenu_page(
            'lexoseo',
            'Captcha Statistics',
            'Captcha Statistics',
            'administrator',
            'lexo_captcha_stats',
            [StatisticsPage::class, 'content'],
        );
    }

    public static function content()
    {
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
                Captcha Statistics
            </h1>
            
            <p>
                Caught Spam:
                
                <b>
                    <?= esc_html($caught_spam) ?>
                </b>
            </p>

            <p>
                Total Evaluations:
                
                <b>
                    <?= esc_html($total_evaluations) ?>
                </b>
            </p>

            <?php

            if ($caught_spam && $total_evaluations) {
                ?>

                <p>
                    Spam Quota:
                    
                    <b>
                        <?= esc_html(($caught_spam / $total_evaluations) * 100) ?>%
                    </b>
                </p>

                <?php
            }

            if (!empty($statistics)) {
                ?>

                <h1>
                    Spam Log
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
                        Reason:
                        
                        <b>
                            <?= esc_html($statistic_entry->reason) ?>
                        </b>
                    </p>
                    
                    <p>
                        Evaluation Timestamp:
                        
                        <b>
                            <?= esc_html($statistic_entry->timestamp) ?>
                        </b>
                    </p>
                    
                    <p>
                        User-Agent:
                        
                        <b>
                            <?= esc_html($statistic_entry->user_agent ?? '-') ?>
                        </b>
                    </p>
                    
                    <p>
                        Referer:
                        
                        <b>
                            <?= esc_html($statistic_entry->referer ?? '-') ?>
                        </b>
                    </p>
                    
                    <p>
                        First Client Interaction Timestamp:
                        
                        <b>
                            <?= esc_html($statistic_entry->interaction_timestamp ?? '-') ?>
                        </b>
                    </p>
                    
                    <p>
                        Token Given by Client:
                        
                        <b>
                            <?= esc_html($statistic_entry->given_token ?? '-') ?>
                        </b>
                    </p>
                    
                    <p>
                        Expected Token:
                        
                        <b>
                            <?= esc_html($statistic_entry->expected_token ?? '-') ?>
                        </b>
                    </p>
                    
                    <p>
                        Token Generation Timestamp:
                        
                        <b>
                            <?= esc_html($statistic_entry->token_generation_timestamp ?? '-') ?>
                        </b>
                    </p>

                    <?php

                    if (isset($statistic_entry->additional_data) && !empty($statistic_entry->additional_data)) {
                        ?>

                        <h3>
                            Additional Data
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
