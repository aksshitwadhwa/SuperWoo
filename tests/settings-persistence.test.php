<?php
/**
 * Standalone regression tests for the shared SuperWoo settings persistence rule.
 * Run with: php tests/settings-persistence.test.php
 */

define('ABSPATH', __DIR__ . '/');

$test_options = [];

function __($text) {
    return $text;
}

function get_option($name, $default = false) {
    global $test_options;
    return array_key_exists($name, $test_options) ? $test_options[$name] : $default;
}

function update_option($name, $value) {
    global $test_options;
    $test_options[$name] = $value;
    return true;
}

function wp_parse_args($args, $defaults = []) {
    if (is_object($args)) {
        $args = get_object_vars($args);
    }
    return array_merge($defaults, is_array($args) ? $args : []);
}

function wp_unslash($value) {
    return $value;
}

require_once dirname(__DIR__) . '/includes/functions.php';
require_once dirname(__DIR__) . '/includes/class-plugin.php';

$reflection = new ReflectionClass('SuperWoo_Plugin');
$plugin = $reflection->newInstanceWithoutConstructor();
$persist = $reflection->getMethod('persist_settings_submission');
$assertions = 0;

function assert_same($expected, $actual, $message) {
    global $assertions;
    $assertions++;
    if ($expected !== $actual) {
        fwrite(STDERR, "FAIL: {$message}\nExpected: " . var_export($expected, true) . "\nActual: " . var_export($actual, true) . "\n");
        exit(1);
    }
}

$existing = [
    'enable_cart_recovery' => false,
    'cart_recovery_abandonment_minutes' => 57,
    'cart_recovery_token_days' => 13,
    'enable_cart_drawer' => false,
    'enable_multi_currency' => true,
    'enabled_currency_codes' => ['INR', 'USD'],
    'manual_exchange_rates' => ['USD' => 0.0127],
    'enable_meta_tracking' => false,
    'meta_tracking_kill_switch' => false,
    'meta_primary_pixel_id' => '',
    'legacy_module_setting' => 'keep-existing-value',
];
$test_options['superwoo_settings'] = $existing;

// A/B/F/H: Meta-only updates preserve existing modules and ignore unknown POST keys.
$saved = $persist->invoke($plugin, ['enable_meta_tracking' => true], [
    'enable_meta_tracking' => '1',
    'unexpected_setting' => 'must-not-persist',
]);
assert_same(true, $saved['enable_meta_tracking'], 'Meta Tracking can be enabled');
assert_same(false, $saved['enable_cart_recovery'], 'disabled Cart Recovery remains disabled');
assert_same(57, $saved['cart_recovery_abandonment_minutes'], 'Cart Recovery delay is preserved');
assert_same(13, $saved['cart_recovery_token_days'], 'Cart Recovery retention is preserved');
assert_same(false, $saved['enable_cart_drawer'], 'cart drawer setting is preserved');
assert_same(true, $saved['enable_multi_currency'], 'currency module toggle is preserved');
assert_same(['INR', 'USD'], $saved['enabled_currency_codes'], 'currency list is preserved');
assert_same(['USD' => 0.0127], $saved['manual_exchange_rates'], 'manual currency rates are preserved');
assert_same('keep-existing-value', $saved['legacy_module_setting'], 'unsubmitted persisted keys survive');
assert_same(false, array_key_exists('unexpected_setting', $saved), 'unknown POST keys are rejected');

$sanitize_pixel_id = $reflection->getMethod('sanitize_meta_pixel_id');
assert_same('', superwoo_get_settings()['meta_primary_pixel_id'], 'Primary Pixel ID defaults empty');
assert_same('123456789012345', $sanitize_pixel_id->invoke($plugin, '123456789012345'), 'numeric Pixel ID is accepted');
assert_same('', $sanitize_pixel_id->invoke($plugin, '12<script>'), 'malformed Pixel ID is rejected');
assert_same('', $sanitize_pixel_id->invoke($plugin, ['12345']), 'non-scalar Pixel ID is rejected');

$saved = $persist->invoke($plugin, ['meta_primary_pixel_id' => '123456789012345'], [
    'meta_primary_pixel_id' => '123456789012345',
]);
assert_same('123456789012345', $saved['meta_primary_pixel_id'], 'Primary Pixel ID persists');
$saved = $persist->invoke($plugin, ['enable_logging' => true], ['enable_logging' => '1']);
assert_same('123456789012345', $saved['meta_primary_pixel_id'], 'unrelated save preserves Primary Pixel ID');
$saved = $persist->invoke($plugin, ['meta_primary_pixel_id' => ''], ['meta_primary_pixel_id' => '']);
assert_same('', $saved['meta_primary_pixel_id'], 'Primary Pixel ID can be intentionally cleared');

// C: an explicitly submitted unchecked checkbox can be turned off.
$saved = $persist->invoke($plugin, ['enable_meta_tracking' => false], ['enable_meta_tracking' => '0']);
assert_same(false, $saved['enable_meta_tracking'], 'Meta Tracking can be disabled');

// D: kill switch can be toggled both ways without resetting unrelated settings.
$saved = $persist->invoke($plugin, ['meta_tracking_kill_switch' => true], ['meta_tracking_kill_switch' => '1']);
assert_same(true, $saved['meta_tracking_kill_switch'], 'kill switch can be enabled');
assert_same(57, $saved['cart_recovery_abandonment_minutes'], 'kill switch save preserves Cart Recovery');
$saved = $persist->invoke($plugin, ['meta_tracking_kill_switch' => false], ['meta_tracking_kill_switch' => '0']);
assert_same(false, $saved['meta_tracking_kill_switch'], 'kill switch can be disabled');

// E: unrelated partial settings save preserves Meta settings.
$persist->invoke($plugin, ['enable_meta_tracking' => true], ['enable_meta_tracking' => '1']);
$saved = $persist->invoke($plugin, ['enable_logging' => true], ['enable_logging' => '1']);
assert_same(true, $saved['enable_meta_tracking'], 'unrelated save preserves Meta Tracking');
assert_same(false, $saved['meta_tracking_kill_switch'], 'unrelated save preserves kill switch');

// Current full settings form deliberately treats omitted checkbox values as off.
$test_options['superwoo_settings']['enable_cart_drawer'] = true;
$saved = $persist->invoke($plugin, [
    'enable_cart_drawer' => false,
    'enable_meta_tracking' => false,
], ['superwoo_settings_full_form' => '1']);
assert_same(false, $saved['enable_cart_drawer'], 'unchecked full-form checkbox is saved as off');
assert_same(false, $saved['enable_meta_tracking'], 'unchecked full-form Meta checkbox is saved as off');
assert_same(57, $saved['cart_recovery_abandonment_minutes'], 'full-form save preserves unrepresented Cart Recovery value');

// G: genuinely absent settings still resolve through the documented defaults.
$test_options['superwoo_settings'] = [];
$defaults = superwoo_get_settings();
assert_same(false, $defaults['enable_meta_tracking'], 'clean-install Meta default is off');
assert_same('', $defaults['meta_primary_pixel_id'], 'clean-install Pixel ID default is empty');
assert_same(30, $defaults['cart_recovery_abandonment_minutes'], 'clean-install Cart Recovery delay default is 30');
assert_same(7, $defaults['cart_recovery_token_days'], 'clean-install Cart Recovery retention default is 7');

echo "PASS: {$assertions} settings persistence assertions\n";
