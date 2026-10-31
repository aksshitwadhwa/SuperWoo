<?php
/** Provisioned signed-only mode remains latched if a public-key constant is later removed. */
define('ABSPATH', __DIR__ . '/');
define('MINUTE_IN_SECONDS', 60);
define('SUPERWOO_VERSION', '1.6.31');
define('SUPERWOO_FILE', dirname(__DIR__) . '/superwoo.php');
class WP_Error { public function __construct($code = '') {} }
$requested_url = '';
$body = json_encode([
    'version' => '9.9.9',
    'package' => 'https://digtize.com/plugins/superwoo/SuperWoo-9.9.9.zip',
    'sha256' => str_repeat('a', 64),
    'package_size' => 1,
], JSON_UNESCAPED_SLASHES);
function add_filter($hook, $callback, $priority = 10, $accepted_args = 1) {}
function add_action($hook, $callback, $priority = 10, $accepted_args = 1) {}
function plugin_basename($file) { return 'superwoo/superwoo.php'; }
function __( $text, $domain = null) { return $text; }
function is_wp_error($value) { return $value instanceof WP_Error; }
function get_site_transient($key) { return false; }
function set_site_transient($key, $value, $expiration = 0) { return true; }
function delete_site_transient($key) { return true; }
function get_site_option($key, $default = false) { return 'superwoo_update_signed_mode_required' === $key ? 'required' : $default; }
function update_site_option($key, $value) { return true; }
function wp_safe_remote_get($url, $args = []) { global $requested_url; $requested_url = $url; return ['response' => ['code' => 200]]; }
function wp_remote_retrieve_response_code($response) { return (int) ($response['response']['code'] ?? 0); }
function wp_remote_retrieve_body($response) { global $body; return $body; }
require_once dirname(__DIR__) . '/includes/class-github-updater.php';
$updater = new SuperWoo_GitHub_Updater();
$result = $updater->inject_update((object) ['response' => []]);
if (!empty($result->response['superwoo/superwoo.php']) || SuperWoo_GitHub_Updater::SIGNED_API_URL !== $requested_url) {
    fwrite(STDERR, "FAIL: signed-only latch allowed legacy metadata or selected legacy endpoint\n");
    exit(1);
}
fwrite(STDOUT, "PASS: signed-only mode remains latched without the configured trust-anchor constant\n");
