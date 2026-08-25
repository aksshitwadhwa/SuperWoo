<?php
defined('ABSPATH') || exit;

$i = 0;
$field_prefix = 'pbi_bundle_rule';
$show_remove_button = false;
?>
<h2><?php echo esc_html($editor_title); ?></h2>
<form class="superwoo-offer-edit-form" data-superwoo-offer-form data-superwoo-inline-offer-form>
    <?php include SUPERWOO_PATH . 'admin/views/bundle-rule-row.php'; ?>

    <p class="submit">
        <button type="submit" class="button button-primary" data-superwoo-save-offer><?php esc_html_e('Save Offer', 'superwoo'); ?></button>
        <?php if (!empty($rule['id'])) : ?>
            <button type="button" class="button button-link-delete" data-superwoo-delete-current-offer><?php esc_html_e('Delete Offer', 'superwoo'); ?></button>
        <?php endif; ?>
        <button type="button" class="button" data-superwoo-cancel-new-offer><?php esc_html_e('Close', 'superwoo'); ?></button>
    </p>
    <div class="superwoo-ajax-status" data-superwoo-offer-status role="status" aria-live="polite"></div>
</form>
