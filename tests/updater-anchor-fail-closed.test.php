<?php
/** A defined but empty trust anchor is a provisioning error, never legacy permission. */
define('ABSPATH', __DIR__ . '/');
define('MINUTE_IN_SECONDS', 60);
define('SUPERWOO_VERSION', '1.6.31');
define('SUPERWOO_FILE', dirname(__DIR__) . '/superwoo.php');
define('SUPERWOO_UPDATE_TRUSTED_PUBLIC_KEYS', []);

class WP_Error {
    public function __construct($code = '') {}
}
$document_body = '';
$transient = false;
function add_filter($hook, $callback, $priority = 10, $accepted_args = 1) {}
function add_action($hook, $callback, $priority = 10, $accepted_args = 1) {}
function plugin_basename($file) { return 'superwoo/superwoo.php'; }
function __( $text, $domain = null) { return $text; }
function is_wp_error($value) { return $value instanceof WP_Error; }
function get_site_transient($key) { global $transient; return $transient; }
function set_site_transient($key, $value, $expiration = 0) { global $transient; $transient = $value; return true; }
function delete_site_transient($key) { global $transient; $transient = false; return true; }
function wp_safe_remote_get($url, $args = []) { return ['response' => ['code' => 200]]; }
function wp_remote_retrieve_response_code($response) { return (int) ($response['response']['code'] ?? 0); }
function wp_remote_retrieve_body($response) { global $document_body; return $document_body; }
require_once dirname(__DIR__) . '/includes/class-github-updater.php';

$document_body = json_encode([
    'version' => '9.9.9',
    'package' => 'https://digtize.com/plugins/superwoo/SuperWoo-9.9.9.zip',
    'sha256' => str_repeat('a', 64),
    'package_size' => 1,
], JSON_UNESCAPED_SLASHES);
$updater = new SuperWoo_GitHub_Updater();
$result = $updater->inject_update((object) ['response' => []]);
if (!empty($result->response['superwoo/superwoo.php'])) {
    fwrite(STDERR, "FAIL: empty defined trust anchors allowed unsigned update\n");
    exit(1);
}
fwrite(STDOUT, "PASS: explicitly defined empty trust anchors fail closed\n");
