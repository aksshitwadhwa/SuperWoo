<?php
/** Local-only static signed-feed fixture. Never use PHP's built-in server in production. */
$root = getenv('SUPERWOO_SIGNED_FIXTURE_ROOT');
$root = is_string($root) ? realpath($root) : false;
if (false === $root || !is_dir($root)) {
    http_response_code(503);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'] ?? '';
if (!in_array($method, ['GET', 'HEAD'], true)) {
    header('Allow: GET, HEAD');
    http_response_code(405);
    exit;
}

$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
$files = [
    '/plugins/superwoo/update-signed.json' => ['update-signed.json', 'application/json; charset=utf-8', 'public, max-age=60, must-revalidate'],
    '/plugins/superwoo/SuperWoo-1.6.32.zip' => ['SuperWoo-1.6.32.zip', 'application/zip', 'public, max-age=31536000, immutable'],
];
if (!is_string($path) || !isset($files[$path])) {
    http_response_code(404);
    exit;
}

[$filename, $content_type, $cache_control] = $files[$path];
$file = realpath($root . DIRECTORY_SEPARATOR . $filename);
if (false === $file || 0 !== strpos($file, $root . DIRECTORY_SEPARATOR) || !is_file($file) || !is_readable($file)) {
    http_response_code(404);
    exit;
}

$size = filesize($file);
if (false === $size) {
    http_response_code(503);
    exit;
}
http_response_code(200);
header('Content-Type: ' . $content_type);
header('Content-Length: ' . $size);
header('Cache-Control: ' . $cache_control);
header('X-Content-Type-Options: nosniff');
if ('HEAD' !== $method) {
    readfile($file);
}
