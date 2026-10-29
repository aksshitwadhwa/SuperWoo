<?php
/** Verify a drawer quantity request returns one calculated, persisted cart. */
define('ABSPATH', __DIR__ . '/');

class Test_Cart_Response extends Exception {
    public $payload;
    public function __construct($payload) { $this->payload = $payload; }
}
class Test_Cart_Session {
    public $cookie_calls = 0;
    public function set_customer_session_cookie($enabled) { $this->cookie_calls++; }
}
class Test_Drawer_Cart {
    public $cart_contents = ['item-key' => ['quantity' => 1, 'data' => 'product']];
    public $calculation_calls = 0;
    public $session_calls = 0;
    public $last_refresh_totals = null;
    public $total = 269;
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
    public function set_session() { $this->session_calls++; }
    public function remove_cart_item($key) { if (!isset($this->cart_contents[$key])) { return false; } unset($this->cart_contents[$key]); return true; }
    public function get_cart_hash() { return 'cart-hash'; }
    public function get_cart_contents_count() { return $this->cart_contents['item-key']['quantity'] ?? 0; }
}

$GLOBALS['test_cart_hooks'] = [];
$GLOBALS['test_wc'] = (object) ['cart' => new Test_Drawer_Cart(), 'session' => new Test_Cart_Session()];
function WC() { return $GLOBALS['test_wc']; }
function superwoo_is_woocommerce_active() { return true; }
function wp_unslash($value) { return $value; }
function sanitize_text_field($value) { return (string) $value; }
function absint($value) { return abs((int) $value); }
function wp_verify_nonce($nonce, $action) { return $nonce === 'valid' && $action === 'superwoo_cart_nonce'; }
function __($message, $domain = '') { return $message; }
function superwoo_log($message, $data = [], $level = '') {}
function wc_clear_notices() {}
function add_action($hook, $callback, $priority = 10) { $GLOBALS['test_cart_hooks'][] = $callback; }
function remove_action($hook, $callback, $priority = 10) {
    $GLOBALS['test_cart_hooks'] = array_values(array_filter($GLOBALS['test_cart_hooks'], static function ($item) use ($callback) { return $item !== $callback; }));
}
function superwoo_cart_drawer_fragments($calculate_totals = true) {
    if ($calculate_totals) { throw new Exception('Fragments must use calculated totals.'); }
    return ['.superwoo-cart-drawer__inner' => WC()->cart->get_cart_contents_count() . '|' . WC()->cart->total];
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
check($first['data']['fragments']['.superwoo-cart-drawer__inner'] === '2|538', 'line total did not reflect quantity');
check(WC()->cart->calculation_calls === 1 && WC()->cart->last_refresh_totals === false, 'totals calculated more than once');
check(WC()->cart->session_calls === 1 && WC()->session->cookie_calls === 1, 'updated cart session not persisted');
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

$decreased = run_request(1);
check($decreased['success'] && $decreased['data']['count'] === 1, 'decrease to one failed');
check($decreased['data']['fragments']['.superwoo-cart-drawer__inner'] === '1|269', 'decreased line total was stale');
$_POST = ['cart_item_key' => 'item-key', 'nonce' => 'valid'];
try { (new SuperWoo_Cart_Drawer())->ajax_remove_cart_item(); }
catch (Test_Cart_Response $response) { $removed = $response->payload; }
check(isset($removed) && $removed['success'] && $removed['data']['count'] === 0, 'minus at one did not remove the line');
check($removed['data']['fragments']['.superwoo-cart-drawer__inner'] === '0|0', 'empty cart total was stale');

echo "PASS: AJAX increase, decrease, remove at one, totals, session, rerun, gift guard, key and nonce checks\n";
