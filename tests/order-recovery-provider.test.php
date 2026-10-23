<?php
define('ABSPATH', __DIR__ . '/');
class WP_Error { private $code; public function __construct($code, $message = '', $data = null) { $this->code = $code; } public function get_error_code() { return $this->code; } }
function is_wp_error($value) { return $value instanceof WP_Error; }
function sanitize_text_field($value) { return trim(strip_tags((string) $value)); }
function sanitize_key($value) { return strtolower(preg_replace('/[^a-z0-9_\-]/', '', (string) $value)); }
function absint($value) { return abs((int) $value); }
function wp_safe_remote_get($url, $args) { $GLOBALS['requests'][] = [$url, $args]; return array_shift($GLOBALS['responses']); }
function wp_remote_retrieve_response_code($response) { return $response['code']; }
function wp_remote_retrieve_body($response) { return $response['body']; }
require dirname(__DIR__) . '/includes/order-recovery/class-order-recovery-provider.php';
$checks = 0;
$assert = static function ($condition, $message) use (&$checks) { $checks++; if (!$condition) { fwrite(STDERR, "FAIL: {$message}\n"); exit(1); } };
$response = static function ($code, $body) { return ['code'=>$code,'body'=>is_string($body) ? $body : json_encode($body)]; };
$GLOBALS['requests'] = [];
$GLOBALS['responses'] = [$response(200, ['id'=>'pay_12345','amount'=>234100,'currency'=>'INR','status'=>'captured','captured'=>true,'amount_refunded'=>100,'refund_status'=>'partial','order_id'=>'order_abc','created_at'=>1735689600]), $response(200, ['items'=>[['id'=>'rfnd_12345','payment_id'=>'pay_12345','amount'=>100,'currency'=>'INR','status'=>'processed','created_at'=>1735689601]]])];
$verified = (new SuperWoo_Order_Recovery_Provider('rzp_test','secret'))->verify('pay_12345');
$assert(!is_wp_error($verified) && $verified['state']==='verified' && $verified['amount_minor']===234100 && $verified['amount_refunded_minor']===100, 'verified payment returns normalized amounts, currency, and refunds');
$assert($verified['refund_evidence_complete'] === true, 'short final refund page proves the refund listing ended');
$assert(count($GLOBALS['requests'])===2 && strpos($GLOBALS['requests'][0][0], '/payments/pay_12345')!==false && strpos($GLOBALS['requests'][1][0], '/refunds?count=100&skip=0')!==false, 'provider verification uses payment and paginated refunds GET endpoints');
$full_refund_page = ['items'=>[]];
for ($i = 0; $i < 100; $i++) { $full_refund_page['items'][] = ['id'=>'rfnd_' . $i,'payment_id'=>'pay_12345','amount'=>1,'currency'=>'INR','status'=>'processed']; }
$GLOBALS['responses'] = [$response(200, ['id'=>'pay_12345','amount'=>300,'currency'=>'INR','status'=>'captured','captured'=>true,'amount_refunded'=>300]), $response(200,$full_refund_page), $response(200,$full_refund_page), $response(200,$full_refund_page)];
$truncated_refunds = (new SuperWoo_Order_Recovery_Provider('rzp_test','secret'))->verify('pay_12345');
$assert(!is_wp_error($truncated_refunds) && $truncated_refunds['refund_evidence_complete'] === false, 'a full final page at the pagination ceiling remains explicitly incomplete');
$GLOBALS['responses'] = [$response(200, ['id'=>'pay_12345','amount'=>100,'currency'=>'INR','status'=>'captured','amount_refunded'=>0]), $response(200, ['items'=>[['id'=>'rfnd_pending','payment_id'=>'pay_12345','amount'=>50,'currency'=>'INR','status'=>'pending']]])];
$pending_refund = (new SuperWoo_Order_Recovery_Provider('rzp_test','secret'))->verify('pay_12345');
$assert(!is_wp_error($pending_refund) && $pending_refund['refund_evidence_complete'] === false, 'unresolved refund state blocks refund completeness');
$GLOBALS['responses'] = [$response(200, ['id'=>'pay_12345','amount'=>100,'currency'=>'INR','status'=>'captured','amount_refunded'=>0]), $response(200, ['items'=>[['id'=>'rfnd_done','payment_id'=>'pay_12345','amount'=>50,'currency'=>'INR','status'=>'processed']]])];
$refund_total_mismatch = (new SuperWoo_Order_Recovery_Provider('rzp_test','secret'))->verify('pay_12345');
$assert(!is_wp_error($refund_total_mismatch) && $refund_total_mismatch['refund_evidence_complete'] === false, 'refund rows inconsistent with payment aggregate remain incomplete');
$assert($GLOBALS['requests'][0][1]['timeout']===10 && $GLOBALS['requests'][0][1]['redirection']===0 && $GLOBALS['requests'][0][1]['sslverify']===true, 'requests are bounded, nonredirecting, and TLS verified');
$GLOBALS['responses'] = [$response(401, ['error'=>['description'=>'secret details']])];
$auth = (new SuperWoo_Order_Recovery_Provider('rzp_test','secret'))->verify('pay_12345');
$assert(is_wp_error($auth) && $auth->get_error_code()==='provider_auth_error', 'authentication failure is a safe provider error');
$GLOBALS['responses'] = [$response(404, ['error'=>['description'=>'private provider detail']])];
$missing_payment = (new SuperWoo_Order_Recovery_Provider('rzp_test','secret'))->verify('pay_12345');
$assert(is_wp_error($missing_payment) && $missing_payment->get_error_code()==='provider_not_found', 'provider not-found response remains unverified evidence');
$GLOBALS['responses'] = [$response(429, [])];
$limited = (new SuperWoo_Order_Recovery_Provider('rzp_test','secret'))->verify('pay_12345');
$assert(is_wp_error($limited) && $limited->get_error_code()==='provider_rate_limited', 'rate limit is not interpreted as payment failure');
$GLOBALS['responses'] = [new WP_Error('timeout','raw transport detail')];
$timeout = (new SuperWoo_Order_Recovery_Provider('rzp_test','secret'))->verify('pay_12345');
$assert(is_wp_error($timeout) && $timeout->get_error_code()==='provider_transport_error', 'timeouts remain nonterminal provider errors');
$GLOBALS['responses'] = [$response(200, ['id'=>'pay_other','amount'=>100,'currency'=>'INR','status'=>'failed']), $response(200, ['items'=>[]])];
$mismatch = (new SuperWoo_Order_Recovery_Provider('rzp_test','secret'))->verify('pay_12345');
$assert(is_wp_error($mismatch) && $mismatch->get_error_code()==='provider_id_mismatch', 'a different returned provider identifier is rejected');
$GLOBALS['responses'] = [$response(200, ['id'=>'pay_12345','amount'=>100,'currency'=>'INR','status'=>'failed','amount_refunded'=>0]), $response(200, ['items'=>[]])];
$explicit_failed = (new SuperWoo_Order_Recovery_Provider('rzp_test','secret'))->verify('pay_12345');
$assert(!is_wp_error($explicit_failed) && $explicit_failed['status']==='failed' && empty($explicit_failed['captured']), 'only a successful exact provider response can report an explicit failed status');
$assert(is_wp_error((new SuperWoo_Order_Recovery_Provider('', ''))->verify('pay_12345')), 'missing credentials prevent provider requests');
$base_record = ['currency'=>'INR','gross_minor'=>234100,'razorpay_order_id'=>'order_abc','wc_order_id'=>0];
$base_provider = ['amount_minor'=>234100,'currency'=>'INR','order_id'=>'order_abc'];
$exact = SuperWoo_Order_Recovery_Provider::correlate($base_record, $base_provider, null);
$assert($exact['state']==='verified' && $exact['match_result']==='payment_id_exact_provider_order_only', 'matching payment evidence without Woo order reference stays provider-only');
$conflicted = SuperWoo_Order_Recovery_Provider::correlate($base_record, ['amount_minor'=>200000,'currency'=>'USD','order_id'=>'order_other'], null);
$assert($conflicted['state']==='verified_conflict' && strpos($conflicted['match_result'], 'amount_mismatch')!==false && strpos($conflicted['match_result'], 'currency_mismatch')!==false && strpos($conflicted['match_result'], 'provider_order_id_mismatch')!==false, 'amount, currency, and provider order-reference mismatches become explicit conflicts');
$ambiguous = SuperWoo_Order_Recovery_Provider::correlate($base_record, $base_provider, ['ambiguous'=>true]);
$assert($ambiguous['state']==='verified_conflict' && strpos($ambiguous['match_result'], 'multiple_woocommerce_orders')!==false, 'ambiguous WooCommerce payment-ID match becomes a conflict');
class Recovery_Provider_Order { private $id; public function __construct($id) { $this->id=$id; } public function get_id() { return $this->id; } }
$wc_match = SuperWoo_Order_Recovery_Provider::correlate($base_record, $base_provider, ['order'=>new Recovery_Provider_Order(8319)]);
$assert($wc_match['state']==='verified' && $wc_match['match_result']==='payment_id_exact_wc_order_8319', 'exact payment ID plus WooCommerce CRUD match is recorded explicitly');
$reference_conflict_record = $base_record; $reference_conflict_record['wc_order_id']=999;
$reference_conflict = SuperWoo_Order_Recovery_Provider::correlate($reference_conflict_record, $base_provider, ['order'=>new Recovery_Provider_Order(8319)]);
$assert($reference_conflict['state']==='verified_conflict' && strpos($reference_conflict['match_result'], 'woocommerce_order_reference_mismatch')!==false, 'conflicting imported WooCommerce order reference stays reviewable');
echo "PASS: {$checks} provider verification checks\n";
