<?php defined('ABSPATH') || exit; ?>
<tr class="form-field superwoo-term-icon-field">
    <th scope="row"><label for="superwoo-term-icon-id"><?php esc_html_e('Benefit Icon', 'superwoo'); ?></label></th>
    <td>
        <div class="superwoo-term-icon-preview">
            <img src="<?php echo esc_url($icon_url); ?>" alt="" <?php echo $icon_url ? '' : 'style="display:none;"'; ?>>
        </div>
        <input type="hidden" name="benefit_icon" id="superwoo-term-icon-id" value="<?php echo esc_attr($icon_id); ?>">
        <button type="button" class="button" id="superwoo-term-select-icon"><?php esc_html_e('Select Icon', 'superwoo'); ?></button>
        <button type="button" class="button" id="superwoo-term-remove-icon" <?php echo $icon_url ? '' : 'style="display:none;"'; ?>><?php esc_html_e('Remove Icon', 'superwoo'); ?></button>
    </td>
</tr>
