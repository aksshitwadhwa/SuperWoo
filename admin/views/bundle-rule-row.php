<?php
defined('ABSPATH') || exit;

$offer_type = isset($rule['offer_type']) && 'price_gift' === $rule['offer_type'] ? 'price_gift' : 'product_discount';
$enabled = !empty($rule['enabled']);
$applies_to = isset($rule['applies_to']) && in_array($rule['applies_to'], ['global', 'category', 'products'], true) ? $rule['applies_to'] : 'products';
$category_id = absint($rule['category_id'] ?? 0);
$product_ids = isset($rule['product_ids']) && is_array($rule['product_ids']) ? array_map('absint', $rule['product_ids']) : [];
$min_qty = isset($rule['min_qty']) ? absint($rule['min_qty']) : '';
$discount = isset($rule['discount']) ? $rule['discount'] : '';
$min_amount = isset($rule['min_amount']) ? $rule['min_amount'] : '';
$max_amount = isset($rule['max_amount']) ? $rule['max_amount'] : '';
$free_product_ids = isset($rule['free_product_ids']) && is_array($rule['free_product_ids']) ? array_map('absint', $rule['free_product_ids']) : [];
if (empty($free_product_ids) && !empty($rule['free_product_id'])) {
    $free_product_ids = [absint($rule['free_product_id'])];
}
$field_prefix = isset($field_prefix) ? $field_prefix : 'pbi_bundle_rules[' . $i . ']';
$show_remove_button = isset($show_remove_button) ? (bool) $show_remove_button : true;
?>
<section class="superwoo-offer-card" data-rule-index="<?php echo esc_attr($i); ?>" data-offer-type="<?php echo esc_attr($offer_type); ?>" data-applies-to="<?php echo esc_attr($applies_to); ?>">
    <input type="hidden" name="<?php echo esc_attr($field_prefix); ?>[id]" value="<?php echo esc_attr($rule['id'] ?? ''); ?>">
    <div class="superwoo-offer-card__top">
        <label class="superwoo-switch">
            <input type="checkbox" name="<?php echo esc_attr($field_prefix); ?>[enabled]" value="1" <?php checked($enabled); ?>>
            <span><?php esc_html_e('Enabled', 'superwoo'); ?></span>
        </label>

        <label class="superwoo-offer-type">
            <span><?php esc_html_e('Offer type', 'superwoo'); ?></span>
            <select class="superwoo-offer-type-select" name="<?php echo esc_attr($field_prefix); ?>[offer_type]">
                <option value="product_discount" <?php selected($offer_type, 'product_discount'); ?>><?php esc_html_e('Flat product discount', 'superwoo'); ?></option>
                <option value="price_gift" <?php selected($offer_type, 'price_gift'); ?>><?php esc_html_e('Price range free product', 'superwoo'); ?></option>
            </select>
        </label>

        <?php if ($show_remove_button) : ?>
            <button type="button" class="button superwoo-remove-rule"><?php esc_html_e('Remove Offer', 'superwoo'); ?></button>
        <?php endif; ?>
    </div>

    <div class="superwoo-offer-scope">
        <div class="superwoo-field-grid">
            <label class="superwoo-field superwoo-field--wide">
                <span><?php esc_html_e('Offer title', 'superwoo'); ?></span>
                <input type="text" name="<?php echo esc_attr($field_prefix); ?>[title]" value="<?php echo esc_attr($rule['title'] ?? ''); ?>" placeholder="<?php esc_attr_e('Summer free gift, Buy 2 save 10%, etc.', 'superwoo'); ?>">
            </label>

            <label class="superwoo-field">
                <span><?php esc_html_e('Applies to', 'superwoo'); ?></span>
                <select class="superwoo-applies-to-select" name="<?php echo esc_attr($field_prefix); ?>[applies_to]">
                    <option value="global" <?php selected($applies_to, 'global'); ?>><?php esc_html_e('Whole store', 'superwoo'); ?></option>
                    <option value="category" <?php selected($applies_to, 'category'); ?>><?php esc_html_e('Product category', 'superwoo'); ?></option>
                    <option value="products" <?php selected($applies_to, 'products'); ?>><?php esc_html_e('Specific products', 'superwoo'); ?></option>
                </select>
            </label>

            <label class="superwoo-field superwoo-category-field">
                <span><?php esc_html_e('Category', 'superwoo'); ?></span>
                <select name="<?php echo esc_attr($field_prefix); ?>[category_id]">
                    <option value=""><?php esc_html_e('Select category', 'superwoo'); ?></option>
                    <?php foreach ($categories as $cat) : ?>
                        <option value="<?php echo esc_attr($cat->term_id); ?>" <?php selected($category_id, $cat->term_id); ?>><?php echo esc_html($cat->name); ?></option>
                    <?php endforeach; ?>
                </select>
            </label>

            <label class="superwoo-field superwoo-field--wide superwoo-products-field">
                <span><?php esc_html_e('Products', 'superwoo'); ?></span>
                <select class="superwoo-product-image-search superwoo-product-search" multiple="multiple" name="<?php echo esc_attr($field_prefix); ?>[product_ids][]" data-placeholder="<?php esc_attr_e('Search products...', 'superwoo'); ?>" data-allow_clear="true">
                    <?php foreach ($product_ids as $product_id) : ?>
                        <?php $selected_product = wc_get_product($product_id); ?>
                        <?php if ($selected_product) : ?>
                            <option value="<?php echo esc_attr($product_id); ?>" data-image="<?php echo esc_url($this->get_product_image_url($selected_product)); ?>" data-price="<?php echo esc_attr($this->get_product_price_label($selected_product)); ?>" selected><?php echo esc_html($this->get_product_search_name($selected_product)); ?></option>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </select>
                <small><?php esc_html_e('Used only when Applies to is Specific products.', 'superwoo'); ?></small>
            </label>
        </div>
    </div>

    <div class="superwoo-offer-panel superwoo-offer-panel--product">
        <h2><?php esc_html_e('Flat Product Discount', 'superwoo'); ?></h2>
        <p><?php esc_html_e('Set the minimum quantity and percentage discount. The offer applies to the selected scope above.', 'superwoo'); ?></p>

        <div class="superwoo-field-grid">
            <label class="superwoo-field">
                <span><?php esc_html_e('Minimum quantity', 'superwoo'); ?></span>
                <input type="number" min="1" step="1" name="<?php echo esc_attr($field_prefix); ?>[min_qty]" value="<?php echo esc_attr($min_qty); ?>" placeholder="2">
            </label>

            <label class="superwoo-field">
                <span><?php esc_html_e('Discount (%)', 'superwoo'); ?></span>
                <input type="number" min="0" max="100" step="0.01" name="<?php echo esc_attr($field_prefix); ?>[discount]" value="<?php echo esc_attr($discount); ?>" placeholder="10">
            </label>
        </div>
    </div>

    <div class="superwoo-offer-panel superwoo-offer-panel--gift">
        <h2><?php esc_html_e('Price Range Free Product', 'superwoo'); ?></h2>
        <p><?php esc_html_e('When the selected scope subtotal is inside this range, SuperWoo auto-adds the free product and shows a notice.', 'superwoo'); ?></p>

        <div class="superwoo-field-grid">
            <label class="superwoo-field">
                <span><?php esc_html_e('Subtotal from', 'superwoo'); ?></span>
                <input type="number" min="0" step="0.01" name="<?php echo esc_attr($field_prefix); ?>[min_amount]" value="<?php echo esc_attr($min_amount); ?>" placeholder="500">
            </label>

            <label class="superwoo-field">
                <span><?php esc_html_e('Subtotal to', 'superwoo'); ?></span>
                <input type="number" min="0" step="0.01" name="<?php echo esc_attr($field_prefix); ?>[max_amount]" value="<?php echo esc_attr($max_amount); ?>" placeholder="<?php esc_attr_e('No limit', 'superwoo'); ?>">
            </label>

            <label class="superwoo-field superwoo-field--wide">
                <span><?php esc_html_e('Free products', 'superwoo'); ?></span>
                <select class="superwoo-product-image-search superwoo-free-product-search" multiple="multiple" name="<?php echo esc_attr($field_prefix); ?>[free_product_ids][]" data-placeholder="<?php esc_attr_e('Search free products...', 'superwoo'); ?>" data-allow_clear="true">
                    <?php foreach ($free_product_ids as $free_product_id) : ?>
                        <?php $free_product = wc_get_product($free_product_id); ?>
                        <?php if ($free_product) : ?>
                            <option value="<?php echo esc_attr($free_product_id); ?>" data-image="<?php echo esc_url($this->get_product_image_url($free_product)); ?>" data-price="<?php echo esc_attr($this->get_product_price_label($free_product)); ?>" selected><?php echo esc_html($this->get_product_search_name($free_product)); ?></option>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </select>
                <small><?php esc_html_e('Choose one or more products to add free when this offer is unlocked.', 'superwoo'); ?></small>
            </label>
        </div>
    </div>
</section>
