<?php
/** Network-free regression tests for the unsigned legacy transition mode. */
define('ABSPATH', __DIR__ . '/');
define('MINUTE_IN_SECONDS', 60);
define('SUPERWOO_VERSION', '1.5.6');
define('SUPERWOO_FILE', dirname(__DIR__) . '/superwoo.php');

class WP_Error {
    private $code;
    public function __construct($code = '') { $this->code = $code; }
    public function get_error_code() { return $this->code; }
}

$test_transients = [];
$test_http_response = null;
$test_http_body = '';
$test_http_headers = [];
$test_package_file = null;
$test_http_calls = [];
$test_hooks = [];
function add_filter($hook, $callback, $priority = 10, $accepted_args = 1) { global $test_hooks; $test_hooks[] = ['filter', $hook]; }
function add_action($hook, $callback, $priority = 10, $accepted_args = 1) { global $test_hooks; $test_hooks[] = ['action', $hook]; }
function plugin_basename($file) { return 'superwoo/superwoo.php'; }
function __( $text, $domain = null) { return $text; }
function is_wp_error($value) { return $value instanceof WP_Error; }
function get_site_transient($key) { global $test_transients; return $test_transients[$key] ?? false; }
function set_site_transient($key, $value, $expiration = 0) { global $test_transients; $test_transients[$key] = $value; return true; }
function delete_site_transient($key) { global $test_transients; unset($test_transients[$key]); return true; }
function wp_tempnam($filename = '') { return tempnam(sys_get_temp_dir(), 'sw-https-update-'); }
function wp_safe_remote_get($url, $args = []) {
    global $test_http_calls, $test_http_response, $test_package_file;
    $test_http_calls[] = [$url, $args];
    if (!empty($args['stream']) && !empty($args['filename']) && is_string($test_package_file) && is_file($test_package_file)) {
        copy($test_package_file, $args['filename']);
    }
    return $test_http_response;
}
function wp_remote_retrieve_response_code($response) { return is_array($response) ? (int) ($response['response']['code'] ?? 0) : 0; }
function wp_remote_retrieve_body($response) { global $test_http_body; return $test_http_body; }
function wp_remote_retrieve_header($response, $header) { global $test_http_headers; return $test_http_headers[strtolower($header)] ?? ''; }

require_once dirname(__DIR__) . '/includes/class-github-updater.php';

$assertions = 0;
function update_assert($condition, $message) {
    global $assertions;
    $assertions++;
    if (!$condition) { fwrite(STDERR, "FAIL: {$message}\n"); exit(1); }
}
function update_same($expected, $actual, $message) {
    update_assert($expected === $actual, $message . ' (expected ' . var_export($expected, true) . ', got ' . var_export($actual, true) . ')');
}
function update_json($value) { return json_encode($value, JSON_UNESCAPED_SLASHES); }
function clear_update_state() {
    global $test_transients, $test_http_calls;
    $test_transients = [];
    $test_http_calls = [];
}
function queue_update_metadata($release, $code = 200) {
    global $test_http_response, $test_http_body, $test_http_headers;
    $test_http_body = update_json($release);
    $test_http_response = ['response' => ['code' => $code]];
    $test_http_headers = ['content-length' => (string) strlen($test_http_body)];
}

$updater = new SuperWoo_GitHub_Updater();
$updater->hooks();
update_assert(in_array(['filter', 'upgrader_pre_download'], $test_hooks, true), 'registers package verification hook');
update_assert(in_array(['action', 'upgrader_process_complete'], $test_hooks, true), 'registers temporary package cleanup');

$tmp = sys_get_temp_dir() . '/superwoo-https-update-' . bin2hex(random_bytes(5));
mkdir($tmp, 0700);
mkdir($tmp . '/SuperWoo');
file_put_contents($tmp . '/SuperWoo/superwoo.php', "<?php\n/* Plugin Name: SuperWoo\n * Version: 1.5.7\n */\n");
$zip_path = $tmp . '/SuperWoo-1.5.7.zip';
$zip = new ZipArchive();
update_assert(true === $zip->open($zip_path, ZipArchive::CREATE | ZipArchive::EXCL), 'creates test ZIP');
$zip->addFile($tmp . '/SuperWoo/superwoo.php', 'SuperWoo/superwoo.php');
$zip->close();
$package_size = filesize($zip_path);
$package_hash = hash_file('sha256', $zip_path);
$release = [
    'name' => 'SuperWoo',
    'slug' => 'superwoo',
    'version' => '1.5.7',
    'requires' => '6.2',
    'requires_php' => '7.4',
    'package' => 'https://digtize.com/plugins/superwoo/SuperWoo-1.5.7.zip',
    'sha256' => $package_hash,
    'package_size' => $package_size,
    'homepage' => 'https://digtize.com/plugins/superwoo/',
    'description' => 'Test release',
];

clear_update_state();
queue_update_metadata($release);
$offer = $updater->inject_update((object) ['response' => []]);
update_same('1.5.7', $offer->response['superwoo/superwoo.php']->new_version ?? null, 'valid HTTPS feed creates WordPress update offer');
update_same($release['package'], $offer->response['superwoo/superwoo.php']->package ?? null, 'update offer uses approved package');
update_same(SuperWoo_GitHub_Updater::API_URL, $test_http_calls[0][0] ?? null, 'unprovisioned client stays on the existing legacy metadata endpoint');
update_same(0, $test_http_calls[0][1]['redirection'] ?? null, 'metadata redirects disabled');
update_same(true, $test_http_calls[0][1]['sslverify'] ?? null, 'metadata TLS verification enabled');
update_same(65537, $test_http_calls[0][1]['limit_response_size'] ?? null, 'metadata response bounded');

