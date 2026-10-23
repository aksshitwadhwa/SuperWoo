<?php
/** In-process signed-release / update-server fixture integration tests. */
define('ABSPATH', __DIR__ . '/');
define('MINUTE_IN_SECONDS', 60);
define('SUPERWOO_VERSION', '1.5.5');
define('SUPERWOO_FILE', dirname(__DIR__) . '/superwoo.php');

class WP_Error {
    private $code;
    public function __construct($code = '') { $this->code = $code; }
    public function get_error_code() { return $this->code; }
}
$fixture_transients = [];
$fixture_options = [];
$fixture_server = null;
function add_filter($hook, $callback, $priority = 10, $accepted_args = 1) {}
function add_action($hook, $callback, $priority = 10, $accepted_args = 1) {}
function plugin_basename($file) { return 'superwoo/superwoo.php'; }
function __( $text, $domain = null) { return $text; }
function is_wp_error($value) { return $value instanceof WP_Error; }
function get_site_transient($key) { global $fixture_transients; return $fixture_transients[$key] ?? false; }
function set_site_transient($key, $value, $expiration = 0) { global $fixture_transients; $fixture_transients[$key] = $value; return true; }
function delete_site_transient($key) { global $fixture_transients; unset($fixture_transients[$key]); return true; }
function get_site_option($key, $default = false) { global $fixture_options; return $fixture_options[$key] ?? $default; }
function update_site_option($key, $value) { global $fixture_options; $fixture_options[$key] = $value; return true; }
function wp_tempnam($filename = '') { return tempnam(sys_get_temp_dir(), 'sw-release-'); }
function wp_safe_remote_get($url, $args = []) { global $fixture_server; return $fixture_server->respond($url, $args); }
function wp_remote_retrieve_response_code($response) { return is_array($response) ? (int) ($response['response']['code'] ?? 0) : 0; }
function wp_remote_retrieve_body($response) { return is_array($response) ? (string) ($response['body'] ?? '') : ''; }
function wp_remote_retrieve_header($response, $header) { return is_array($response) ? ($response['headers'][strtolower($header)] ?? '') : ''; }

require_once dirname(__DIR__) . '/includes/class-superwoo-update-manifest.php';
require_once __DIR__ . '/fixtures/superwoo-update-server.php';
require_once dirname(__DIR__) . '/includes/class-github-updater.php';

$count = 0;
function release_assert($condition, $message) {
    global $count;
    $count++;
    if (!$condition) { fwrite(STDERR, "FAIL: {$message}\n"); exit(1); }
}
function release_same($expected, $actual, $message) {
    release_assert($expected === $actual, $message . ' (expected ' . var_export($expected, true) . ', got ' . var_export($actual, true) . ')');
}
class Release_Fixture_Updater extends SuperWoo_GitHub_Updater {
    public $keys = [];
    protected function trusted_public_keys() { return $this->keys; }
}

$tmp = sys_get_temp_dir() . '/superwoo-signed-release-' . bin2hex(random_bytes(6));
mkdir($tmp, 0700);
$remove = static function ($path) use (&$remove) {
    if (is_dir($path)) { foreach (array_diff(scandir($path), ['.', '..']) as $child) { $remove($path . DIRECTORY_SEPARATOR . $child); } rmdir($path); }
    elseif (file_exists($path)) { unlink($path); }
};
$zip_path = $tmp . '/SuperWoo-1.5.6.zip';
$zip = new ZipArchive();
release_assert(true === $zip->open($zip_path, ZipArchive::CREATE | ZipArchive::EXCL), 'creates isolated fixture ZIP');
$zip->addFromString('SuperWoo/superwoo.php', "<?php\n/* Plugin Name: SuperWoo\n * Version: 1.5.6\n */\n");
$zip->addFromString('SuperWoo/readme.txt', "Fixture release.\n");
$zip->close();

$keypair = sodium_crypto_sign_seed_keypair(str_repeat("\x31", SODIUM_CRYPTO_SIGN_SEEDBYTES));
$secret_b64 = base64_encode(sodium_crypto_sign_secretkey($keypair));
$public_b64 = base64_encode(sodium_crypto_sign_publickey($keypair));
$key_path = $tmp . '/dev-key.b64';
file_put_contents($key_path, $secret_b64 . "\n");
chmod($key_path, 0600);
$manifest_path = $tmp . '/update.json';
$command = escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg(dirname(__DIR__) . '/tools/sign-superwoo-release.php')
    . ' --package=' . escapeshellarg($zip_path)
    . ' --version=1.5.6 --package-url=https://digtize.com/plugins/superwoo/SuperWoo-1.5.6.zip'
    . ' --sequence=101 --key-id=dev-2026 --expires-at=' . escapeshellarg(gmdate('Y-m-d\TH:i:s\Z', time() + 3600))
    . ' --private-key-file=' . escapeshellarg($key_path) . ' --output=' . escapeshellarg($manifest_path);
