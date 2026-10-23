<?php
/** Standalone, network-free tests for signed SuperWoo update verification. */

define('ABSPATH', __DIR__ . '/');
define('MINUTE_IN_SECONDS', 60);
define('SUPERWOO_VERSION', '1.5.5');
define('SUPERWOO_FILE', dirname(__DIR__) . '/superwoo.php');

class WP_Error {
    private $code;
    public function __construct($code = '') { $this->code = $code; }
    public function get_error_code() { return $this->code; }
}

$test_site_transients = [];
$test_site_options = [];
$test_http_calls = [];
$test_http_response = null;
$test_http_body = '';
$test_http_headers = [];
$test_package_response_file = null;
$test_hooks = [];

function add_filter($hook, $callback, $priority = 10, $accepted_args = 1) { global $test_hooks; $test_hooks[] = ['filter', $hook]; }
function add_action($hook, $callback, $priority = 10, $accepted_args = 1) { global $test_hooks; $test_hooks[] = ['action', $hook]; }
function plugin_basename($file) { return 'superwoo/superwoo.php'; }
function __( $text, $domain = null) { return $text; }
function is_wp_error($value) { return $value instanceof WP_Error; }
function get_site_transient($key) { global $test_site_transients; return $test_site_transients[$key] ?? false; }
function set_site_transient($key, $value, $expiration = 0) { global $test_site_transients; $test_site_transients[$key] = $value; return true; }
function delete_site_transient($key) { global $test_site_transients; unset($test_site_transients[$key]); return true; }
function get_site_option($key, $default = false) { global $test_site_options; return $test_site_options[$key] ?? $default; }
function update_site_option($key, $value) { global $test_site_options; $test_site_options[$key] = $value; return true; }
function wp_tempnam($filename = '') { return tempnam(sys_get_temp_dir(), 'sw-updater-'); }
function wp_safe_remote_get($url, $args = []) {
    global $test_http_calls, $test_http_response, $test_package_response_file;
    $test_http_calls[] = [$url, $args];
    if (!empty($args['stream']) && !empty($args['filename']) && is_string($test_package_response_file)) {
        copy($test_package_response_file, $args['filename']);
    }
    return $test_http_response;
}
function wp_remote_retrieve_response_code($response) { return is_array($response) ? (int) ($response['response']['code'] ?? 0) : 0; }
function wp_remote_retrieve_body($response) { global $test_http_body; return $test_http_body; }
function wp_remote_retrieve_header($response, $header) { global $test_http_headers; return $test_http_headers[strtolower($header)] ?? ''; }

require_once dirname(__DIR__) . '/includes/class-github-updater.php';

