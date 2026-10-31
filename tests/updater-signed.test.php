<?php
/** Signed updater integration tests with ephemeral keys and mocked WordPress HTTP/options APIs. */
if (!extension_loaded('sodium') || !class_exists('ZipArchive')) {
    fwrite(STDERR, "SKIP: signed updater tests require sodium and ZipArchive\n");
    exit(0);
}

define('ABSPATH', __DIR__ . '/');
define('MINUTE_IN_SECONDS', 60);
define('SUPERWOO_VERSION', '1.6.31');
define('SUPERWOO_FILE', dirname(__DIR__) . '/superwoo.php');

$pair_a = sodium_crypto_sign_keypair();
$secret_a = sodium_crypto_sign_secretkey($pair_a);
$public_a = sodium_crypto_sign_publickey($pair_a);
$pair_b = sodium_crypto_sign_keypair();
$secret_b = sodium_crypto_sign_secretkey($pair_b);
$public_b = sodium_crypto_sign_publickey($pair_b);
define('SUPERWOO_UPDATE_TRUSTED_PUBLIC_KEYS', [
    'test-key-a' => base64_encode($public_a),
    'test-key-b' => base64_encode($public_b),
]);

class WP_Error {
    private $code;
    public function __construct($code = '') { $this->code = $code; }
    public function get_error_code() { return $this->code; }
}

$signed_transients = [];
$signed_options = [];
$signed_option_write_result = true;
$signed_http_response = null;
$signed_http_body = '';
$signed_http_headers = [];
$signed_package_file = null;
$signed_http_calls = [];
function add_filter($hook, $callback, $priority = 10, $accepted_args = 1) {}
function add_action($hook, $callback, $priority = 10, $accepted_args = 1) {}
function plugin_basename($file) { return 'SuperWoo/superwoo.php'; }
function __( $text, $domain = null) { return $text; }
function is_wp_error($value) { return $value instanceof WP_Error; }
function get_site_transient($key) { global $signed_transients; return $signed_transients[$key] ?? false; }
function set_site_transient($key, $value, $expiration = 0) { global $signed_transients; $signed_transients[$key] = $value; return true; }
function delete_site_transient($key) { global $signed_transients; unset($signed_transients[$key]); return true; }
function get_site_option($key, $default = false) { global $signed_options; return $signed_options[$key] ?? $default; }
function update_site_option($key, $value) { global $signed_options, $signed_option_write_result; if (!$signed_option_write_result) { return false; } $signed_options[$key] = $value; return true; }
function wp_tempnam($filename = '') { return tempnam(sys_get_temp_dir(), 'sw-signed-update-'); }
function wp_safe_remote_get($url, $args = []) {
    global $signed_http_calls, $signed_http_response, $signed_package_file;
    $signed_http_calls[] = [$url, $args];
    if (!empty($args['stream']) && !empty($args['filename']) && is_string($signed_package_file) && is_file($signed_package_file)) {
        copy($signed_package_file, $args['filename']);
    }
    return $signed_http_response;
}
function wp_remote_retrieve_response_code($response) { return is_array($response) ? (int) ($response['response']['code'] ?? 0) : 0; }
function wp_remote_retrieve_body($response) { global $signed_http_body; return $signed_http_body; }
function wp_remote_retrieve_header($response, $header) { global $signed_http_headers; return $signed_http_headers[strtolower($header)] ?? ''; }

require_once dirname(__DIR__) . '/includes/class-github-updater.php';

$assertions = 0;
function signed_assert($condition, $label) {
    global $assertions;
    $assertions++;
    if (!$condition) { fwrite(STDERR, "FAIL: {$label}\n"); exit(1); }
}
function signed_same($expected, $actual, $label) {
    signed_assert($expected === $actual, $label . ' (expected ' . var_export($expected, true) . ', got ' . var_export($actual, true) . ')');
}
function signed_reset_transient() {
    global $signed_transients, $signed_http_calls;
    $signed_transients = [];
    $signed_http_calls = [];
}
function signed_manifest($version, $sequence, $key_id, $package, $size, $sha256, $expiry = null) {
    return [
        'schema_version' => 1,
        'slug' => 'superwoo',
        'version' => $version,
        'requires' => '6.2',
        'requires_php' => '7.4',
        'package' => $package,
        'sha256' => $sha256,
        'package_size' => (int) $size,
        'sequence' => (int) $sequence,
        'key_id' => $key_id,
        'expires_at' => $expiry ?: gmdate('Y-m-d\\TH:i:s\\Z', time() + 86400),
    ];
}
function signed_envelope($manifest, $secret) {
    $encoded_secret = base64_encode($secret);
    return ['manifest' => $manifest, 'signature' => SuperWoo_Update_Manifest::sign_manifest($manifest, $encoded_secret)];
}
function signed_queue($envelope, $status = 200, $raw = null) {
    global $signed_http_response, $signed_http_body, $signed_http_headers;
    $signed_http_body = null === $raw ? SuperWoo_Update_Manifest::canonical_json($envelope) : $raw;
    $signed_http_response = ['response' => ['code' => $status]];
    $signed_http_headers = ['content-length' => (string) strlen($signed_http_body)];
}
function signed_has_update($updater) {
    $result = $updater->inject_update((object) ['response' => []]);
    return $result->response['SuperWoo/superwoo.php'] ?? false;
}
function signed_remove_tree($path) {
    if (is_dir($path)) {
        foreach (new DirectoryIterator($path) as $entry) {
            if ($entry->isDot()) { continue; }
            signed_remove_tree($entry->getPathname());
        }
        rmdir($path);
    } elseif (is_file($path)) {
        unlink($path);
    }
}

