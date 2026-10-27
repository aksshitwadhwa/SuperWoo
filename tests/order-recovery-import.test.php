<?php
define('ABSPATH', __DIR__ . '/');

class WP_Error { private $code; public function __construct($code, $message = '') { $this->code = $code; } public function get_error_code() { return $this->code; } }
function is_wp_error($value) { return $value instanceof WP_Error; }
function __($text) { return $text; }
function sanitize_text_field($value) { return trim(strip_tags((string) $value)); }
function sanitize_textarea_field($value) { return sanitize_text_field($value); }
function sanitize_email($value) { return filter_var((string) $value, FILTER_VALIDATE_EMAIL) ? (string) $value : ''; }
function sanitize_key($value) { return strtolower(preg_replace('/[^a-z0-9_\-]/', '', (string) $value)); }
function absint($value) { return abs((int) $value); }
function wp_json_encode($value) { return json_encode($value); }

require dirname(__DIR__) . '/includes/order-recovery/class-order-recovery-csv.php';
require dirname(__DIR__) . '/includes/order-recovery/class-order-recovery-classifier.php';

$checks = 0;
$assert = static function ($condition, $message) use (&$checks) { $checks++; if (!$condition) { fwrite(STDERR, "FAIL: {$message}\n"); exit(1); } };

class Recovery_Test_Order {
    private $id; private $transaction; private $total; private $currency; private $number;
    public function __construct($id, $transaction = '', $total = 0, $currency = 'INR', $number = '') { $this->id = $id; $this->transaction = $transaction; $this->total = $total; $this->currency = $currency; $this->number = $number ?: (string) $id; }
    public function get_id() { return $this->id; }
    public function get_transaction_id() { return $this->transaction; }
    public function get_total() { return $this->total; }
    public function get_currency() { return $this->currency; }
    public function get_order_number() { return $this->number; }
}
$GLOBALS['recovery_test_orders'] = [8319 => new Recovery_Test_Order(8319, 'pay_known', 2285.76, 'INR', 'SW-8319')];
function wc_get_orders($args) {
    if (!empty($GLOBALS['recovery_test_lookup_failure'])) { return []; }
    $found = [];
    foreach ($GLOBALS['recovery_test_orders'] as $order) {
        if (isset($args['transaction_id']) && $order->get_transaction_id() === $args['transaction_id']) { $found[$order->get_id()] = $order; }
        if (isset($args['search']) && strpos($order->get_order_number(), $args['search']) !== false) { $found[$order->get_id()] = $order; }
    }
    $found = array_values($found); $per_page = (int) ($args['limit'] ?? 100); $page = (int) ($args['page'] ?? 1);
    return (object) ['orders'=>array_slice($found, ($page - 1) * $per_page, $per_page), 'max_num_pages'=>max(1, (int) ceil(count($found) / $per_page))];
}
function wc_get_order($id) { return $GLOBALS['recovery_test_orders'][(int) $id] ?? false; }

class Recovery_Test_Repository {
    public $rows = [];
    public function add_transaction($batch_id, $row_number, $row, $duplicate) {
        $key = $row['payment_id'] ? hash('sha256', 'razorpay-payment:' . strtolower($row['payment_id'])) : hash('sha256', 'razorpay-row:' . $row['source_row_hash']);
        $new = !isset($this->rows[$key]);
        if ($duplicate) { $row['classification'] = 'duplicate_ambiguous'; }
        $this->rows[$key] = $row;
        return ['id' => count($this->rows), 'new' => $new, 'duplicate' => !$new || $duplicate];
    }
}

$tmp = tempnam(sys_get_temp_dir(), 'superwoo-recovery-');
file_put_contents($tmp, "\xEF\xBB\xBFRazorpay Payment ID,Razorpay Order ID,WooCommerce Order ID,Status,Amount,Fee,Fee GST,Refund,Created At,Currency,Notes,Email,Contact,Notes:checkout_source\n" .
    "pay_known,rz_known,,captured,2341.00,55.24,9.94,0,2025-02-01T10:30:00+05:30,INR,campaign=store,buyer@example.test,+919876543210,store\n" .
    "pay_missing,rz_missing,99999,captured,100.00,2.00,0.36,0,2025-02-02 08:00:00,INR,,,,\n" .
    ",,,captured,25.00,0.50,0.09,0,2025-02-03T10:00:00Z,INR,,,,");