$assertions = 0;
function updater_assert($condition, $message) {
    global $assertions;
    $assertions++;
    if (!$condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
}
function updater_same($expected, $actual, $message) {
    updater_assert($expected === $actual, $message . ' (expected ' . var_export($expected, true) . ', got ' . var_export($actual, true) . ')');
}
function canonical_fixture_json($value) {
    if (is_array($value)) {
        $is_list = [] === $value || array_keys($value) === range(0, count($value) - 1);
        if (!$is_list) { ksort($value, SORT_STRING); }
        foreach ($value as $key => $item) { if (is_array($item)) { $value[$key] = canonical_fixture_value($item); } }
    }
    return json_encode(canonical_fixture_value($value), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | (defined('JSON_UNESCAPED_LINE_TERMINATORS') ? JSON_UNESCAPED_LINE_TERMINATORS : 0));
}
function canonical_fixture_value($value) {
    if (!is_array($value)) { return $value; }
    $is_list = [] === $value || array_keys($value) === range(0, count($value) - 1);
    if (!$is_list) { ksort($value, SORT_STRING); }
    foreach ($value as $key => $item) { $value[$key] = canonical_fixture_value($item); }
    return $value;
}

$seed = str_repeat("\x42", SODIUM_CRYPTO_SIGN_SEEDBYTES);
$keypair = sodium_crypto_sign_seed_keypair($seed);
$secret_key = sodium_crypto_sign_secretkey($keypair);
$public_key = sodium_crypto_sign_publickey($keypair);
$public_key_b64 = base64_encode($public_key);

class Fixture_SuperWoo_Updater extends SuperWoo_GitHub_Updater {
    public $fixture_keys = [];
    public $fixture_now;
    protected function trusted_public_keys() { return $this->fixture_keys; }
    protected function current_utc_timestamp() { return $this->fixture_now ?: time(); }
    public function accepts_package_url($url) { return $this->is_approved_package_url($url); }
}
$updater = new Fixture_SuperWoo_Updater();
$updater->fixture_keys = ['fixture-2026' => $public_key_b64];
$updater->hooks();
updater_assert(in_array(['filter', 'upgrader_pre_download'], $test_hooks, true), 'registers WordPress pre-download verification hook');
updater_assert(in_array(['action', 'upgrader_process_complete'], $test_hooks, true), 'registers temporary-file cleanup hook');

$make_manifest = static function ($overrides = []) {
    return array_merge([
        'schema_version' => 1,
        'slug'            => 'superwoo',
        'version'         => '1.5.6',
        'requires'        => '6.2',
        'requires_php'    => '7.4',
        'package'         => 'https://digtize.com/plugins/superwoo/SuperWoo-1.5.6.zip',
        'sha256'          => str_repeat('a', 64),
        'package_size'    => 1234,
        'sequence'        => 1,
        'key_id'          => 'fixture-2026',
        'expires_at'      => gmdate('Y-m-d\\TH:i:s\\Z', time() + 3600),
    ], $overrides);
};
$make_envelope = static function ($manifest, $sign = true) use ($secret_key) {
    $signature = $sign ? base64_encode(sodium_crypto_sign_detached(canonical_fixture_json($manifest), $secret_key)) : base64_encode(str_repeat("\0", SODIUM_CRYPTO_SIGN_BYTES));
    $envelope = ['manifest' => $manifest, 'signature' => $signature];
    return [$envelope, canonical_fixture_json($envelope)];
};
$queue_metadata = static function ($body, $code = 200) {
    global $test_http_response, $test_http_body, $test_http_headers;
    $test_http_response = ['response' => ['code' => $code]];
    $test_http_body = $body;
    $test_http_headers = ['content-length' => (string) strlen($body)];
};
$clear_updater_state = static function () {
    global $test_site_transients, $test_site_options, $test_http_calls;
    $test_site_transients = [];
    $test_site_options = [];
    $test_http_calls = [];
};

// Production updater has no Stage 2 trust anchor yet: no network update or legacy fallback.
$production_updater = new SuperWoo_GitHub_Updater();
$transient = (object) ['response' => ['superwoo/superwoo.php' => (object) ['new_version' => '9.9.9']]];
$cleared = $production_updater->inject_update($transient);
updater_assert(!isset($cleared->response['superwoo/superwoo.php']), 'unconfigured production trust anchors remove stale unsigned offer');
updater_same(0, count($test_http_calls), 'empty production trust anchors do not fetch unsigned legacy metadata');

// Legitimate signed metadata is accepted, sequence-pinned and exposed through core updater fields.
$clear_updater_state();
$manifest = $make_manifest(['sequence' => 4]);
[$envelope, $body] = $make_envelope($manifest);
$queue_metadata($body);
$updated = $updater->inject_update((object) ['response' => []]);
updater_same('1.5.6', $updated->response['superwoo/superwoo.php']->new_version ?? null, 'valid signed release is offered to WordPress');
updater_same($manifest['package'], $updated->response['superwoo/superwoo.php']->package ?? null, 'verified package URL is passed to WordPress');
updater_same(4, $test_site_options[SuperWoo_GitHub_Updater::SEQUENCE_OPTION]['sequence'] ?? null, 'valid release sequence is persisted');
updater_same(0, $test_http_calls[0][1]['redirection'] ?? null, 'metadata redirects are disabled');
updater_same(true, $test_http_calls[0][1]['sslverify'] ?? null, 'metadata TLS certificate verification is explicitly enabled');

// A future key is accepted during a deliberate overlap, then the retired key is rejected.
$rotation_pair = sodium_crypto_sign_seed_keypair(str_repeat("\x17", SODIUM_CRYPTO_SIGN_SEEDBYTES));
$rotation_secret = sodium_crypto_sign_secretkey($rotation_pair);
$rotation_public = base64_encode(sodium_crypto_sign_publickey($rotation_pair));
$updater->fixture_keys['fixture-2027'] = $rotation_public;
$rotation_manifest = $make_manifest(['key_id' => 'fixture-2027', 'sequence' => 5]);
$rotation_signature = base64_encode(sodium_crypto_sign_detached(canonical_fixture_json($rotation_manifest), $rotation_secret));
$rotation_body = canonical_fixture_json(['manifest' => $rotation_manifest, 'signature' => $rotation_signature]);
$clear_updater_state();
$queue_metadata($rotation_body);
updater_same('1.5.6', $updater->inject_update((object) ['response' => []])->response['superwoo/superwoo.php']->new_version ?? null, 'new key is accepted while both rotation anchors are trusted');
unset($updater->fixture_keys['fixture-2026']);
$retired_manifest = $make_manifest(['sequence' => 6]);
[, $retired_body] = $make_envelope($retired_manifest);
$clear_updater_state();
$queue_metadata($retired_body);
updater_assert(empty($updater->inject_update((object) ['response' => []])->response['superwoo/superwoo.php']), 'retired key is rejected after rotation overlap ends');
$updater->fixture_keys['fixture-2026'] = $public_key_b64;

// Tampering, unknown key, malformed signature, schema violations and noncanonical JSON fail closed.
foreach ([
    'missing signature' => static function ($m) use ($make_envelope) { [$e] = $make_envelope($m); unset($e['signature']); return canonical_fixture_json($e); },
    'bad signature' => static function ($m) use ($make_envelope) { [$e] = $make_envelope($m, false); return canonical_fixture_json($e); },
    'tampered manifest' => static function ($m) use ($make_envelope) { [$e, $raw] = $make_envelope($m); $e['manifest']['version'] = '1.5.7'; return canonical_fixture_json($e); },
    'unknown key' => static function ($m) use ($make_envelope) { $m['key_id'] = 'unknown-key'; [$e] = $make_envelope($m); return canonical_fixture_json($e); },
    'invalid schema' => static function ($m) use ($make_envelope) { unset($m['sha256']); [$e] = $make_envelope($m); return canonical_fixture_json($e); },
    'expired metadata' => static function ($m) use ($make_envelope) { $m['expires_at'] = gmdate('Y-m-d\\TH:i:s\\Z', time() - 60); [$e] = $make_envelope($m); return canonical_fixture_json($e); },
    'overlong metadata lifetime' => static function ($m) use ($make_envelope) { $m['expires_at'] = gmdate('Y-m-d\\TH:i:s\\Z', time() + 31 * 86400); [$e] = $make_envelope($m); return canonical_fixture_json($e); },
    'noncanonical whitespace' => static function ($m) use ($make_envelope) { [$e, $raw] = $make_envelope($m); return " " . $raw; },
    'duplicate JSON key' => static function ($m) use ($make_envelope) { [$e, $raw] = $make_envelope($m); return str_replace('{"manifest":', '{"manifest":{},"manifest":', $raw); },
] as $case => $body_factory) {
    $clear_updater_state();
    $invalid_manifest = $make_manifest(['sequence' => 9]);
    $queue_metadata($body_factory($invalid_manifest));
    $result = $updater->inject_update((object) ['response' => []]);
    updater_assert(empty($result->response['superwoo/superwoo.php']), "{$case} metadata is not offered");
    updater_assert(empty($test_site_transients[SuperWoo_GitHub_Updater::CACHE_KEY]), "{$case} metadata is not cached");
}

// Lower sequence, same-sequence replacement and downgrade are rejected even with valid signatures.
$clear_updater_state();
$test_site_options[SuperWoo_GitHub_Updater::SEQUENCE_OPTION] = ['sequence' => 8, 'manifest_hash' => hash('sha256', canonical_fixture_json($make_manifest(['sequence' => 8])))];
[$old_envelope, $old_body] = $make_envelope($make_manifest(['sequence' => 7]));
$queue_metadata($old_body);
updater_assert(empty($updater->inject_update((object) ['response' => []])->response['superwoo/superwoo.php']), 'lower signed sequence is rejected');
$replacement = $make_manifest(['sequence' => 8, 'version' => '1.5.7']);
[, $replacement_body] = $make_envelope($replacement);
$queue_metadata($replacement_body);
updater_assert(empty($updater->inject_update((object) ['response' => []])->response['superwoo/superwoo.php']), 'different manifest cannot reuse an already accepted sequence');
$downgrade = $make_manifest(['sequence' => 9, 'version' => '1.5.4']);
[, $downgrade_body] = $make_envelope($downgrade);
$queue_metadata($downgrade_body);
updater_assert(empty($updater->inject_update((object) ['response' => []])->response['superwoo/superwoo.php']), 'validly signed version downgrade is rejected by WordPress version comparison');

foreach ([
    'http://digtize.com/plugins/superwoo/a.zip',
    'https://evil.example/plugins/superwoo/a.zip',
    'https://cdn.digtize.com/plugins/superwoo/a.zip',
    'https://user@digtize.com/plugins/superwoo/a.zip',
    'https://digtize.com:444/plugins/superwoo/a.zip',
    'https://digtize.com/plugins/superwoo/../a.zip',
    'https://digtize.com/plugins/superwoo/%2e%2e/a.zip',
    'https://digtize.com/plugins/superwoo/a.zip?download=1',
    'https://digtize.com/plugins/superwoo/a.zip#fragment',
    'https://127.0.0.1/plugins/superwoo/a.zip',
    'https://digtize.com/plugins/other/a.zip',
] as $url) {
    updater_assert(!$updater->accepts_package_url($url), 'rejects unapproved package URL: ' . $url);
}
updater_assert($updater->accepts_package_url('https://digtize.com/plugins/superwoo/SuperWoo-1.5.6.zip'), 'accepts exact approved package host and path');

// Metadata endpoint status failures (including redirects) do not create update offers.
foreach ([301, 302, 403, 500] as $code) {
    $clear_updater_state();
    [, $valid_body] = $make_envelope($make_manifest(['sequence' => 10]));
    $queue_metadata($valid_body, $code);
    updater_assert(empty($updater->inject_update((object) ['response' => []])->response['superwoo/superwoo.php']), "metadata HTTP {$code} is rejected");
}

// A valid archive passes only after signed size and digest checks; WP receives a local file.
$clear_updater_state();
$fixture_dir = sys_get_temp_dir() . '/superwoo-updater-fixture-' . uniqid('', true);
mkdir($fixture_dir . '/SuperWoo', 0777, true);
file_put_contents($fixture_dir . '/SuperWoo/superwoo.php', "<?php\n");
$fixture_zip = $fixture_dir . '/package.zip';
$zip = new ZipArchive();
updater_assert(true === $zip->open($fixture_zip, ZipArchive::CREATE), 'creates local ZIP fixture');
$zip->addFile($fixture_dir . '/SuperWoo/superwoo.php', 'SuperWoo/superwoo.php');
$zip->close();
$zip_size = filesize($fixture_zip);
$zip_hash = hash_file('sha256', $fixture_zip);
$package_manifest = $make_manifest(['package_size' => $zip_size, 'sha256' => $zip_hash, 'sequence' => 11]);
[$package_envelope, $package_body] = $make_envelope($package_manifest);
$test_site_transients[SuperWoo_GitHub_Updater::CACHE_KEY] = $package_envelope;
$test_site_options[SuperWoo_GitHub_Updater::SEQUENCE_OPTION] = ['sequence' => 11, 'manifest_hash' => hash('sha256', canonical_fixture_json($package_manifest))];
$test_package_response_file = $fixture_zip;
$test_http_response = ['response' => ['code' => 200]];
$test_http_headers = ['content-length' => (string) $zip_size];
$local_package = $updater->verified_package_download(false, $package_manifest['package'], null, ['plugin' => 'superwoo/superwoo.php']);
updater_assert(is_string($local_package) && is_file($local_package), 'valid signed and hashed ZIP returns a local file to WordPress');
$last_download = end($test_http_calls);
updater_same(0, $last_download[1]['redirection'] ?? null, 'package redirects are disabled');
updater_same(true, $last_download[1]['sslverify'] ?? null, 'package TLS verification is enabled');
updater_same(true, $last_download[1]['stream'] ?? null, 'package is streamed to a bounded temporary file');
$updater->cleanup_temporary_package();
updater_assert(!file_exists($local_package), 'temporary verified package is cleaned up');

// For this plugin, any package mismatch or transfer/integrity problem is WP_Error (never false fallback).
$make_bad_download = static function ($code, $header_length, $source_file, $manifest_overrides = []) use ($updater, $make_manifest, $make_envelope, &$test_site_transients, &$test_site_options, &$test_http_response, &$test_http_headers, &$test_package_response_file) {
    global $test_site_transients, $test_site_options, $test_http_response, $test_http_headers, $test_package_response_file;
    $base = $make_manifest(array_merge(['package_size' => filesize($source_file), 'sha256' => hash_file('sha256', $source_file), 'sequence' => 12], $manifest_overrides));
    [$envelope] = $make_envelope($base);
    $test_site_transients[SuperWoo_GitHub_Updater::CACHE_KEY] = $envelope;
    $test_site_options[SuperWoo_GitHub_Updater::SEQUENCE_OPTION] = ['sequence' => 12, 'manifest_hash' => hash('sha256', canonical_fixture_json($base))];
    $test_package_response_file = $source_file;
    $test_http_response = ['response' => ['code' => $code]];
    $test_http_headers = ['content-length' => (string) $header_length];
    $result = $updater->verified_package_download(false, $base['package'], null, ['plugin' => 'superwoo/superwoo.php']);
    updater_assert(is_wp_error($result), 'SuperWoo package failure returns WP_Error and cannot fall back to unsigned download');
};
$bad_file = $fixture_dir . '/bad.zip';
file_put_contents($bad_file, 'not a zip archive');
$clear_updater_state();
$make_bad_download(200, filesize($bad_file), $bad_file);
$make_bad_download(302, filesize($fixture_zip), $fixture_zip);
$make_bad_download(200, filesize($fixture_zip) + 1, $fixture_zip);
$make_bad_download(200, '', $fixture_zip);
$make_bad_download(200, filesize($fixture_zip), $fixture_zip, ['sha256' => str_repeat('b', 64)]);
$make_bad_download(200, filesize($fixture_zip), $fixture_zip, ['package_size' => filesize($fixture_zip) + 1]);
$tls_manifest = $make_manifest(['package_size' => $zip_size, 'sha256' => $zip_hash, 'sequence' => 13]);
[$tls_envelope] = $make_envelope($tls_manifest);
$test_site_transients[SuperWoo_GitHub_Updater::CACHE_KEY] = $tls_envelope;
$test_site_options[SuperWoo_GitHub_Updater::SEQUENCE_OPTION] = ['sequence' => 13, 'manifest_hash' => hash('sha256', canonical_fixture_json($tls_manifest))];
$test_package_response_file = $fixture_zip;
$test_http_response = new WP_Error('tls-verification-failed');
$test_http_headers = ['content-length' => (string) $zip_size];
$tls_failure = $updater->verified_package_download(false, $tls_manifest['package'], null, ['plugin' => 'superwoo/superwoo.php']);
updater_assert(is_wp_error($tls_failure), 'TLS or transport failure returns WP_Error and fails closed');
$reply = $updater->verified_package_download(false, 'https://digtize.com/plugins/superwoo/unlisted.zip', null, ['plugin' => 'superwoo/superwoo.php']);
updater_assert(is_wp_error($reply), 'unsigned package URL substitution is rejected with WP_Error');
$evil_host_reply = $updater->verified_package_download(false, 'https://evil.example/SuperWoo.zip', null, ['plugin' => 'superwoo/superwoo.php']);
updater_assert(is_wp_error($evil_host_reply), 'unapproved host on a SuperWoo update returns WP_Error instead of falling through to WordPress downloader');
$preempted_reply = $updater->verified_package_download('/tmp/unverified-superwoo.zip', $package_manifest['package'], null, ['plugin' => 'superwoo/superwoo.php']);
updater_assert(is_wp_error($preempted_reply), 'preempted package path for SuperWoo cannot bypass local integrity verification');
$unrelated = $updater->verified_package_download(false, 'https://other.example/plugin.zip', null, ['plugin' => 'other/other.php']);
updater_same(false, $unrelated, 'unrelated plugin package continues through WordPress unchanged');

// Existing cached metadata must still be signature-checked before use.
$clear_updater_state();
$tampered = $package_envelope;
$tampered['manifest']['version'] = '9.9.9';
$test_site_transients[SuperWoo_GitHub_Updater::CACHE_KEY] = $tampered;
$queue_metadata('{"unsigned":true}');
updater_assert(empty($updater->inject_update((object) ['response' => []])->response['superwoo/superwoo.php']), 'tampered transient cache is discarded and unsigned fallback is rejected');

$remove_tree = static function ($path) use (&$remove_tree) {
    if (is_dir($path)) { foreach (array_diff(scandir($path), ['.', '..']) as $child) { $remove_tree($path . DIRECTORY_SEPARATOR . $child); } rmdir($path); }
    elseif (file_exists($path)) { unlink($path); }
};
$remove_tree($fixture_dir);

echo "PASS: {$assertions} updater hardening assertions (network disabled; fixture key only)\n";
