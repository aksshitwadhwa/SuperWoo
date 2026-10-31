<?php
defined('ABSPATH') || exit;
// Prioritize free delivery until WooCommerce confirms it is unlocked. Then
// hand the progress card to the next configured cart offer.
$delivery_pending = !empty($delivery) && empty($delivery['applied']);
$delivery_progress = $delivery_pending && !empty($delivery['target'])
    ? max(0, min(100, ((float) ($delivery['current'] ?? 0) / (float) $delivery['target']) * 100))
    : null;
?>
<div class="superwoo-cart-progress-group">
    <div class="superwoo-cart-progress-group__delivery">
        <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M3 5h12v4h4l3 4v5h-2a3 3 0 0 1-6 0H9a3 3 0 0 1-6 0H1V7a2 2 0 0 1 2-2Zm12 6v4h5v-1l-2-3h-3ZM6 16a1 1 0 1 0 0 2 1 1 0 0 0 0-2Zm11 0a1 1 0 1 0 0 2 1 1 0 0 0 0-2Z"/></svg>
        <span><?php echo esc_html($delivery['message']); ?></span>
    </div>
    <?php if (!$delivery_pending) : ?>
        <?php echo $offer_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
    <?php endif; ?>
    <?php if (null !== $delivery_progress) : ?>
        <div class="superwoo-bundle-progress" role="progressbar" aria-label="<?php esc_attr_e('Free shipping progress', 'superwoo'); ?>" aria-valuemin="0" aria-valuemax="<?php echo esc_attr((float) $delivery['target']); ?>" aria-valuenow="<?php echo esc_attr(min((float) $delivery['current'], (float) $delivery['target'])); ?>">
            <span class="superwoo-bundle-progress__fill" style="width:<?php echo esc_attr($delivery_progress); ?>%;"></span>
            <div class="superwoo-bundle-progress__labels">
                <span><?php echo wp_kses_post(superwoo_format_selected_currency_amount($delivery['current'])); ?></span>
                <span>/</span>
                <span><?php echo wp_kses_post(superwoo_format_selected_currency_amount($delivery['target'])); ?></span>
            </div>
        </div>
    <?php endif; ?>
</div>
