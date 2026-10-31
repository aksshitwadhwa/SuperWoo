<?php
/** Exercise the release signer with an ephemeral, non-production Ed25519 key. */
if (!extension_loaded('sodium') || !class_exists('ZipArchive')) {
    fwrite(STDERR, "SKIP: signing workflow requires sodium and ZipArchive\n");
    exit(0);
}

define('ABSPATH', __DIR__ . '/');
define('SUPERWOO_RELEASE_TOOL', true);
require_once dirname(__DIR__) . '/includes/class-superwoo-update-manifest.php';

$root = sys_get_temp_dir() . '/superwoo-signing-test-' . bin2hex(random_bytes(6));
mkdir($root, 0700);
$plugin_dir = $root . '/SuperWoo';
mkdir($plugin_dir, 0700);
$plugin_file = $plugin_dir . '/superwoo.php';
file_put_contents($plugin_file, "<?php\n/* Plugin Name: SuperWoo\n * Version: 1.6.32\n */\n");
$package = $root . '/SuperWoo-1.6.32.zip';
$zip = new ZipArchive();
if (true !== $zip->open($package, ZipArchive::CREATE | ZipArchive::EXCL)) {
    fwrite(STDERR, "FAIL: could not create synthetic package\n");
    exit(1);
}
$zip->addFile($plugin_file, 'SuperWoo/superwoo.php');
$zip->close();

$pair = sodium_crypto_sign_keypair();
$secret_key = sodium_crypto_sign_secretkey($pair);
$public_key = sodium_crypto_sign_publickey($pair);
$key_file = $root . '/test-private.key';
file_put_contents($key_file, base64_encode($secret_key));
chmod($key_file, 0600);
$metadata_file = $root . '/signed.json';
$expiry = gmdate('Y-m-d\TH:i:s\Z', time() + 86400);
$signer = dirname(__DIR__) . '/tools/sign-superwoo-release.php';
$command = [
    escapeshellarg(PHP_BINARY), escapeshellarg($signer),
    '--package=' . escapeshellarg($package), '--version=1.6.32',
    '--package-url=' . escapeshellarg('https://digtize.com/plugins/superwoo/SuperWoo-1.6.32.zip'),
    '--sequence=9001', '--key-id=test-only-phase15', '--expires-at=' . escapeshellarg($expiry),
    '--private-key-file=' . escapeshellarg($key_file), '--output=' . escapeshellarg($metadata_file),
];
$output = [];
$status = 0;
exec(implode(' ', $command) . ' 2>&1', $output, $status);
$assertions = 0;
$assert = static function ($condition, $label) use (&$assertions) {
    $assertions++;
    if (!$condition) {
        fwrite(STDERR, "FAIL: {$label}\n");
        exit(1);
    }
};
$assert(0 === $status && is_file($metadata_file), 'signer emits metadata for the valid package and protected test key');
$envelope = json_decode(file_get_contents($metadata_file), true);
$assert(is_array($envelope) && isset($envelope['manifest'], $envelope['signature']), 'emitted envelope has manifest and detached signature');
$manifest = $envelope['manifest'];
$assert(SuperWoo_Update_Manifest::validate_manifest($manifest, time(), 'digtize.com', '/plugins/superwoo/', 52428800), 'emitted manifest matches updater schema and limits');
$assert(hash_equals(hash_file('sha256', $package), $manifest['sha256']), 'manifest SHA-256 matches final ZIP bytes');
$assert(SuperWoo_Update_Manifest::verify_manifest_signature($manifest, $envelope['signature'], base64_encode($public_key)), 'updater verifier accepts signer output');
$tampered = $manifest;
$tampered['version'] = '9.9.9';
$assert(!SuperWoo_Update_Manifest::verify_manifest_signature($tampered, $envelope['signature'], base64_encode($public_key)), 'tampered signed metadata is rejected');
file_put_contents($package, "tamper", FILE_APPEND);
$assert(!hash_equals($manifest['sha256'], hash_file('sha256', $package)), 'modified package no longer matches signed digest');

$bad_key = $root . '/insecure-private.key';
file_put_contents($bad_key, base64_encode($secret_key));
chmod($bad_key, 0644);
$bad_output = $root . '/insecure-output.json';
$bad_command = [
    escapeshellarg(PHP_BINARY), escapeshellarg($signer), '--package=' . escapeshellarg($package),
    '--version=1.6.32', '--package-url=' . escapeshellarg('https://digtize.com/plugins/superwoo/SuperWoo-1.6.32.zip'),
    '--sequence=9002', '--key-id=test-only-phase15', '--expires-at=' . escapeshellarg($expiry),
    '--private-key-file=' . escapeshellarg($bad_key), '--output=' . escapeshellarg($bad_output),
];
$bad_log = [];
$bad_status = 0;
exec(implode(' ', $bad_command) . ' 2>&1', $bad_log, $bad_status);
$assert(0 !== $bad_status && !file_exists($bad_output), 'group/world-readable private key is refused without output');

$wrong_host_output = $root . '/wrong-host.json';
$wrong_host_command = [
    escapeshellarg(PHP_BINARY), escapeshellarg($signer), '--package=' . escapeshellarg($package),
    '--version=1.6.32', '--package-url=' . escapeshellarg('https://evil.example/plugins/superwoo/SuperWoo-1.6.32.zip'),
    '--sequence=9003', '--key-id=test-only-phase15', '--expires-at=' . escapeshellarg($expiry),
    '--private-key-file=' . escapeshellarg($key_file), '--output=' . escapeshellarg($wrong_host_output),
];
$wrong_host_log = [];
$wrong_host_status = 0;
exec(implode(' ', $wrong_host_command) . ' 2>&1', $wrong_host_log, $wrong_host_status);
$assert(0 !== $wrong_host_status && !file_exists($wrong_host_output), 'unapproved package host is refused without signed output');

foreach (glob($root . '/*') as $file) {
    if (is_file($file)) {
        unlink($file);
    } elseif (is_dir($file)) {
        foreach (glob($file . '/*') as $child) { if (is_file($child)) { unlink($child); } }
        rmdir($file);
    }
}
rmdir($root);
fwrite(STDOUT, "PASS: {$assertions} release signing workflow assertions (ephemeral test key only)\n");
