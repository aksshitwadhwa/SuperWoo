<?php
defined('ABSPATH') || exit;

$min_qty = absint($tier['min_qty'] ?? ($tier['qty'] ?? 0));
$max_qty = absint($tier['max_qty'] ?? 0);
$discount = isset($tier['discount']) ? $tier['discount'] : '';
$free_product_id = absint($tier['free_product_id'] ?? 0);
$free_product = $free_product_id ? wc_get_product($free_product_id) : null;
$free_product_label = $free_product ? sprintf('%1$s (#%2$s)', $free_product->get_name(), $free_product_id) : '';
?>
<div class="superwoo-tier-row">
    <label><?php esc_html_e('Qty from', 'superwoo'); ?></label>
    <input type="number" min="1" step="1" name="pbi_bundle_rules[<?php echo esc_attr($rule_index); ?>][tiers][<?php echo esc_attr($tier_index); ?>][min_qty]" value="<?php echo esc_attr($min_qty); ?>" placeholder="2">
    <label><?php esc_html_e('to', 'superwoo'); ?></label>
    <input type="number" min="0" step="1" name="pbi_bundle_rules[<?php echo esc_attr($rule_index); ?>][tiers][<?php echo esc_attr($tier_index); ?>][max_qty]" value="<?php echo esc_attr($max_qty); ?>" placeholder="<?php esc_attr_e('No limit', 'superwoo'); ?>">
    <label><?php esc_html_e('Save', 'superwoo'); ?></label>
    <input type="number" min="0" max="100" step="0.01" name="pbi_bundle_rules[<?php echo esc_attr($rule_index); ?>][tiers][<?php echo esc_attr($tier_index); ?>][discount]" value="<?php echo esc_attr($discount); ?>" placeholder="10">
    <span>%</span>
    <label><?php esc_html_e('Free product', 'superwoo'); ?></label>
    <select class="wc-product-search superwoo-free-product-search" name="pbi_bundle_rules[<?php echo esc_attr($rule_index); ?>][tiers][<?php echo esc_attr($tier_index); ?>][free_product_id]" data-placeholder="<?php esc_attr_e('Search product...', 'superwoo'); ?>" data-action="woocommerce_json_search_products_and_variations" data-allow_clear="true">
        <?php if ($free_product) : ?>
            <option value="<?php echo esc_attr($free_product_id); ?>" selected><?php echo esc_html($free_product_label); ?></option>
        <?php endif; ?>
    </select>
    <button type="button" class="button superwoo-remove-tier"><?php esc_html_e('Remove', 'superwoo'); ?></button>
</div>
