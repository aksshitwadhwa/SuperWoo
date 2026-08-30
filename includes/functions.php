<?php
defined('ABSPATH') || exit;

function superwoo_is_woocommerce_active() {
    return class_exists('WooCommerce') && function_exists('WC');
}

/**
 * Return the initialized WooCommerce cart for a frontend cart request.
 *
 * WooCommerce intentionally does not initialize cart/session objects for every
 * request type (for example, background and some API requests). Callers that
 * only render cart UI must skip rendering when it is unavailable.
 *
 * @return WC_Cart|null
 */
function superwoo_get_cart() {
    if (!superwoo_is_woocommerce_active()) {
        return null;
    }

    $woocommerce = WC();
    if (!$woocommerce || !isset($woocommerce->cart) || !($woocommerce->cart instanceof WC_Cart)) {
        return null;
    }

    return $woocommerce->cart;
}

function superwoo_template($template, $args = []) {
    $path = SUPERWOO_PATH . 'templates/' . ltrim($template, '/');

    if (!file_exists($path)) {
        return '';
    }

    if (!empty($args) && is_array($args)) {
        extract($args, EXTR_SKIP);
    }

    ob_start();
    include $path;
    return ob_get_clean();
}

function superwoo_get_settings() {
    $currencylayer_api_url = 'https://api.currencylayer.com/live?access_key={api_key}&source={base}&currencies={symbols}';
    $defaults = [
        'enable_benefits'       => true,
        'enable_how_to_use'     => true,
        'enable_faqs'           => true,
        'enable_reviews'        => true,
        'enable_variation_cards' => true,
        'enable_bundle_offers'  => true,
        'enable_cart_drawer'    => true,
        'enable_elementor_products_carousel' => false,
        'cart_auto_open'        => true,
        'cart_drawer_crosssell' => true,
        'cart_drawer_coupon'    => 'checkout_link',
        'enable_add_to_cart_diagnostics' => false,
        'enable_logging'        => false,
        'show_discount_percentage' => false,
        'header_cart_icon'      => 'outline-bag',
        'enable_multi_currency' => false,
        'enabled_currency_codes' => ['INR', 'USD', 'EUR'],
        'default_currency'      => 'INR',
        'currency_auto_detect'  => true,
        'exchange_rate_api_url' => $currencylayer_api_url,
        'exchange_rate_api_key' => '',
        'exchange_rate_cache_minutes' => 720,
        'manual_exchange_rates' => [],
    ];

    $settings = get_option('superwoo_settings', []);
    $settings = wp_parse_args(is_array($settings) ? $settings : [], $defaults);
    if (empty($settings['exchange_rate_api_url']) || false !== strpos((string) $settings['exchange_rate_api_url'], 'api.frankfurter.dev')) {
        $settings['exchange_rate_api_url'] = $currencylayer_api_url;
    }

    return $settings;
}

function superwoo_log($message, $context = [], $level = 'info') {
    if (empty(superwoo_get_settings()['enable_logging'])) {
        return;
    }

    $context = is_array($context) ? $context : [];
    $context['plugin_version'] = defined('SUPERWOO_VERSION') ? SUPERWOO_VERSION : '';
    $line = '[' . current_time('mysql') . '] [' . strtoupper($level) . '] ' . (string) $message . ' ' . wp_json_encode($context) . "\n";

    $uploads = wp_upload_dir();
    if (empty($uploads['error']) && !empty($uploads['basedir'])) {
        $log_dir = trailingslashit($uploads['basedir']) . 'superwoo-logs';
        if (wp_mkdir_p($log_dir)) {
            $log_file = trailingslashit($log_dir) . 'superwoo.log';
            file_put_contents($log_file, $line, FILE_APPEND | LOCK_EX); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
        }
    }

    if (function_exists('wc_get_logger')) {
        wc_get_logger()->log($level, (string) $message, ['source' => 'superwoo', 'context' => $context]);
    } else {
        // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Fallback when WooCommerce logging is unavailable.
        error_log('SuperWoo [' . strtoupper($level) . '] ' . $message . ' ' . wp_json_encode($context));
    }
}

function superwoo_log_file_path() {
    $uploads = wp_upload_dir();
    return empty($uploads['error']) && !empty($uploads['basedir']) ? trailingslashit($uploads['basedir']) . 'superwoo-logs/superwoo.log' : '';
}

function superwoo_fatal_log_file_path() {
    return defined('WP_CONTENT_DIR') ? trailingslashit(WP_CONTENT_DIR) . 'superwoo-fatal.log' : '';
}