$parsed = SuperWoo_Order_Recovery_CSV::parse($tmp);
$assert(!is_wp_error($parsed), 'valid UTF-8 Razorpay CSV should parse');
$assert(count($parsed['rows']) === 3, 'parser should keep all nonblank records');
$known = $parsed['rows'][0];
$assert($known['gross_minor'] === 234100 && $known['fee_minor'] === 5524 && $known['fee_gst_minor'] === 994, 'decimal currency values should normalize to integer minor units');
$assert($known['paid_at_utc'] === '2025-02-01 05:00:00', 'timezone-aware timestamps should normalize to UTC');
$assert($known['customer_email'] === 'buyer@example.test' && $known['customer_phone'] === '+919876543210', 'customer contact fields should normalize');
$assert($known['notes']['checkout_source'] === 'store', 'notes exported in a separate metadata column should normalize');
$assert(in_array('payment_id', $parsed['rows'][2]['missing_fields'], true) && in_array('razorpay_order_id', $parsed['rows'][2]['missing_fields'], true), 'missing identifiers should be listed explicitly');

$match = SuperWoo_Order_Recovery_Classifier::find_existing_order('pay_known', 0);
$known_class = SuperWoo_Order_Recovery_Classifier::classify($known, $match);
$assert($known_class['classification'] === 'existing_order', 'exact transaction ID lookup should identify an existing order');
$number_match = SuperWoo_Order_Recovery_Classifier::find_existing_order('', 0, 'SW-8319');
$assert(!empty($number_match['order']) && $number_match['match'] === 'order_number', 'exact WooCommerce order number should match through WooCommerce CRUD query');
$GLOBALS['recovery_test_orders'] = [];
for ($i = 1; $i <= 100; $i++) { $GLOBALS['recovery_test_orders'][$i] = new Recovery_Test_Order($i, 'other_' . $i, 1, 'INR', 'SW-PAGE-' . $i); }
$GLOBALS['recovery_test_orders'][101] = new Recovery_Test_Order(101, 'pay_on_page_two', 1, 'INR', 'SW-PAGE');
$paged_match = SuperWoo_Order_Recovery_Classifier::find_existing_order('', 0, 'SW-PAGE');
$assert(!empty($paged_match['order']) && $paged_match['order']->get_id() === 101, 'exact order-number match is found after paginating beyond the first page of search results');
$GLOBALS['recovery_test_orders'][102] = new Recovery_Test_Order(102, 'pay_duplicate_all_pages', 1, 'INR', 'DUP-A');
for ($i = 103; $i <= 203; $i++) { $GLOBALS['recovery_test_orders'][$i] = new Recovery_Test_Order($i, 'pay_duplicate_all_pages', 1, 'INR', 'DUP-' . $i); }
$paged_ambiguous = SuperWoo_Order_Recovery_Classifier::find_existing_order('pay_duplicate_all_pages', 0);
$assert(!empty($paged_ambiguous['ambiguous']), 'duplicate transaction IDs across multiple result pages remain ambiguous');
$GLOBALS['recovery_test_lookup_failure'] = true;
$lookup_failure = SuperWoo_Order_Recovery_Classifier::find_existing_order('pay_any', 0);
$assert(!empty($lookup_failure['conflict']) && $lookup_failure['match'] === 'payment_id_lookup_incomplete', 'incomplete WooCommerce lookup fails closed as a conflict');
unset($GLOBALS['recovery_test_lookup_failure']);
$GLOBALS['recovery_test_orders'] = [8319 => new Recovery_Test_Order(8319, 'pay_known', 2285.76, 'INR', 'SW-8319')];
$known = SuperWoo_Order_Recovery_Classifier::attach_order_totals($known, $match['order']);
$assert($known['wc_total_minor'] === 228576 && $known['reconciled_difference_minor'] === 5524, 'fee-sized discrepancy should remain a separate gross-versus-order delta');
$assert($known['fee_minor'] === 5524, 'Razorpay fee should not be subtracted from captured customer payment');

