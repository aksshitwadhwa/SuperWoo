<?php
/** Run with wp eval-file against the socket-only disposable offer audit site. */
if (!defined('WP_CLI') || !WP_CLI || !defined('DB_NAME') || DB_NAME !== 'superwoo_offer_audit'
    || get_option('home') !== 'http://superwoo-offer-audit.test') {
    throw new RuntimeException('This integration test is restricted to the disposable offer audit database/site.');
}
if (!defined('DOING_AJAX')) { define('DOING_AJAX', true); }
$plugin_root = dirname(__DIR__, 2);
require_once $plugin_root . '/includes/functions.php';
$baseline = getenv('SUPERWOO_OFFER_BASELINE');
require_once ($baseline ?: $plugin_root . '/includes/class-bundle-offers.php');

$decimals = $baseline ? 0 : 2;
add_filter('pre_option_woocommerce_price_num_decimals', static function () use (&$decimals) { return $decimals; });
add_filter('pre_option_woocommerce_currency', static function () { return 'INR'; });
add_filter('pre_option_woocommerce_enable_coupons', static function () { return 'yes'; });

function audit_product($name, $regular, $sale = null) {
    $product = new WC_Product_Simple();
    $product->set_name($name);
    $product->set_status('publish');
    $product->set_virtual(true);
    $product->set_tax_status('none');
    $product->set_regular_price((string) $regular);
    if ($sale !== null) { $product->set_sale_price((string) $sale); }
    $product->set_price((string) ($sale ?? $regular));
    $product->save();
    return $product;
}
$paid = audit_product('Audit Hair Boost', 1035, 749);
$gift = audit_product('Audit gift', 265);
$rules = [
    ['id'=>'gift','title'=>'Gift','enabled'=>true,'priority'=>4,'offer_type'=>'price_gift','applies_to'=>'global','min_amount'=>2000,'max_amount'=>2999,'free_product_ids'=>[$gift->get_id()]],
    ['id'=>'ten','title'=>'10%','enabled'=>true,'priority'=>3,'offer_type'=>'price_discount','applies_to'=>'global','min_amount'=>3000,'max_amount'=>7000,'discount'=>10],
    ['id'=>'fifteen','title'=>'15%','enabled'=>true,'priority'=>2,'offer_type'=>'price_discount','applies_to'=>'global','min_amount'=>7001,'max_amount'=>14000,'discount'=>15],
    ['id'=>'twenty','title'=>'20%','enabled'=>true,'priority'=>1,'offer_type'=>'price_discount','applies_to'=>'global','min_amount'=>14001,'max_amount'=>0,'discount'=>20],
];
add_filter('pre_option_pbi_bundle_rules', static function () use (&$rules) { return $rules; });
$offers = new SuperWoo_Bundle_Offers();
$offers->hooks();
wc_load_cart();
$cart = WC()->cart;
$cart->empty_cart(false);
$key = $cart->add_to_cart($paid->get_id(), 4);
if (!$key) { throw new RuntimeException('Could not add fixture product.'); }
$cart->set_quantity($key, 5);
$initial = [
    'mode'=>$baseline ? 'baseline' : 'fixed', 'unit'=>(float) $cart->get_cart()[$key]['data']->get_price(),
    'base'=>$offers->get_cart_subtotal_excluding_gifts($cart), 'total'=>(float) $cart->get_total('edit'),
    'summary'=>superwoo_cart_summary_amounts($cart),
];
echo 'SCREENSHOT_CART=' . wp_json_encode($initial) . "\n";
if ($baseline) {
    if (abs($initial['total'] - 3035) > 0.001) { throw new RuntimeException('Baseline did not reproduce the screenshot.'); }
    echo "PASS: real WooCommerce reproduced the original 3035 total\n";
    return;
}
$GLOBALS['offer_runtime_checks'] = 0;
function audit_expect($actual, $expected, $label) {
    $GLOBALS['offer_runtime_checks']++;
    if (is_float($expected) || is_int($expected)) {
        if (abs($actual - $expected) < 0.00001) { return; }
    } elseif ($actual === $expected) { return; }
    throw new RuntimeException($label . ': expected ' . var_export($expected,true) . ', got ' . var_export($actual,true));
}
audit_expect((float) $cart->get_total('edit'), 3370.50, 'screenshot one-time 10%');
audit_expect(superwoo_cart_summary_amounts($cart)['discount'], 374.50, 'screenshot drawer discount');