$cached = $updater->inject_update((object) ['response' => []]);
update_same(1, count($test_http_calls), 'validated release is cached');
update_same('1.5.7', $cached->response['superwoo/superwoo.php']->new_version ?? null, 'cached release remains valid');

foreach ([
    ['http URL', 'http://digtize.com/plugins/superwoo/SuperWoo-1.5.7.zip'],
    ['wrong host', 'https://evil.example/plugins/superwoo/SuperWoo-1.5.7.zip'],
    ['subdomain', 'https://cdn.digtize.com/plugins/superwoo/SuperWoo-1.5.7.zip'],
    ['wrong path', 'https://digtize.com/plugins/other/SuperWoo-1.5.7.zip'],
    ['query string', 'https://digtize.com/plugins/superwoo/SuperWoo-1.5.7.zip?x=1'],
    ['fragment', 'https://digtize.com/plugins/superwoo/SuperWoo-1.5.7.zip#x'],
    ['credentials', 'https://user@digtize.com/plugins/superwoo/SuperWoo-1.5.7.zip'],
    ['nonstandard port', 'https://digtize.com:444/plugins/superwoo/SuperWoo-1.5.7.zip'],
    ['encoded path', 'https://digtize.com/plugins/superwoo/%2e%2e/SuperWoo-1.5.7.zip'],
] as [$case, $url]) {
    clear_update_state();
    $bad = $release;
    $bad['package'] = $url;
    queue_update_metadata($bad);
    update_assert(empty($updater->inject_update((object) ['response' => []])->response['superwoo/superwoo.php']), "rejects {$case}");
}

foreach ([301, 302, 404, 500] as $code) {
    clear_update_state();
    queue_update_metadata($release, $code);
    update_assert(empty($updater->inject_update((object) ['response' => []])->response['superwoo/superwoo.php']), "rejects metadata HTTP {$code}");
}
clear_update_state();
$test_http_response = new WP_Error('fixture_network_error');
update_assert(empty($updater->inject_update((object) ['response' => []])->response['superwoo/superwoo.php']), 'metadata network failure fails closed');
clear_update_state();
$test_http_body = str_repeat('x', 65537);
$test_http_response = ['response' => ['code' => 200]];
update_assert(empty($updater->inject_update((object) ['response' => []])->response['superwoo/superwoo.php']), 'oversized metadata fails closed');

$local_zip = null;
clear_update_state();
queue_update_metadata($release);
$test_package_file = $zip_path;
$test_http_response = ['response' => ['code' => 200]];
$test_http_headers = ['content-length' => (string) $package_size];
$local_zip = $updater->verified_package_download(false, $release['package'], null, ['plugin' => 'superwoo/superwoo.php']);
update_assert(is_string($local_zip) && is_file($local_zip), 'valid package downloaded and checked against feed hash');
$package_call = end($test_http_calls);
update_same(0, $package_call[1]['redirection'] ?? null, 'package redirects disabled');
update_same(true, $package_call[1]['sslverify'] ?? null, 'package TLS verification enabled');
$updater->cleanup_temporary_package();
update_assert(!file_exists($local_zip), 'temporary package cleaned up');

$bad = $release;
$bad['sha256'] = str_repeat('0', 64);
clear_update_state();
queue_update_metadata($bad);
$test_http_headers = ['content-length' => (string) $package_size];
$tampered = $updater->verified_package_download(false, $bad['package'], null, ['plugin' => 'superwoo/superwoo.php']);
update_assert(is_wp_error($tampered) && 'superwoo_update_integrity_failed' === $tampered->get_error_code(), 'modified ZIP rejected');

clear_update_state();
queue_update_metadata($release);
$test_http_response = ['response' => ['code' => 302]];
$test_http_headers = ['content-length' => (string) $package_size];
$redirected = $updater->verified_package_download(false, $release['package'], null, ['plugin' => 'superwoo/superwoo.php']);
update_assert(is_wp_error($redirected), 'package redirects fail closed');

clear_update_state();
queue_update_metadata($release);
$test_http_response = new WP_Error('fixture_package_network_error');
$network_failure = $updater->verified_package_download(false, $release['package'], null, ['plugin' => 'superwoo/superwoo.php']);
update_assert(is_wp_error($network_failure), 'package network failure fails closed');

clear_update_state();
queue_update_metadata($release);
$wrong_package = $updater->verified_package_download(false, 'https://evil.example/SuperWoo.zip', null, ['plugin' => 'superwoo/superwoo.php']);
update_assert(is_wp_error($wrong_package), 'package URL substitution is rejected');

$unrelated = (object) ['response' => ['other/other.php' => true]];
update_assert($unrelated === $updater->inject_update($unrelated), 'unrelated WordPress update transient is preserved');
$other_reply = false;
update_assert(false === $updater->verified_package_download($other_reply, 'https://example.org/plugin.zip', null, ['plugin' => 'other/other.php']), 'unrelated package downloads remain untouched');

foreach ([$tmp . '/SuperWoo/superwoo.php', $zip_path] as $path) { if (file_exists($path)) { unlink($path); } }
rmdir($tmp . '/SuperWoo');
rmdir($tmp);
fwrite(STDOUT, "PASS: {$assertions} legacy update compatibility assertions (transitional unsigned feed; not authenticated)\n");
