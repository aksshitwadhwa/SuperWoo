<?php
define('ABSPATH', __DIR__ . '/');
define('ARRAY_A', 'ARRAY_A');
function absint($value) { return abs((int) $value); }
function sanitize_key($value) { return strtolower(preg_replace('/[^a-z0-9_\-]/', '', (string) $value)); }
function sanitize_text_field($value) { return trim(strip_tags((string) $value)); }
function wp_json_encode($value) { return json_encode($value); }
class WP_Error { public function get_error_code() { return 'test_error'; } }
function is_wp_error($value) { return $value instanceof WP_Error; }

class Recovery_Repository_DB {
    public $prefix = 'wp_'; public $insert_id = 0; public $rows = []; public $last_count_query = '';
    public function esc_like($value) { return addcslashes((string) $value, '_%\\'); }
    public function prepare($query, ...$values) {
        if (count($values) === 1 && is_array($values[0])) { $values = $values[0]; }
        foreach ($values as $value) {
            $replacement = is_int($value) ? (string) $value : "'" . addslashes((string) $value) . "'";
            $query = preg_replace('/%[sd]/', $replacement, $query, 1);
        }
        return $query;
    }
    public function get_row($query, $format = null) {
        if (preg_match("/idempotency_key='([^']+)'/", $query, $m)) {
            foreach ($this->rows as $row) { if ($row['idempotency_key'] === stripslashes($m[1])) { return ['id'=>$row['id'],'duplicate_count'=>$row['duplicate_count']]; } }
        }
        return null;
    }
    public function get_var($query) {
        if (strpos($query, 'SELECT COUNT(*)') === 0) { $this->last_count_query = $query; return 0; }
        if (preg_match('/source_row_hash FROM [^ ]+ WHERE id=(\d+)/', $query, $m)) { return $this->rows[(int)$m[1]]['source_row_hash'] ?? null; }
        if (preg_match("/id FROM [^ ]+ WHERE idempotency_key='([^']+)'/", $query, $m)) {
            foreach ($this->rows as $row) { if ($row['idempotency_key'] === stripslashes($m[1])) { return $row['id']; } }
        }
        return null;
    }
    public function insert($table, $data) { $id = ++$this->insert_id; $data['id'] = $id; $this->rows[$id] = $data; return 1; }
    public function update($table, $data, $where) { $id = (int)$where['id']; $this->rows[$id] = array_merge($this->rows[$id], $data); return 1; }
    public function query($query) { return 1; }
    public function get_results($query, $format = null) { return []; }
}
$GLOBALS['wpdb'] = new Recovery_Repository_DB();
require dirname(__DIR__) . '/includes/order-recovery/class-order-recovery-schema.php';
require dirname(__DIR__) . '/includes/order-recovery/class-order-recovery-repository.php';

$assertions = 0;
$assert = static function ($ok, $message) use (&$assertions) { $assertions++; if (!$ok) { fwrite(STDERR, "FAIL: {$message}\n"); exit(1); } };
$row = [
    'payment_id'=>'pay_12345','razorpay_order_id'=>'rz_12345','wc_order_id'=>0,'wc_order_number'=>'','receipt'=>'',
    'description'=>'','notes'=>[],'reported_status'=>'captured','classification'=>'missing_order_candidate','classification_reason'=>'test',
    'currency'=>'INR','gross_minor'=>1000,'wc_total_minor'=>null,'fee_minor'=>null,'fee_gst_minor'=>null,'refund_minor'=>0,
    'refund_reference'=>'','refund_status'=>'','refund_at_utc'=>null,'reconciled_difference_minor'=>null,'paid_at_utc'=>null,
    'customer_email'=>'','customer_phone'=>'','missing_fields'=>[],'data_warnings'=>[],'source_row_hash'=>str_repeat('a',64),'source_values'=>[],
];
$repo = new SuperWoo_Order_Recovery_Repository();
$saved = $repo->add_transaction(1, 1, $row, false);
$assert(!is_wp_error($saved), 'initial evidence row is stored');
$GLOBALS['wpdb']->update('', [
    'verification_state'=>'verified','verified_at_utc'=>'2026-10-23 00:00:00','provider_status'=>'captured',
    'provider_currency'=>'INR','provider_amount_minor'=>1000,'provider_amount_refunded_minor'=>0,
    'provider_refund_status'=>'none','provider_refunds_complete'=>1,'provider_captured'=>1,
    'provider_order_id'=>'rz_12345','provider_match_result'=>'verified','provider_created_at_utc'=>'2026-10-22 00:00:00',
    'provider_refunds_json'=>'[]','verification_error'=>'',
], ['id'=>$saved['id']]);
$changed = $row; $changed['source_row_hash'] = str_repeat('b',64); $changed['gross_minor'] = 1200;
$repo->add_transaction(2, 1, $changed, false);
$stored = $GLOBALS['wpdb']->rows[$saved['id']];
$assert($stored['verification_state']==='unverified' && $stored['verified_at_utc']===null, 'changed import invalidates the prior verification timestamp and state');
$assert($stored['provider_status']==='' && $stored['provider_amount_minor']===null && $stored['provider_refunds_complete']===0 && $stored['provider_refunds_json']===null, 'changed import clears every stale provider and refund fact');
$repo->transactions(['missing_wc_only'=>true, 'classification'=>'missing_order_candidate', 'per_page'=>20]);
$filtered_query = $GLOBALS['wpdb']->last_count_query;
$assert(strpos($filtered_query, 'wc_order_id > 0') !== false && strpos($filtered_query, 'recovered_order_id = 0') !== false && strpos($filtered_query, "classification='missing_order_candidate'") !== false, 'missing WooCommerce filter requires an original order ID, missing classification and no recovered order');
$assert(strpos($filtered_query, 'workshop') !== false && strpos($filtered_query, 'masterclass') !== false && strpos($filtered_query, 'webinar') !== false, 'missing WooCommerce filter excludes Workshop wording');
echo "PASS: {$assertions} order-recovery repository assertions\n";