exec($command . ' 2>&1', $tool_output, $tool_exit);
release_same(0, $tool_exit, 'release signer CLI succeeds with development key and fixture package');
$signed_body = file_get_contents($manifest_path);
$envelope = json_decode($signed_body, true);
release_assert(is_array($envelope) && SuperWoo_Update_Manifest::canonical_json($envelope) === $signed_body, 'CLI writes byte-for-byte canonical verifier envelope');
release_assert(SuperWoo_Update_Manifest::verify_manifest_signature($envelope['manifest'], $envelope['signature'], $public_b64), 'CLI signature verifies with Stage 1 Ed25519 verifier primitive');
release_same(hash_file('sha256', $zip_path), $envelope['manifest']['sha256'] ?? null, 'manifest digest is calculated from final ZIP bytes');
release_same(filesize($zip_path), $envelope['manifest']['package_size'] ?? null, 'manifest includes exact package byte length');
$run_signer = static function ($arguments) use ($zip_path, $key_path, $tmp) {
    $out = $arguments['output'] ?? ($tmp . '/negative-' . bin2hex(random_bytes(4)) . '.json');
    $base = escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg(dirname(__DIR__) . '/tools/sign-superwoo-release.php')
        . ' --package=' . escapeshellarg($zip_path) . ' --version=1.5.6'
        . ' --package-url=' . escapeshellarg($arguments['url'] ?? 'https://digtize.com/plugins/superwoo/SuperWoo-1.5.6.zip')
        . ' --sequence=' . escapeshellarg($arguments['sequence'] ?? '102')
        . ' --key-id=' . escapeshellarg($arguments['key_id'] ?? 'dev-2026')
        . ' --expires-at=' . escapeshellarg($arguments['expires'] ?? gmdate('Y-m-d\\TH:i:s\\Z', time() + 3600))
        . ' --private-key-file=' . escapeshellarg($arguments['key'] ?? $key_path)
        . ' --output=' . escapeshellarg($out);
    exec($base . ' 2>&1', $lines, $status);
    return [$status, $out];
};
[$overwrite_status] = $run_signer(['output' => $manifest_path]);
release_assert(1 === $overwrite_status, 'signer refuses to overwrite existing signed metadata');
[$host_status, $host_output] = $run_signer(['url' => 'https://evil.example/plugins/superwoo/SuperWoo-1.5.6.zip']);
release_assert(1 === $host_status && !file_exists($host_output), 'signer refuses wrong package host without producing metadata');
[$path_status, $path_output] = $run_signer(['url' => 'https://digtize.com/plugins/elsewhere/SuperWoo-1.5.6.zip']);
release_assert(1 === $path_status && !file_exists($path_output), 'signer refuses wrong package path without producing metadata');
[$expired_status, $expired_output] = $run_signer(['expires' => gmdate('Y-m-d\\TH:i:s\\Z', time() - 60)]);
release_assert(1 === $expired_status && !file_exists($expired_output), 'signer refuses already expired metadata');
chmod($key_path, 0644);
[$permission_status, $permission_output] = $run_signer([]);
release_assert(1 === $permission_status && !file_exists($permission_output), 'signer rejects a group/world-readable private key file');
chmod($key_path, 0600);

$server = new SuperWoo_Update_Server_Fixture();
$server->metadata_body = $signed_body;
$server->package_file = $zip_path;
$fixture_server = $server;
$updater = new Release_Fixture_Updater();
$updater->keys = ['dev-2026' => $public_b64];
$offer = $updater->inject_update((object) ['response' => []]);
release_same('1.5.6', $offer->response['superwoo/superwoo.php']->new_version ?? null, 'fixture API serves valid signed metadata into normal WordPress update offer');
$local_zip = $updater->verified_package_download(false, $envelope['manifest']['package'], null, ['plugin' => 'superwoo/superwoo.php']);
release_assert(is_string($local_zip) && is_file($local_zip), 'valid fixture package passes signed SHA-256 verification');
$install = $tmp . '/installed';
mkdir($install);
$install_zip = new ZipArchive();
release_assert(true === $install_zip->open($local_zip), 'verified local package opens for installation simulation');
release_assert($install_zip->extractTo($install), 'verified local package extracts to isolated installation fixture');
$install_zip->close();
$installed_header = file_get_contents($install . '/SuperWoo/superwoo.php');
release_assert(false !== strpos($installed_header, 'Version: 1.5.6'), 'simulated installed plugin has signed release version');
$updater->cleanup_temporary_package();

$reset = static function () use ($server) {
    global $fixture_transients, $fixture_options;
    $fixture_transients = [];
    $fixture_options = [];
    $server->metadata_error = false;
    $server->metadata_status = 200;
    $server->package_error = false;
    $server->package_status = 200;
};
$try_metadata = static function ($body, $status = 200) use ($server, $updater, $reset) {
    $reset();
    $server->metadata_body = $body;
    $server->metadata_status = $status;
    return $updater->inject_update((object) ['response' => []]);
};
$assert_no_offer = static function ($body, $status, $case) use ($try_metadata) {
    $result = $try_metadata($body, $status);
    release_assert(empty($result->response['superwoo/superwoo.php']), "{$case} fails closed without update offer");
};

