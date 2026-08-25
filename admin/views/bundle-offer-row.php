<?php
defined('ABSPATH') || exit;

$offer_id = $rule['id'] ?? '';
$edit_url = admin_url('admin.php?page=superwoo-bundle-offers&action=edit&offer=' . rawurlencode($offer_id));
$type_label = 'price_gift' === ($rule['offer_type'] ?? '') ? __('Price range free products', 'superwoo') : __('Flat product discount', 'superwoo');
$scope_label = __('Specific products', 'superwoo');

if ('global' === ($rule['applies_to'] ?? '')) {
    $scope_label = __('Whole store', 'superwoo');
} elseif ('category' === ($rule['applies_to'] ?? '')) {
    $term = get_term(absint($rule['category_id'] ?? 0), 'product_cat');
    $scope_label = $term && !is_wp_error($term) ? sprintf(__('Category: %s', 'superwoo'), $term->name) : __('Product category', 'superwoo');
}
?>
<tr data-superwoo-offer-row data-offer-id="<?php echo esc_attr($offer_id); ?>">
    <td>
        <strong><a href="<?php echo esc_url($edit_url); ?>" data-superwoo-edit-offer><?php echo esc_html($this->get_offer_title($rule)); ?></a></strong>
        <div class="row-actions">
            <span class="edit"><a href="<?php echo esc_url($edit_url); ?>" data-superwoo-edit-offer><?php esc_html_e('Edit', 'superwoo'); ?></a></span>
        </div>
    </td>
    <td><?php echo esc_html($type_label); ?></td>
    <td><?php echo esc_html($scope_label); ?></td>
    <td>
        <label class="superwoo-list-toggle">
            <input type="checkbox" data-superwoo-toggle-offer <?php checked(!empty($rule['enabled'])); ?>>
            <span><?php echo !empty($rule['enabled']) ? esc_html__('Enabled', 'superwoo') : esc_html__('Disabled', 'superwoo'); ?></span>
        </label>
    </td>
    <td>
        <a class="button" href="<?php echo esc_url($edit_url); ?>" data-superwoo-edit-offer><?php esc_html_e('Edit', 'superwoo'); ?></a>
        <button type="button" class="button button-link-delete" data-superwoo-delete-offer><?php esc_html_e('Delete', 'superwoo'); ?></button>
    </td>
</tr>