function superwoo_health_report() {
    $settings = superwoo_get_settings();
    $log_path = superwoo_log_file_path();
    $fatal_path = superwoo_fatal_log_file_path();
    $report = [
        'environment' => [
            ['label' => __('SuperWoo version', 'superwoo'), 'value' => defined('SUPERWOO_VERSION') ? SUPERWOO_VERSION : __('Unknown', 'superwoo'), 'status' => 'ok'],
            ['label' => __('WordPress version', 'superwoo'), 'value' => get_bloginfo('version'), 'status' => 'ok'],
            ['label' => __('WooCommerce', 'superwoo'), 'value' => superwoo_is_woocommerce_active() ? (defined('WC_VERSION') ? WC_VERSION : __('Active', 'superwoo')) : __('Not available', 'superwoo'), 'status' => superwoo_is_woocommerce_active() ? 'ok' : 'warning'],
            ['label' => __('PHP version', 'superwoo'), 'value' => PHP_VERSION, 'status' => version_compare(PHP_VERSION, '7.4', '>=') ? 'ok' : 'warning'],
            ['label' => __('Plugin folder', 'superwoo'), 'value' => defined('SUPERWOO_FILE') ? plugin_basename(SUPERWOO_FILE) : __('Unknown', 'superwoo'), 'status' => (defined('SUPERWOO_FILE') && 'superwoo/superwoo.php' === strtolower(plugin_basename(SUPERWOO_FILE))) ? 'ok' : 'warning'],
        ],
        'diagnostics' => [
            ['label' => __('SuperWoo logging', 'superwoo'), 'value' => !empty($settings['enable_logging']) ? __('Enabled', 'superwoo') : __('Disabled', 'superwoo'), 'status' => !empty($settings['enable_logging']) ? 'ok' : 'neutral'],
            ['label' => __('Application log', 'superwoo'), 'value' => $log_path ? ($log_path . (file_exists($log_path) ? ' (' . size_format(filesize($log_path)) . ')' : '')) : __('Unavailable', 'superwoo'), 'status' => ($log_path && is_dir(dirname($log_path)) && wp_is_writable(dirname($log_path))) ? 'ok' : 'warning'],
            ['label' => __('Fatal error log', 'superwoo'), 'value' => $fatal_path . (file_exists($fatal_path) ? ' (' . size_format(filesize($fatal_path)) . ')' : ''), 'status' => file_exists($fatal_path) ? 'warning' : 'neutral'],
            ['label' => __('WordPress.org updater', 'superwoo'), 'value' => __('Provided by WordPress.org', 'superwoo'), 'status' => 'ok'],
        ],
        'cart' => [],
    ];

    $cart = superwoo_get_cart();
    if (!$cart) {
        $report['cart'][] = ['label' => __('Cart context', 'superwoo'), 'value' => __('Cart is not initialized in this admin request.', 'superwoo'), 'status' => 'neutral'];
        return $report;
    }

    foreach ($cart->get_cart() as $key => $item) {
        $product = isset($item['data']) && $item['data'] instanceof WC_Product ? $item['data'] : null;
        $catalog_product = $product ? wc_get_product($product->get_id()) : null;
        $cart_price = $product ? (float) $product->get_price() : 0;
        $catalog_price = $catalog_product ? (float) $catalog_product->get_price('edit') : 0;
        $status = ($catalog_price > 0 && abs($cart_price - $catalog_price) > 0.01) ? 'warning' : 'ok';
        /* translators: 1: product ID, 2: quantity. */
        $label = sprintf(__('Product #%1$s × %2$s', 'superwoo'), $product ? $product->get_id() : 'n/a', isset($item['quantity']) ? (int) $item['quantity'] : 0);
        /* translators: 1: catalog price, 2: cart price, 3: line total. */
        $value = sprintf(__('Catalog %1$s / Cart %2$s / Line %3$s', 'superwoo'), wc_price($catalog_price), wc_price($cart_price), wc_price((float) ($item['line_total'] ?? 0)));
        $report['cart'][] = ['label' => $label, 'value' => $value, 'status' => $status];
    }
    return $report;
}

function superwoo_format_selected_currency_amount($base_inr_amount) {
    if (function_exists('superwoo_currency') && superwoo_currency()->is_enabled()) {
        return superwoo_currency()->format_amount(superwoo_currency()->convert_amount($base_inr_amount));
    }

    return wc_price($base_inr_amount);
}

function superwoo_cart_count() {
    $cart = superwoo_get_cart();
    if (!$cart) {
        return 0;
    }

    return (int) $cart->get_cart_contents_count();
}


