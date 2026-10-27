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

echo "PASS: selected free gift is priced at zero in the cart calculation\n";
