<?php
defined('ABSPATH') || exit;

$new_rule = $this->get_default_rule();
$free_delivery_threshold = $this->get_free_delivery_threshold();
?>
<div class="wrap superwoo-admin-page">
    <h1 class="wp-heading-inline"><?php esc_html_e('Offers', 'superwoo'); ?></h1>
    <button type="button" class="page-title-action" data-superwoo-show-new-offer><?php esc_html_e('Create New Offer', 'superwoo'); ?></button>
    <hr class="wp-header-end">

    <p><?php esc_html_e('Manage product quantity discounts and cart price-range free product offers.', 'superwoo'); ?></p>

    <?php if (!empty($_GET['settings-updated'])) : ?>
        <div class="notice notice-success is-dismissible">
            <p><?php esc_html_e('Offer settings saved.', 'superwoo'); ?></p>
        </div>
    <?php endif; ?>

    <form class="superwoo-offer-settings" method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
        <?php wp_nonce_field('superwoo_save_offer_settings'); ?>
        <input type="hidden" name="action" value="superwoo_save_offer_settings">
        <h2><?php esc_html_e('Free Delivery Notice', 'superwoo'); ?></h2>
        <p><?php esc_html_e('Set the cart amount that unlocks free delivery. Leave empty or 0 to hide the free-delivery notice from the cart drawer.', 'superwoo'); ?></p>
        <label for="superwoo_free_delivery_threshold"><?php esc_html_e('Free delivery amount', 'superwoo'); ?></label>
        <div class="superwoo-offer-settings__row">
            <input type="number" id="superwoo_free_delivery_threshold" name="superwoo_free_delivery_threshold" min="0" step="0.01" value="<?php echo esc_attr($free_delivery_threshold > 0 ? $free_delivery_threshold : ''); ?>" placeholder="3000">
            <button type="submit" class="button button-primary"><?php esc_html_e('Save Settings', 'superwoo'); ?></button>
        </div>
    </form>

    <div class="superwoo-offers-admin-grid">
        <div class="superwoo-offers-list" data-superwoo-offers-list>
            <table class="widefat fixed striped superwoo-offers-table">
                <thead>
                    <tr>
                        <th><?php esc_html_e('Offer', 'superwoo'); ?></th>
                        <th><?php esc_html_e('Type', 'superwoo'); ?></th>
                        <th><?php esc_html_e('Scope', 'superwoo'); ?></th>
                        <th><?php esc_html_e('Status', 'superwoo'); ?></th>
                        <th><?php esc_html_e('Actions', 'superwoo'); ?></th>
                    </tr>
                </thead>
                <tbody data-superwoo-offer-rows>
                    <?php foreach ($rules as $rule) : ?>
                        <?php include SUPERWOO_PATH . 'admin/views/bundle-offer-row.php'; ?>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <div class="superwoo-empty-state" data-superwoo-empty-state <?php echo empty($rules) ? '' : 'hidden'; ?>>
                <h2><?php esc_html_e('No offers yet', 'superwoo'); ?></h2>
                <p><?php esc_html_e('Create your first offer from the editor on this page.', 'superwoo'); ?></p>
                <button type="button" class="button button-primary" data-superwoo-show-new-offer><?php esc_html_e('Create New Offer', 'superwoo'); ?></button>
            </div>

            <div class="superwoo-ajax-status" data-superwoo-offers-status role="status" aria-live="polite"></div>
        </div>

        <div class="superwoo-inline-offer-editor" data-superwoo-inline-editor hidden>
            <?php
            $rule = $new_rule;
            $editor_title = __('Create Offer', 'superwoo');
            include SUPERWOO_PATH . 'admin/views/bundle-offer-inline-form.php';
            ?>
        </div>
    </div>
</div>
