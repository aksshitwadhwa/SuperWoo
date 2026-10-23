<?php
/** Regression checks for public product-review media upload limits. */

define('ABSPATH', __DIR__ . '/');
define('HOUR_IN_SECONDS', 3600);
define('MB_IN_BYTES', 1024 * 1024);

$review_media_upload_calls = 0;
$review_rate_limit_calls = 0;
$review_rate_limit_allowed = false;
$review_attachment_id = 100;

function absint($value) { return abs((int) $value); }
function get_post_type($post_id) { return 1 === (int) $post_id ? 'product' : ''; }
function sanitize_text_field($value) { return trim((string) $value); }
function wp_unslash($value) { return $value; }
function wp_verify_nonce($nonce, $action) { return 'valid' === $nonce; }
function superwoo_rate_limit($bucket, $limit, $window) {
    global $review_rate_limit_allowed, $review_rate_limit_calls;
    $review_rate_limit_calls++;
    return $review_rate_limit_allowed;
}
function wp_handle_upload($file, $overrides) {
    global $review_media_upload_calls;
    $review_media_upload_calls++;
    return ['file' => $file['tmp_name'], 'type' => 'image/jpeg', 'url' => 'https://example.test/upload.jpg'];
}
function wp_insert_attachment($data, $file, $parent) {
    global $review_attachment_id;
    return $review_attachment_id++;
}
function is_wp_error($value) { return false; }
function wp_update_attachment_metadata($attachment_id, $metadata) { return true; }
function wp_generate_attachment_metadata($attachment_id, $file) { return []; }
function sanitize_file_name($name) { return preg_replace('/[^a-zA-Z0-9._-]/', '', $name); }

require_once dirname(__DIR__) . '/includes/class-product-reviews.php';

function expect_review_media($condition, $message) {
    if (!$condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
}

$reviews = (new ReflectionClass('SuperWoo_Product_Reviews'))->newInstanceWithoutConstructor();
$_POST['superwoo_review_images_nonce'] = 'valid';
$_FILES['superwoo_review_media'] = [
    'name' => 'blocked.jpg',
    'type' => 'image/jpeg',
    'tmp_name' => '/tmp/not-uploaded.jpg',
    'error' => 0,
    'size' => 100,
];
$reviews->save_review_images(10, 0, ['comment_post_ID' => 1]);
expect_review_media(0 === $review_media_upload_calls, 'rate-limited media submission must not store files');
expect_review_media(1 === $review_rate_limit_calls, 'media submission must consult the rate limit');
unset($_FILES['superwoo_review_media']);

$first_file = tempnam(sys_get_temp_dir(), 'sw-review-');
$second_file = tempnam(sys_get_temp_dir(), 'sw-review-');
file_put_contents($first_file, str_repeat('a', 100));
file_put_contents($second_file, str_repeat('b', 100));
$remaining_bytes = 150;
$files = [
    ['name' => 'first.jpg', 'tmp_name' => $first_file, 'size' => 1, 'error' => 0],
    ['name' => 'second.jpg', 'tmp_name' => $second_file, 'size' => 1, 'error' => 0],
];
$upload = new ReflectionMethod('SuperWoo_Product_Reviews', 'upload_review_attachments');
$remaining_reference = &$remaining_bytes;
$upload->invokeArgs($reviews, [&$files, 1, 4, 1000, &$remaining_reference, ['jpg' => 'image/jpeg']]);
expect_review_media(1 === $review_media_upload_calls, 'combined upload budget must stop files that exceed the remaining bytes');
expect_review_media(50 === $remaining_bytes, 'budget must decrease by verified temporary-file size');

$oversized_actual_file = tempnam(sys_get_temp_dir(), 'sw-review-');
file_put_contents($oversized_actual_file, str_repeat('c', 100));
$remaining_bytes = 99;
$files = [['name' => 'oversized.jpg', 'tmp_name' => $oversized_actual_file, 'size' => 1, 'error' => 0]];
$upload->invokeArgs($reviews, [&$files, 1, 4, 1000, &$remaining_reference, ['jpg' => 'image/jpeg']]);
expect_review_media(1 === $review_media_upload_calls, 'request-declared size must not bypass the actual-file size cap');

$source = file_get_contents(dirname(__DIR__) . '/includes/class-product-reviews.php');
expect_review_media(false !== strpos($source, "superwoo_rate_limit('review_media_upload', 5, HOUR_IN_SECONDS)"), 'review media must have a per-client hourly submission limit');
expect_review_media(false !== strpos($source, '$remaining_bytes = 20 * MB_IN_BYTES;'), 'a review submission must start with the 20 MB aggregate budget');

unlink($first_file);
unlink($second_file);
unlink($oversized_actual_file);

echo "PASS: review media hourly, aggregate-size, and actual-file-size limits\n";