foreach ([2, 0] as $decimals) {
    foreach ([2, 3, 4, 5, 9, 10, 18, 19, 20, 10, 5, 4, 2] as $quantity) {
        $cart->set_quantity($key, $quantity);
        $base = 749 * $quantity;
        $rate = $base >= 14001 ? 20 : ($base >= 7001 ? 15 : ($base >= 3000 ? 10 : 0));
        $expected = round($base * (1-$rate/100), $decimals);
        audit_expect((float) $cart->get_total('edit'), $expected, "Woo total quantity $quantity/$decimals decimals");
        audit_expect($offers->get_cart_subtotal_excluding_gifts($cart), $base, 'pre-offer qualification base');
        $gifts = array_filter($cart->get_cart(), static function ($item) { return !empty($item['superwoo_free_gift']); });
        audit_expect(count($gifts), $base>=2000 && $base<=2999 ? 1 : 0, 'real Woo gift count');
        foreach ($gifts as $item) { audit_expect((float) $item['line_total'], 0, 'gift line total'); }
        for ($pass=0; $pass<3; $pass++) { $cart->calculate_totals(); }
        audit_expect((float) $cart->get_total('edit'), $expected, 'real Woo repeated totals');
    }
}

// Honor exact configured min/max amounts, including fractional-rupee gaps.
$decimals = 2;
foreach ([[1999,null],[2000,'gift'],[2999,'gift'],[2999.99,null],[3000,'ten'],[7000,'ten'],[7000.01,null],[7001,'fifteen'],[14000,'fifteen'],[14000.01,null],[14001,'twenty']] as [$amount,$tier]) {
    $cart->empty_cart(false);
    $boundary = audit_product('Boundary ' . $amount, $amount);
    $cart->add_to_cart($boundary->get_id(),1);
    audit_expect($offers->get_selected_offer($cart)['rule']['id'] ?? null,$tier,'configured tier boundary '.$amount);
}

// A legitimate coupon can add savings; fixing compounding must not remove it.
$cart->empty_cart(false);
$key = $cart->add_to_cart($paid->get_id(),5);
$coupon = new WC_Coupon();
$coupon->set_code('offer-audit-' . $paid->get_id());
$coupon->set_discount_type('percent');
$coupon->set_amount(10);
$coupon->save();
audit_expect($cart->apply_coupon($coupon->get_code()), true, 'apply real Woo coupon');
$cart->calculate_totals();
audit_expect((float) $cart->get_total('edit'),3033.45,'coupon stacks once after the single cart offer');
$cart->remove_coupon($coupon->get_code());
$cart->calculate_totals();
audit_expect((float) $cart->get_total('edit'),3370.50,'coupon removal restores only the offer reduction');

// Old session metadata/totals must heal on a fresh catalog hydration.
$cart->set_session();
$saved = WC()->session->get('cart');
$saved[$key]['_superwoo_base_inr_price'] = 674;
$saved[$key]['_superwoo_current_inr_price'] = 607;
$saved[$key]['_superwoo_offer_base_price'] = 674;
$saved[$key]['_superwoo_currency'] = 'INR';
WC()->session->set('cart',$saved);
$totals = WC()->session->get('cart_totals');
$totals['total'] = 3035;
WC()->session->set('cart_totals',$totals);
$cart->get_cart_from_session();
audit_expect((float) $cart->get_total('edit'),3370.50,'old session refreshed from current catalog sale price');
audit_expect($offers->get_cart_subtotal_excluding_gifts($cart),3745,'old session qualification base');
audit_expect(count(wc_get_orders(['limit'=>1,'return'=>'ids'])),0,'integration did not create orders');
echo 'PASS: ' . $GLOBALS['offer_runtime_checks'] . " real WooCommerce offer checks; no checkout, order, or payment executed\n";
