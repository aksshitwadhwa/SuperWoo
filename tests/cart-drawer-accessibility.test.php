<?php
/** Static guardrails for the cart dialog's browser-level accessibility behavior. */
$script = file_get_contents(__DIR__ . '/../public/js/cart-drawer.js');
$css = file_get_contents(__DIR__ . '/../public/css/cart-drawer.css');
$template = file_get_contents(__DIR__ . '/../templates/cart-drawer-inner.php');
$progress = file_get_contents(__DIR__ . '/../templates/cart-progress.php');
$browser_test = file_get_contents(__DIR__ . '/cart-drawer-browser.test.js');
$checks = [
    'keyboard trap is installed in capture phase' => (bool) preg_match('/document\.addEventListener\(\s*[\'\"]keydown[\'\"],[\s\S]*?\},\s*true\s*\)/', $script),
    'trap handles both Tab directions' => strpos($script, "event.key !== 'Tab'") !== false && strpos($script, 'event.shiftKey') !== false,
    'hidden and aria-hidden controls are excluded' => strpos($script, "!element.closest('[hidden], [aria-hidden=\"true\"]')") !== false,
    'no-focusable dialog retains focus' => strpos($script, 'if (!visibleFocusable.length)') !== false && strpos($script, 'drawerNode.focus()') !== false,
    'reduced-motion preference has a dedicated rule' => strpos($css, '@media (prefers-reduced-motion: reduce)') !== false,
    'drawer transitions are shortened for reduced motion' => strpos($css, 'transition-duration: 0.01ms !important;') !== false,
    'cart quantity field has a product-specific accessible name' => strpos($template, 'Quantity for %s') !== false && strpos($template, 'aria-label=') !== false,
    'dynamic coupon feedback and errors are announced' => strpos($template, 'data-superwoo-coupon-feedback role="status" aria-live="polite"') !== false && strpos($template, 'data-superwoo-cart-error role="alert"') !== false,
    'shipping progress exposes accessible range semantics' => strpos($progress, 'role="progressbar"') !== false && strpos($progress, 'aria-valuemin=') !== false && strpos($progress, 'aria-valuemax=') !== false && strpos($progress, 'aria-valuenow=') !== false,
    'browser regression covers focus wrap, Escape, and restoration' => strpos($browser_test, 'Tab did not wrap') !== false && strpos($browser_test, "key:'Escape'") !== false && strpos($browser_test, 'restore trigger focus') !== false,
];
foreach ($checks as $label => $passed) {
    if (!$passed) {
        fwrite(STDERR, "FAIL: $label\n");
        exit(1);
    }
}
echo 'PASS: ' . count($checks) . " cart dialog accessibility source guardrails\n";
