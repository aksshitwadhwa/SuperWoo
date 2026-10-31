<?php
/** Razorpay product-page Buy Now must not consume the normal WooCommerce cart. */
define('ABSPATH', __DIR__ . '/');
class WP_Error {
    public $code;
    public function __construct($code, $message, $data = []) { $this->code = $code; }
}
class Buy_Now_Response extends Exception {
    public $payload;
    public function __construct($payload) { $this->payload = $payload; }
}
class Buy_Now_Session {
    public $values = [];
    public $drop_snapshot_writes = false;
    public function get($key) { return $this->values[$key] ?? null; }
    public function set($key, $value) { if ($key !== 'superwoo_buy_now_saved_cart_v1' || !$this->drop_snapshot_writes) { $this->values[$key] = $value; } }
    public function __unset($key) { unset($this->values[$key]); }
}
class Buy_Now_Cart {
    public $items = [];
    public $fail_add = false;
    public $session;
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
class Buy_Now_Cart_Session_Proxy {
    public $persistent = [];
    public function persistent_cart_update() { $this->persistent = WC()->cart->get_cart_for_session(); }
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
    public function get_route() { return '/1cc/v1/order/create'; }
}
function WC() { return $GLOBALS['buy_now_wc']; }
function wc_load_cart() {
    if (null === WC()->cart) {
        WC()->cart = new Buy_Now_Cart();
        WC()->cart->session = new Buy_Now_Cart_Session_Proxy();
    }
}
function wp_verify_nonce($nonce, $action) { return 'valid' === $nonce && in_array($action, ['wp_rest', 'superwoo_buy_now_snapshot'], true); }
function wp_unslash($value) { return $value; }
function wp_send_json_success($data) { throw new Buy_Now_Response(['success' => true, 'data' => $data]); }
function wp_send_json_error($data, $status = 400) { throw new Buy_Now_Response(['success' => false, 'data' => $data, 'status' => $status]); }
function wc_stock_amount($value) { return (int) $value; }
function wc_get_product($id) { return $id === 99 ? new Buy_Now_Product(10) : ($id === 10 ? new Buy_Now_Product() : null); }
function absint($value) { return abs((int) $value); }
function sanitize_key($value) { return strtolower((string) $value); }
function sanitize_text_field($value) { return (string) $value; }
function wc_clean($value) { return (string) $value; }
function __($message, $domain = '') { return $message; }
function nocache_headers() {}
function is_admin() { return false; }
function wp_doing_ajax() { return false; }
function is_checkout() { return false; }
function is_order_received_page() { return false; }
function check($condition, $message) { if (!$condition) { fwrite(STDERR, "FAIL: $message\n"); exit(1); } }

require_once dirname(__DIR__) . '/includes/class-buy-now-cart.php';
$session = new Buy_Now_Session();
$cart = new Buy_Now_Cart();
$cart->session = new Buy_Now_Cart_Session_Proxy();
$cart->items = ['old-a' => ['product_id' => 1, 'quantity' => 2], 'old-b' => ['product_id' => 2, 'quantity' => 1]];
$session->set('cart', $cart->items);
$session->set('applied_coupons', ['SAVE10']);
$GLOBALS['buy_now_wc'] = (object) ['cart' => null, 'session' => $session];
$module = new SuperWoo_Buy_Now_Cart();
$request = new Buy_Now_Request(['pdpCheckout' => 'true', 'productId' => 10, 'quantity' => 1]);

$_POST = ['nonce' => 'valid'];
try { $module->ajax_snapshot_cart(); }
catch (Buy_Now_Response $response) { $snapshot_response = $response->payload; }
check(isset($snapshot_response) && $snapshot_response['success'] && $snapshot_response['data']['count'] === 2, 'pre-click snapshot did not save the original cart');
check(count($session->get('superwoo_buy_now_saved_cart_v1')['cart']) === 2, 'late-loaded WooCommerce cart was snapshotted before session hydration');
$cart = WC()->cart;
check(count($cart->items) === 2, 'pre-click snapshot changed cart items');
$_POST = ['nonce' => 'bad'];
try { $module->ajax_snapshot_cart(); }
catch (Buy_Now_Response $response) { $bad_snapshot = $response->payload; }
check(isset($bad_snapshot) && !$bad_snapshot['success'] && $bad_snapshot['status'] === 403, 'snapshot accepted an invalid nonce');

check(null === $module->prepare_razorpay_order(null, $request, '/1cc/v1/order/create', []), 'Buy Now preparation failed');
check(count($cart->items) === 1 && $cart->items['buy-now']['product_id'] === 10, 'old cart leaked into Buy Now order');
check(count($session->get('superwoo_buy_now_saved_cart_v1')['cart']) === 2, 'original cart snapshot missing');

// The gateway draft is built from the one-product cart, but the response must
// leave the customer's original cart in WooCommerce's session immediately.
$response = (object) ['order_id' => 123];
check($response === $module->restore_after_razorpay_request($response, [], $request), 'gateway response changed');
check(count($cart->items) === 2 && isset($cart->items['old-a'], $cart->items['old-b']), 'order creation left the temporary Buy Now product in the normal cart');
check(count($session->get('cart')) === 2, 'original cart was not saved after gateway order creation');
check(count($cart->session->persistent) === 2, 'logged-in persistent cart was not restored');
check(is_array($session->get('superwoo_buy_now_saved_cart_v1')), 'snapshot disappeared before later gateway callbacks');

$cart->empty_cart();
$session->set('cart', []);
$module->restore_before_regular_cart_api(null, new class {
    public function get_method() { return 'GET'; }
}, '/wc/store/v1/cart', []);
check(count($cart->items) === 2 && count($session->get('cart')) === 2, 'Store API exposed a cleared Razorpay cart');
$cart->empty_cart();
$session->set('cart', []);
$_GET['wc-ajax'] = 'get_refreshed_fragments';
$module->restore_before_cart_fragments();
unset($_GET['wc-ajax']);
check(count($cart->items) === 2, 'WooCommerce fragments exposed a cleared Razorpay cart');

// Razorpay shipping and coupon callbacks can empty the cart in a later request.
$cart->empty_cart();
check($response === $module->restore_after_razorpay_request($response, [], new class {
    public function get_route() { return '/1cc/v1/shipping'; }
}), 'shipping callback response changed');
check(count($cart->items) === 2, 'later Razorpay callback erased the original cart');
$cart->empty_cart();
check($response === $module->restore_after_razorpay_request($response, [], new class {
    public function get_route() { return '/wc/store/v1/cart'; }
}) && count($cart->items) === 0, 'unrelated REST request restored the cart');
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

// An empty original cart must be restored to empty after Buy Now, too.
$cart->fail_add = false;
$cart->items = [];
$session->set('cart', []);
check(null === $module->prepare_razorpay_order(null, $request, '/1cc/v1/order/create', []), 'Buy Now from empty cart failed');
check(count($cart->items) === 1, 'gateway draft did not contain the selected product');
$module->restore_after_razorpay_request($response, [], $request);
check($cart->items === [] && $session->get('cart') === [], 'Buy Now product remained in previously empty cart');
check($cart->session->persistent === [], 'empty persistent cart was not restored');
check(SuperWoo_Buy_Now_Cart::restore_pending(), 'empty original cart snapshot could not be finalized');

$session->drop_snapshot_writes = true;
$_POST = ['nonce' => 'valid'];
try { $module->ajax_snapshot_cart(); }
catch (Buy_Now_Response $response) { $unsaved_snapshot = $response->payload; }
check(isset($unsaved_snapshot) && !$unsaved_snapshot['success'] && $unsaved_snapshot['status'] === 503, 'snapshot endpoint claimed success without saving its evidence');
$session->drop_snapshot_writes = false;

// A later Buy Now click must refresh, not reuse, a snapshot from an older flow.
$cart->items = ['latest-a' => ['product_id' => 3], 'latest-b' => ['product_id' => 4], 'latest-c' => ['product_id' => 5]];
$session->set('cart', $cart->items);
$_POST = ['nonce' => 'valid'];
try { $module->ajax_snapshot_cart(); }
catch (Buy_Now_Response $response) { $fresh_snapshot = $response->payload; }
check(isset($fresh_snapshot['success']) && $fresh_snapshot['data']['count'] === 3, 'subsequent Buy Now did not replace a stale cart snapshot');
check(count($session->get('superwoo_buy_now_saved_cart_v1')['cart']) === 3, 'refreshed Buy Now snapshot omitted updated cart items');

echo "PASS: Buy Now order isolation, immediate cart restore, later callbacks, empty cart, coupon, nonce, variation, rerun\n";
