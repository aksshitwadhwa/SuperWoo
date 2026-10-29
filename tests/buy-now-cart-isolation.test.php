<?php
/** Razorpay product-page Buy Now must not consume the normal WooCommerce cart. */
define('ABSPATH', __DIR__ . '/');
class WP_Error {
    public $code;
    public function __construct($code, $message, $data = []) { $this->code = $code; }
}
class Buy_Now_Session {
    public $values = [];
    public function get($key) { return $this->values[$key] ?? null; }
    public function set($key, $value) { $this->values[$key] = $value; }
    public function __unset($key) { unset($this->values[$key]); }
}
class Buy_Now_Cart {
    public $items = [];
    public $fail_add = false;
    public function get_cart_for_session() { return $this->items; }
    public function empty_cart($persistent = true) { $this->items = []; }
    public function add_to_cart($id, $quantity, $variation, $attrs) {
        if ($this->fail_add) { return false; }
        $this->items['buy-now'] = ['product_id' => $id, 'quantity' => $quantity, 'variation_id' => $variation, 'variation' => $attrs];
        return 'buy-now';
    }
    public function get_cart_from_session() { $this->items = WC()->session->get('cart') ?: []; }
    public function calculate_totals() {}
    public function set_session() { WC()->session->set('cart', $this->items); }
}
class Buy_Now_Product {
    private $parent;
    public function __construct($parent = 0) { $this->parent = $parent; }
    public function is_purchasable() { return true; }
    public function get_parent_id() { return $this->parent; }
    public function is_type($type) { return false; }
}
class Buy_Now_Request {
    private $params;
    private $nonce;
    public function __construct($params, $nonce = 'valid') { $this->params = $params; $this->nonce = $nonce; }
    public function get_params() { return $this->params; }
    public function get_method() { return 'POST'; }
    public function get_header($header) { return $this->nonce; }
}
function WC() { return $GLOBALS['buy_now_wc']; }
function wc_load_cart() {}
function wp_verify_nonce($nonce, $action) { return 'valid' === $nonce && 'wp_rest' === $action; }
function wc_stock_amount($value) { return (int) $value; }
function wc_get_product($id) { return $id === 99 ? new Buy_Now_Product(10) : ($id === 10 ? new Buy_Now_Product() : null); }
function absint($value) { return abs((int) $value); }
function sanitize_key($value) { return strtolower((string) $value); }
function sanitize_text_field($value) { return (string) $value; }
function wc_clean($value) { return (string) $value; }
function __($message, $domain = '') { return $message; }
function is_admin() { return false; }
function wp_doing_ajax() { return false; }
function is_checkout() { return false; }
function is_order_received_page() { return false; }
function check($condition, $message) { if (!$condition) { fwrite(STDERR, "FAIL: $message\n"); exit(1); } }

require_once dirname(__DIR__) . '/includes/class-buy-now-cart.php';
$session = new Buy_Now_Session();
$cart = new Buy_Now_Cart();
$cart->items = ['old-a' => ['product_id' => 1, 'quantity' => 2], 'old-b' => ['product_id' => 2, 'quantity' => 1]];
$session->set('cart', $cart->items);
$session->set('applied_coupons', ['SAVE10']);
$GLOBALS['buy_now_wc'] = (object) ['cart' => $cart, 'session' => $session];
$module = new SuperWoo_Buy_Now_Cart();
$request = new Buy_Now_Request(['pdpCheckout' => 'true', 'productId' => 10, 'quantity' => 1]);

check(null === $module->prepare_razorpay_order(null, $request, '/1cc/v1/order/create', []), 'Buy Now preparation failed');
check(count($cart->items) === 1 && $cart->items['buy-now']['product_id'] === 10, 'old cart leaked into Buy Now order');
check(count($session->get('superwoo_buy_now_saved_cart_v1')['cart']) === 2, 'original cart snapshot missing');

// Razorpay may empty the temporary cart after payment; the snapshot must survive.
$cart->empty_cart();
check(SuperWoo_Buy_Now_Cart::restore_pending(), 'cart restoration failed');
check(count($cart->items) === 2 && $cart->items['old-a']['quantity'] === 2, 'original items or quantities changed');
check($session->get('applied_coupons') === ['SAVE10'], 'original coupon was lost');
check(null === $session->get('superwoo_buy_now_saved_cart_v1'), 'snapshot was not cleared');
check(!SuperWoo_Buy_Now_Cart::restore_pending(), 'repeat restore changed the cart');

$variation_request = new Buy_Now_Request([
    'pdpCheckout' => 'true', 'productId' => 10, 'variationId' => 99,
    'quantity' => 2, 'variations' => '{"attribute_pa_size":"large"}',
    'fieldObj' => ['Gift note' => 'Happy birthday'],
]);
check(null === $module->prepare_razorpay_order(null, $variation_request, '/1cc/v1/order/create', []), 'variation Buy Now failed');
check($cart->items['buy-now']['variation_id'] === 99 && $cart->items['buy-now']['quantity'] === 2, 'selected variation or quantity missing');
check($cart->items['buy-now']['variation']['Gift note'] === 'Happy birthday', 'selected custom field missing');
check(null === $module->prepare_razorpay_order(null, $request, '/1cc/v1/order/create', []), 'repeated Buy Now failed');
check(count($session->get('superwoo_buy_now_saved_cart_v1')['cart']) === 2, 'repeated Buy Now replaced the original snapshot');
check(SuperWoo_Buy_Now_Cart::restore_pending() && count($cart->items) === 2, 'repeat Buy Now lost the original cart');

$before = $cart->items;
$invalid = $module->prepare_razorpay_order(null, new Buy_Now_Request(['pdpCheckout' => 'true', 'productId' => 10], 'bad'), '/1cc/v1/order/create', []);
check($invalid instanceof WP_Error && $cart->items === $before, 'invalid nonce changed the cart');
$not_pdp = $module->prepare_razorpay_order(null, new Buy_Now_Request(['productId' => 10]), '/1cc/v1/order/create', []);
check(null === $not_pdp && $cart->items === $before, 'regular checkout changed the cart');
$bad_variation = $module->prepare_razorpay_order(null, new Buy_Now_Request(['pdpCheckout' => 'true', 'productId' => 11, 'variationId' => 99]), '/1cc/v1/order/create', []);
check($bad_variation instanceof WP_Error && $cart->items === $before, 'unrelated variation changed the cart');

$cart->fail_add = true;
$failed = $module->prepare_razorpay_order(null, $request, '/1cc/v1/order/create', []);
check($failed instanceof WP_Error && $cart->items === $before, 'failed Buy Now did not restore original cart');

echo "PASS: Buy Now isolation, saved items and coupon, failed add, nonce, variation, rerun\n";
