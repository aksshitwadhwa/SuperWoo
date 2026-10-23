#!/usr/bin/env php
<?php
/** Ed25519 release-manifest signer. Production keys may be used only on the approved offline signing workstation. */
define('SUPERWOO_RELEASE_TOOL', true);
require_once dirname(__DIR__) . '/includes/class-superwoo-update-manifest.php';

function release_tool_fail($message, $code = 1) {
    fwrite(STDERR, "ERROR: {$message}\n");
    exit($code);
}

function release_tool_usage() {
    return "Usage: php tools/sign-superwoo-release.php --package=FILE --version=X.Y.Z --package-url=HTTPS_URL --sequence=N --key-id=ID --expires-at=UTC --private-key-file=FILE --output=FILE [--requires=6.2] [--requires-php=7.4]\n";
}

$options = getopt('', [
    'package:', 'version:', 'package-url:', 'sequence:', 'key-id:', 'expires-at:',
    'private-key-file:', 'output:', 'requires::', 'requires-php::', 'help',
]);
if (isset($options['help'])) {
    fwrite(STDOUT, release_tool_usage());
    exit(0);
}
foreach (['package', 'version', 'package-url', 'sequence', 'key-id', 'expires-at', 'private-key-file', 'output'] as $required) {
    if (!isset($options[$required]) || !is_string($options[$required]) || '' === $options[$required]) {
        release_tool_fail(release_tool_usage());
    }
}
if (!extension_loaded('sodium') || !function_exists('sodium_crypto_sign_detached')) {
    release_tool_fail('PHP sodium is required; refusing to create unsigned metadata.');
}

$package_file = realpath($options['package']);
$key_file = realpath($options['private-key-file']);
$output_file = $options['output'];
if (false === $package_file || !is_file($package_file) || false === $key_file || !is_file($key_file)) {
    release_tool_fail('Package or private key file does not exist.');
}
if (file_exists($output_file)) {
    release_tool_fail('Output already exists; refusing to overwrite signed release metadata.');
}
$permissions = fileperms($key_file);
if (false !== $permissions && (0 !== ($permissions & 0004) || 0 !== ($permissions & 0040))) {
    release_tool_fail('Private key file is readable by group or others; require mode 0600.');
}
$key_contents = file_get_contents($key_file);
if (!is_string($key_contents)) {
    release_tool_fail('Could not read private key file.');
}
$secret_key = trim($key_contents);
if (false === base64_decode($secret_key, true) || 64 !== strlen(base64_decode($secret_key, true))) {
    release_tool_fail('Private key file must contain one base64 Ed25519 64-byte secret key.');
}

$url = $options['package-url'];
if (!SuperWoo_Update_Manifest::is_approved_package_url($url, 'digtize.com', '/plugins/superwoo/')) {
    release_tool_fail('Package URL is outside the exact approved HTTPS host/path.');
}
$filename = basename((string) parse_url($url, PHP_URL_PATH));
if (!hash_equals($filename, basename($package_file)) || !preg_match('/\A[A-Za-z0-9._-]+\.zip\z/', $filename)) {
    release_tool_fail('Package URL basename must exactly match the immutable ZIP filename.');
}
$size = filesize($package_file);
if (false === $size || $size < 1 || $size > 52428800) {
    release_tool_fail('Package size must be between 1 byte and 50 MiB.');
}
$digest = hash_file('sha256', $package_file);
if (!is_string($digest)) {
    release_tool_fail('Could not hash final package bytes.');
}

$zip = new ZipArchive();
if (true !== $zip->open($package_file)) {
    release_tool_fail('Package is not a readable ZIP archive.');
}
$root_ok = true;
$header_version = null;
for ($i = 0; $i < $zip->numFiles; $i++) {
    $entry = $zip->getNameIndex($i);
    if (!is_string($entry) || 0 === strpos($entry, '/') || false !== strpos($entry, '\\') || preg_match('#(?:^|/)\.{1,2}(?:/|$)#', $entry) || 'superwoo' !== strtolower((string) strtok($entry, '/'))) {
        $root_ok = false;
        break;
    }
    if (0 === strcasecmp($entry, 'SuperWoo/superwoo.php')) {
        $contents = $zip->getFromIndex($i);
        if (is_string($contents) && preg_match('/^[ \t*#@]*Version:[ \t]*([^\r\n]+)/mi', $contents, $match)) {
            $header_version = trim($match[1]);
        }
    }
}
$zip->close();
if (!$root_ok || !is_string($header_version) || !hash_equals($options['version'], $header_version)) {
    release_tool_fail('ZIP must contain the SuperWoo main plugin file and its header version must match --version.');
}
$digest_after_zip_inspection = hash_file('sha256', $package_file);
if (!is_string($digest_after_zip_inspection) || !hash_equals($digest, $digest_after_zip_inspection)) {
    release_tool_fail('Package bytes changed during inspection; no metadata was signed.');
}

$sequence = filter_var($options['sequence'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
$manifest = [
    'schema_version' => 1,
    'slug' => 'superwoo',
    'version' => $options['version'],
    'requires' => $options['requires'] ?? '6.2',
    'requires_php' => $options['requires-php'] ?? '7.4',
    'package' => $url,
    'sha256' => $digest,
    'package_size' => (int) $size,
    'sequence' => false === $sequence ? 0 : (int) $sequence,
    'key_id' => $options['key-id'],
    'expires_at' => $options['expires-at'],
];
$now = time();
if (!SuperWoo_Update_Manifest::validate_manifest($manifest, $now, 'digtize.com', '/plugins/superwoo/', 52428800)) {
    release_tool_fail('Manifest fields are invalid, including key ID, versions, sequence, package URL, or expiry (expiry must be within 30 days).');
}
$signature = SuperWoo_Update_Manifest::sign_manifest($manifest, $secret_key);
if (!is_string($signature)) {
    release_tool_fail('Signing failed; refusing to emit unsigned metadata.');
}
$json = SuperWoo_Update_Manifest::canonical_json(['manifest' => $manifest, 'signature' => $signature]);
if (!is_string($json)) {
    release_tool_fail('Canonical JSON encoding failed.');
}
$handle = @fopen($output_file, 'x');
if (false === $handle) {
    release_tool_fail('Could not create output file exclusively.');
}
$written = fwrite($handle, $json);
fclose($handle);
if ($written !== strlen($json)) {
    @unlink($output_file);
    release_tool_fail('Incomplete output write; partial metadata was removed.');
}
@chmod($output_file, 0644);
fwrite(STDOUT, "Signed metadata written: {$output_file}\nVersion: {$manifest['version']}\nKey ID: {$manifest['key_id']}\nPackage SHA-256: {$digest}\nPackage bytes: {$size}\nExpires: {$manifest['expires_at']}\n");
