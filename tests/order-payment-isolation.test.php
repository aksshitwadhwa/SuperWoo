<?php

/**
 * Static regression guard: SuperWoo must not subscribe to WooCommerce payment
 * or order-status lifecycle hooks, or implement gateway/payment operations.
 */
$root = dirname(__DIR__);
$runtime_files = [];
$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root . '/includes'));
foreach ($iterator as $file) {
    if ($file->isFile() && 'php' === $file->getExtension()) {
        $runtime_files[] = $file->getPathname();
    }
}

$checks = 0;
$failures = [];
$assert = static function ($condition, $message) use (&$checks, &$failures) {
    $checks++;
    if (!$condition) {
        $failures[] = $message;
    }
};

foreach ($runtime_files as $file) {
    $source = file_get_contents($file);
    $relative = str_replace($root . '/', '', $file);

    $assert(
        !preg_match('/add_(?:action|filter)\s*\(\s*[\'\"]woocommerce_(?:order_status(?:_[a-z_]+)?|payment_complete|payment_failed|payment_processed)[\'\"]/', $source),
        $relative . ' registers an order-status/payment lifecycle hook'
    );
    $assert(
        !preg_match('/\b(?:process_payment|process_refund|payment_complete|set_paid|wc_create_order)\s*\(/', $source),
        $relative . ' contains an order/payment mutation or gateway operation'
    );
}

$plugin_source = file_get_contents($root . '/includes/class-plugin.php');
$assert(
    !preg_match('/woocommerce_(?:after_checkout_validation|checkout_order_processed|order_status_changed|payment_complete|order_status_failed)/', $plugin_source),
    'The composition root registers checkout/order/payment diagnostic listeners'
);

$webhook_source = file_get_contents($root . '/includes/class-razorpay-abandoned-webhook.php');
$assert(
    !preg_match('/\b(?:wc_get_order|wc_create_order|update_status|set_status|payment_complete|set_paid|process_payment)\s*\(/', $webhook_source),
    'The Razorpay abandoned-cart receiver touches WooCommerce orders or payments'
);

if ($failures) {
    fwrite(STDERR, implode("\n", $failures) . "\n");
    fwrite(STDERR, 'FAIL: ' . count($failures) . ' failures across ' . $checks . " assertions\n");
    exit(1);
}

echo 'PASS: ' . $checks . " order/payment isolation assertions\n";
