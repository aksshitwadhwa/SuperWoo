<?php
/** Standalone regression checks for SuperWoo's security hardening helpers. */

define('ABSPATH', __DIR__ . '/');
define('MINUTE_IN_SECONDS', 60);
define('WP_CONTENT_DIR', sys_get_temp_dir() . '/superwoo-security-content-' . uniqid('', true));

$superwoo_transients = [];
$superwoo_http_calls = [];
function wp_salt($scheme = 'auth') { return 'test-only-salt-' . $scheme; }
function sanitize_key($key) { return strtolower(preg_replace('/[^a-z0-9_\-]/', '', (string) $key)); }
function absint($value) { return abs((int) $value); }
function get_transient($key) { global $superwoo_transients; return $superwoo_transients[$key]['value'] ?? false; }
function set_transient($key, $value, $expiration = 0) { global $superwoo_transients; $superwoo_transients[$key] = ['value' => $value, 'expiration' => $expiration]; return true; }
function wp_mkdir_p($path) { return is_dir($path) || mkdir($path, 0777, true); }
function trailingslashit($path) { return rtrim($path, '/\\') . DIRECTORY_SEPARATOR; }
function wp_upload_dir() { return ['basedir' => WP_CONTENT_DIR . '/uploads', 'error' => false]; }
function wp_parse_url($url) { return parse_url($url); }
function add_query_arg($args, $url) { return $url . (false === strpos($url, '?') ? '?' : '&') . http_build_query($args); }
function esc_url_raw($url) { return $url; }
function wp_safe_remote_get($url, $args = []) { global $superwoo_http_calls; $superwoo_http_calls[] = [$url, $args]; return []; }
function is_wp_error($value) { return false; }
function wp_remote_retrieve_body($response) { return ''; }

require dirname(__DIR__) . '/includes/functions.php';
require dirname(__DIR__) . '/includes/class-currency.php';

function expect_security($condition, $message) {
    if (!$condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
}

$_SERVER['REMOTE_ADDR'] = '203.0.113.45';
expect_security(superwoo_rate_limit('video_event', 2, 60), 'first request should pass');
expect_security(superwoo_rate_limit('video_event', 2, 60), 'request at the configured limit should pass');
expect_security(!superwoo_rate_limit('video_event', 2, 60), 'request above the configured limit should be rejected');
$stored_keys = array_keys($superwoo_transients);
expect_security(1 === count($stored_keys) && false === strpos($stored_keys[0], '203.0.113.45'), 'rate-limit storage key must not expose the client IP');

$log_path = superwoo_log_file_path();
expect_security(superwoo_protect_log_directory(dirname($log_path)), 'diagnostic directory should be creatable/protectable');
expect_security(file_exists(dirname($log_path) . '/index.php'), 'directory index guard should exist');
expect_security(false !== strpos(file_get_contents(dirname($log_path) . '/.htaccess'), 'Require all denied'), 'Apache deny rule should exist');
expect_security(false !== strpos(file_get_contents(dirname($log_path) . '/web.config'), '<deny users="*"'), 'IIS deny rule should exist');

$legacy_log = WP_CONTENT_DIR . '/superwoo-fatal.log';
file_put_contents($legacy_log, "legacy diagnostic\n");
$fatal_log = superwoo_fatal_log_file_path();
expect_security($fatal_log === dirname($log_path) . '/superwoo-fatal.log', 'fatal diagnostics should resolve inside the protected log directory');
expect_security(file_exists($fatal_log) && !file_exists($legacy_log), 'legacy web-root fatal log should be relocated');

$currency = (new ReflectionClass('SuperWoo_Currency'))->newInstanceWithoutConstructor();
$rates_method = new ReflectionMethod('SuperWoo_Currency', 'get_api_rates');
$rates_method->invoke($currency, ['exchange_rate_api_url' => 'http://127.0.0.1/internal'], ['USD']);
expect_security(0 === count($superwoo_http_calls), 'HTTP and private-network API URLs must not be requested');
$rates_method->invoke($currency, ['exchange_rate_api_url' => 'https://rates.example/api'], ['USD']);
expect_security(1 === count($superwoo_http_calls) && 'https' === parse_url($superwoo_http_calls[0][0], PHP_URL_SCHEME), 'public HTTPS rate endpoint should use the safe WordPress HTTP API');

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

echo "PASS: 12 security hardening assertions\n";
