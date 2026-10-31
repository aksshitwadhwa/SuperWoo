<?php
/** Local HTTP acceptance test for the static signed-update endpoint fixture. */
if (!extension_loaded('sodium') || !class_exists('ZipArchive')) {
    fwrite(STDERR, "SKIP: signed endpoint HTTP test requires sodium and ZipArchive\n");
    exit(0);
}

define('SUPERWOO_RELEASE_TOOL', true);
require_once dirname(__DIR__) . '/includes/class-superwoo-update-manifest.php';

$assertions = 0;
$assert = static function ($condition, $label) use (&$assertions) {
    $assertions++;
    if (!$condition) {
        fwrite(STDERR, "FAIL: {$label}\n");
        exit(1);
    }
};
$root = sys_get_temp_dir() . '/superwoo-endpoint-http-' . bin2hex(random_bytes(6));
mkdir($root, 0700);
$plugin = $root . '/superwoo.php';
file_put_contents($plugin, "<?php\n/* Plugin Name: SuperWoo\n * Version: 1.6.32\n */\n");
$zip_path = $root . '/SuperWoo-1.6.32.zip';
$zip = new ZipArchive();
$assert(true === $zip->open($zip_path, ZipArchive::CREATE | ZipArchive::EXCL), 'creates synthetic plugin package');
$zip->addFile($plugin, 'SuperWoo/superwoo.php');
$zip->close();

$keypair = sodium_crypto_sign_keypair();
$secret_key = sodium_crypto_sign_secretkey($keypair);
$manifest = [
    'schema_version' => 1,
    'slug' => 'superwoo',
    'version' => '1.6.32',
    'requires' => '6.2',
    'requires_php' => '7.4',
    'package' => 'https://digtize.com/plugins/superwoo/SuperWoo-1.6.32.zip',
    'sha256' => hash_file('sha256', $zip_path),
    'package_size' => filesize($zip_path),
    'sequence' => 170001,
    'key_id' => 'phase17-http-fixture-only',
    'expires_at' => gmdate('Y-m-d\\TH:i:s\\Z', time() + 86400),
];
$signature = SuperWoo_Update_Manifest::sign_manifest($manifest, base64_encode($secret_key));
$envelope = SuperWoo_Update_Manifest::canonical_json(['manifest' => $manifest, 'signature' => $signature]);
file_put_contents($root . '/update-signed.json', $envelope);

$port = random_int(20000, 45000);
$router = __DIR__ . '/fixtures/signed-update-server/router.php';
$environment = array_merge($_ENV, ['SUPERWOO_SIGNED_FIXTURE_ROOT' => $root]);
$descriptors = [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']];
$process = proc_open([PHP_BINARY, '-S', '127.0.0.1:' . $port, $router], $descriptors, $pipes, dirname(__DIR__), $environment);
if (!is_resource($process)) {
    fwrite(STDERR, "FAIL: could not start local fixture server\n");
    exit(1);
}
fclose($pipes[0]);
$base = 'http://127.0.0.1:' . $port;
$request = static function ($method, $path) use ($base) {
    $context = stream_context_create(['http' => ['method' => $method, 'ignore_errors' => true, 'follow_location' => 0, 'timeout' => 2]]);
    $body = @file_get_contents($base . $path, false, $context);
    $headers = $http_response_header ?? [];
    $status = 0;
    foreach ($headers as $header) {
        if (preg_match('#^HTTP/\\S+\\s+(\\d{3})#', $header, $match)) {
            $status = (int) $match[1];
        }
    }
    return [$status, false === $body ? '' : $body, $headers];
};

$metadata = null;
for ($attempt = 0; $attempt < 30; $attempt++) {
    [$status, $body, $headers] = $request('GET', '/plugins/superwoo/update-signed.json');
    if (200 === $status) {
        $metadata = [$status, $body, $headers];
        break;
    }
    usleep(100000);
}
$assert(is_array($metadata), 'local signed metadata endpoint becomes ready');
[$status, $body, $headers] = $metadata;
$assert(200 === $status && hash_equals($envelope, $body), 'serves the exact signed envelope without transformation');
$decoded = json_decode($body, true);
$assert(is_array($decoded) && SuperWoo_Update_Manifest::verify_manifest_signature($decoded['manifest'], $decoded['signature'], base64_encode(sodium_crypto_sign_publickey($keypair))), 'served envelope verifies with its ephemeral public key');
$assert(false !== strpos(implode("\n", $headers), 'application/json'), 'metadata content type is JSON');
$assert(false !== strpos(implode("\n", $headers), 'max-age=60'), 'metadata has short revalidation cache policy');
$assert(false === stripos(implode("\n", $headers), 'Location:'), 'metadata response does not redirect');

[$status, $package_body, $headers] = $request('GET', '/plugins/superwoo/SuperWoo-1.6.32.zip');
$assert(200 === $status && hash_equals(hash_file('sha256', $zip_path), hash('sha256', $package_body)), 'serves package bytes bound by signed SHA-256');
$assert((string) strlen($package_body) === (string) filesize($zip_path), 'package byte count matches signed manifest');
$assert(false !== strpos(implode("\n", $headers), 'immutable'), 'versioned package is marked immutable');
[$status, $head_body] = $request('HEAD', '/plugins/superwoo/update-signed.json');
$assert(200 === $status && '' === $head_body, 'HEAD returns readiness headers and no body');
[$status] = $request('GET', '/plugins/superwoo/missing.json');
$assert(404 === $status, 'unknown endpoint returns 404 without redirect');
[$status] = $request('POST', '/plugins/superwoo/update-signed.json');
$assert(405 === $status, 'unsupported method is rejected');

proc_terminate($process);
foreach ($pipes as $pipe) {
    if (is_resource($pipe)) { fclose($pipe); }
}
proc_close($process);
foreach (glob($root . '/*') as $file) {
    if (is_file($file)) { unlink($file); }
}
rmdir($root);
fwrite(STDOUT, "PASS: {$assertions} local signed endpoint HTTP assertions (ephemeral key only)\n");
