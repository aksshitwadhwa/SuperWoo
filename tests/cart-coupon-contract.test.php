<?php
/** Source-boundary checks for the native Smart Cart coupon flow. */
$root = dirname(__DIR__);
$class = file_get_contents($root . '/includes/class-cart-drawer.php');
$template = file_get_contents($root . '/templates/cart-drawer-inner.php');
$javascript = file_get_contents($root . '/public/js/cart-drawer.js');
$settings = file_get_contents($root . '/includes/class-plugin.php');
$assertions = 0;

function coupon_contract_assert($condition, $message) {
    global $assertions;
    $assertions++;
    if (!$condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
}

coupon_contract_assert(strpos($class, "add_action('wc_ajax_superwoo_apply_cart_coupon'") !== false, 'WooCommerce coupon apply route is registered');
coupon_contract_assert(strpos($class, "add_action('wc_ajax_superwoo_remove_cart_coupon'") !== false, 'WooCommerce coupon removal route is registered');
coupon_contract_assert(strpos($class, "add_action('wp_ajax_nopriv_superwoo_apply_cart_coupon'") !== false, 'Guest coupon application route is registered');
coupon_contract_assert(strpos($class, "add_action('wp_ajax_nopriv_superwoo_remove_cart_coupon'") !== false, 'Guest coupon removal route is registered');
coupon_contract_assert((bool) preg_match('/function ajax_apply_cart_coupon\(\).*?\$this->verify_ajax\(\).*?WC\(\)->cart->apply_coupon\(\$code\)/s', $class), 'Coupon application verifies request nonce then delegates validation to WooCommerce');
coupon_contract_assert((bool) preg_match('/function ajax_remove_cart_coupon\(\).*?\$this->verify_ajax\(\).*?WC\(\)->cart->remove_coupon\(\$code\)/s', $class), 'Coupon removal verifies request nonce then delegates to WooCommerce');
coupon_contract_assert(strpos($template, "'inline' === (\$settings['cart_drawer_coupon']") !== false, 'Inline coupon UI follows merchant setting');
coupon_contract_assert(strpos($template, 'wc_coupons_enabled()') !== false, 'Coupon UI is hidden when WooCommerce coupons are disabled');
coupon_contract_assert(strpos($template, 'data-superwoo-coupon-form') !== false && strpos($template, 'aria-live="polite"') !== false, 'Coupon form includes a live status region');
coupon_contract_assert(strpos($javascript, "post('superwoo_apply_cart_coupon'") !== false && strpos($javascript, "post('superwoo_remove_cart_coupon'") !== false, 'Apply and remove actions use existing nonce-aware cart request flow');
coupon_contract_assert(strpos($settings, "['inline', 'checkout_link', 'disabled']") !== false, 'Coupon setting accepts only supported modes');
coupon_contract_assert(strpos($class, "\$_POST['price']") === false, 'Coupon endpoints do not accept client-submitted pricing');

echo "PASS: {$assertions} native coupon security and integration contract assertions\n";