$tmp = sys_get_temp_dir() . '/superwoo-signed-updater-' . bin2hex(random_bytes(6));
mkdir($tmp, 0700);
mkdir($tmp . '/SuperWoo', 0700);
file_put_contents($tmp . '/SuperWoo/superwoo.php', "<?php\n/* Plugin Name: SuperWoo\n * Version: 1.6.32\n */\n");
$zip_path = $tmp . '/SuperWoo-1.6.32.zip';
$zip = new ZipArchive();
signed_assert(true === $zip->open($zip_path, ZipArchive::CREATE | ZipArchive::EXCL), 'creates synthetic installable plugin ZIP');
$zip->addFile($tmp . '/SuperWoo/superwoo.php', 'SuperWoo/superwoo.php');
$zip->close();
$package_size = filesize($zip_path);
$package_hash = hash_file('sha256', $zip_path);
$package_url = 'https://digtize.com/plugins/superwoo/SuperWoo-1.6.32.zip';
$package_url_33 = 'https://digtize.com/plugins/superwoo/SuperWoo-1.6.33.zip';
$valid_manifest = signed_manifest('1.6.32', 100, 'test-key-a', $package_url, $package_size, $package_hash);
$valid_envelope = signed_envelope($valid_manifest, $secret_a);
$updater = new SuperWoo_GitHub_Updater();

signed_reset_transient();
signed_queue($valid_envelope);
$offer = signed_has_update($updater);
signed_same('1.6.32', $offer->new_version ?? null, 'valid signed manifest creates the WordPress update offer');
signed_same($package_url, $offer->package ?? null, 'offer package URL is bound to the signed manifest');
signed_same(SuperWoo_GitHub_Updater::SIGNED_API_URL, $signed_http_calls[0][0] ?? null, 'provisioned trust anchor selects the dedicated signed metadata endpoint');
signed_same(100, $signed_options[SuperWoo_GitHub_Updater::SEQUENCE_OPTION]['sequence'] ?? null, 'verified sequence is persisted as replay floor');
signed_same(0, $signed_http_calls[0][1]['redirection'] ?? null, 'metadata redirects disabled');
signed_same(true, $signed_http_calls[0][1]['sslverify'] ?? null, 'metadata TLS verification enabled');
$cached_offer = signed_has_update($updater);
signed_same('1.6.32', $cached_offer->new_version ?? null, 'cached signed envelope is reverified and accepted');
signed_same(1, count($signed_http_calls), 'valid signed metadata is cached without a second network request');

// A modified signature and a flat unsigned document both fail closed under an explicitly configured anchor.
$bad_signature = $valid_envelope;
$bad_signature['signature'] = base64_encode(random_bytes(64));
signed_reset_transient();
signed_queue($bad_signature);
signed_assert(false === signed_has_update($updater), 'invalid signature is rejected');
signed_same(1, count($signed_http_calls), 'invalid signature does not trigger legacy fallback or a second metadata request');
$flat = ['version' => '9.9.9', 'package' => $package_url, 'sha256' => $package_hash, 'package_size' => $package_size];
signed_reset_transient();
signed_queue($flat);
signed_assert(false === signed_has_update($updater), 'unsigned flat metadata is rejected when trust anchors are provisioned');

$wrong_key_id = signed_manifest('1.6.33', 101, 'not-trusted', $package_url, $package_size, $package_hash);
$unknown_key_envelope = signed_envelope($wrong_key_id, $secret_a);
signed_reset_transient();
signed_queue($unknown_key_envelope);
signed_assert(false === signed_has_update($updater), 'unknown key identifier is rejected');

