<?php defined('ABSPATH') || exit; ?>
<div class="superwoo-cart-drawer__header">
    <div class="superwoo-cart-title">
        <span class="superwoo-cart-title__icon" aria-hidden="true"><svg viewBox="0 0 24 24" focusable="false"><path d="M2 3h2l2.2 12.2A2.2 2.2 0 0 0 8.4 17h9.8a2.2 2.2 0 0 0 2.1-1.6L22 8H6M9 21a1.5 1.5 0 1 0 0-3 1.5 1.5 0 0 0 0 3Zm8 0a1.5 1.5 0 1 0 0-3 1.5 1.5 0 0 0 0 3Z"/></svg></span>
        <strong><?php esc_html_e('Your Cart', 'superwoo'); ?></strong>
        <span class="superwoo-cart-count"><?php echo esc_html(superwoo_cart_count()); ?></span>
    </div>
    <button type="button" class="superwoo-cart-close" data-superwoo-close-cart aria-label="<?php esc_attr_e('Close cart', 'superwoo'); ?>">×</button>
</div>

<div class="superwoo-cart-live" aria-live="polite"></div>
<div class="superwoo-cart-error" data-superwoo-cart-error role="alert" hidden></div>

<?php if ($cart->is_empty()) : ?>
    <div class="superwoo-cart-empty">
        <span class="superwoo-cart-empty__icon" aria-hidden="true"><svg viewBox="0 0 48 48" focusable="false"><path d="M5 7h5l5 25h22l6-18H13M18 39a2.5 2.5 0 1 0 0-5 2.5 2.5 0 0 0 0 5Zm17 0a2.5 2.5 0 1 0 0-5 2.5 2.5 0 0 0 0 5Z"/></svg></span>
        <h3><?php esc_html_e('Your cart is waiting!', 'superwoo'); ?></h3>
        <p><?php esc_html_e('Explore the store and find something you will love.', 'superwoo'); ?></p>
        <a class="superwoo-cart-primary" href="<?php echo esc_url(wc_get_page_permalink('shop')); ?>"><?php esc_html_e('Continue Shopping', 'superwoo'); ?></a>
    </div>
