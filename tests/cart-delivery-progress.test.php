<?php
/** Delivery copy must share the offer card without changing shipping prices. */
define('ABSPATH', __DIR__ . '/');
define('SUPERWOO_PATH', dirname(__DIR__) . '/');
class WooCommerce {}
class WC_Cart {
    public $items = [['quantity'=>2,'line_subtotal'=>198]];
    public $shipping = 75;
    public $calculated = true;
    public function get_cart() { return $this->items; }
    public function is_empty() { return !$this->items; }
    public function needs_shipping() { return true; }
    public function has_calculated_shipping() { return $this->calculated; }
    public function get_shipping_methods() { return ['chosen-rate']; }
    public function get_shipping_total() { return $this->shipping; }
    public function get_shipping_tax() { return 0; }
    public function calculate_totals() { throw new RuntimeException('Rendering delivery must not recalculate or change shipping.'); }
}
class SuperWoo_Bundle_Offers {
    public static $html = '<div class="superwoo-bundle-notice"><div>Gift at INR2000</div><div class="superwoo-bundle-progress">INR198 / INR2000</div></div>';
    public function get_notices_html() { return self::$html; }
    public function get_cart_offer_state($cart) { return ['discounts'=>[],'gifts'=>[]]; }
}
$GLOBALS['delivery_wc'] = (object)['cart'=>new WC_Cart()];
$GLOBALS['delivery_threshold'] = 999;
function WC() { return $GLOBALS['delivery_wc']; }
function get_option($key,$default=false) { return $key==='superwoo_offer_free_delivery_threshold' ? $GLOBALS['delivery_threshold'] : $default; }
function __($text,$domain='') { return $text; }
function esc_html($value) { return htmlspecialchars((string)$value,ENT_QUOTES); }
function esc_attr($value) { return esc_html($value); }
function esc_attr_e($value,$domain='') { echo esc_attr(__($value,$domain)); }
function wp_kses_post($value) { return $value; }
function wp_strip_all_tags($value) { return strip_tags($value); }
function wc_price($value) { return 'INR'.$value; }
require_once dirname(__DIR__).'/includes/functions.php';
require_once dirname(__DIR__).'/includes/class-cart-drawer.php';
function delivery_check($ok,$message) { if (!$ok) { throw new RuntimeException($message); } }
$drawer=new SuperWoo_Cart_Drawer();
$html=superwoo_cart_offer_notices_html();
delivery_check($drawer->get_cart_notice_message()==='', 'Delivery is still in the separate top banner.');
delivery_check(strpos($html,'Add INR801 more to unlock free delivery.')!==false,'Delivery remaining amount is incorrect.');
delivery_check(strpos($html,'Gift at INR2000')===false,'A lower-priority offer appeared before free delivery was unlocked.');
delivery_check(substr_count($html,'class="superwoo-bundle-progress"')===1,'Only the highest-priority free-delivery progress bar should be visible.');
delivery_check(strpos($html,'INR999')!==false && strpos($html,'INR2000')===false,'The free-delivery target was not prioritized.');
delivery_check(strpos($html,'class="superwoo-cart-progress-group"')<strpos($html,'free delivery.'),'Delivery is outside the shared card.');
SuperWoo_Bundle_Offers::$html='';
$html=superwoo_cart_offer_notices_html();
delivery_check(strpos($html,'INR999')!==false && substr_count($html,'class="superwoo-bundle-progress"')===1,'Delivery-only cart has no threshold bar.');
WC()->cart->shipping=0;
SuperWoo_Bundle_Offers::$html='<div class="superwoo-bundle-notice"><div>Next offer</div><div class="superwoo-bundle-progress">Next offer progress</div></div>';
$html=superwoo_cart_offer_notices_html();
delivery_check(strpos($html,'Free delivery is applied to this order.')!==false,'Actual free shipping is missing.');
delivery_check(strpos($html,'Next offer')!==false && substr_count($html,'class="superwoo-bundle-progress"')===1,'The next offer did not take over after free delivery was achieved.');
WC()->cart->shipping=75;
WC()->cart->items[0]['line_subtotal']=1200;
delivery_check($drawer->get_delivery_progress()===[],'Paid shipping was falsely called free above the marketing threshold.');
WC()->cart->items[0]['line_subtotal']=198;
WC()->cart->calculated=false;
delivery_check(!empty($drawer->get_delivery_progress()) && empty($drawer->get_delivery_progress()['applied']),'Pending free-delivery progress disappeared before shipping calculation.');
$GLOBALS['delivery_threshold']=0;
delivery_check($drawer->get_delivery_progress()===[],'Disabled delivery promotion remained visible.');
delivery_check(WC()->cart->shipping===75,'Delivery rendering modified shipping.');
$cart_css = file_get_contents(dirname(__DIR__) . '/public/css/cart-drawer.css');
delivery_check(is_string($cart_css) && preg_match('/\.superwoo-cart-shell\s+\.superwoo-bundle-progress__labels\s*\{[^}]*color:\s*#071120\s*;/s', $cart_css), 'Progress labels do not retain readable dark text across the filled and unfilled track.');
echo "PASS: unified offer/delivery card, fallback progress, truthful shipping states and read-only rendering\n";
