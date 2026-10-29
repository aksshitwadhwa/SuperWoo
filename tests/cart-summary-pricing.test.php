<?php
/** Verify regular-price subtotal, savings, shipping, tax and WooCommerce total. */
define('ABSPATH', __DIR__ . '/');
class WooCommerce {}
class WC_Product {
    private $regular;
    private $price;
    public function __construct($regular, $price) { $this->regular = $regular; $this->price = $price; }
    public function get_regular_price($context = 'view') { return $this->regular; }
    public function get_price($context = 'view') { return $this->price; }
}
class WC_Cart {
    public $items = [];
    public $merchandise = 0;
    public $merchandise_tax = 0;
    public $shipping = 0;
    public $shipping_tax = 0;
    public $fees = 0;
    public $fee_tax = 0;
    public $tax = 0;
    public $total = 0;
    public $including_tax = false;
    public function get_cart() { return $this->items; }
    public function display_prices_including_tax() { return $this->including_tax; }
    public function get_cart_contents_total() { return $this->merchandise; }
    public function get_cart_contents_tax() { return $this->merchandise_tax; }
    public function get_fee_total() { return $this->fees; }
    public function get_fee_tax() { return $this->fee_tax; }
    public function get_total_tax() { return $this->tax; }
    public function get_cart_shipping_total() { return $this->shipping ? wc_price($this->shipping + ($this->including_tax ? $this->shipping_tax : 0)) : 'Free!'; }
    public function get_total($context = 'view') { return $this->total; }
}
class Summary_Currency {
    public $enabled = false;
    public function is_enabled() { return $this->enabled; }
    public function convert_product_price($product, $price) { return $price * 2; }
}
$GLOBALS['summary_cart'] = new WC_Cart();
$GLOBALS['summary_currency'] = new Summary_Currency();
function WC() { return (object) ['cart' => $GLOBALS['summary_cart']]; }
function superwoo_currency() { return $GLOBALS['summary_currency']; }
function wc_get_price_to_display($product, $args) { return $args['price'] * $args['qty'] * ($GLOBALS['summary_cart']->including_tax ? 1.1 : 1); }
function wc_get_price_decimals() { return 2; }
function wc_price($amount) { return ($amount < 0 ? '-' : '') . 'INR' . number_format(abs($amount), 2, '.', ''); }
function wp_kses_post($html) { return $html; }
function esc_html_e($text, $domain = '') { echo $text; }
function check($condition, $message) { if (!$condition) { fwrite(STDERR, "FAIL: $message\n"); exit(1); } }

require_once dirname(__DIR__) . '/includes/functions.php';
$cart = $GLOBALS['summary_cart'];
$cart->items = [
    'sale' => ['data' => new WC_Product(459, 269), 'quantity' => 1, 'line_total' => 269],
    'sale2' => ['data' => new WC_Product(375, 325), 'quantity' => 1, 'line_total' => 325],
    'gift' => ['data' => new WC_Product(100, 0), 'quantity' => 1, 'line_total' => 0, 'superwoo_free_gift' => true],
];
$cart->merchandise = 594;
$cart->shipping = 75;
$cart->total = 669;
$summary = superwoo_cart_summary_amounts($cart);
check($summary['subtotal'] === 934.0 && $summary['discount'] === 340.0, 'MRP and combined sale/free-gift savings are wrong');
check(abs($summary['subtotal'] - $summary['discount'] + $cart->shipping - $cart->total) < 0.001, 'cart summary does not reconcile to payable total');
$html = superwoo_cart_total_html();
check(strpos($html, 'INR934.00') < strpos($html, '-INR340.00'), 'subtotal/discount display order is wrong');
check(strpos($html, '-INR340.00') < strpos($html, 'Shipping') && strpos($html, 'Shipping') < strpos($html, 'Total'), 'shipping/total display order is wrong');
check(strpos($html, 'INR669.00') !== false, 'WooCommerce payable total not shown');

$cart->items = ['custom' => ['data' => new WC_Product(100, 120), 'quantity' => 1, 'line_total' => 120]];
$cart->merchandise = 120;
$cart->shipping = 0;
$cart->total = 120;
$summary = superwoo_cart_summary_amounts($cart);
check($summary['subtotal'] === 120.0 && $summary['discount'] === 0.0, 'higher custom cart price created a false discount');

$cart->items = ['coupon' => ['data' => new WC_Product(100, 100), 'quantity' => 1, 'line_total' => 90, 'line_tax' => 9]];
$cart->merchandise = 90;
$cart->merchandise_tax = 9;
$cart->shipping = 5;
$cart->shipping_tax = 1;
$cart->fees = 2;
$cart->fee_tax = 0.2;
$cart->tax = 10.2;
$cart->total = 107.2;
$summary = superwoo_cart_summary_amounts($cart);
check($summary['subtotal'] === 100.0 && $summary['discount'] === 10.0 && $summary['fees'] === 2.0 && $summary['tax'] === 10.2, 'ex-tax coupon, fee or tax breakdown wrong');
check(abs($summary['subtotal'] - $summary['discount'] + $cart->shipping + $summary['fees'] + $summary['tax'] - $cart->total) < 0.001, 'ex-tax total did not reconcile');

$cart->including_tax = true;
$cart->total = 107.2;
$summary = superwoo_cart_summary_amounts($cart);
check($summary['subtotal'] === 110.0 && $summary['discount'] === 11.0 && $summary['fees'] === 2.2 && $summary['tax'] === 0.0, 'inclusive-tax breakdown wrong');
check(abs($summary['subtotal'] - $summary['discount'] + $cart->shipping + $cart->shipping_tax + $summary['fees'] - $cart->total) < 0.001, 'inclusive-tax total did not reconcile');

$cart->including_tax = false;
$cart->items = ['currency' => ['data' => new WC_Product(459, 269), 'quantity' => 2, 'line_total' => 1076]];
$cart->merchandise = 1076;
$cart->shipping = 0;
$cart->fees = 0;
$cart->fee_tax = 0;
$cart->tax = 0;
$cart->total = 1076;
$GLOBALS['summary_currency']->enabled = true;
$summary = superwoo_cart_summary_amounts($cart);
check($summary['subtotal'] === 1836.0 && $summary['discount'] === 760.0, 'quantity or currency-converted regular price not used');

$GLOBALS['summary_currency']->enabled = false;
$cart->items = ['fee-discount' => ['data' => new WC_Product(100, 100), 'quantity' => 1, 'line_total' => 100]];
$cart->merchandise = 100;
$cart->fees = -10;
$cart->total = 90;
$summary = superwoo_cart_summary_amounts($cart);
check($summary['subtotal'] === 100.0 && $summary['discount'] === 10.0 && $summary['fees'] === 0.0, 'negative fee discount did not reconcile');

echo "PASS: regular subtotal, sale/coupon/gift savings, custom price, tax, fees, currency and payable total\n";
