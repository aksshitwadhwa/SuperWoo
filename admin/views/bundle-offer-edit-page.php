<?php
defined('ABSPATH') || exit;

$is_new = 'new' === $action;
$i = 0;
$field_prefix = 'pbi_bundle_rule';
$show_remove_button = false;
?>
<div class="wrap superwoo-admin-page">
    <h1><?php echo esc_html($is_new ? __('Create Offer', 'superwoo') : __('Edit Offer', 'superwoo')); ?></h1>
    <p><a href="<?php echo esc_url(admin_url('admin.php?page=superwoo-bundle-offers')); ?>"><?php esc_html_e('Back to Offers', 'superwoo'); ?></a></p>

    <form class="superwoo-offer-edit-form" data-superwoo-offer-form>
        <?php include SUPERWOO_PATH . 'admin/views/bundle-rule-row.php'; ?>

        <p class="submit">
            <button type="submit" class="button button-primary" data-superwoo-save-offer><?php esc_html_e('Save Offer', 'superwoo'); ?></button>
            <a class="button" href="<?php echo esc_url(admin_url('admin.php?page=superwoo-bundle-offers')); ?>"><?php esc_html_e('Back to Offers', 'superwoo'); ?></a>
        </p>
        <div class="superwoo-ajax-status" data-superwoo-offer-status role="status" aria-live="polite"></div>
    </form>
</div>
