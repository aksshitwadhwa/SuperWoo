<?php
defined('ABSPATH') || exit;

/**
 * Keep a Razorpay Magic Checkout product-page purchase separate from the shop cart.
 * Razorpay creates its draft order from WC()->cart before opening its modal.
 */
class SuperWoo_Buy_Now_Cart {
    private const SESSION_KEY = 'superwoo_buy_now_saved_cart_v1';

    public function hooks() {
        add_filter('rest_dispatch_request', [$this, 'prepare_razorpay_order'], 5, 4);
        add_action('template_redirect', [$this, 'restore_on_storefront'], 0);
        add_action('wp_enqueue_scripts', [$this, 'enqueue_assets']);
    }

    public function enqueue_assets() {
        if (!is_product()) {
            return;
        }
        wp_enqueue_script('superwoo-buy-now-cart', SUPERWOO_URL . 'public/js/buy-now-cart.js', [], SUPERWOO_VERSION . '.' . filemtime(SUPERWOO_PATH . 'public/js/buy-now-cart.js'), true);
    }

    public function prepare_razorpay_order($result, $request, $route, $handler) {
        if (null !== $result || '/1cc/v1/order/create' !== $route || 'POST' !== $request->get_method()) {
            return $result;
        }

        $params = $request->get_params();
        if (empty($params['pdpCheckout'])) {
            return $result;
        }
        if (!wp_verify_nonce($request->get_header('X-WP-Nonce'), 'wp_rest')) {
            return new WP_Error('superwoo_buy_now_nonce', __('Buy Now session could not be verified.', 'superwoo'), ['status' => 401]);
        }
        if (!function_exists('wc_load_cart') || !function_exists('WC')) {
            return new WP_Error('superwoo_buy_now_cart', __('Cart is unavailable. Please try again.', 'superwoo'), ['status' => 503]);
        }

        $cart_was_missing = null === WC()->cart;
        wc_load_cart();
        if ($cart_was_missing && WC()->cart) {
            WC()->cart->get_cart_from_session();
        }
        if (!WC()->cart || !WC()->session) {
            return new WP_Error('superwoo_buy_now_cart', __('Cart is unavailable. Please try again.', 'superwoo'), ['status' => 503]);
        }

        $product_id = isset($params['productId']) ? absint($params['productId']) : 0;
        $variation_id = isset($params['variationId']) ? absint($params['variationId']) : 0;
        $quantity = isset($params['quantity']) ? wc_stock_amount($params['quantity']) : 1;
        $product = wc_get_product($variation_id ?: $product_id);
        if (!$product_id || !$product || $quantity < 1 || $quantity > 1000 || !$product->is_purchasable()) {
            return new WP_Error('superwoo_buy_now_product', __('Please choose a valid product and quantity.', 'superwoo'), ['status' => 400]);
        }
        if ($variation_id && (int) $product->get_parent_id() !== $product_id) {
            return new WP_Error('superwoo_buy_now_variation', __('The selected variation does not belong to this product.', 'superwoo'), ['status' => 400]);
        }
        if (!$variation_id && $product->is_type('variable')) {
            return new WP_Error('superwoo_buy_now_variation', __('Please choose product options before buying.', 'superwoo'), ['status' => 400]);
        }

        $variations = [];
        if (!empty($params['variations'])) {
            $decoded = is_array($params['variations']) ? $params['variations'] : json_decode((string) $params['variations'], true);
            if (!is_array($decoded)) {
                return new WP_Error('superwoo_buy_now_variation', __('Invalid product options.', 'superwoo'), ['status' => 400]);
            }
            foreach ($decoded as $key => $value) {
                if (is_scalar($value)) {
                    $variations[sanitize_key($key)] = wc_clean((string) $value);
                }
            }
        }
        // The installed Razorpay integration forwards product-page custom
        // fields in fieldObj when it builds its temporary cart. Preserve those
        // same selected values before its draft order is created.
        if (!empty($params['fieldObj']) && is_array($params['fieldObj'])) {
            foreach ($params['fieldObj'] as $key => $value) {
                if (is_scalar($value) && '' !== (string) $value) {
                    $variations[sanitize_text_field((string) $key)] = wc_clean((string) $value);
                }
            }
        }

        $saved = WC()->session->get(self::SESSION_KEY);
        if (!is_array($saved) || empty($saved['cart'])) {
            $saved = $this->snapshot();
            // An empty original cart has nothing to preserve.
            if (!empty($saved['cart'])) {
                WC()->session->set(self::SESSION_KEY, $saved);
            }
        }

        WC()->cart->empty_cart(false);
        $added = WC()->cart->add_to_cart($product_id, $quantity, $variation_id, $variations);
        if (!$added) {
            if (!empty($saved['cart'])) {
                self::restore_pending();
            }
            return new WP_Error('superwoo_buy_now_add_failed', __('The selected product could not be added to Buy Now.', 'superwoo'), ['status' => 400]);
        }
        WC()->cart->calculate_totals();
        WC()->cart->set_session();
        return null;
    }

    private function snapshot() {
        $keys = ['applied_coupons', 'chosen_shipping_methods', 'shipping_method_counts', 'previous_shipping_methods'];
        $saved = [
            'created_at' => time(),
            'cart' => WC()->cart->get_cart_for_session(),
        ];
        foreach ($keys as $key) {
            $saved[$key] = WC()->session->get($key);
        }
        return $saved;
    }

    public static function restore_pending() {
        if (!function_exists('WC') || !WC()->session || !WC()->cart) {
            return false;
        }
        $saved = WC()->session->get(self::SESSION_KEY);
        if (!is_array($saved) || empty($saved['cart'])) {
            return false;
        }
        // Keep the saved snapshot until WooCommerce has rehydrated the cart.
        WC()->cart->empty_cart(false);
        foreach (['cart', 'applied_coupons', 'chosen_shipping_methods', 'shipping_method_counts', 'previous_shipping_methods'] as $key) {
            WC()->session->set($key, $saved[$key] ?? null);
        }
        WC()->cart->get_cart_from_session();
        WC()->cart->calculate_totals();
        WC()->cart->set_session();
        WC()->session->__unset(self::SESSION_KEY);
        return true;
    }

    public function restore_on_storefront() {
        if (is_admin() || wp_doing_ajax() || (defined('REST_REQUEST') && REST_REQUEST) || !empty($_GET['wc-api'])) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
            return;
        }
        if (is_checkout() && !is_order_received_page()) {
            return;
        }
        self::restore_pending();
    }
}
