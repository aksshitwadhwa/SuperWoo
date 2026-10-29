<?php
/** Cart offer pricing must use the payable sale price on every totals pass. */
define('ABSPATH', __DIR__ . '/');

function is_admin() { return false; }
function absint($value) { return abs((int) $value); }
function wc_get_price_decimals() { return 2; }

class WC_Product {
    private $id;
    private $regular_price;
    private $price;
    public function __construct($id, $regular_price, $price) {
        $this->id = $id;
        $this->regular_price = $regular_price;
        $this->price = $price;
    }
    public function get_id() { return $this->id; }
    public function get_regular_price() { return $this->regular_price; }
    public function get_price($context = 'view') { return $this->price; }
    public function set_price($price) { $this->price = (float) $price; }
}

class Offer_Test_Cart {
    public $cart_contents;
    public function __construct($items) { $this->cart_contents = $items; }
    public function get_cart() { return $this->cart_contents; }
    public function set_quantity($key, $quantity, $refresh = true) {
        $this->cart_contents[$key]['quantity'] = $quantity;
    }
    public function subtotal() {
        $total = 0;
        foreach ($this->cart_contents as $item) {
            $total += $item['data']->get_price() * $item['quantity'];
        }
        return $total;
    }
}

require_once dirname(__DIR__) . '/includes/class-bundle-offers.php';

class Offer_Test_Offers extends SuperWoo_Bundle_Offers {
    public $discount = 10;
    public function get_rules() {
        return [[
            'enabled' => true,
            'priority' => 1,
            'offer_type' => 'product_discount',
            'applies_to' => 'products',
            'product_ids' => [11, 12],
            'min_qty' => 1,
            'min_amount' => 0,
            'discount' => $this->discount,
        ]];
    }
}

function expect_amount($actual, $expected, $label) {
    if (abs($actual - $expected) > 0.00001) {
        fwrite(STDERR, "FAIL: $label: expected $expected, got $actual\n");
        exit(1);
    }
}

$sale = new WC_Product(11, 360, 265);
$regular = new WC_Product(12, 100, 100);
$other = new WC_Product(13, 50, 50);
$cart = new Offer_Test_Cart([
    'sale' => ['product_id' => 11, 'quantity' => 2, 'data' => $sale],
    'regular' => ['product_id' => 12, 'quantity' => 1, 'data' => $regular],
    'other' => ['product_id' => 13, 'quantity' => 1, 'data' => $other],
]);
$offers = new Offer_Test_Offers();
$offers->apply_discounts($cart);
expect_amount($sale->get_price(), 238.50, 'sale item discounted from its payable price');
expect_amount($regular->get_price(), 90, 'regular item discounted');
expect_amount($other->get_price(), 50, 'ineligible item unchanged');
expect_amount($cart->subtotal(), 617, 'cart subtotal after offer');

$offers->apply_discounts($cart);
expect_amount($cart->subtotal(), 617, 'repeated totals do not compound the offer');

$offers->discount = 0;
$offers->apply_discounts($cart);
expect_amount($sale->get_price(), 265, 'sale price restored when offer no longer qualifies');
expect_amount($cart->subtotal(), 680, 'cart subtotal restored');

$offers->discount = 100;
$offers->apply_discounts($cart);
expect_amount($sale->get_price(), 0, 'full discount on sale item');
expect_amount($regular->get_price(), 0, 'full discount on regular item');
expect_amount($other->get_price(), 50, 'unrelated item unchanged at full discount');

echo "PASS: sale and regular items use payable-price discounts; totals are stable and reversible\n";
