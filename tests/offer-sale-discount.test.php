<?php
/** Cart offer pricing must use the payable sale price on every totals pass. */
define('ABSPATH', __DIR__ . '/');

function is_admin() { return false; }
function absint($value) { return abs((int) $value); }
$GLOBALS['offer_test_price_decimals'] = 2;
function wc_get_price_decimals() { return $GLOBALS['offer_test_price_decimals']; }
class Offer_Test_Session {
    public $values = ['cart_totals' => ['total' => 3153]];
    public function get($key) { return $this->values[$key] ?? null; }
    public function set($key, $value) { $this->values[$key] = $value; }
}
$GLOBALS['offer_test_session'] = new Offer_Test_Session();
function WC() { return (object) ['session' => $GLOBALS['offer_test_session']]; }

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

$updated_sale = new WC_Product(11, 360, 250);
$restored_item = $offers->reset_offer_base_price_from_session([
    'product_id' => 11,
    'quantity' => 2,
    'data' => $updated_sale,
    '_superwoo_offer_base_price' => 265,
]);
if (isset($restored_item['_superwoo_offer_base_price'])) {
    fwrite(STDERR, "FAIL: old offer base survived cart session restore\n");
    exit(1);
}
if (WC()->session->get('cart_totals') !== null) {
    fwrite(STDERR, "FAIL: saved pre-offer base was reset without invalidating stale WooCommerce totals\n");
    exit(1);
}
WC()->session->set('cart_totals', ['total' => 3504]);
$offers->reset_offer_base_price_from_session(['product_id' => 11, 'quantity' => 2, 'data' => $updated_sale]);
if (WC()->session->get('cart_totals')['total'] !== 3504) {
    fwrite(STDERR, "FAIL: a cart item without saved offer data unnecessarily invalidated WooCommerce totals\n");
    exit(1);
}
$updated_cart = new Offer_Test_Cart(['sale' => $restored_item]);
$offers->apply_discounts($updated_cart);
expect_amount($updated_sale->get_price(), 225, 'offer did not use the updated catalog sale price');
expect_amount($offers->get_cart_subtotal_excluding_gifts($updated_cart), 500, 'offer threshold did not use the sale price before discount');
$offers->apply_discounts($updated_cart);
expect_amount($updated_sale->get_price(), 225, 'updated sale price compounded on repeated totals');

$offers->discount = 0;
$offers->apply_discounts($cart);
expect_amount($sale->get_price(), 265, 'sale price restored when offer no longer qualifies');
expect_amount($cart->subtotal(), 680, 'cart subtotal restored');

$offers->discount = 100;
$offers->apply_discounts($cart);
expect_amount($sale->get_price(), 0, 'full discount on sale item');
expect_amount($regular->get_price(), 0, 'full discount on regular item');
expect_amount($other->get_price(), 50, 'unrelated item unchanged at full discount');

// The reported Partial Pack cart must use its WooCommerce sale price, even
// though the regular price is much higher. Exercise both quantity directions.
$partial_pack = new WC_Product(11, 2070, 1298);
$reference_cart = new Offer_Test_Cart([
    'partial-pack' => ['product_id' => 11, 'variation_id' => 11, 'quantity' => 1, 'data' => $partial_pack],
]);
$reference_offer = new Offer_Test_Offers();
foreach ([1, 2, 3, 4, 3, 2, 1] as $quantity) {
    $reference_cart->set_quantity('partial-pack', $quantity, false);
    $reference_offer->apply_discounts($reference_cart);
    expect_amount($reference_offer->get_cart_subtotal_excluding_gifts($reference_cart), 1298 * $quantity, "Partial Pack sale subtotal at quantity $quantity");
    expect_amount($reference_cart->subtotal(), 1168.20 * $quantity, "Partial Pack payable total at quantity $quantity");
    $reference_offer->apply_discounts($reference_cart);
    expect_amount($reference_cart->subtotal(), 1168.20 * $quantity, "Partial Pack repeated totals at quantity $quantity");
}
$reference_cart->set_quantity('partial-pack', 3, false);
$reference_offer->apply_discounts($reference_cart);
expect_amount(1298 * 3 - $reference_cart->subtotal(), 389.40, 'reported Partial Pack discount must be 10% of the sale subtotal');
expect_amount($reference_cart->subtotal(), 3504.60, 'reported Partial Pack payable total before other adjustments');
$GLOBALS['offer_test_price_decimals'] = 0;
$reference_offer->apply_discounts($reference_cart);
expect_amount($reference_cart->subtotal(), 3504, 'whole-rupee store must apply the offer once');
$reference_offer->apply_discounts($reference_cart);
expect_amount($reference_cart->subtotal(), 3504, 'whole-rupee store must not compound the offer on recalculation');
$GLOBALS['offer_test_price_decimals'] = 2;

class Offer_Test_Actual_Tiers extends SuperWoo_Bundle_Offers {
    public function get_rules() {
        return [
            ['id' => 'ten', 'enabled' => true, 'priority' => 3, 'offer_type' => 'price_discount', 'applies_to' => 'global', 'min_amount' => 3000, 'max_amount' => 7000, 'discount' => 10],
            ['id' => 'fifteen', 'enabled' => true, 'priority' => 2, 'offer_type' => 'price_discount', 'applies_to' => 'global', 'min_amount' => 7001, 'max_amount' => 14000, 'discount' => 15],
        ];
    }
}
$tier_product = new WC_Product(4968, 2070, 1298);
$tier_cart = new Offer_Test_Cart(['partial-pack' => ['product_id' => 4900, 'variation_id' => 4968, 'quantity' => 1, 'data' => $tier_product]]);
$actual_tiers = new Offer_Test_Actual_Tiers();
foreach ([[1, 1298], [2, 2596], [3, 3504.60], [4, 4672.80], [5, 5841], [6, 6619.80], [4, 4672.80], [2, 2596]] as [$quantity, $expected]) {
    $tier_cart->set_quantity('partial-pack', $quantity, false);
    $actual_tiers->apply_discounts($tier_cart);
    expect_amount($tier_cart->subtotal(), $expected, "configured tier payable total at quantity $quantity");
}
$tier_cart->set_quantity('partial-pack', 3, false);
$actual_tiers->apply_discounts($tier_cart);
$selected = $actual_tiers->get_selected_offer($tier_cart);
if (($selected['rule']['id'] ?? '') !== 'ten') { fwrite(STDERR, "FAIL: screenshot's 10% tier was not selected at 3894\n"); exit(1); }
expect_amount(7001 - $actual_tiers->get_cart_subtotal_excluding_gifts($tier_cart), 3107, '15% progress amount');
$GLOBALS['offer_test_price_decimals'] = 0;
$actual_tiers->apply_discounts($tier_cart);
expect_amount($tier_cart->subtotal(), 3504, 'actual INR zero-decimal 10% tier total');
$tier_cart->set_quantity('partial-pack', 6, false);
$actual_tiers->apply_discounts($tier_cart);
expect_amount($tier_cart->subtotal(), 6618, 'actual INR zero-decimal 15% tier total');
$GLOBALS['offer_test_price_decimals'] = 2;

echo "PASS: Partial Pack sale pricing, configured 10%/15% tiers, quantity changes, session refresh and zero-decimal INR rounding\n";
