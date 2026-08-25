<?php
defined('ABSPATH') || exit;

if (empty($rule['offer_type']) || !WC()->cart) {
    return;
}

if ('product_discount' === $rule['offer_type']) {
    $qty = $discount->get_cart_qty_for_offer_rule($cart, $rule);
    $min_qty = absint($rule['min_qty'] ?? 0);
    $discount_amount = (float) ($rule['discount'] ?? 0);

    if ($qty <= 0 || $min_qty <= 0 || $discount_amount <= 0) {
        return;
    }

    if ($qty < $min_qty) {
        $remaining = $min_qty - $qty;
        ?>
        <div class="superwoo-bundle-notice superwoo-bundle-notice--discount">
            <div class="superwoo-bundle-notice__top">
                <span class="superwoo-bundle-notice__icon" aria-hidden="true">
                    <svg viewBox="0 0 24 24" focusable="false"><path d="M20.6 13.1 12.1 21.6a2 2 0 0 1-2.8 0l-6.9-6.9a2 2 0 0 1-.6-1.4V4a2 2 0 0 1 2-2h9.3a2 2 0 0 1 1.4.6l6.1 6.1a3.1 3.1 0 0 1 0 4.4ZM7 8.5A1.5 1.5 0 1 0 7 5a1.5 1.5 0 0 0 0 3Z"/></svg>
                </span>
                <div>
                    <?php
                    printf(
                        wp_kses_post(__('Add <strong>%1$s</strong> more eligible product(s) to get %2$s%% off.', 'superwoo')),
                        esc_html($remaining),
                        esc_html($discount_amount)
                    );
                    ?>
                </div>
                <strong><?php echo esc_html(sprintf(__('Buy %1$s+', 'superwoo'), $min_qty)); ?></strong>
            </div>
            <div class="superwoo-bundle-progress">
                <span class="superwoo-bundle-progress__fill" style="width:<?php echo esc_attr(max(0, min(100, ($qty / $min_qty) * 100))); ?>%;"></span>
                <div class="superwoo-bundle-progress__labels">
                    <span class="superwoo-bundle-progress__current"><?php echo esc_html($qty); ?></span>
                    <span class="superwoo-bundle-progress__separator">/</span>
                    <span class="superwoo-bundle-progress__target"><?php echo esc_html($min_qty); ?></span>
                </div>
            </div>
        </div>
        <?php
        return;
    }
    ?>
    <div class="superwoo-bundle-notice superwoo-bundle-notice--success">
        <?php
        printf(
            esc_html__('You unlocked %s%% off eligible products.', 'superwoo'),
            esc_html($discount_amount)
        );
        ?>
    </div>
    <?php
    return;
}

if ('price_gift' === $rule['offer_type']) {
    $subtotal = $discount->get_cart_subtotal_excluding_gifts($cart, $rule);
    $min = (float) ($rule['min_amount'] ?? 0);
    $max = (float) ($rule['max_amount'] ?? 0);
    $gift_names = [];
    foreach ($discount->get_free_product_ids($rule) as $free_product_id) {
        $gift = wc_get_product($free_product_id);
        if ($gift) {
            $gift_names[] = $gift->get_name();
        }
    }

    if (empty($gift_names)) {
        return;
    }

    $gift_label = implode(', ', $gift_names);

    if ($subtotal < $min) {
        $remaining = $min - $subtotal;
        ?>
        <div class="superwoo-bundle-notice superwoo-bundle-notice--gift">
            <div class="superwoo-bundle-notice__top">
                <span class="superwoo-bundle-notice__icon" aria-hidden="true">
                    <svg viewBox="0 0 24 24" focusable="false"><path d="M20.6 13.1 12.1 21.6a2 2 0 0 1-2.8 0l-6.9-6.9a2 2 0 0 1-.6-1.4V4a2 2 0 0 1 2-2h9.3a2 2 0 0 1 1.4.6l6.1 6.1a3.1 3.1 0 0 1 0 4.4ZM7 8.5A1.5 1.5 0 1 0 7 5a1.5 1.5 0 0 0 0 3Z"/></svg>
                </span>
                <div>
                    <?php
                    printf(
                        wp_kses_post(__('Add <strong>%1$s</strong> more to unlock<br><span>%2$s <strong>Free</strong>.</span>', 'superwoo')),
                        wp_kses_post(superwoo_format_selected_currency_amount($remaining)),
                        esc_html($gift_label)
                    );
                    ?>
                </div>
                <strong><?php echo wp_kses_post(superwoo_format_selected_currency_amount($min)); ?>+</strong>
            </div>
            <div class="superwoo-bundle-progress">
                <span class="superwoo-bundle-progress__fill" style="width:<?php echo esc_attr($min > 0 ? max(0, min(100, ($subtotal / $min) * 100)) : 100); ?>%;"></span>
                <div class="superwoo-bundle-progress__labels">
                    <span class="superwoo-bundle-progress__current"><?php echo wp_kses_post(superwoo_format_selected_currency_amount($subtotal)); ?></span>
                    <span class="superwoo-bundle-progress__separator">/</span>
                    <span class="superwoo-bundle-progress__target"><?php echo wp_kses_post(superwoo_format_selected_currency_amount($min)); ?></span>
                </div>
            </div>
        </div>
        <?php
        return;
    }

    if ($max > 0 && $subtotal > $max) {
        return;
    }
    ?>
    <div class="superwoo-bundle-notice superwoo-bundle-notice--success">
        <?php
        printf(
            esc_html__('%s has been added as your free gift.', 'superwoo'),
            esc_html($gift_label)
        );
        ?>
    </div>
    <?php
}
