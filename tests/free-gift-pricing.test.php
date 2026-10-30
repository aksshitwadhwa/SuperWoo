<?php

/** Focused regression for the price-range free-gift cart calculation. */
define('ABSPATH', __DIR__ . '/');

function is_admin() {
    return false;
}

function absint($value) {
    return abs((int) $value);
}

class WC_Product {
    private $price;

    public function __construct($price) {
        $this->price = (float) $price;
    }

    public function get_price($context = 'view') {
        return $this->price;
    }

    public function set_price($price) {
        $this->price = (float) $price;
    }
    public function get_id() { return 12; }
}

class WC_Order_Item_Product {
    public $metadata = [];
    private $total;
    private $subtotal;
    public function __construct($total = 0, $subtotal = 0) { $this->total = $total; $this->subtotal = $subtotal; }
    public function get_total() { return $this->total; }
    public function get_subtotal() { return $this->subtotal; }
    public function add_meta_data($key, $value, $unique = false) { $this->metadata[$key] = $value; }
}

class Test_Cart {
    public $cart_contents;

    public function __construct($items) {
        $this->cart_contents = $items;
    }

    public function get_cart() {
        return $this->cart_contents;
    }

    public function set_quantity($key, $quantity, $refresh = true) {
        $this->cart_contents[$key]['quantity'] = $quantity;
        return true;
    }
}

require_once dirname(__DIR__) . '/includes/class-bundle-offers.php';
function wc_get_price_decimals() { return 2; }

class Test_Free_Gift_Bundle_Offers extends SuperWoo_Bundle_Offers {
    public function get_selected_offer($cart) {
        return [
            'rule' => [
                'id'               => 'gift-test',
                'offer_type'       => 'price_gift',
                'free_product_ids' => [77],
            ],
            // Price-range gift offers intentionally have no discount tier.
            'tier' => [],
        ];
    }
}

$gift_product = new WC_Product(265);
$cart = new Test_Cart([
    'gift-row' => [
        'product_id'       => 77,
        'variation_id'     => 0,
        'quantity'         => 1,
        'superwoo_free_gift' => true,
        'superwoo_gift_id' => 77,
        'data'             => $gift_product,
    ],
]);

(new Test_Free_Gift_Bundle_Offers())->apply_discounts($cart);

if (0.0 !== $gift_product->get_price()) {
    fwrite(STDERR, 'FAIL: selected free gift retained a payable price of ' . $gift_product->get_price() . "\n");
    exit(1);
}

$offers = new Test_Free_Gift_Bundle_Offers();
$order_item = new WC_Order_Item_Product(0, 0);
$offers->record_free_gift_order_line($order_item, 'gift-row', $cart->get_cart()['gift-row'], null);
if (($order_item->metadata['_superwoo_free_gift'] ?? '') !== 'yes' || ($order_item->metadata['_superwoo_gift_id'] ?? 0) !== 77) {
    fwrite(STDERR, "FAIL: free item identity was not retained on the WooCommerce order item\n");
    exit(1);
}
$paid_item = new WC_Order_Item_Product(100, 100);
$offers->record_free_gift_order_line($paid_item, 'gift-row', $cart->get_cart()['gift-row'], null);
if ($paid_item->metadata) { fwrite(STDERR, "FAIL: a priced item was marked as a free gift\n"); exit(1); }

class Test_Quantity_Bundle_Offers extends SuperWoo_Bundle_Offers {
    public function get_selected_offer($cart) {
        $item = $cart->get_cart()['paid-row'];
        return $item['quantity'] >= 2
            ? ['rule' => ['offer_type' => 'product_discount'], 'tier' => ['discount' => 10]]
            : null;
    }
    public function rule_applies_to_product($rule, $product_id, $variation_id = 0) { return true; }
}

$sale_product = new WC_Product(80);
$offer_cart = new Test_Cart(['paid-row' => ['product_id' => 12, 'quantity' => 1, 'data' => $sale_product]]);
$quantity_offer = new Test_Quantity_Bundle_Offers();
$offer_cart->set_quantity('paid-row', 2, false);
$quantity_offer->apply_discounts($offer_cart);
if ($sale_product->get_price() !== 72.0) { fwrite(STDERR, "FAIL: quantity increase did not apply 10% once to the sale price\n"); exit(1); }
$quantity_offer->apply_discounts($offer_cart);
if ($sale_product->get_price() !== 72.0) { fwrite(STDERR, "FAIL: repeated totals compounded the offer discount\n"); exit(1); }
$offer_cart->set_quantity('paid-row', 1, false);
$quantity_offer->apply_discounts($offer_cart);
if ($sale_product->get_price() !== 80.0) { fwrite(STDERR, "FAIL: quantity decrease did not restore the sale price\n"); exit(1); }
$offer_cart->set_quantity('paid-row', 2, false);
$quantity_offer->apply_discounts($offer_cart);
if ($sale_product->get_price() !== 72.0) { fwrite(STDERR, "FAIL: requalified offer used a previously discounted price\n"); exit(1); }

echo "PASS: free gift and quantity offer pricing remain stable across recalculations\n";
