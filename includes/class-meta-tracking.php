<?php
defined('ABSPATH') || exit;

require_once SUPERWOO_PATH . 'includes/meta-tracking/interface-observer.php';
require_once SUPERWOO_PATH . 'includes/meta-tracking/class-consent.php';
require_once SUPERWOO_PATH . 'includes/meta-tracking/class-event-normalizer.php';

/**
 * Disabled-by-default, read-only Meta tracking observer and browser dispatcher.
 *
 * Phase 3A intentionally has no event dispatcher and registers no WooCommerce
 * checkout, payment, order, cart, or gateway hooks.
 */
class SuperWoo_Meta_Tracking {
    public function hooks() {
        add_action('wp_enqueue_scripts', [$this, 'enqueue_observer']);
    }

    public function is_enabled() {
        $settings = superwoo_get_settings();
        return !empty($settings['enable_meta_tracking']) && empty($settings['meta_tracking_kill_switch']);
    }

    public function enqueue_observer() {
        if (!$this->is_enabled() || !function_exists('is_checkout') || !is_checkout()) {
            return;
        }
        if (function_exists('is_order_received_page') && is_order_received_page()) {
            return;
        }

        $settings = superwoo_get_settings();
        $script_path = SUPERWOO_PATH . 'public/js/meta-checkout-observer.js';
        if (!is_readable($script_path)) {
            return;
        }

        wp_enqueue_script(
            'superwoo-meta-checkout-observer',
            SUPERWOO_URL . 'public/js/meta-checkout-observer.js',
            [],
            (string) filemtime($script_path),
            true
        );
        wp_localize_script('superwoo-meta-checkout-observer', 'SuperWooMetaTrackingConfig', [
            'enabled' => true,
            'killSwitch' => false,
            'consentState' => SuperWoo_Meta_Tracking_Consent::state(),
            'diagnostics' => !empty($settings['meta_tracking_diagnostics']),
            'primaryPixelId' => $this->sanitize_pixel_id($settings['meta_primary_pixel_id'] ?? ''),
            'commercePayload' => $this->commerce_payload(),
            'priceFormat' => [
                'decimalSeparator' => function_exists('wc_get_price_decimal_separator') ? wc_get_price_decimal_separator() : '.',
                'thousandSeparator' => function_exists('wc_get_price_thousand_separator') ? wc_get_price_thousand_separator() : ',',
            ],
            'razorpayStatus' => 'unsupported',
        ]);
    }

    private function sanitize_pixel_id($value) {
        $value = is_scalar($value) ? trim((string) $value) : '';
        return preg_match('/^[0-9]{1,32}$/D', $value) ? $value : '';
    }

    /** Read the already-calculated WooCommerce cart; never recalculate it. */
    private function commerce_payload() {
        if (!function_exists('WC') || !WC() || !WC()->cart || !function_exists('get_woocommerce_currency')) {
            return null;
        }

        $cart = WC()->cart;
        $currency = strtoupper((string) get_woocommerce_currency());
        if (!preg_match('/^[A-Z]{3}$/D', $currency) || !method_exists($cart, 'get_total')) {
            return null;
        }

        $value = $cart->get_total('edit');
        if (!is_numeric($value) || !is_finite((float) $value)) {
            return null;
        }

        $content_ids = [];
        $num_items = 0;
        foreach ((array) $cart->get_cart() as $item) {
            if (!is_array($item)) {
                continue;
            }
            $product_id = !empty($item['variation_id']) ? absint($item['variation_id']) : absint($item['product_id'] ?? 0);
            $quantity = isset($item['quantity']) && is_numeric($item['quantity']) ? max(0, (float) $item['quantity']) : 0;
            if ($product_id > 0 && $quantity > 0) {
                $content_ids[] = (string) $product_id;
                $num_items += $quantity;
            }
        }

        if (!$content_ids || $num_items < 1) {
            return null;
        }

        return [
            'currency' => $currency,
            'value' => (float) $value,
            'content_ids' => array_values(array_unique($content_ids)),
            'content_type' => 'product',
            'num_items' => $num_items,
        ];
    }
}
