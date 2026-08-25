<?php defined('ABSPATH') || exit; ?>
<div class="superwoo-faq-row">
    <label><?php esc_html_e('Question', 'superwoo'); ?></label>
    <input type="text" name="product_faqs[<?php echo esc_attr($i); ?>][question]" value="<?php echo esc_attr($question); ?>" placeholder="<?php esc_attr_e('e.g. Is this product safe for daily use?', 'superwoo'); ?>">
    <label><?php esc_html_e('Answer', 'superwoo'); ?></label>
    <textarea name="product_faqs[<?php echo esc_attr($i); ?>][answer]" placeholder="<?php esc_attr_e('Write the answer here...', 'superwoo'); ?>"><?php echo esc_textarea($answer); ?></textarea>
    <button type="button" class="button superwoo-remove-faq-row"><?php esc_html_e('Remove FAQ', 'superwoo'); ?></button>
</div>
