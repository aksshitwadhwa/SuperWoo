<?php
/** Verify a drawer quantity request returns one calculated, persisted cart. */
define('ABSPATH', __DIR__ . '/');

class Test_Cart_Response extends Exception {
    public $payload;
    public function __construct($payload) { $this->payload = $payload; }
}
class Test_Cart_Session {
    public $cookie_calls = 0;
    public $values = [];
    public $drop_cart_writes = false;
    public function set_customer_session_cookie($enabled) { $this->cookie_calls++; }
    public function set($key, $value) { if ('cart' !== $key || !$this->drop_cart_writes) { $this->values[$key] = $value; } }
    public function get($key, $default = null) { return $this->values[$key] ?? $default; }
}
class Test_Drawer_Cart {
    public $cart_contents = ['item-key' => ['quantity' => 1, 'data' => 'product']];
    public $calculation_calls = 0;
    public $session_calls = 0;
    public $last_refresh_totals = null;
    public $total = 269;
    public $shipping_total = 75;
    public $shipping_calculated = true;
    public $shipping_methods = ['flat-rate'];
    public function get_cart() { return $this->cart_contents; }
    public function set_quantity($key, $quantity, $refresh_totals = true) {
        $this->last_refresh_totals = $refresh_totals;
        $this->cart_contents[$key]['quantity'] = $quantity;
        if ($refresh_totals) { $this->calculate_totals(); }
        return true;
    }
    public function calculate_totals() {
        $this->calculation_calls++;
        if (!isset($this->cart_contents['item-key'])) { $this->total = 0; return; }
        // Simulate an earlier third-party totals hook changing the quantity.
        $this->cart_contents['item-key']['quantity'] = 99;
        foreach ($GLOBALS['test_cart_hooks'] as $callback) { $callback($this); }
        $this->total = 269 * $this->cart_contents['item-key']['quantity'];
    }
    public function set_session() { $this->session_calls++; WC()->session->set('cart', $this->cart_contents); }
    public function remove_cart_item($key) { if (!isset($this->cart_contents[$key])) { return false; } unset($this->cart_contents[$key]); return true; }
    public function get_cart_hash() { return 'cart-hash'; }
    public function get_cart_contents_count() { return $this->cart_contents['item-key']['quantity'] ?? 0; }
    public function is_empty() { return !$this->cart_contents; }
    public function needs_shipping() { return true; }
    public function has_calculated_shipping() { return $this->shipping_calculated; }
    public function get_shipping_methods() { return $this->shipping_methods; }
    public function get_shipping_total() { return $this->shipping_total; }
    public function get_shipping_tax() { return 0; }
}

$GLOBALS['test_cart_hooks'] = [];
$GLOBALS['registered_hooks'] = [];
$GLOBALS['test_wc'] = (object) ['cart' => new Test_Drawer_Cart(), 'session' => new Test_Cart_Session()];
function WC() { return $GLOBALS['test_wc']; }
function superwoo_is_woocommerce_active() { return true; }
function wp_unslash($value) { return $value; }
function sanitize_text_field($value) { return (string) $value; }
function absint($value) { return abs((int) $value); }
function wp_verify_nonce($nonce, $action) { return $nonce === 'valid' && $action === 'superwoo_cart_nonce'; }
function wp_create_nonce($action) { return $action === 'superwoo_cart_nonce' ? 'fresh-nonce' : ''; }
function nocache_headers() {}
function __($message, $domain = '') { return $message; }
function superwoo_log($message, $data = [], $level = '') {}
function superwoo_get_settings() { return ['enable_cart_drawer' => true]; }
function get_option($key, $default = null) { return $key === 'superwoo_offer_free_delivery_threshold' ? 300 : $default; }
function superwoo_cart_offer_state() { return ['discounts' => [], 'gifts' => []]; }
function superwoo_format_selected_currency_amount($amount) { return 'INR' . $amount; }
function wp_strip_all_tags($value) { return strip_tags($value); }
function wc_clear_notices() {}
function add_action($hook, $callback, $priority = 10) {
    $GLOBALS['registered_hooks'][$hook][] = $callback;
    if ($hook === 'woocommerce_before_calculate_totals') { $GLOBALS['test_cart_hooks'][] = $callback; }
}
function add_filter($hook, $callback, $priority = 10) {}
function remove_action($hook, $callback, $priority = 10) {
    $GLOBALS['test_cart_hooks'] = array_values(array_filter($GLOBALS['test_cart_hooks'], static function ($item) use ($callback) { return $item !== $callback; }));
}
function superwoo_cart_drawer_fragments($calculate_totals = true) {
    if ($calculate_totals) { throw new Exception('Fragments must use calculated totals.'); }
    return ['.superwoo-cart-drawer__inner' => WC()->cart->get_cart_contents_count() . '|' . WC()->cart->total];
}
function superwoo_cart_drawer_inner_fragment_html($drawer = null, $include_cross_sells = true) {
    if ($include_cross_sells && !WC()->cart->is_empty()) { throw new Exception('Quantity response rebuilt the recommendation cards.'); }
    return WC()->cart->get_cart_contents_count() . '|' . WC()->cart->total;
}
function superwoo_cart_count() { return WC()->cart->get_cart_contents_count(); }
function wp_send_json_success($data) { throw new Test_Cart_Response(['success' => true, 'data' => $data]); }
function wp_send_json_error($data, $status = 400) { throw new Test_Cart_Response(['success' => false, 'data' => $data, 'status' => $status]); }

require_once dirname(__DIR__) . '/includes/class-cart-drawer.php';

