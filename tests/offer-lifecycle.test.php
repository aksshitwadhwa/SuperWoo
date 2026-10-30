<?php
/** Exercise the nested totals callbacks WooCommerce fires on gift add/remove. */
define('ABSPATH', __DIR__ . '/');
function is_admin() { return false; }
function absint($value) { return abs((int) $value); }
$GLOBALS['offer_decimals'] = 0;
function wc_get_price_decimals() { return $GLOBALS['offer_decimals']; }
function wc_get_rounding_precision() { return max(6, wc_get_price_decimals() + 2); }
function has_term($id, $taxonomy, $product_id) { return $id === 7 && in_array($product_id, [11, 12], true); }
class Lifecycle_Session {
    public $values = [];
    public function get($key) { return $this->values[$key] ?? null; }
    public function set($key, $value) { $this->values[$key] = $value; }
}
$GLOBALS['lifecycle_wc'] = (object) ['session' => new Lifecycle_Session()];
function WC() { return $GLOBALS['lifecycle_wc']; }
class WC_Product {
    private $id;
    private $price;
    private $regular;
    public function __construct($id, $price, $regular = null) { $this->id = $id; $this->price = $price; $this->regular = $regular ?? $price; }
    public function get_id() { return $this->id; }
    public function get_price($context = 'view') { return $this->price; }
    public function get_regular_price($context = 'view') { return $this->regular; }
    public function set_price($price) { $this->price = (float) $price; }
    public function is_purchasable() { return true; }
    public function is_in_stock() { return true; }
    public function is_type($type) { return false; }
}
function wc_get_product($id) { return new WC_Product($id, 265); }
require_once dirname(__DIR__) . '/includes/class-bundle-offers.php';
class Lifecycle_Offers extends SuperWoo_Bundle_Offers {
    public $rules;
    public function __construct() {
        $this->rules = [
            ['id'=>'specific-gift','enabled'=>true,'priority'=>1,'offer_type'=>'price_gift','applies_to'=>'products','product_ids'=>[99],'min_amount'=>0,'max_amount'=>0,'free_product_ids'=>[77]],
            ['id'=>'gift','enabled'=>true,'priority'=>4,'offer_type'=>'price_gift','applies_to'=>'global','min_amount'=>2000,'max_amount'=>2999,'free_product_ids'=>[77]],
            ['id'=>'ten','enabled'=>true,'priority'=>3,'offer_type'=>'price_discount','applies_to'=>'global','min_amount'=>3000,'max_amount'=>7000,'discount'=>10],
            ['id'=>'fifteen','enabled'=>true,'priority'=>2,'offer_type'=>'price_discount','applies_to'=>'global','min_amount'=>7001,'max_amount'=>14000,'discount'=>15],
            ['id'=>'twenty','enabled'=>true,'priority'=>1,'offer_type'=>'price_discount','applies_to'=>'global','min_amount'=>14001,'max_amount'=>0,'discount'=>20],
        ];
    }
    public function get_rules() { return $this->rules; }
}
class Lifecycle_Cart {
    public $cart_contents;
    public $offers;
    public $depth = 0;
    public $nested = 0;
    public $throw_on_add = false;
    public function __construct($offers, $price = 749, $quantity = 4) {
        $this->offers = $offers;
        $this->cart_contents = ['paid'=>['product_id'=>11,'quantity'=>$quantity,'data'=>new WC_Product(11, $price, 1035)]];
    }
    public function get_cart() { return $this->cart_contents; }
    public function is_empty() { return !$this->cart_contents; }
    public function set_quantity($key, $quantity, $refresh = true) {
        $this->cart_contents[$key]['quantity'] = $quantity;
        if ($refresh) { $this->calculate_totals(); }
    }
    public function add_to_cart($id, $quantity, $variation, $attributes, $metadata) {
        if ($this->throw_on_add) { throw new RuntimeException('injected gift add failure'); }
        $key = 'gift-' . $id;
        $this->cart_contents[$key] = $metadata + ['product_id'=>$id,'quantity'=>$quantity,'data'=>wc_get_product($id)];
        // WC_Cart registers calculate_totals on woocommerce_add_to_cart.
        $this->calculate_totals();
        return $key;
    }
    public function remove_cart_item($key) {
        unset($this->cart_contents[$key]);
        // WC_Cart and SuperWoo both listen to woocommerce_cart_item_removed.
        $this->calculate_totals();
        $this->offers->sync_gifts_after_item_removed($key, $this);
        return true;
    }
    public function calculate_totals() {
        if ($this->depth++ > 12) { throw new RuntimeException('recursive cart calculation'); }
        if ($this->depth > 1) { $this->nested++; }
        try { $this->offers->apply_discounts($this); }
        finally { $this->depth--; }
    }
    public function subtotal() {
        $sum = 0;
        foreach ($this->cart_contents as $item) { $sum += $item['quantity'] * $item['data']->get_price(); }
        return $sum;
    }
    public function gifts() {
        return array_filter($this->cart_contents, static function($item) { return !empty($item['superwoo_free_gift']); });
    }
}
$checks = 0;
function lifecycle_check($ok, $message) {
    $GLOBALS['checks']++;
    if (!$ok) { fwrite(STDERR, "FAIL: $message\n"); exit(1); }
}
function lifecycle_amount($actual, $expected, $message) {
    lifecycle_check(abs($actual-$expected) < 0.00001, "$message (expected $expected, got $actual)");
}

