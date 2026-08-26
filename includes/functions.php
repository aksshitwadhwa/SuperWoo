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
    if (function_exists('wc_get_logger')) {
        wc_get_logger()->log($level, (string) $message, ['source' => 'superwoo', 'context' => $context]);
    } else {
        error_log('SuperWoo [' . strtoupper($level) . '] ' . $message . ' ' . wp_json_encode($context));
    }
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


function superwoo_is_razorpay_magic_checkout_available() {
    if (!function_exists('isRazorpayPluginEnabled') || !function_exists('is1ccEnabled')) {
        return false;
    }

    return isRazorpayPluginEnabled() && is1ccEnabled();
}

function superwoo_get_razorpay_magic_checkout_data() {
    $settings = get_option('woocommerce_razorpay_settings', []);
    $settings = is_array($settings) ? $settings : [];
    $site_url = get_option('siteurl');

    return [
        'enabled'              => superwoo_is_razorpay_magic_checkout_available(),
        'orderApi'             => rest_url('1cc/v1/order/create'),
        'abandonedCartApi'     => rest_url('1cc/v1/abandoned-cart'),
        'restNonce'            => wp_create_nonce('wp_rest'),
        'siteUrl'              => $site_url,
        'blogName'             => get_bloginfo('name'),
        'cookies'              => function_exists('wc_clean') ? wc_clean(wp_unslash($_COOKIE)) : [],
        'requestData'          => function_exists('wc_clean') ? wc_clean(wp_unslash($_REQUEST)) : [],
        'version'              => get_option('rzp_woocommerce_current_version', ''),
        'merchantKey'          => isset($settings['key_id']) ? sanitize_text_field($settings['key_id']) : '',
        'checkoutScriptUrl'    => defined('RZP_CHECKOUTJS_URL') ? RZP_CHECKOUTJS_URL : 'https://checkout.razorpay.com/v1/magic-checkout.js',
        'selectedCurrency'     => function_exists('superwoo_currency') ? superwoo_currency()->get_selected_currency() : get_woocommerce_currency(),
    ];
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
        <span><?php esc_html_e('Subtotal', 'superwoo'); ?></span>
        <strong><?php echo wp_kses_post($cart->get_cart_subtotal()); ?></strong>
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
        <button type="button" class="superwoo-cart-primary" data-superwoo-pay-now>
            <span class="superwoo-cart-primary__icon" aria-hidden="true">
                <svg viewBox="0 0 24 24" focusable="false"><path d="M17 9V7A5 5 0 0 0 7 7v2H6a2 2 0 0 0-2 2v8a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-8a2 2 0 0 0-2-2h-1ZM9 7a3 3 0 0 1 6 0v2H9V7Z"/></svg>
            </span>
            <span>
                <?php
                printf(
                    esc_html__('Proceed to Checkout · %s', 'superwoo'),
                    wp_strip_all_tags($cart->get_cart_subtotal())
                );
                ?>
            </span>
        </button>
        <?php
    endif;

    return ob_get_clean();
}
