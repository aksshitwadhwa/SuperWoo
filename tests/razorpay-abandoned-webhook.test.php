<?php
/** Standalone checks for the receive-only, redacted Razorpay webhook listener. */

define('ABSPATH', __DIR__ . '/');
define('MINUTE_IN_SECONDS', 60);
define('WP_CONTENT_DIR', sys_get_temp_dir() . '/superwoo-razorpay-webhook-' . uniqid('', true));

$superwoo_options = [
    'superwoo_razorpay_abandoned_webhook_token' => str_repeat('R', 64),
    'superwoo_razorpay_abandoned_webhook_short_token' => str_repeat('S', 24),
];
$registered_routes = [];
$superwoo_log_writable = true;
function add_action(...$args) {}
function register_rest_route($namespace, $route, $args) { global $registered_routes; $registered_routes[] = [$namespace, $route, $args]; }
function get_option($key, $default = false) { global $superwoo_options; return $superwoo_options[$key] ?? $default; }
function update_option($key, $value, $autoload = null) { global $superwoo_options; $superwoo_options[$key] = $value; return true; }
function wp_generate_password($length = 12, $special = true, $extra = false) { return str_repeat('S', $length); }
function wp_generate_uuid4() { return 'receipt-test-123'; }
function rest_url($path = '') { return 'https://modu.co.in/wp-json/' . $path; }
function home_url($path = '/') { return 'https://modu.co.in' . $path; }
function wp_parse_url($url, $component = -1) { return parse_url($url, $component); }
function wp_upload_dir() { return ['basedir' => WP_CONTENT_DIR . '/uploads', 'error' => false]; }
function trailingslashit($path) { return rtrim($path, '/\\') . DIRECTORY_SEPARATOR; }
function wp_mkdir_p($path) { return is_dir($path) || mkdir($path, 0777, true); }
function superwoo_protect_log_directory($directory) { global $superwoo_log_writable; return $superwoo_log_writable && wp_mkdir_p($directory); }
function superwoo_rate_limit($bucket, $limit, $window = 60) { return true; }
function sanitize_key($value) { return strtolower(preg_replace('/[^a-z0-9_\-]/i', '', (string) $value)); }
function sanitize_text_field($value) { return trim(strip_tags((string) $value)); }
function absint($value) { return abs((int) $value); }
function wp_json_encode($value, $flags = 0) { return json_encode($value, $flags); }
function __($value, $domain = null) { return $value; }

class WP_Error {
    public $code;
    public $message;
    public $data;
    public function __construct($code, $message = '', $data = []) { $this->code = $code; $this->message = $message; $this->data = $data; }
    public function get_error_data() { return $this->data; }
}
class WP_REST_Response {
    public $data;
    public $status;
    public function __construct($data, $status = 200) { $this->data = $data; $this->status = $status; }
}
class Webhook_Test_Request {
    private $params;
    private $body;
    private $method;
    public function __construct($params, $body, $method = 'POST') { $this->params = $params; $this->body = $body; $this->method = $method; }
    public function get_param($key) { $json = json_decode($this->body, true); return is_array($json) && array_key_exists($key, $json) ? $json[$key] : ($this->params[$key] ?? null); }
    public function get_url_params() { return $this->params; }
    public function get_body() { return $this->body; }
    public function get_method() { return $this->method; }
}

require dirname(__DIR__) . '/includes/class-razorpay-abandoned-webhook.php';