<?php else : ?>
    <div class="superwoo-cart-scroll" data-superwoo-cart-scroll>
        <?php $cart_notice_message = $drawer->get_cart_notice_message(); ?>
        <?php if ($cart_notice_message) : ?>
            <div class="superwoo-cart-message">
                <span class="superwoo-cart-message__icon" aria-hidden="true">
                    <svg viewBox="0 0 24 24" focusable="false">
                        <path d="M3 6.75A1.75 1.75 0 0 1 4.75 5h9.5A1.75 1.75 0 0 1 16 6.75V9h2.2c.53 0 1.04.24 1.37.66l1.8 2.25c.25.31.38.7.38 1.1v3.24c0 .97-.78 1.75-1.75 1.75h-.34a2.75 2.75 0 0 1-5.32 0H9.66a2.75 2.75 0 0 1-5.32 0H4.25A1.75 1.75 0 0 1 2.5 16.25v-9.5H3Zm13 3.75v5.75h.34a2.75 2.75 0 0 1 5.32 0H20a.25.25 0 0 0 .25-.25v-2.99a.25.25 0 0 0-.05-.16l-1.8-2.25a.25.25 0 0 0-.2-.1H16ZM5 17.25a1.25 1.25 0 1 0 2.5 0 1.25 1.25 0 0 0-2.5 0Zm11.5 0a1.25 1.25 0 1 0 2.5 0 1.25 1.25 0 0 0-2.5 0Z"/>
                    </svg>
                </span>
                <span><?php echo esc_html($cart_notice_message); ?></span>
            </div>
        <?php endif; ?>

        <?php echo superwoo_cart_offer_notices_html(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>

        <div class="superwoo-cart-items">
            <?php foreach ($cart->get_cart() as $cart_item_key => $cart_item) : ?>
                <?php
                $product = isset($cart_item['data']) ? $cart_item['data'] : null;
                if (!$product || !($product instanceof WC_Product)) {
                    continue;
                }

                $product_id = absint($cart_item['product_id']);
                $permalink = $product->is_visible() ? $product->get_permalink($cart_item) : '';
                $is_free_gift = !empty($cart_item['superwoo_free_gift']);
                $quantity = max(1, absint($cart_item['quantity'] ?? 1));
                $line_subtotal = superwoo_cart_item_subtotal_amount($cart_item, $cart->display_prices_including_tax());
                $unit_price = (float) wc_get_price_to_display($product, ['price' => (float) $product->get_price('edit')]);
                $regular_price = (float) $product->get_regular_price('edit');

                if (function_exists('superwoo_currency') && superwoo_currency()->is_enabled()) {
                    $regular_price = superwoo_currency()->convert_product_price($product, $regular_price);
                }
                $regular_unit_price = $regular_price > 0 ? (float) wc_get_price_to_display($product, ['price' => $regular_price]) : 0;
                $show_sale_price = !$is_free_gift && $product->is_on_sale() && $regular_unit_price > $unit_price;
                $sale_percent = $show_sale_price && $regular_unit_price > 0
                    ? (int) round((($regular_unit_price - $unit_price) / $regular_unit_price) * 100)
                    : 0;
                $payable_line_total = isset($cart_item['line_total'])
                    ? (float) $cart_item['line_total'] + ($cart->display_prices_including_tax() ? (float) ($cart_item['line_tax'] ?? 0) : 0)
                    : $unit_price * $quantity;
                ?>
                <div class="superwoo-cart-item"
                     data-cart-item-key="<?php echo esc_attr($cart_item_key); ?>"
                     data-product-id="<?php echo esc_attr($product_id); ?>"
                     data-variation-id="<?php echo esc_attr(absint($cart_item['variation_id'] ?? 0)); ?>">
                    <div class="superwoo-cart-item__image">
                        <?php echo $product->get_image('woocommerce_thumbnail'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                    </div>
                    <div class="superwoo-cart-item__content">
                        <div class="superwoo-cart-item__name">
                            <?php if ($permalink) : ?>
                                <a href="<?php echo esc_url($permalink); ?>"><?php echo esc_html($product->get_name()); ?></a>
                            <?php else : ?>
                                <?php echo esc_html($product->get_name()); ?>
                            <?php endif; ?>
                        </div>
                        <div class="superwoo-cart-item__meta">
                            <?php echo wc_get_formatted_cart_item_data($cart_item); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                        </div>
                        <div class="superwoo-cart-item__price">
                            <?php if ($is_free_gift) : ?>
                                <?php if ($line_subtotal > 0) : ?><del class="superwoo-cart-item__gift-worth"><?php echo wp_kses_post(wc_price($unit_price)); ?></del><?php endif; ?>
                                <span class="superwoo-free-gift-badge"><strong><?php esc_html_e('Free', 'superwoo'); ?></strong></span>
                            <?php else : ?>
                                <?php if ($show_sale_price) : ?><del><?php echo wp_kses_post(wc_price($regular_unit_price)); ?></del><?php endif; ?>
                                <strong><?php echo wp_kses_post(wc_price($unit_price)); ?></strong>
                                <?php if ($show_sale_price && !empty($settings['show_discount_percentage']) && $sale_percent > 0) : ?><span class="superwoo-cart-item__sale-badge"><?php echo esc_html(sprintf(__('%d%% off', 'superwoo'), $sale_percent)); ?></span><?php endif; ?>
                                <span class="superwoo-cart-item__price-label"><?php echo esc_html(sprintf(_n('%d item', '%d items', $quantity, 'superwoo'), $quantity)); ?> · <?php echo wp_kses_post(wc_price($payable_line_total)); ?></span>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="superwoo-cart-item__actions">
                        <?php if ($is_free_gift) : ?>
                            <span class="superwoo-gift-qty"><?php esc_html_e('Qty 1', 'superwoo'); ?></span>
                        <?php else : ?>
                            <div class="superwoo-qty" aria-label="<?php esc_attr_e('Update quantity', 'superwoo'); ?>">
                                <button type="button" data-superwoo-qty-minus aria-label="<?php esc_attr_e('Decrease quantity', 'superwoo'); ?>">−</button>
                                <input type="number" min="1" step="1" value="<?php echo esc_attr(max(1, absint($cart_item['quantity']))); ?>" aria-label="<?php echo esc_attr(sprintf(__('Quantity for %s', 'superwoo'), $product->get_name())); ?>" data-superwoo-qty-committed="<?php echo esc_attr(max(1, absint($cart_item['quantity']))); ?>" data-superwoo-qty-input>
                                <button type="button" data-superwoo-qty-plus aria-label="<?php esc_attr_e('Increase quantity', 'superwoo'); ?>">+</button>
                            </div>
                            <button type="button" class="superwoo-remove-item" data-superwoo-remove-item aria-label="<?php esc_attr_e('Remove item', 'superwoo'); ?>">×</button>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <?php if (function_exists('wc_coupons_enabled') && wc_coupons_enabled() && 'inline' === ($settings['cart_drawer_coupon'] ?? 'checkout_link')) : ?>
            <section class="superwoo-cart-coupon" aria-label="<?php esc_attr_e('Coupon code', 'superwoo'); ?>">
                <details class="superwoo-cart-coupon__details" <?php echo $cart->get_applied_coupons() ? 'open' : ''; ?>>
                    <summary><?php esc_html_e('Have a coupon code?', 'superwoo'); ?></summary>
                    <form class="superwoo-cart-coupon__form" data-superwoo-coupon-form>
                        <label class="screen-reader-text" for="superwoo-cart-coupon-code"><?php esc_html_e('Coupon code', 'superwoo'); ?></label>
                        <input id="superwoo-cart-coupon-code" type="text" name="coupon_code" autocomplete="off" maxlength="100" placeholder="<?php esc_attr_e('Enter coupon code', 'superwoo'); ?>" required>
                        <button type="submit"><?php esc_html_e('Apply', 'superwoo'); ?></button>
                    </form>
                    <p class="superwoo-cart-coupon__feedback" data-superwoo-coupon-feedback role="status" aria-live="polite"></p>
                </details>
                <?php foreach ($cart->get_applied_coupons() as $coupon_code) : ?>
                    <div class="superwoo-cart-coupon__applied">
                        <span><?php echo esc_html($coupon_code); ?></span>
                        <button type="button" data-superwoo-remove-coupon="<?php echo esc_attr($coupon_code); ?>"><?php esc_html_e('Remove', 'superwoo'); ?></button>
                    </div>
                <?php endforeach; ?>
            </section>
        <?php endif; ?>

        <?php if (!empty($crosssell)) : ?>
            <div class="superwoo-cross-sells">
                <div class="superwoo-cross-sells__header">
                    <h3><?php echo esc_html($drawer->get_recommendation_heading($settings['cart_recommendation_heading'] ?? '')); ?></h3>
                    <?php if (count($crosssell) > 1) : ?>
                        <div class="superwoo-cross-sells__controls" aria-label="<?php esc_attr_e('Browse recommended products', 'superwoo'); ?>">
                            <button type="button" class="superwoo-cross-sells__arrow" data-superwoo-cross-sell-nav="previous" aria-controls="superwoo-cross-sells-list" aria-label="<?php esc_attr_e('Previous recommended products', 'superwoo'); ?>">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="m15 18-6-6 6-6"/></svg>
                            </button>
                            <button type="button" class="superwoo-cross-sells__arrow" data-superwoo-cross-sell-nav="next" aria-controls="superwoo-cross-sells-list" aria-label="<?php esc_attr_e('Next recommended products', 'superwoo'); ?>">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="m9 18 6-6-6-6"/></svg>
                            </button>
                        </div>
                    <?php endif; ?>
                </div>
                <div class="superwoo-cross-sells__row" id="superwoo-cross-sells-list" role="region" aria-label="<?php esc_attr_e('Recommended products', 'superwoo'); ?>" tabindex="0">
                    <?php foreach ($crosssell as $product) : ?>
                        <div class="superwoo-cross-sell">
                            <a href="<?php echo esc_url($product->get_permalink()); ?>" class="superwoo-cross-sell__image">
                                <?php echo $product->get_image('woocommerce_thumbnail'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                            </a>
                            <a href="<?php echo esc_url($product->get_permalink()); ?>" class="superwoo-cross-sell__title"><?php echo esc_html($product->get_name()); ?></a>
                            <div class="superwoo-cross-sell__price"><?php echo wp_kses_post($product->get_price_html()); ?></div>
                            <button type="button" class="superwoo-cross-sell__add" data-superwoo-add-cross-sell="<?php echo esc_attr($product->get_id()); ?>"><?php esc_html_e('Add', 'superwoo'); ?></button>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>

        <?php
        $trust_items = !empty($settings['cart_trust_enabled']) && is_array($settings['cart_trust_items'] ?? null)
            ? $settings['cart_trust_items'] : [];
        $trust_paths = [
            'shipping' => 'M3 5h12v4h4l3 4v5h-2a3 3 0 0 1-6 0H9a3 3 0 0 1-6 0H1V7a2 2 0 0 1 2-2Zm12 6v4h5v-1l-2-3h-3ZM6 16a1 1 0 1 0 0 2 1 1 0 0 0 0-2Zm11 0a1 1 0 1 0 0 2 1 1 0 0 0 0-2Z',
            'secure' => 'M12 2 4 5v6c0 5 3.4 9.2 8 11 4.6-1.8 8-6 8-11V5l-8-3Zm0 3 5 1.9V11c0 3.7-2.3 7-5 8.4-2.7-1.4-5-4.7-5-8.4V6.9L12 5Zm-1 9.5-2-2-1.4 1.4L11 17l5.5-5.5-1.4-1.4-4.1 4.4Z',
            'returns' => 'M12 5V2L7 6l5 4V7c3.3 0 6 2.7 6 6a6 6 0 0 1-10.4 4l-1.5 1.3A8 8 0 1 0 12 5Z',
            'support' => 'M12 2a9 9 0 0 0-9 9v6a3 3 0 0 0 3 3h2v-8H5v-1a7 7 0 0 1 14 0v1h-3v8h2a3 3 0 0 0 3-3v-6a9 9 0 0 0-9-9Z',
        ];
        $visible_trust_items = [];
        foreach ($trust_items as $trust_key => $trust_item) {
            if (!is_array($trust_item) || empty($trust_item['enabled']) || empty($trust_item['heading']) || empty($trust_item['description'])) {
                continue;
            }
            $visible_trust_items[] = [$trust_key, $trust_item];
        }
        ?>
        <?php if ($visible_trust_items) : ?>
            <div class="superwoo-cart-trust" aria-label="<?php esc_attr_e('Store information', 'superwoo'); ?>">
                <?php foreach ($visible_trust_items as [$trust_key, $trust_item]) : ?>
                    <div class="superwoo-cart-trust__item">
                        <span class="superwoo-cart-trust__icon" aria-hidden="true"><svg viewBox="0 0 24 24" focusable="false"><path d="<?php echo esc_attr($trust_paths[$trust_key] ?? $trust_paths['shipping']); ?>"/></svg></span>
                        <span class="superwoo-cart-trust__copy">
                            <?php if (!empty($trust_item['url'])) : ?><a href="<?php echo esc_url($trust_item['url']); ?>" rel="nofollow"><?php endif; ?>
                            <strong><?php echo esc_html($trust_item['heading']); ?></strong>
                            <small><?php echo esc_html($trust_item['description']); ?></small>
                            <?php if (!empty($trust_item['url'])) : ?></a><?php endif; ?>
                        </span>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <div class="superwoo-cart-drawer__footer">
        <?php echo superwoo_cart_total_html(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
        <div class="superwoo-privacy-consent">
            <input type="checkbox" id="superwoo-privacy-consent" data-superwoo-privacy-consent required aria-describedby="superwoo-privacy-consent-error">
            <label for="superwoo-privacy-consent">
                <?php esc_html_e('By proceeding to checkout, you acknowledge our', 'superwoo'); ?>
                <a href="<?php echo esc_url(home_url('/privacy-policy/')); ?>"><?php esc_html_e('Privacy Policy', 'superwoo'); ?></a>
                <?php esc_html_e('and agree to the processing of your personal information for order processing, payment, delivery, and customer support.', 'superwoo'); ?>
            </label>
        </div>
        <p class="superwoo-privacy-consent__error" id="superwoo-privacy-consent-error" role="alert" hidden><?php esc_html_e('Please acknowledge the Privacy Policy to continue.', 'superwoo'); ?></p>
        <?php echo superwoo_cart_primary_button_html(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
    </div>
<?php endif; ?>