$tampered_manifest = $valid_envelope;
$tampered_manifest['manifest']['version'] = '9.9.9';
signed_reset_transient();
signed_queue($tampered_manifest);
signed_assert(false === signed_has_update($updater), 'manifest field tampering invalidates signature');

$wrong_host = signed_manifest('1.6.33', 101, 'test-key-a', 'https://evil.example/plugins/superwoo/SuperWoo-1.6.33.zip', $package_size, $package_hash);
signed_reset_transient();
signed_queue(signed_envelope($wrong_host, $secret_a));
signed_assert(false === signed_has_update($updater), 'even trusted signature cannot authorize a package URL outside host allowlist');
$wrong_path = signed_manifest('1.6.33', 101, 'test-key-a', 'https://digtize.com/plugins/elsewhere/SuperWoo-1.6.33.zip', $package_size, $package_hash);
signed_reset_transient();
signed_queue(signed_envelope($wrong_path, $secret_a));
signed_assert(false === signed_has_update($updater), 'wrong package path is rejected');
$version_mismatched_url = signed_manifest('1.6.33', 101, 'test-key-a', $package_url, $package_size, $package_hash);
signed_reset_transient();
signed_queue(signed_envelope($version_mismatched_url, $secret_a));
signed_assert(false === signed_has_update($updater), 'package filename must bind to signed release version');

$expired = signed_manifest('1.6.33', 101, 'test-key-a', $package_url_33, $package_size, $package_hash, gmdate('Y-m-d\\TH:i:s\\Z', time() - 60));
signed_reset_transient();
signed_queue(signed_envelope($expired, $secret_a));
signed_assert(false === signed_has_update($updater), 'expired signed metadata is rejected');
$too_far = signed_manifest('1.6.33', 101, 'test-key-a', $package_url_33, $package_size, $package_hash, gmdate('Y-m-d\\TH:i:s\\Z', time() + 31 * 86400));
signed_reset_transient();
signed_queue(signed_envelope($too_far, $secret_a));
signed_assert(false === signed_has_update($updater), 'metadata beyond maximum lifetime is rejected');

$noncanonical_body = "{ \"manifest\": " . SuperWoo_Update_Manifest::canonical_json($valid_manifest) . ", \"signature\": \"" . $valid_envelope['signature'] . "\" }";
signed_reset_transient();
signed_queue($valid_envelope, 200, $noncanonical_body);
signed_assert(false === signed_has_update($updater), 'noncanonical envelope transport is rejected');

// Equal sequence is allowed only for the exact accepted manifest; lower sequence and downgrade are not.
$replay = signed_manifest('1.6.32', 99, 'test-key-a', $package_url, $package_size, $package_hash);
signed_reset_transient();
signed_queue(signed_envelope($replay, $secret_a));
signed_assert(false === signed_has_update($updater), 'lower release sequence replay is rejected');
$conflict = signed_manifest('1.6.32', 100, 'test-key-a', $package_url, $package_size, str_repeat('a', 64));
signed_reset_transient();
signed_queue(signed_envelope($conflict, $secret_a));
signed_assert(false === signed_has_update($updater), 'same sequence with a different manifest is rejected');
$downgrade = signed_manifest('1.6.31', 101, 'test-key-a', 'https://digtize.com/plugins/superwoo/SuperWoo-1.6.31.zip', $package_size, $package_hash);
signed_reset_transient();
signed_queue(signed_envelope($downgrade, $secret_a));
signed_assert(false === signed_has_update($updater), 'higher sequence cannot authorize a version downgrade');

$write_failure_manifest = signed_manifest('1.6.32', 1, 'test-key-a', $package_url, $package_size, $package_hash);
signed_reset_transient();
$signed_options = [];
$signed_option_write_result = false;
signed_queue(signed_envelope($write_failure_manifest, $secret_a));
signed_assert(false === signed_has_update($updater), 'failure to persist replay floor fails closed');
$signed_option_write_result = true;

// Verify the exact package download path using the signed digest and size.
signed_reset_transient();
signed_queue($valid_envelope);
$signed_package_file = $zip_path;
$signed_http_response = ['response' => ['code' => 200]];
$signed_http_headers = ['content-length' => (string) $package_size];
$download = $updater->verified_package_download(false, $package_url, null, ['plugin' => 'SuperWoo/superwoo.php']);
signed_assert(is_string($download) && is_file($download), 'valid package is streamed and matches signed SHA-256 and size');
$package_request = end($signed_http_calls);
signed_same($package_url, $package_request[0] ?? null, 'only exact signed package URL is requested');
signed_same(0, $package_request[1]['redirection'] ?? null, 'package redirects disabled');
signed_same(true, $package_request[1]['sslverify'] ?? null, 'package TLS verification enabled');
$updater->cleanup_temporary_package();
signed_assert(!file_exists($download), 'verified temporary package is cleaned up');