function expect_webhook($condition, $message) {
    if (!$condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
}

$webhook = new SuperWoo_Razorpay_Abandoned_Webhook();
$webhook->register_route();
expect_webhook(2 === count($registered_routes), 'legacy and compact REST receiver routes should be registered');
expect_webhook(['GET', 'HEAD', 'POST'] === $registered_routes[0][2]['methods'] && ['GET', 'HEAD', 'POST'] === $registered_routes[1][2]['methods'], 'both routes should accept read-only probes and webhook POSTs');
expect_webhook(false !== strpos(SuperWoo_Razorpay_Abandoned_Webhook::callback_url(), str_repeat('S', 24)), 'callback URL should carry the compact secret token');
expect_webhook(strlen(SuperWoo_Razorpay_Abandoned_Webhook::callback_url()) <= 60, 'compact callback URL should stay below 60 characters on the expected domain');
expect_webhook(true === $webhook->authorize(new Webhook_Test_Request(['token' => str_repeat('R', 64)], '')), 'correct token on HTTPS should authorize');
expect_webhook($webhook->authorize(new Webhook_Test_Request(['token' => 'incorrect'], '')) instanceof WP_Error, 'incorrect token should be rejected');
expect_webhook(true === $webhook->authorize(new Webhook_Test_Request(['token' => str_repeat('R', 64)], json_encode(['token' => 'razorpay-payload-token']))), 'JSON payload token must not override the authenticated route token');
expect_webhook(true === $webhook->authorize_short(new Webhook_Test_Request(['token' => str_repeat('S', 24)], '')), 'compact callback token should authorize');
expect_webhook($webhook->authorize_short(new Webhook_Test_Request(['token' => 'incorrect'], '')) instanceof WP_Error, 'incorrect compact token should be rejected');
expect_webhook(true === $webhook->authorize_short(new Webhook_Test_Request(['token' => str_repeat('S', 24)], json_encode(['token' => 'razorpay-payload-token']))), 'JSON payload token must not override the compact route token');
$readiness = $webhook->receive(new Webhook_Test_Request(['token' => str_repeat('R', 64)], '', 'GET'));
expect_webhook($readiness instanceof WP_REST_Response && 200 === $readiness->status && ['ready' => true] === $readiness->data, 'GET URL validation should receive a simple readiness response');
$head_readiness = $webhook->receive(new Webhook_Test_Request(['token' => str_repeat('R', 64)], '', 'HEAD'));
expect_webhook($head_readiness instanceof WP_REST_Response && 200 === $head_readiness->status, 'HEAD URL validation should receive a successful response');
$empty_post_probe = $webhook->receive(new Webhook_Test_Request(['token' => str_repeat('R', 64)], ''));
expect_webhook($empty_post_probe instanceof WP_REST_Response && 200 === $empty_post_probe->status, 'empty POST validation probe should not be rejected');
expect_webhook(!file_exists(SuperWoo_Razorpay_Abandoned_Webhook::log_file_path()), 'readiness probes should not create payload receipts');

$payload = [
    'platform' => 'woocommerce',
    'currency' => 'INR',
    'email' => 'customer-private@example.test',
    'phone' => '+919999999999',
    'token' => 'secret-cart-token-value',
    'cart_token' => 'private-cart-token-value',
    'abandoned_checkout_url' => 'https://shop.example.test/private-checkout?key=private',
    'line_items' => [['name' => 'Private product name', 'quantity' => 2]],
    'promotions' => [['code' => 'PRIVATE-CODE']],
    'utm_parameters' => ['utm_source' => 'campaign'],
    'customer' => ['email' => 'customer-private@example.test', 'Shipping_address' => ['Address1' => 'Private address']],
];
$body = json_encode($payload);
$response = $webhook->receive(new Webhook_Test_Request(['token' => str_repeat('R', 64)], $body));
expect_webhook($response instanceof WP_REST_Response && 202 === $response->status, 'valid JSON should receive an accepted response');
expect_webhook(['received' => true, 'receipt_id' => 'receipt-test-123'] === $response->data, 'response should reveal only receipt acknowledgement');

$log_path = SuperWoo_Razorpay_Abandoned_Webhook::log_file_path();
$log = file_get_contents($log_path);
$entry = json_decode(trim($log), true);
expect_webhook('woocommerce' === $entry['platform'] && 'INR' === $entry['currency'], 'safe platform and currency summary should be recorded');
expect_webhook(1 === $entry['line_item_count'] && 1 === $entry['promotion_count'] && 1 === $entry['utm_parameter_count'], 'payload structure counts should be recorded');
expect_webhook(true === $entry['email_field_present'] && true === $entry['phone_field_present'], 'presence booleans should confirm customer fields arrived');
foreach (['customer-private@example.test', '+919999999999', 'secret-cart-token-value', 'private-cart-token-value', 'Private address', 'PRIVATE-CODE', 'Private product name', 'private-checkout'] as $sensitive_value) {
    expect_webhook(false === strpos($log, $sensitive_value), 'raw sensitive payload value must not appear in the receipt log');
}

$invalid = $webhook->receive(new Webhook_Test_Request(['token' => str_repeat('R', 64)], '{invalid json'));
expect_webhook($invalid instanceof WP_Error && 400 === $invalid->get_error_data()['status'], 'malformed JSON should be rejected');
$oversized = $webhook->receive(new Webhook_Test_Request(['token' => str_repeat('R', 64)], str_repeat('x', SuperWoo_Razorpay_Abandoned_Webhook::MAX_BODY_BYTES + 1)));
expect_webhook($oversized instanceof WP_Error && 413 === $oversized->get_error_data()['status'], 'oversized webhook should be rejected');
$empty_object = $webhook->receive(new Webhook_Test_Request(['token' => str_repeat('R', 64)], '{}'));
expect_webhook($empty_object instanceof WP_Error && 400 === $empty_object->get_error_data()['status'], 'empty payload object should be rejected');
$superwoo_log_writable = false;
$log_failure = $webhook->receive(new Webhook_Test_Request(['token' => str_repeat('R', 64)], json_encode(['platform' => 'woocommerce'])));
expect_webhook($log_failure instanceof WP_Error && 503 === $log_failure->get_error_data()['status'], 'receipt must not be acknowledged when the redacted log cannot be written');

$remove_tree = static function ($path) use (&$remove_tree) {
    if (is_dir($path)) {
        foreach (array_diff(scandir($path), ['.', '..']) as $child) {
            $remove_tree($path . DIRECTORY_SEPARATOR . $child);
        }
        rmdir($path);
    } elseif (file_exists($path)) {
        unlink($path);
    }
};
$remove_tree(WP_CONTENT_DIR);
echo "PASS: 32 Razorpay abandoned-webhook assertions\n";