$missing = $parsed['rows'][1];
$missing_match = SuperWoo_Order_Recovery_Classifier::find_existing_order($missing['payment_id'], $missing['wc_order_id']);
$missing_class = SuperWoo_Order_Recovery_Classifier::classify($missing, $missing_match);
$assert($missing_class['classification'] === 'missing_order_candidate' && strpos($missing_class['reason'], 'unverified') !== false, 'CSV-reported captured state should only produce an unverified candidate');
$failed = $missing; $failed['reported_status'] = 'failed';
$assert(SuperWoo_Order_Recovery_Classifier::classify($failed)['classification'] === 'manual_review', 'CSV-reported failed status must remain unverified manual review');
$workshop = $missing; $workshop['receipt'] = 'WS-2025-44';
$assert(SuperWoo_Order_Recovery_Classifier::classify($workshop, null, ['receipt_prefixes' => ['WS-']])['classification'] === 'workshop_excluded', 'configured exact Workshop receipt prefix should exclude');
$assert(SuperWoo_Order_Recovery_Classifier::classify($workshop, $match, ['receipt_prefixes' => ['WS-']])['classification'] === 'existing_order', 'a matched WooCommerce order remains visible even when the payment has a Workshop receipt');
$workshop_meta = $missing; $workshop_meta['notes'] = ['checkout_source' => 'workshop_checkout'];
$assert(SuperWoo_Order_Recovery_Classifier::classify($workshop_meta, null, ['checkout_sources' => ['workshop_checkout']])['classification'] === 'workshop_excluded', 'configured exact checkout source should exclude');
$keyword = $missing; $keyword['description'] = 'Workshop registration';
$assert(SuperWoo_Order_Recovery_Classifier::classify($keyword)['classification'] === 'manual_review', 'generic Workshop wording should only flag manual review');
$amount_only = $parsed['rows'][2]; $amount_only['receipt'] = ''; $amount_only['wc_order_id'] = 0;
$assert(SuperWoo_Order_Recovery_Classifier::classify($amount_only)['classification'] === 'manual_review', 'amount/customer data alone must not establish a match');

$repo = new Recovery_Test_Repository();
$counts = SuperWoo_Order_Recovery_CSV::import_rows([$missing, $missing], $repo, 1);
$assert(count($repo->rows) === 1 && $counts['duplicates'] === 1 && reset($repo->rows)['classification'] === 'duplicate_ambiguous', 'repeated provider ID in one CSV should be idempotent and flagged ambiguous');
$repeat = SuperWoo_Order_Recovery_CSV::import_rows([$missing], $repo, 2);
$assert(count($repo->rows) === 1 && $repeat['updated'] === 1, 're-importing an already seen payment ID must not create a second record');

file_put_contents($tmp, "Payment ID;Status;Amount;Currency\npay_semicolon;captured;12.50;INR\n");
$semicolon = SuperWoo_Order_Recovery_CSV::parse($tmp);
$assert(!is_wp_error($semicolon) && $semicolon['rows'][0]['gross_minor'] === 1250, 'semicolon-delimited exports should be accepted');
file_put_contents($tmp, "Payment ID,Status,Amount,Refund ID,Refund Status,Refund Date\npay_refund,refunded,20.00,rfnd_123,processed,2025-02-04T10:00:00Z\n");
$refunds = SuperWoo_Order_Recovery_CSV::parse($tmp);
$assert(!is_wp_error($refunds) && $refunds['rows'][0]['refund_reference'] === 'rfnd_123' && $refunds['rows'][0]['refund_status'] === 'processed', 'refund identifiers and states should be retained separately');
file_put_contents($tmp, "Payment ID,Status,Amount,Created At\npay_timezone,captured,12.34,2025-02-05 12:30:00\n");
$timezone = SuperWoo_Order_Recovery_CSV::parse($tmp);
$assert(!is_wp_error($timezone) && in_array('Timestamp had no timezone; interpreted as UTC.', $timezone['rows'][0]['data_warnings'], true), 'timezone-less timestamps should be explicitly warned as assumed UTC');
file_put_contents($tmp, "Razorpay Payment ID,Razorpay Order ID,Razorpay Amount,Currency,Payment Status,Amount Refunded in Payment Export,Refund Status in Payment Export,Payment Created At,Razorpay Description,WooCommerce Order ID in Notes,WooCommerce Order Number in Notes,Customer Email in Payment Export,Customer Contact in Payment Export\n" .
    "pay_recon,rz_recon,3845.0,INR,captured,0.0,,05/10/2026 16:32:22,Store purchase,8051.0,8051.0,,\n");
