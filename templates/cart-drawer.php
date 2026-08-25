<?php defined('ABSPATH') || exit; ?>
<div class="superwoo-cart-shell" data-superwoo-cart-shell aria-hidden="true">
    <div class="superwoo-cart-backdrop" data-superwoo-close-cart></div>
    <aside class="superwoo-cart-drawer" role="dialog" aria-modal="true" aria-label="<?php esc_attr_e('Shopping cart', 'superwoo'); ?>" tabindex="-1">
        <div class="superwoo-cart-drawer__inner">
            <?php echo $drawer->get_inner_html(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
        </div>
    </aside>
</div>
<?php if ($drawer->should_render_mobile_bottom_nav()) : ?>
    <div class="superwoo-mobile-search-sheet" data-superwoo-mobile-search-sheet aria-hidden="true">
        <button type="button" class="superwoo-mobile-search-sheet__backdrop" data-superwoo-close-mobile-search aria-label="<?php esc_attr_e('Close search', 'superwoo'); ?>"></button>
        <div class="superwoo-mobile-search-sheet__panel" role="dialog" aria-modal="true" aria-label="<?php esc_attr_e('Search products', 'superwoo'); ?>">
            <form role="search" method="get" action="<?php echo esc_url(home_url('/')); ?>" class="superwoo-mobile-search-form">
                <label class="screen-reader-text" for="superwoo-mobile-search-input"><?php esc_html_e('Search products', 'superwoo'); ?></label>
                <input id="superwoo-mobile-search-input" type="search" name="s" placeholder="<?php esc_attr_e('Search products', 'superwoo'); ?>" data-superwoo-mobile-search-input>
                <input type="hidden" name="post_type" value="product">
                <button type="submit"><?php esc_html_e('Search', 'superwoo'); ?></button>
                <button type="button" class="superwoo-mobile-search-form__close" data-superwoo-close-mobile-search aria-label="<?php esc_attr_e('Close search', 'superwoo'); ?>">×</button>
            </form>
        </div>
    </div>
    <nav class="superwoo-mobile-bottom-nav" aria-label="<?php esc_attr_e('Mobile quick navigation', 'superwoo'); ?>" data-superwoo-mobile-bottom-nav>
        <?php foreach ($drawer->get_mobile_nav_items() as $item) : ?>
            <?php
            $item_classes = 'superwoo-mobile-bottom-nav__item';
            if (!empty($item['active'])) {
                $item_classes .= ' is-active';
            }
            ?>
            <?php if (!empty($item['button']) && 'search' === $item['key']) : ?>
                <button type="button" class="<?php echo esc_attr($item_classes); ?>" data-superwoo-open-mobile-search <?php echo !empty($item['active']) ? 'aria-current="page"' : ''; ?>>
                    <span class="superwoo-mobile-bottom-nav__icon"><?php echo $drawer->mobile_nav_icon($item['key']); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
                    <span class="superwoo-mobile-bottom-nav__label"><?php echo esc_html($item['label']); ?></span>
                </button>
            <?php elseif (!empty($item['button']) && 'cart' === $item['key']) : ?>
                <button type="button" class="<?php echo esc_attr($item_classes); ?>" data-superwoo-open-cart <?php echo !empty($item['active']) ? 'aria-current="page"' : ''; ?>>
                    <span class="superwoo-mobile-bottom-nav__icon">
                        <?php echo $drawer->mobile_nav_icon($item['key']); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                        <span class="superwoo-cart-count superwoo-mobile-bottom-nav__count"><?php echo esc_html(superwoo_cart_count()); ?></span>
                    </span>
                    <span class="superwoo-mobile-bottom-nav__label"><?php echo esc_html($item['label']); ?></span>
                </button>
            <?php else : ?>
                <a class="<?php echo esc_attr($item_classes); ?>" href="<?php echo esc_url($item['url']); ?>" <?php echo !empty($item['active']) ? 'aria-current="page"' : ''; ?>>
                    <span class="superwoo-mobile-bottom-nav__icon"><?php echo $drawer->mobile_nav_icon($item['key']); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
                    <span class="superwoo-mobile-bottom-nav__label"><?php echo esc_html($item['label']); ?></span>
                </a>
            <?php endif; ?>
        <?php endforeach; ?>
    </nav>
<?php endif; ?>
<?php if (is_singular('product')) : ?>
    <div class="superwoo-mobile-buy-now" data-superwoo-mobile-buy-now>
        <div class="superwoo-mobile-buy-now__qty" aria-label="<?php esc_attr_e('Choose quantity', 'superwoo'); ?>">
            <button type="button" data-superwoo-mobile-qty-minus aria-label="<?php esc_attr_e('Decrease quantity', 'superwoo'); ?>">−</button>
            <input type="number" min="1" step="1" value="1" data-superwoo-mobile-qty-input aria-label="<?php esc_attr_e('Quantity', 'superwoo'); ?>">
            <button type="button" data-superwoo-mobile-qty-plus aria-label="<?php esc_attr_e('Increase quantity', 'superwoo'); ?>">+</button>
        </div>
        <button type="button" class="superwoo-mobile-buy-now__button" data-superwoo-sticky-add-to-cart>
            <?php esc_html_e('ADD TO CART', 'superwoo'); ?>
        </button>
    </div>
<?php endif; ?>