$assert_no_offer('', 404, 'missing metadata');
$server->metadata_error = true;
release_assert(empty($updater->inject_update((object) ['response' => []])->response['superwoo/superwoo.php']), 'metadata network failure fails closed');
$server->metadata_error = false;
$assert_no_offer($signed_body, 302, 'metadata redirect');

$expired = $envelope['manifest'];
$expired['expires_at'] = gmdate('Y-m-d\TH:i:s\Z', time() - 30);
$expired_signature = base64_encode(sodium_crypto_sign_detached(SuperWoo_Update_Manifest::canonical_json($expired), sodium_crypto_sign_secretkey($keypair)));
$expired_body = SuperWoo_Update_Manifest::canonical_json(['manifest' => $expired, 'signature' => $expired_signature]);
$assert_no_offer($expired_body, 200, 'expired signed metadata');

$bad_signature = $envelope;
$bad_signature['signature'] = base64_encode(random_bytes(SODIUM_CRYPTO_SIGN_BYTES));
$assert_no_offer(SuperWoo_Update_Manifest::canonical_json($bad_signature), 200, 'invalid signature');
$wrong_host = $envelope['manifest'];
$wrong_host['package'] = 'https://evil.example/plugins/superwoo/SuperWoo-1.5.6.zip';
$wrong_sig = base64_encode(sodium_crypto_sign_detached(SuperWoo_Update_Manifest::canonical_json($wrong_host), sodium_crypto_sign_secretkey($keypair)));
$assert_no_offer(SuperWoo_Update_Manifest::canonical_json(['manifest' => $wrong_host, 'signature' => $wrong_sig]), 200, 'wrong signed package host');
$wrong_path = $envelope['manifest'];
$wrong_path['package'] = 'https://digtize.com/plugins/elsewhere/SuperWoo-1.5.6.zip';
$wrong_path_sig = base64_encode(sodium_crypto_sign_detached(SuperWoo_Update_Manifest::canonical_json($wrong_path), sodium_crypto_sign_secretkey($keypair)));
$assert_no_offer(SuperWoo_Update_Manifest::canonical_json(['manifest' => $wrong_path, 'signature' => $wrong_path_sig]), 200, 'wrong signed package path');

$downgrade = $envelope['manifest'];
$downgrade['version'] = '1.5.4';
$downgrade['sequence'] = 102;
$down_sig = base64_encode(sodium_crypto_sign_detached(SuperWoo_Update_Manifest::canonical_json($downgrade), sodium_crypto_sign_secretkey($keypair)));
$assert_no_offer(SuperWoo_Update_Manifest::canonical_json(['manifest' => $downgrade, 'signature' => $down_sig]), 200, 'signed lower-version release');

$reset();
$server->metadata_body = $signed_body;
$server->package_file = $zip_path;
$server->package_file = $tmp . '/modified.zip';
file_put_contents($server->package_file, file_get_contents($zip_path) . 'tamper');
$tampered_download = $updater->verified_package_download(false, $envelope['manifest']['package'], null, ['plugin' => 'superwoo/superwoo.php']);
release_assert(is_wp_error($tampered_download), 'modified ZIP body fails signed digest check');
$reset();
$server->metadata_body = $signed_body;
$server->package_file = $zip_path;
$server->package_status = 302;
$redirected_package = $updater->verified_package_download(false, $envelope['manifest']['package'], null, ['plugin' => 'superwoo/superwoo.php']);
release_assert(is_wp_error($redirected_package), 'package redirect fails closed');
$reset();
$server->metadata_body = $signed_body;
$server->package_error = true;
$network_package = $updater->verified_package_download(false, $envelope['manifest']['package'], null, ['plugin' => 'superwoo/superwoo.php']);
release_assert(is_wp_error($network_package), 'package network failure fails closed');

// Overlap rotation is explicit: the new key works while pinned, and the old key stops when removed.
$new_pair = sodium_crypto_sign_seed_keypair(str_repeat("\x66", SODIUM_CRYPTO_SIGN_SEEDBYTES));
$new_public = base64_encode(sodium_crypto_sign_publickey($new_pair));
$rotated = $envelope['manifest'];
$rotated['key_id'] = 'dev-2027';
$rotated['sequence'] = 103;
$rotated['expires_at'] = gmdate('Y-m-d\TH:i:s\Z', time() + 3600);
$rotated_signature = base64_encode(sodium_crypto_sign_detached(SuperWoo_Update_Manifest::canonical_json($rotated), sodium_crypto_sign_secretkey($new_pair)));
$rotated_body = SuperWoo_Update_Manifest::canonical_json(['manifest' => $rotated, 'signature' => $rotated_signature]);
$updater->keys['dev-2027'] = $new_public;
$reset();
$server->metadata_body = $rotated_body;
release_same('1.5.6', $updater->inject_update((object) ['response' => []])->response['superwoo/superwoo.php']->new_version ?? null, 'new signing key accepted during trust-anchor overlap');
unset($updater->keys['dev-2026']);
$assert_no_offer($signed_body, 200, 'retired development key');

$remove($tmp);
fwrite(STDOUT, "PASS: {$count} signed release / simulated update-server integration assertions (network disabled; development keys only)\n");
