<?php
define('ABSPATH', __DIR__ . '/');
class WP_Error { private $code; public function __construct($code, $message = '') { $this->code = $code; } public function get_error_code() { return $this->code; } }
function __($text) { return $text; }
function is_wp_error($value) { return $value instanceof WP_Error; }
function current_user_can($capability) { return true; }
function sanitize_text_field($value) { return trim(strip_tags((string) $value)); }
function absint($value) { return abs((int) $value); }
class WC_Product { public $id; public function __construct($id) { $this->id = $id; } public function is_type($type) { return false; } }
class WC_Order_Item_Product {
    public $product; public $quantity; public $subtotal; public $total;
    public function set_product($product) { $this->product = $product; }
    public function set_quantity($quantity) { $this->quantity = $quantity; }
    public function set_subtotal($subtotal) { $this->subtotal = $subtotal; }
    public function set_total($total) { $this->total = $total; }
}
class WC_Order {
    public $items = []; public $meta = ['_superwoo_recovery_payment_only'=>'yes','_superwoo_recovery_historical_order_id'=>5992,'_superwoo_recovery_items_missing'=>'yes'];
    public function get_status() { return 'superwoo-review'; }
    public function get_meta($key, $single = true) { return $this->meta[$key] ?? ''; }
    public function get_transaction_id() { return 'pay_recovery5992'; }
    public function get_currency() { return 'INR'; }
    public function get_total() { return 919.00; }
    public function get_items($type) { return $this->items; }
    public function add_item($item) { $this->items[] = $item; }
    public function update_meta_data($key, $value) { $this->meta[$key] = $value; }
    public function save() { return 8597; }
}
class SuperWoo_Order_Recovery_Payment_Record {
    const STATUS = 'superwoo-review';
    public static function claim($id) { return $id === 'pay_recovery5992' ? ['state'=>'created','order_id'=>8597] : false; }
}
$GLOBALS['item_test_order'] = new WC_Order();
function wc_get_order($id) { return $id === 8597 ? $GLOBALS['item_test_order'] : false; }
function wc_get_product($id) { return in_array($id, [101, 102], true) ? new WC_Product($id) : false; }
require dirname(__DIR__) . '/includes/order-recovery/class-order-recovery-items.php';
$checks = 0;
$assert = static function ($ok, $message) use (&$checks) { $checks++; if (!$ok) { fwrite(STDERR, "FAIL: {$message}\n"); exit(1); } };
$record = ['payment_id'=>'pay_recovery5992','wc_order_id'=>5992,'gross_minor'=>91900];
$lines = [['product_id'=>'101','quantity'=>'1','subtotal'=>'919.00','total'=>'919.00'], ['product_id'=>'102','quantity'=>'1','subtotal'=>'0','total'=>'0']];
$wrong = $lines; $wrong[0]['total'] = '918.00';
$assert(is_wp_error(SuperWoo_Order_Recovery_Items::add($record, $wrong, 'Customer checkout screenshot', 7)) && !$GLOBALS['item_test_order']->items, 'mismatched totals cannot create item rows');
$assert(is_wp_error(SuperWoo_Order_Recovery_Items::add($record, $lines, 'CSV', 7)) && !$GLOBALS['item_test_order']->items, 'a source description is required');
$bad = $lines; $bad[0]['product_id'] = '999';
$assert(is_wp_error(SuperWoo_Order_Recovery_Items::add($record, $bad, 'Customer checkout screenshot', 7)) && !$GLOBALS['item_test_order']->items, 'unavailable catalog products cannot be added');
$added = SuperWoo_Order_Recovery_Items::add($record, $lines, 'Customer checkout screenshot', 7);
$assert($added === 8597 && count($GLOBALS['item_test_order']->items) === 2, 'paid product and free gift become actual WooCommerce item rows');
$assert($GLOBALS['item_test_order']->items[0]->total === '919.00' && $GLOBALS['item_test_order']->items[1]->total === '0.00' && $GLOBALS['item_test_order']->meta['_superwoo_recovery_items_approved_by'] === 7, 'exact totals and reviewer are stored');
$assert(is_wp_error(SuperWoo_Order_Recovery_Items::add($record, $lines, 'Customer checkout screenshot', 7)) && count($GLOBALS['item_test_order']->items) === 2, 'repeating approval cannot duplicate product rows');
echo "PASS: {$checks} recovery item checks\n";