function superwoo_cart_drawer_fragments($calculate_totals = true) {
    $cart = superwoo_get_cart();
    if (!$cart) {
        return [];
    }

    if ($calculate_totals) {
        $cart->calculate_totals();
    }
    if (WC()->session) {
        $cart->set_session();
        WC()->session->set('cart', $cart->get_cart_for_session());
    }

    $cart_drawer = new SuperWoo_Cart_Drawer();

    return [
        '.superwoo-cart-drawer__inner' => superwoo_cart_drawer_inner_fragment_html($cart_drawer),
        '.superwoo-cart-offer-notices' => superwoo_cart_offer_notices_html(),
        '.superwoo-cart-count'         => '<span class="superwoo-cart-count">' . esc_html(superwoo_cart_count()) . '</span>',
        '.superwoo-cart-total'         => superwoo_cart_total_html(),
        '.superwoo-cart-primary'       => superwoo_cart_primary_button_html(),
    ];
}

function superwoo_cart_drawer_inner_fragment_html($cart_drawer = null) {
    if (!$cart_drawer instanceof SuperWoo_Cart_Drawer) {
        $cart_drawer = new SuperWoo_Cart_Drawer();
    }

    return '<div class="superwoo-cart-drawer__inner">' . $cart_drawer->get_inner_html() . '</div>';
}

function superwoo_cart_offer_notices_html() {
    $cart = superwoo_get_cart();

    ob_start();
    ?>
    <div class="superwoo-cart-offer-notices">
        <?php
        if (class_exists('SuperWoo_Bundle_Offers') && $cart) {
            echo (new SuperWoo_Bundle_Offers())->get_notices_html(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        }
        ?>
    </div>
    <?php
    return ob_get_clean();
}

function superwoo_cart_offer_state() {
    $cart = superwoo_get_cart();

    if (!class_exists('SuperWoo_Bundle_Offers') || !$cart) {
        return [
            'discounts' => [],
            'gifts'     => [],
        ];
    }

    return (new SuperWoo_Bundle_Offers())->get_cart_offer_state($cart);
}

function superwoo_cart_total_html() {
    $cart = superwoo_get_cart();
    if (!$cart) {
        return '';
    }

    ob_start();
    ?>
    <div class="superwoo-cart-total">
        <div class="superwoo-cart-total__row">
            <span><?php esc_html_e('Subtotal', 'superwoo'); ?></span>
            <strong><?php echo wp_kses_post($cart->get_cart_subtotal()); ?></strong>
        </div>
        <div class="superwoo-cart-total__row">
            <span><?php esc_html_e('Shipping', 'superwoo'); ?></span>
            <strong><?php echo wp_kses_post(wc_price((float) $cart->get_shipping_total())); ?></strong>
        </div>
        <div class="superwoo-cart-total__row superwoo-cart-total__row--grand-total">
            <span><?php esc_html_e('Total', 'superwoo'); ?></span>
            <strong><?php echo wp_kses_post(wc_price((float) $cart->get_total('edit'))); ?></strong>
        </div>
    </div>
    <?php
    return ob_get_clean();
}

function superwoo_cart_primary_button_html() {
    $cart = superwoo_get_cart();
    if (!$cart) {
        return '';
    }

    ob_start();
    if ($cart->is_empty()) :
        ?>
        <a class="superwoo-cart-primary" href="<?php echo esc_url(wc_get_page_permalink('shop')); ?>">
            <?php esc_html_e('Continue Shopping', 'superwoo'); ?>
        </a>
        <?php
    else :
        ?>
        <?php
        // Keep WooCommerce's native checkout classes so payment extensions,
        // including Razorpay Magic Checkout, can intercept this control.
        ?>
        <a class="superwoo-cart-primary checkout wc-forward" href="<?php echo esc_url(wc_get_checkout_url()); ?>">
            <span class="superwoo-cart-primary__icon" aria-hidden="true">
                <svg viewBox="0 0 24 24" focusable="false"><path d="M17 9V7A5 5 0 0 0 7 7v2H6a2 2 0 0 0-2 2v8a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-8a2 2 0 0 0-2-2h-1ZM9 7a3 3 0 0 1 6 0v2H9V7Z"/></svg>
            </span>
            <span>
                <?php
                printf(
                    /* translators: %s: formatted cart subtotal. */
                    esc_html__('Proceed to Checkout · %s', 'superwoo'),
                    esc_html(wp_strip_all_tags($cart->get_cart_subtotal()))
                );
                ?>
            </span>
        </a>
        <?php
    endif;

    return ob_get_clean();
}