// The signed version and ZIP plugin header must agree, even if the signed digest is valid.
$wrong_version_dir = $tmp . '/WrongVersion';
mkdir($wrong_version_dir, 0700);
mkdir($wrong_version_dir . '/SuperWoo', 0700);
file_put_contents($wrong_version_dir . '/SuperWoo/superwoo.php', "<?php\n/* Plugin Name: SuperWoo\n * Version: 9.9.9\n */\n");
$wrong_version_zip = $wrong_version_dir . '/SuperWoo-1.6.33.zip';
$wrong_version_archive = new ZipArchive();
signed_assert(true === $wrong_version_archive->open($wrong_version_zip, ZipArchive::CREATE | ZipArchive::EXCL), 'creates ZIP with mismatched embedded plugin version');
$wrong_version_archive->addFile($wrong_version_dir . '/SuperWoo/superwoo.php', 'SuperWoo/superwoo.php');
$wrong_version_archive->close();
$wrong_version_size = filesize($wrong_version_zip);
$wrong_version_hash = hash_file('sha256', $wrong_version_zip);
$wrong_version_manifest = signed_manifest('1.6.33', 101, 'test-key-a', $package_url_33, $wrong_version_size, $wrong_version_hash);
$wrong_version_envelope = signed_envelope($wrong_version_manifest, $secret_a);
signed_reset_transient();
signed_queue($wrong_version_envelope);
$signed_package_file = $wrong_version_zip;
$signed_http_response = ['response' => ['code' => 200]];
$signed_http_headers = ['content-length' => (string) $wrong_version_size];
$wrong_header_zip = $updater->verified_package_download(false, $package_url_33, null, ['plugin' => 'SuperWoo/superwoo.php']);
signed_assert(is_wp_error($wrong_header_zip) && 'superwoo_update_integrity_failed' === $wrong_header_zip->get_error_code(), 'ZIP header version mismatch is rejected');

$bad_package_manifest = signed_manifest('1.6.33', 102, 'test-key-a', $package_url_33, $package_size, str_repeat('0', 64));
$bad_package_envelope = signed_envelope($bad_package_manifest, $secret_a);
signed_reset_transient();
signed_queue($bad_package_envelope);
$signed_package_file = $zip_path;
$signed_http_response = ['response' => ['code' => 200]];
$signed_http_headers = ['content-length' => (string) $package_size];
$modified_zip = $updater->verified_package_download(false, $package_url_33, null, ['plugin' => 'SuperWoo/superwoo.php']);
signed_assert(is_wp_error($modified_zip) && 'superwoo_update_integrity_failed' === $modified_zip->get_error_code(), 'package bytes not matching signed digest are rejected');

signed_reset_transient();
signed_queue($valid_envelope);
$wrong_package_reply = $updater->verified_package_download(false, 'https://evil.example/SuperWoo.zip', null, ['plugin' => 'SuperWoo/superwoo.php']);
signed_assert(is_wp_error($wrong_package_reply), 'substituted download URL is rejected');

signed_reset_transient();
signed_queue($valid_envelope);
$signed_http_response = ['response' => ['code' => 302]];
$signed_http_headers = ['content-length' => (string) $package_size];
$redirected_package = $updater->verified_package_download(false, $package_url, null, ['plugin' => 'SuperWoo/superwoo.php']);
signed_assert(is_wp_error($redirected_package), 'package redirect is rejected');

signed_reset_transient();
$signed_http_response = new WP_Error('fixture_timeout');
$signed_http_body = '';
signed_assert(false === signed_has_update($updater), 'metadata network failure fails closed');

// Key rotation: an overlapping new public key can authorize a newer monotonic release.
$rotated = signed_manifest('1.6.33', 103, 'test-key-b', $package_url_33, $package_size, $package_hash);
signed_reset_transient();
signed_queue(signed_envelope($rotated, $secret_b));
signed_same('1.6.33', signed_has_update($updater)->new_version ?? null, 'overlap key rotation accepts the new trusted key at higher sequence/version');

signed_remove_tree($tmp);
fwrite(STDOUT, "PASS: {$assertions} signed updater integration assertions (ephemeral test keys only)\n");
