<?php
define('ABSPATH', __DIR__ . '/');
class WP_Error {
    private $code;
    public function __construct($code, $message = '') { $this->code = $code; }
    public function get_error_code() { return $this->code; }
}
function is_wp_error($value) { return $value instanceof WP_Error; }
function __($value, $domain = '') { return $value; }
function _n_noop($one, $many, $domain = '') { return [$one, $many]; }
function absint($value) { return abs((int) $value); }
function sanitize_key($value) { return strtolower(preg_replace('/[^a-z0-9_\-]/', '', (string) $value)); }
function sanitize_text_field($value) { return trim(strip_tags((string) $value)); }
function is_email($value) { return (bool) filter_var($value, FILTER_VALIDATE_EMAIL); }
function current_user_can($capability) { return !empty($GLOBALS['payment_record_admin']); }
function wp_json_encode($value) { return json_encode($value); }
$GLOBALS['payment_record_options'] = [];
$GLOBALS['payment_record_admin'] = true;
function get_option($key, $default = false) { return $GLOBALS['payment_record_options'][$key] ?? $default; }
function add_option($key, $value, $deprecated = '', $autoload = false) {
    if (array_key_exists($key, $GLOBALS['payment_record_options'])) { return false; }
    $GLOBALS['payment_record_options'][$key] = $value;
    return true;
}
function update_option($key, $value, $autoload = false) { $GLOBALS['payment_record_options'][$key] = $value; return true; }
$GLOBALS['payment_record_existing_order'] = false;
function wc_get_orders($args) { return (object) ['orders' => [], 'max_num_pages' => 1]; }
function wc_get_order($id) { return $GLOBALS['payment_record_existing_order']; }
class SuperWoo_Order_Recovery_Provider {
    public static $next;
    public function verify($payment_id) { return self::$next; }
    public static function correlate($record, $result, $match) { return $result; }
}
class SuperWoo_Order_Recovery_Repository {
    public static $fresh;
    public function transaction($id) { return self::$fresh; }
}
class WC_Order {
    public static $created = [];
    public static $throw_after_save = false;
    public $fields = [];
    public $meta = [];
    public function set_status($value) { $this->fields['status'] = $value; }
    public function set_created_via($value) { $this->fields['created_via'] = $value; }
    public function set_currency($value) { $this->fields['currency'] = $value; }
    public function set_payment_method($value) { $this->fields['payment_method'] = $value; }
    public function set_payment_method_title($value) { $this->fields['payment_method_title'] = $value; }
    public function set_transaction_id($value) { $this->fields['transaction_id'] = $value; }
    public function set_total($value) { $this->fields['total'] = $value; }
    public function set_billing_email($value) { $this->fields['billing_email'] = $value; }
    public function set_billing_phone($value) { $this->fields['billing_phone'] = $value; }
    public function update_meta_data($key, $value) { $this->meta[$key] = $value; }
    public function save() { self::$created[] = $this; if (self::$throw_after_save) { throw new Exception('simulated post-save interruption'); } return 9000 + count(self::$created); }
}
require dirname(__DIR__) . '/includes/order-recovery/class-order-recovery-classifier.php';
require dirname(__DIR__) . '/includes/order-recovery/class-order-recovery-payment-record.php';

