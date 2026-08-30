<?php defined('ABSPATH') || exit; ?>
<div class="superwoo-cart-drawer__header">
    <div class="superwoo-cart-title">
        <strong><?php esc_html_e('Your Cart', 'superwoo'); ?></strong>
        <span class="superwoo-cart-count"><?php echo esc_html(superwoo_cart_count()); ?></span>
    </div>
    <button type="button" class="superwoo-cart-close" data-superwoo-close-cart aria-label="<?php esc_attr_e('Close cart', 'superwoo'); ?>">×</button>
</div>

<div class="superwoo-cart-live" aria-live="polite"></div>

<?php if ($cart->is_empty()) : ?>
    <div class="superwoo-cart-empty">
        <h3><?php esc_html_e('Your cart is empty', 'superwoo'); ?></h3>
        <p><?php esc_html_e('Add a product to start shopping.', 'superwoo'); ?></p>
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
                ?>
                <div class="superwoo-cart-item" data-cart-item-key="<?php echo esc_attr($cart_item_key); ?>">
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
                        <div class="superwoo-cart-item__price"><?php echo wp_kses_post($cart->get_product_price($product)); ?></div>
                        <?php if ($is_free_gift) : ?>
                            <div class="superwoo-free-gift-badge"><strong><?php esc_html_e('Free', 'superwoo'); ?></strong> <?php esc_html_e('Gift', 'superwoo'); ?></div>
                        <?php endif; ?>
                    </div>
                    <div class="superwoo-cart-item__actions">
                        <?php if ($is_free_gift) : ?>
                            <span class="superwoo-gift-qty"><?php esc_html_e('Qty 1', 'superwoo'); ?></span>
                        <?php else : ?>
                            <div class="superwoo-qty" aria-label="<?php esc_attr_e('Update quantity', 'superwoo'); ?>">
                                <button type="button" data-superwoo-qty-minus aria-label="<?php esc_attr_e('Decrease quantity', 'superwoo'); ?>">−</button>
                                <input type="number" min="1" step="1" value="<?php echo esc_attr(max(1, absint($cart_item['quantity']))); ?>" data-superwoo-qty-input>
                                <button type="button" data-superwoo-qty-plus aria-label="<?php esc_attr_e('Increase quantity', 'superwoo'); ?>">+</button>
                            </div>
                            <button type="button" class="superwoo-remove-item" data-superwoo-remove-item aria-label="<?php esc_attr_e('Remove item', 'superwoo'); ?>">×</button>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <?php if (!empty($crosssell)) : ?>
            <div class="superwoo-cross-sells">
                <h3><?php esc_html_e('You may also like', 'superwoo'); ?></h3>
                <div class="superwoo-cross-sells__row">
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
    </div>

    <div class="superwoo-cart-drawer__footer">
        <?php echo superwoo_cart_total_html(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
        <?php echo superwoo_cart_primary_button_html(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
        <?php if (superwoo_razorpay_magic_checkout_available()) : ?>
            <div id="error-message" class="superwoo-cart-primary__error" aria-live="polite"></div>
        <?php endif; ?>
    </div>
<?php endif; ?>