function run_request($quantity, $key = 'item-key', $nonce = 'valid') {
    $_POST = ['cart_item_key' => $key, 'quantity' => $quantity, 'nonce' => $nonce];
    try { (new SuperWoo_Cart_Drawer())->ajax_update_cart_item(); }
    catch (Test_Cart_Response $response) { return $response->payload; }
    throw new Exception('No AJAX response returned.');
}
function check($condition, $message) {
    if (!$condition) { fwrite(STDERR, "FAIL: $message\n"); exit(1); }
}

$first = run_request(2);
check($first['success'] && $first['data']['count'] === 2, 'updated quantity missing from AJAX response');
check($first['data']['updatedQuantity'] === 2, 'AJAX response did not confirm the committed quantity');
check($first['data']['fragments']['.superwoo-cart-drawer__inner'] === '2|538', 'line total did not reflect quantity');
check($first['data']['preserveCrossSells'] === true && count($first['data']['fragments']) === 1, 'quantity response was not compact');
check(WC()->cart->calculation_calls === 1 && WC()->cart->last_refresh_totals === false, 'totals calculated more than once');
check(WC()->cart->session_calls === 1 && WC()->session->cookie_calls === 1, 'updated cart session not persisted');
check(WC()->session->get('cart')['item-key']['quantity'] === 2, 'saved cart quantity did not match the response');
check($GLOBALS['test_cart_hooks'] === [], 'temporary quantity protector was not removed');

$second = run_request(2);
check($second['data']['fragments']['.superwoo-cart-drawer__inner'] === '2|538', 'repeat request changed the result');
check(WC()->cart->calculation_calls === 2, 'repeat request did not calculate once');

$before = WC()->cart->calculation_calls;
WC()->cart->cart_contents['item-key']['superwoo_free_gift'] = true;
$gift = run_request(3);
check(!$gift['success'] && WC()->cart->calculation_calls === $before, 'free gift quantity was changed');
unset(WC()->cart->cart_contents['item-key']['superwoo_free_gift']);

$invalid = run_request(3, 'missing-key');
check(!$invalid['success'] && WC()->cart->calculation_calls === $before, 'unknown key changed the cart');
$nonce = run_request(3, 'item-key', 'invalid');
check(!$nonce['success'] && $nonce['status'] === 403, 'invalid nonce was accepted');
check($nonce['data']['code'] === 'superwoo_invalid_nonce', 'invalid nonce was not identified for safe retry');

try { (new SuperWoo_Cart_Drawer())->ajax_refresh_cart_nonce(); }
catch (Test_Cart_Response $response) { $fresh_nonce = $response->payload; }
check(isset($fresh_nonce) && $fresh_nonce['success'] && $fresh_nonce['data']['nonce'] === 'fresh-nonce', 'read-only nonce refresh failed');

$decreased = run_request(1);
check($decreased['success'] && $decreased['data']['count'] === 1, 'decrease to one failed');
check($decreased['data']['fragments']['.superwoo-cart-drawer__inner'] === '1|269', 'decreased line total was stale');
$_POST = ['cart_item_key' => 'item-key', 'nonce' => 'valid'];
try { (new SuperWoo_Cart_Drawer())->ajax_remove_cart_item(); }
catch (Test_Cart_Response $response) { $removed = $response->payload; }
check(isset($removed) && $removed['success'] && $removed['data']['count'] === 0, 'minus at one did not remove the line');
check($removed['data']['fragments']['.superwoo-cart-drawer__inner'] === '0|0', 'empty cart total was stale');

WC()->cart->cart_contents['item-key'] = ['quantity' => 1, 'data' => 'product'];
WC()->session->drop_cart_writes = true;
$not_saved = run_request(2);
check(!$not_saved['success'] && $not_saved['status'] === 409, 'unsaved quantity was reported as successful');
check($not_saved['data']['code'] === 'superwoo_cart_not_persisted', 'unsaved quantity had no actionable error code');
WC()->session->drop_cart_writes = false;

(new SuperWoo_Cart_Drawer())->hooks();
check(isset($GLOBALS['registered_hooks']['wp_ajax_superwoo_update_cart_item'], $GLOBALS['registered_hooks']['wp_ajax_nopriv_superwoo_update_cart_item']), 'quantity AJAX routes were not registered');
check(isset($GLOBALS['registered_hooks']['wp_ajax_superwoo_refresh_cart_nonce'], $GLOBALS['registered_hooks']['wp_ajax_nopriv_superwoo_refresh_cart_nonce']), 'nonce refresh routes were not registered');
check(isset($GLOBALS['registered_hooks']['wc_ajax_superwoo_add_product_to_cart'], $GLOBALS['registered_hooks']['wc_ajax_superwoo_add_cross_sell'], $GLOBALS['registered_hooks']['wc_ajax_superwoo_refresh_cart_drawer']), 'storefront add/refresh routes were not registered');

$drawer = new SuperWoo_Cart_Drawer();
WC()->cart->cart_contents['item-key']['line_subtotal'] = 350;
check($drawer->get_delivery_progress() === [], 'paid shipping was falsely described as unlocked free delivery');
WC()->cart->cart_contents['item-key']['line_subtotal'] = 200;
check(strpos($drawer->get_delivery_progress()['message'], 'INR100') !== false, 'delivery progress did not use the current pre-offer subtotal');
check($drawer->get_cart_notice_message() === '', 'delivery message still appeared in the separate top banner');
WC()->cart->shipping_total = 0;
check($drawer->get_delivery_progress()['message'] === 'Free delivery is applied to this order.', 'calculated free shipping was not reflected in the notice');
WC()->cart->shipping_calculated = false;
check($drawer->get_delivery_progress() === [], 'uncalculated shipping was presented as free');

echo "PASS: AJAX increase, decrease, remove at one, totals, session, rerun, gift guard, nonce and shipping notice checks\n";