$checks = 0;
$assert = static function ($ok, $message) use (&$checks) { $checks++; if (!$ok) { fwrite(STDERR, "FAIL: {$message}\n"); exit(1); } };
$record = ['id'=>7,'payment_id'=>'pay_abc12345','wc_order_id'=>8051,'wc_order_number'=>'8051','source_row_hash'=>str_repeat('a',64),'reported_status'=>'captured','currency'=>'INR','gross_minor'=>384500,'notes_json'=>'{}','description'=>'Order 8051','duplicate_count'=>0,'customer_email'=>'buyer@example.test','customer_phone'=>'+911234567890'];
$provider = ['state'=>'verified','status'=>'captured','captured'=>true,'refund_evidence_complete'=>true,'amount_refunded_minor'=>0,'refunds'=>[],'currency'=>'INR','amount_minor'=>384500,'created_at_utc'=>'2026-10-05 16:32:22'];
SuperWoo_Order_Recovery_Provider::$next = $provider;
SuperWoo_Order_Recovery_Repository::$fresh = $record;
$GLOBALS['payment_record_admin'] = false;
$forbidden = SuperWoo_Order_Recovery_Payment_Record::create($record, [], 19);
$assert(is_wp_error($forbidden) && $forbidden->get_error_code() === 'payment_record_forbidden', 'a non-admin cannot create a review order');
$GLOBALS['payment_record_admin'] = true;
$created = SuperWoo_Order_Recovery_Payment_Record::create($record, [], 19);
$assert($created === 9001 && count(WC_Order::$created) === 1, 'a verified captured payment creates one review order');
$order = WC_Order::$created[0];
$assert($order->fields['status'] === 'superwoo-review' && $order->fields['total'] === '3845.00' && $order->fields['transaction_id'] === $record['payment_id'], 'review order keeps exact provider amount and payment ID');
$assert($order->meta['_superwoo_recovery_historical_order_id'] === 8051 && $order->meta['_superwoo_recovery_items_missing'] === 'yes' && $order->meta['_superwoo_recovery_payment_only'] === 'yes', 'historical reference and missing items are explicit');
$assert(!property_exists($order, 'items') && SuperWoo_Order_Recovery_Payment_Record::claim($record['payment_id'])['order_id'] === 9001, 'no item is fabricated and the claim records the new order ID');
$same_history = $record; $same_history['payment_id'] = 'pay_samehistory123';
$blocked = SuperWoo_Order_Recovery_Payment_Record::create($same_history, [], 19);
$assert(is_wp_error($blocked) && $blocked->get_error_code() === 'payment_record_historical_id_claimed' && count(WC_Order::$created) === 1, 'another payment cannot create a second review order for the same historical ID');
$again = SuperWoo_Order_Recovery_Payment_Record::create($record, [], 19);
$assert(is_wp_error($again) && $again->get_error_code() === 'payment_record_already_claimed' && count(WC_Order::$created) === 1, 'repeat approval cannot create a duplicate');
$workshop = $record; $workshop['payment_id'] = 'pay_workshop123'; $workshop['description'] = 'Workshop Purchase';
$blocked = SuperWoo_Order_Recovery_Payment_Record::create($workshop, [], 19);
$assert(is_wp_error($blocked) && $blocked->get_error_code() === 'payment_record_not_missing_store_order', 'Workshop payment remains outside order creation');
$unreferenced = $record; $unreferenced['payment_id'] = 'pay_unref123'; $unreferenced['wc_order_id'] = 0;
$blocked = SuperWoo_Order_Recovery_Payment_Record::create($unreferenced, [], 19);
$assert(is_wp_error($blocked) && $blocked->get_error_code() === 'payment_record_missing_reference', 'payment without a historical order ID is held for manual review');
$refunded = $record; $refunded['payment_id'] = 'pay_refund123'; SuperWoo_Order_Recovery_Provider::$next = array_merge($provider, ['amount_refunded_minor'=>100]);
$blocked = SuperWoo_Order_Recovery_Payment_Record::create($refunded, [], 19);
$assert(is_wp_error($blocked) && $blocked->get_error_code() === 'payment_record_provider_conflict', 'refunded payment cannot create a review order');
SuperWoo_Order_Recovery_Provider::$next = $provider;
$changed = $record; $changed['payment_id'] = 'pay_changed123'; $changed['wc_order_id'] = 8052; SuperWoo_Order_Recovery_Repository::$fresh = array_merge($changed, ['source_row_hash'=>str_repeat('b',64)]);
$blocked = SuperWoo_Order_Recovery_Payment_Record::create($changed, [], 19);
$assert(is_wp_error($blocked) && $blocked->get_error_code() === 'payment_record_source_changed' && SuperWoo_Order_Recovery_Payment_Record::claim($changed['payment_id'])['state'] === 'claimed', 'changed import leaves a persistent claim for manual reconciliation');
$assert(count(WC_Order::$created) === 1, 'blocked cases do not create orders');
$interrupted = $record; $interrupted['payment_id'] = 'pay_interrupt123'; $interrupted['wc_order_id'] = 8053; SuperWoo_Order_Recovery_Repository::$fresh = $interrupted;
WC_Order::$throw_after_save = true;
$blocked = SuperWoo_Order_Recovery_Payment_Record::create($interrupted, [], 19);
WC_Order::$throw_after_save = false;
$assert(is_wp_error($blocked) && $blocked->get_error_code() === 'payment_record_save_failed' && SuperWoo_Order_Recovery_Payment_Record::claim($interrupted['payment_id'])['state'] === 'claimed', 'post-save interruption leaves a persistent claim');
$again = SuperWoo_Order_Recovery_Payment_Record::create($interrupted, [], 19);
$assert(is_wp_error($again) && $again->get_error_code() === 'payment_record_already_claimed', 'interrupted approval cannot blindly create a duplicate');
echo "PASS: {$checks} payment-only recovery checks\n";
