<?php
define('ABSPATH', __DIR__ . '/');
function absint($value) { return abs((int) $value); }
function is_email($value) { return (bool) filter_var((string) $value, FILTER_VALIDATE_EMAIL); }
class WP_Error { public function __construct($code, $message = '') {} }
function is_wp_error($value) { return $value instanceof WP_Error; }
$GLOBALS['preview_products'] = [10 => (object) ['id'=>10], 11 => (object) ['id'=>11]];
function wc_get_product($id) { return $GLOBALS['preview_products'][(int) $id] ?? false; }
function wc_get_orders($args) { return (object) ['orders'=>[], 'max_num_pages'=>1]; }
function wc_get_order($id) { return false; }
require dirname(__DIR__) . '/includes/order-recovery/class-order-recovery-preview.php';
require dirname(__DIR__) . '/includes/order-recovery/class-order-recovery-classifier.php';
$checks = 0;
$assert = static function ($condition, $message) use (&$checks) { $checks++; if (!$condition) { fwrite(STDERR, "FAIL: {$message}\n"); exit(1); } };
$base = ['id'=>1,'classification'=>'missing_order_candidate','duplicate_count'=>0,'payment_id'=>'pay_12345','razorpay_order_id'=>'order_12345','wc_order_id'=>0,'wc_order_number'=>'','verification_state'=>'verified','provider_status'=>'captured','provider_captured'=>1,'provider_refunds_complete'=>1,'provider_currency'=>'INR','provider_amount_minor'=>10000,'provider_amount_refunded_minor'=>0,'provider_refund_status'=>'','fee_minor'=>250,'fee_gst_minor'=>45,'wc_total_minor'=>9900,'reconciled_difference_minor'=>100];
$evidence = ['source_type'=>'verified_backup','source_reference'=>'backup-2025-02-01/orders/row-22','authority_confirmed'=>true,'payment_id'=>'pay_12345','currency'=>'INR','billing'=>['first_name'=>'Rae','last_name'=>'Buyer','address_1'=>'1 Example Road','city'=>'Pune','postcode'=>'411001','country'=>'IN','email'=>'rae@example.test'],'shipping_required'=>false,'line_items'=>[['product_id'=>10,'variation_id'=>0,'name'=>'Verified item','quantity'=>1,'subtotal_minor'=>10000,'total_minor'=>9000,'tax_minor'=>900,'taxes'=>[['rate_id'=>1,'tax_minor'=>900]]]],'discounts'=>[['code'=>'coupon-review','amount_minor'=>1000]],'discount_total_minor'=>1000,'shipping_lines'=>[],'shipping_total_minor'=>0,'shipping_tax_minor'=>0,'tax_lines'=>[['rate_id'=>1,'tax_minor'=>900]],'tax_total_minor'=>900,'fee_lines'=>[],'order_total_minor'=>9900];
$complete = SuperWoo_Order_Recovery_Preview::build($base, $evidence, null);
$assert($complete['outcome']==='ready_for_review' && $complete['proposed_order']['order_total_minor']===9900, 'complete authoritative evidence produces a review-only representation');
$assert($complete['proposed_order']['status']==='pending_review_only' && $complete['side_effects']['order_write']===false, 'ready preview still performs no order write or paid status transition');
$assert($complete['provenance']['line_items.0.quantity']['source_reference']===$evidence['source_reference'], 'proposed line fields retain their authoritative source reference');
$assert(isset($complete['provenance']['line_items.0.quantity']) && isset($complete['provenance']['customer.billing.address_1']), 'every proposed field has a source reference');
$assert($complete['reconciliation']['provider_minus_proposed_minor']===100 && $complete['payment']['fee_minor']===250 && $complete['payment']['fee_gst_minor']===45, 'provider gross, Woo total, fee, fee GST, and difference stay separate');
$wrong_payment = $evidence; $wrong_payment['payment_id'] = 'pay_other';
$assert(in_array('checkout_payment_id_mismatch', SuperWoo_Order_Recovery_Preview::build($base, $wrong_payment)['reason_codes'], true), 'checkout evidence must link to the exact payment ID');
$with_gift = $evidence; $with_gift['line_items'][] = ['product_id'=>11,'variation_id'=>0,'name'=>'Verified free item','quantity'=>1,'subtotal_minor'=>0,'total_minor'=>0,'tax_minor'=>0,'taxes'=>[],'superwoo_free_gift'=>true];
$gift_preview = SuperWoo_Order_Recovery_Preview::build($base, $with_gift);
$assert($gift_preview['outcome']==='ready_for_review' && count($gift_preview['proposed_order']['line_items'])===2 && $gift_preview['proposed_order']['line_items'][1]['total_minor']===0, 'exact checkout evidence preserves the free item without changing the order total');
$priced_gift = $with_gift; $priced_gift['line_items'][1]['total_minor']=100;
$assert(in_array('line_items.1.free_gift_amounts', SuperWoo_Order_Recovery_Preview::build($base, $priced_gift)['missing_fields'], true), 'gift flag cannot be used to silently include a priced item');
$no_lines = $evidence; $no_lines['line_items'] = [];
$assert(SuperWoo_Order_Recovery_Preview::build($base,$no_lines)['outcome']==='incomplete_evidence', 'missing line items never produce a proposal');
$no_address = $evidence; unset($no_address['billing']['address_1']);
$missing_address = SuperWoo_Order_Recovery_Preview::build($base,$no_address);
$assert($missing_address['outcome']==='incomplete_evidence' && in_array('customer.billing.address_1',$missing_address['missing_fields'],true), 'missing billing address is listed explicitly');
$bad_total = $evidence; $bad_total['order_total_minor']=10000;
$assert(in_array('authoritative_order_total_arithmetic_mismatch',SuperWoo_Order_Recovery_Preview::build($base,$bad_total)['reason_codes'],true), 'arithmetic mismatch in authoritative totals is a conflict');
$bad_discount = $evidence; $bad_discount['discount_total_minor']=500;
$assert(in_array('authoritative_discount_arithmetic_mismatch',SuperWoo_Order_Recovery_Preview::build($base,$bad_discount)['reason_codes'],true), 'discount mismatch is a conflict, not a forced total adjustment');
$existing = SuperWoo_Order_Recovery_Preview::build($base,$evidence,['order'=>new class { public function get_id(){return 88;} }]);
$assert($existing['outcome']==='conflict' && in_array('existing_woocommerce_order',$existing['reason_codes'],true), 'existing order prevents a reconstruction preview');
$ambiguous = SuperWoo_Order_Recovery_Preview::build($base,$evidence,['ambiguous'=>true]);
$assert($ambiguous['outcome']==='conflict' && in_array('ambiguous_woocommerce_match',$ambiguous['reason_codes'],true), 'ambiguous payment reference remains a conflict');
$duplicate = $base; $duplicate['duplicate_count']=1;
$assert(in_array('duplicate_payment_reference',SuperWoo_Order_Recovery_Preview::build($duplicate,$evidence)['reason_codes'],true), 'duplicate payment references remain conflicts');
$unverified = $base; $unverified['verification_state']='unverified';
$assert(in_array('provider_payment_unverified',SuperWoo_Order_Recovery_Preview::build($unverified,$evidence)['reason_codes'],true), 'unverified provider data cannot be reconstructed');
$refunds_incomplete = $base; $refunds_incomplete['provider_refunds_complete']=0;
$assert(in_array('refund_evidence_incomplete',SuperWoo_Order_Recovery_Preview::build($refunds_incomplete,$evidence)['reason_codes'],true), 'incomplete refund listing blocks a ready preview');
$failed = $base; $failed['provider_status']='failed'; $failed['provider_captured']=0;
$assert(SuperWoo_Order_Recovery_Preview::build($failed,$evidence)['outcome']==='conflict', 'failed payments cannot become paid reconstruction candidates');
$authorized = $base; $authorized['provider_status']='authorized'; $authorized['provider_captured']=0;
$assert(in_array('provider_payment_not_captured',SuperWoo_Order_Recovery_Preview::build($authorized,$evidence)['reason_codes'],true), 'authorized but uncaptured payments remain incomplete');
$refunded = $base; $refunded['provider_status']='refunded'; $refunded['provider_refund_status']='full';
$assert(SuperWoo_Order_Recovery_Preview::build($refunded,$evidence)['outcome']==='conflict', 'fully refunded payments cannot become paid reconstruction candidates');
$partial = $base; $partial['provider_amount_refunded_minor']=100; $partial['provider_refund_status']='partial';
$assert(in_array('provider_payment_partially_refunded',SuperWoo_Order_Recovery_Preview::build($partial,$evidence)['reason_codes'],true), 'partial refunds require conflict review');
$workshop = $base; $workshop['classification']='workshop_excluded';
$assert(in_array('workshop_excluded',SuperWoo_Order_Recovery_Preview::build($workshop,$evidence)['reason_codes'],true), 'Workshop exclusions cannot be reconstructed');
$currency_conflict = $evidence; $currency_conflict['currency']='USD';
$assert(in_array('provider_checkout_currency_mismatch',SuperWoo_Order_Recovery_Preview::build($base,$currency_conflict)['reason_codes'],true), 'provider and authoritative checkout currency mismatch is a conflict');
$untrusted = $evidence; $untrusted['source_type']='imported_csv';
$assert(in_array('evidence_source_not_authoritative',SuperWoo_Order_Recovery_Preview::build($base,$untrusted)['reason_codes'],true), 'imported CSV evidence cannot be promoted to checkout authority');
echo "PASS: {$checks} reconstruction preview checks\n";
