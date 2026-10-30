<?php
/** Unit checks for merchant-controlled trust copy sanitization. */
define('ABSPATH', __DIR__ . '/');
define('SUPERWOO_PATH', dirname(__DIR__) . '/');

function __($value, $domain = '') { return $value; }
function sanitize_text_field($value) { return trim(strip_tags((string) $value)); }
function sanitize_key($value) { return preg_replace('/[^a-z0-9_\-]/', '', strtolower((string) $value)); }
function esc_url_raw($value) {
    $scheme = parse_url((string) $value, PHP_URL_SCHEME);
    return in_array($scheme, ['http', 'https'], true) ? (string) $value : '';
}

require_once dirname(__DIR__) . '/includes/functions.php';
require_once dirname(__DIR__) . '/includes/class-plugin.php';
$plugin = (new ReflectionClass('SuperWoo_Plugin'))->newInstanceWithoutConstructor();
$method = (new ReflectionClass('SuperWoo_Plugin'))->getMethod('sanitize_cart_trust_items');
$input = [
    'shipping' => ['enabled' => '1', 'heading' => '<b>Delivery info</b>', 'description' => 'Enter delivery details', 'url' => 'https://shop.example/policy', 'icon' => 'shipping'],
    'secure' => ['enabled' => '', 'heading' => 'Secure', 'description' => 'Merchant wording', 'url' => 'javascript:alert(1)', 'icon' => 'unknown'],
    'unexpected' => ['enabled' => '1', 'heading' => 'Injected'],
];
$output = $method->invoke($plugin, $input);

if (count($output) !== 4 || $output['shipping']['heading'] !== 'Delivery info' || !$output['shipping']['enabled']) {
    throw new RuntimeException('Trust copy fields were not normalized/sanitized.');
}
if ($output['secure']['url'] !== '' || $output['secure']['icon'] !== 'secure' || $output['secure']['enabled']) {
    throw new RuntimeException('Unsafe trust link/icon or disabled state was not handled.');
}
if (isset($output['unexpected'])) {
    throw new RuntimeException('Unknown trust item was accepted.');
}

echo "PASS: merchant trust copy, links, icons, and item keys are sanitized\n";