$reconciliation = SuperWoo_Order_Recovery_CSV::parse($tmp);
$assert(!is_wp_error($reconciliation), 'actual reconciliation export header format should parse');
$recon_row = $reconciliation['rows'][0];
$assert($recon_row['gross_minor'] === 384500 && $recon_row['wc_order_id'] === 8051 && $recon_row['wc_order_number'] === '8051', 'reconciliation amounts and spreadsheet-formatted WooCommerce IDs should normalize exactly');
$assert($recon_row['paid_at_utc'] === '2026-10-05 16:32:22' && $recon_row['description'] === 'Store purchase', 'day-first reconciliation date and description should be retained');
file_put_contents($tmp, "id,order_id,amount,currency,status,created_at,description,notes,WooCommerce Order ID in Razorpay Notes,WooCommerce Order Number in Razorpay Notes\n" .
    "pay_raw,rz_raw,794.0,INR,captured,02/10/2026 10:33:40,Order 8002,,8002.0,8002.0\n");
$raw_missing = SuperWoo_Order_Recovery_CSV::parse($tmp);
$assert(!is_wp_error($raw_missing) && $raw_missing['rows'][0]['wc_order_id'] === 8002 && $raw_missing['rows'][0]['gross_minor'] === 79400, '47-row raw payment format should retain exact WooCommerce reference and rupee amount');
file_put_contents($tmp, "type,debit,credit,settlement_id,payment_id,amount\npayment,0,100,,pay_ledger,100\n");
$mixed_ledger = SuperWoo_Order_Recovery_CSV::parse($tmp);
$assert(is_wp_error($mixed_ledger) && $mixed_ledger->get_error_code() === 'recovery_csv_mixed_ledger', 'mixed settlement ledger must not be imported as a payment-only report');
file_put_contents($tmp, "Payment ID,Status,Amount\npay_no_currency,captured,12.34\n");
$no_currency = SuperWoo_Order_Recovery_CSV::parse($tmp);
$assert(!is_wp_error($no_currency) && $no_currency['rows'][0]['gross_minor'] === null && count($no_currency['rows'][0]['data_warnings']) === 1, 'amounts without currency must not be assigned a minor-unit value');
file_put_contents($tmp, "Random,Unknown\na,b\n");
$malformed = SuperWoo_Order_Recovery_CSV::parse($tmp);
$assert(is_wp_error($malformed) && $malformed->get_error_code() === 'recovery_csv_header', 'unrecognized headers should be rejected');
file_put_contents($tmp, "Payment ID,Status,Amount\n\xFF,paid,1\n");
$encoding = SuperWoo_Order_Recovery_CSV::parse($tmp);
$assert(is_wp_error($encoding) && $encoding->get_error_code() === 'recovery_csv_encoding', 'invalid UTF-8 should be rejected');
file_put_contents($tmp, "Payment ID,Status\nvalid,captured,extra\n");
$bad_row = SuperWoo_Order_Recovery_CSV::parse($tmp);
$assert(is_wp_error($bad_row) && $bad_row->get_error_code() === 'recovery_csv_malformed', 'rows with extra columns should be rejected');
unlink($tmp);

echo 'PASS: ' . $checks . " order-recovery import assertions\n";
