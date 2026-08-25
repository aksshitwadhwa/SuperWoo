<?php defined('ABSPATH') || exit; ?>
<div class="superwoo-faqs">
    <h2 class="superwoo-faqs-heading"><?php esc_html_e('Frequently Asked Questions', 'superwoo'); ?></h2>
    <div class="superwoo-faqs-accordion">
        <?php foreach ($faqs as $index => $faq) : ?>
            <?php
            $question = isset($faq['question']) ? $faq['question'] : '';
            $answer = isset($faq['answer']) ? $faq['answer'] : '';
            if ('' === $question) {
                continue;
            }
            $is_first = 0 === (int) $index;
            ?>
            <div class="superwoo-faq-item<?php echo $is_first ? ' is-open' : ''; ?>">
                <button type="button" class="superwoo-faq-question" aria-expanded="<?php echo $is_first ? 'true' : 'false'; ?>">
                    <span><?php echo esc_html($question); ?></span>
                    <span class="superwoo-faq-icon" aria-hidden="true"></span>
                </button>
                <div class="superwoo-faq-answer" <?php echo $is_first ? '' : 'style="max-height:0;"'; ?>>
                    <div class="superwoo-faq-answer-inner"><?php echo wpautop(wp_kses_post($answer)); ?></div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</div>