// Exact screenshot: 4 x 749 qualifies for a gift; 5 x 749 removes that gift.
// Before the fix, zero-decimal rounding applies 749 -> 674 -> 607, total 3035.
$offers = new Lifecycle_Offers();
$cart = new Lifecycle_Cart($offers);
$cart->calculate_totals();
lifecycle_check(count($cart->gifts()) === 1, 'initial 2996 cart did not receive its gift');
$cart->set_quantity('paid', 5);
lifecycle_check($cart->nested > 0, 'fixture did not exercise nested WooCommerce calculations');
lifecycle_amount($cart->subtotal(), 3370.50, 'screenshot 10% offer must apply only once to 3745');
lifecycle_check(!$cart->gifts(), 'gift from the previous tier survived the percentage offer');

foreach ([0, 2] as $decimals) {
    $GLOBALS['offer_decimals'] = $decimals;
    $cart = new Lifecycle_Cart(new Lifecycle_Offers());
    foreach ([4, 5, 4, 10, 4, 20, 10, 5, 2, 4, 5] as $quantity) {
        $cart->set_quantity('paid', $quantity);
        $base = 749 * $quantity;
        $rate = $base >= 14001 ? 20 : ($base >= 7001 ? 15 : ($base >= 3000 ? 10 : 0));
        $expected = $base * (1 - $rate / 100);
        lifecycle_amount($cart->subtotal(), $expected, "$rate% tier at quantity $quantity, $decimals decimals");
        lifecycle_amount($cart->offers->get_cart_subtotal_excluding_gifts($cart), $base, 'threshold uses the pre-offer sale price');
        lifecycle_check(count($cart->gifts()) === ($base >= 2000 && $base <= 2999 ? 1 : 0), 'gift count mismatches selected tier');
        foreach ($cart->gifts() as $gift) { lifecycle_amount($gift['data']->get_price(), 0, 'gift must remain free'); }
        for ($pass=0; $pass<3; $pass++) { $cart->calculate_totals(); }
        lifecycle_amount($cart->subtotal(), $expected, 'repeated totals changed the discount');
    }
    // Stale gifts must not multiply 10%, 15%, 20%, or a full discount.
    foreach ([10, 15, 20, 100] as $percent) {
        $offers = new Lifecycle_Offers();
        $offers->rules = [['id'=>'only','enabled'=>true,'priority'=>1,'offer_type'=>'product_discount','applies_to'=>'global','min_qty'=>1,'discount'=>$percent]];
        $cart = new Lifecycle_Cart($offers, 749, 5);
        $cart->cart_contents['stale-gift'] = ['product_id'=>77,'quantity'=>1,'superwoo_free_gift'=>true,'data'=>new WC_Product(77,0)];
        $cart->calculate_totals();
        lifecycle_amount($cart->subtotal(), 3745 * (1-$percent/100), "$percent% stale gift removal");
        $cart->calculate_totals();
        lifecycle_amount($cart->subtotal(), 3745 * (1-$percent/100), "$percent% rerun");
    }
}

// Explicit priority and product/variation scopes must still pick a single rule.
$offers = new Lifecycle_Offers();
$offers->rules = [
    ['id'=>'lower','enabled'=>true,'priority'=>2,'offer_type'=>'product_discount','applies_to'=>'global','min_qty'=>1,'discount'=>50],
    ['id'=>'winner','enabled'=>true,'priority'=>1,'offer_type'=>'product_discount','applies_to'=>'products','product_ids'=>[111],'min_qty'=>1,'discount'=>10],
];
$cart = new Lifecycle_Cart($offers, 749, 5);
$cart->cart_contents['paid']['variation_id'] = 111;
$cart->cart_contents['other'] = ['product_id'=>12,'quantity'=>1,'data'=>new WC_Product(12,100)];
$cart->calculate_totals();
lifecycle_amount($cart->subtotal(), 3470.50, 'variation scope or priority stacked/included unrelated discounts');
$offers->rules[1]['enabled'] = false;
$cart->calculate_totals();
lifecycle_amount($cart->subtotal(), 1922.50, 'disabled higher priority rule did not restore and reselect');
$offers->rules = [];
$cart->calculate_totals();
lifecycle_amount($cart->subtotal(), 3845, 'disabling all rules did not restore catalog sale prices');

