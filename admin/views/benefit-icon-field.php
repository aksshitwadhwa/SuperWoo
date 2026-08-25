<?php defined('ABSPATH') || exit; ?>
<div class="form-field superwoo-term-icon-field">
    <label for="superwoo-term-icon-id"><?php esc_html_e('Benefit Icon', 'superwoo'); ?></label>
    <div class="superwoo-term-icon-preview">
        <img src="" alt="" style="display:none;">
    </div>
    <input type="hidden" name="benefit_icon" id="superwoo-term-icon-id" value="">
    <p>
        <button type="button" class="button" id="superwoo-term-select-icon"><?php esc_html_e('Select Icon', 'superwoo'); ?></button>
        <button type="button" class="button" id="superwoo-term-remove-icon" style="display:none;"><?php esc_html_e('Remove Icon', 'superwoo'); ?></button>
    </p>
</div>