// Category thresholds use eligible items only; quantity/amount limits both apply.
$offers->rules = [['id'=>'category','enabled'=>true,'priority'=>1,'offer_type'=>'price_discount','applies_to'=>'category','category_id'=>7,'min_amount'=>1500,'max_amount'=>3000,'discount'=>10]];
$cart = new Lifecycle_Cart($offers,749,2);
$cart->cart_contents['other'] = ['product_id'=>13,'quantity'=>1,'data'=>new WC_Product(13,1000)];
$cart->calculate_totals();
lifecycle_amount($cart->subtotal(),2498,'unrelated products unlocked a category threshold');
$cart->set_quantity('paid',3);
lifecycle_amount($cart->subtotal(),3022.30,'category offer discounted an unrelated item');
$offers->rules = [['id'=>'quantity','enabled'=>true,'priority'=>1,'offer_type'=>'product_discount','applies_to'=>'products','product_ids'=>[11],'min_qty'=>2,'max_qty'=>4,'min_amount'=>1000,'max_amount'=>3000,'discount'=>20]];
$cart->calculate_totals();
lifecycle_amount($cart->subtotal(),2797.60,'quantity offer did not use the eligible sale subtotal');
$cart->set_quantity('paid',5);
lifecycle_amount($cart->subtotal(),4745,'quantity/amount cap did not restore the full sale price');

// Equal priorities use saved order; legacy tiers remain compatible.
$offers->rules = [
    ['id'=>'first','enabled'=>true,'priority'=>1,'offer_type'=>'product_discount','applies_to'=>'global','min_qty'=>1,'discount'=>10],
    ['id'=>'second','enabled'=>true,'priority'=>1,'offer_type'=>'product_discount','applies_to'=>'global','min_qty'=>1,'discount'=>20],
];
$cart->calculate_totals();
lifecycle_amount($cart->subtotal(),4270.50,'equal priorities did not retain saved order');
$offers->rules = [['id'=>'legacy','enabled'=>true,'priority'=>1,'scope'=>'global','tiers'=>[['min_qty'=>1,'discount'=>5],['min_qty'=>3,'discount'=>15]]]];
$cart->calculate_totals();
lifecycle_amount($cart->subtotal(),4033.25,'legacy quantity tier changed or stacked');

// Multiple gift callbacks and manual gift removal must remain stable.
$offers = new Lifecycle_Offers();
$offers->rules[1]['free_product_ids'] = [77,78];
$cart = new Lifecycle_Cart($offers);
$cart->calculate_totals();
lifecycle_check(count($cart->gifts())===2,'multi-gift tier did not add each gift once');
$cart->remove_cart_item('gift-77');
lifecycle_check(count($cart->gifts())===2,'eligible removed gift was not reconciled once');
$cart->set_quantity('paid',5);
lifecycle_check(!$cart->gifts(),'multi-gift transition left a stale gift');
lifecycle_amount($cart->subtotal(),3370.50,'multiple gift callbacks compounded the discount');

// Session restore must discard stale currency bases even when currency is off.
$restored = $offers->reset_offer_base_price_from_session([
    'product_id'=>11, 'quantity'=>5, 'data'=>new WC_Product(11,749),
    '_superwoo_base_inr_price'=>674, '_superwoo_current_inr_price'=>607,
    '_superwoo_currency'=>'INR', '_superwoo_offer_base_price'=>749,
]);
foreach (['_superwoo_base_inr_price','_superwoo_current_inr_price','_superwoo_currency','_superwoo_offer_base_price'] as $key) {
    lifecycle_check(!array_key_exists($key,$restored), "stale $key survived session restore");
}

// A failed gift callback must not leave future calculations permanently locked.
$offers = new Lifecycle_Offers();
$cart = new Lifecycle_Cart($offers);
$cart->throw_on_add = true;
try { $cart->calculate_totals(); lifecycle_check(false, 'failure injection was not reached'); }
catch (RuntimeException $e) { lifecycle_check($e->getMessage()==='injected gift add failure','unexpected gift error'); }
$cart->throw_on_add = false;
$cart->calculate_totals();
lifecycle_check(count($cart->gifts())===1,'gift synchronization stayed locked after a failed callback');
$cart->set_quantity('paid', 5);
lifecycle_amount($cart->subtotal(),3370.50,'pricing stayed locked after a failed callback');

echo "PASS: $checks offer lifecycle assertions (gift transitions, nested totals, 10/15/20/100%, scopes, currency metadata, failure recovery)\n";
