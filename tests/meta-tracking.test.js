'use strict';

const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const test = require('node:test');
const tracking = require('../public/js/meta-checkout-observer.js');

const root = path.resolve(__dirname, '..');
const commercePayload = { currency: 'INR', value: 565, content_ids: ['81', '82'], content_type: 'product', num_items: 2 };

function fakeDocument(type = 'classic') {
    const listeners = {};
    return {
        body: { classList: { contains: (name) => type === 'classic' && name === 'woocommerce-checkout' } },
        addEventListener: (name, callback) => { listeners[name] = callback; },
        removeEventListener: (name) => { delete listeners[name]; },
        querySelector: (selector) => type === 'blocks' && selector.includes('wp-block-woocommerce-checkout') ? { className: 'wc-block-checkout' } : null,
        listeners
    };
}

function makeWindow(overrides = {}, type = 'classic') {
    const calls = [];
    const win = {
        document: fakeDocument(type),
        SuperWooMetaTrackingConfig: Object.assign({ enabled: true, killSwitch: false, consentState: 'granted', diagnostics: false, primaryPixelId: '123456789012345', commercePayload }, overrides),
        fbq: (...args) => calls.push(args),
        console: { info() {} },
        calls
    };
    return win;
}

function selectionEvent(method, isTrusted = true) {
    return {
        isTrusted,
        target: { value: method, checked: true, matches: (selector) => selector === 'input[name="payment_method"]' }
    };
}

function select(win, method, isTrusted = true) {
    win.document.listeners.change(selectionEvent(method, isTrusted));
}

test('tracking disabled does not initialize or dispatch', () => {
    const win = makeWindow({ enabled: false });
    assert.equal(tracking.initialize(win), null);
    assert.deepEqual(win.calls, []);
});

test('WordPress-localized string flags still enable the observer correctly', () => {
    const win = makeWindow({ enabled: '1', killSwitch: '', diagnostics: '1' });
    const result = tracking.initialize(win);
    assert.equal(result.diagnostics.checkoutAdapter, 'classic');
    select(win, 'cod');
    assert.equal(win.calls.length, 1);
    assert.equal(win.SuperWooMetaTrackingDiagnostics.status, 'dispatch_succeeded');
});

test('kill switch blocks tracking', () => {
    const win = makeWindow({ killSwitch: true });
    const result = tracking.initialize(win);
    assert.equal(result.diagnostics.status, 'blocked_kill_switch');
    assert.deepEqual(win.calls, []);
});

test('unknown consent blocks and does not attach an observer', () => {
    const win = makeWindow({ consentState: 'unknown' });
    const result = tracking.initialize(win);
    assert.equal(result.diagnostics.status, 'blocked_consent');
    assert.deepEqual(win.document.listeners, {});
    assert.deepEqual(win.calls, []);
});

test('denied consent blocks dispatch', () => {
    const win = makeWindow({ consentState: 'denied' });
    assert.equal(tracking.initialize(win).diagnostics.status, 'blocked_consent');
    assert.deepEqual(win.calls, []);
});

test('granted consent plus valid prerequisites enables supported Classic observer', () => {
    const win = makeWindow();
    const result = tracking.initialize(win);
    assert.equal(result.diagnostics.checkoutAdapter, 'classic');
    assert.equal(result.diagnostics.eventDispatchAllowed, true);
    assert.deepEqual(win.calls, []);
});

test('missing Pixel ID blocks tracking', () => {
    const win = makeWindow({ primaryPixelId: '' });
    assert.equal(tracking.initialize(win).diagnostics.status, 'blocked_pixel_missing');
    assert.deepEqual(win.calls, []);
});

test('malformed Pixel ID blocks tracking', () => {
    for (const primaryPixelId of ['abc123', '123 45', '123;fbq()', 123]) {
        const win = makeWindow({ primaryPixelId });
        assert.equal(tracking.initialize(win).diagnostics.status, 'blocked_pixel_missing');
        assert.deepEqual(win.calls, []);
    }
});

test('missing and malformed fbq are safely handled on a genuine selection', () => {
    for (const fbq of [undefined, {}, 'not-a-function']) {
        const win = makeWindow();
        win.fbq = fbq;
        const result = tracking.initialize(win);
        select(win, 'cod');
        assert.equal(result.diagnostics.status, 'blocked_fbq_missing');
        assert.deepEqual(win.calls, []);
    }
});

test('throwing fbq is contained and never interrupts event handling', () => {
    const win = makeWindow({ diagnostics: true });
    win.fbq = () => { throw new Error('stub failure'); };
    const result = tracking.initialize(win);
    assert.doesNotThrow(() => select(win, 'cod'));
    assert.equal(win.SuperWooMetaTrackingDiagnostics.status, 'dispatch_failed');
    assert.equal(result.diagnostics.status, 'dispatch_failed');
});

test('genuine Classic payment-method change dispatches once to configured Pixel', () => {
    const win = makeWindow();
    tracking.initialize(win);
    select(win, 'cod');
    assert.equal(win.calls.length, 1);
    assert.equal(win.calls[0][0], 'trackSingle');
    assert.equal(win.calls[0][1], '123456789012345');
    assert.equal(win.calls[0][2], 'AddPaymentInfo');
});

test('default gateway on initial page load does not dispatch', () => {
    const win = makeWindow();
    tracking.initialize(win);
    assert.deepEqual(win.calls, []);
});

test('scripted change events are not treated as genuine customer selection', () => {
    const win = makeWindow();
    tracking.initialize(win);
    select(win, 'cod', false);
    assert.deepEqual(win.calls, []);
});

test('observer rebind and duplicate same-state callback are bounded and suppressed', () => {
    const win = makeWindow();
    const result = tracking.initialize(win);
    result.observer.start(() => { throw new Error('observer callback must remain internal'); });
    select(win, 'cod');
    select(win, 'cod');
    assert.equal(win.calls.length, 1);
});

test('genuine gateway change and switch-back each dispatch as new interactions', () => {
    const win = makeWindow();
    tracking.initialize(win);
    select(win, 'cod');
    select(win, 'bacs');
    select(win, 'cod');
    assert.equal(win.calls.length, 3);
});

test('generic Pixel broadcast is never used', () => {
    const win = makeWindow();
    tracking.initialize(win);
    select(win, 'cod');
    assert.ok(win.calls.every((call) => call[0] === 'trackSingle'));
    assert.ok(!win.calls.some((call) => call[0] === 'track'));
});

test('event payload contains only normalized commerce fields', () => {
    const win = makeWindow({ commercePayload: Object.assign({}, commercePayload, { email: 'customer@example.test', phone: '9999999999', order_key: 'secret' }) });
    tracking.initialize(win);
    select(win, 'cod');
    assert.deepEqual(win.calls[0][3], commercePayload);
    for (const field of ['email', 'phone', 'order_key', 'name', 'address']) {
        assert.equal(Object.hasOwn(win.calls[0][3], field), false);
    }
});

test('payload uses the current displayed WooCommerce total with localized separators', () => {
    const win = makeWindow({ diagnostics: true, priceFormat: { decimalSeparator: ',', thousandSeparator: '.' } });
    const originalQuery = win.document.querySelector;
    win.document.querySelector = (selector) => selector.startsWith('.order-total') ? { textContent: '₹ 1.234,50' } : originalQuery(selector);
    tracking.initialize(win);
    select(win, 'cod');
    assert.equal(win.calls[0][3].value, 1234.5);
});

test('unparseable displayed checkout total fails closed rather than sending stale value', () => {
    const win = makeWindow({ commercePayload, priceFormat: { decimalSeparator: '.', thousandSeparator: ',' } });
    const originalQuery = win.document.querySelector;
    win.document.querySelector = (selector) => selector.startsWith('.order-total') ? { textContent: 'total unavailable' } : originalQuery(selector);
    const result = tracking.initialize(win);
    select(win, 'cod');
    assert.equal(result.diagnostics.status, 'blocked_payload_invalid');
    assert.deepEqual(win.calls, []);
});

test('payload normalization rejects incomplete or malformed cart state', () => {
    assert.equal(tracking.normalizePayload(null), null);
    assert.equal(tracking.normalizePayload({ currency: 'INR', value: 'NaN', content_ids: ['1'], num_items: 1 }), null);
    assert.equal(tracking.normalizePayload({ currency: 'INR', value: 5, content_ids: [], num_items: 1 }), null);
    const win = makeWindow({ commercePayload: null });
    const result = tracking.initialize(win);
    select(win, 'cod');
    assert.equal(result.diagnostics.status, 'blocked_payload_invalid');
    assert.deepEqual(win.calls, []);
});

test('Blocks checkout is explicitly unsupported because selection intent is not established', () => {
    const win = makeWindow({}, 'blocks');
    const result = tracking.initialize(win);
    assert.equal(result.diagnostics.status, 'blocked_unsupported_checkout');
    assert.equal(result.diagnostics.checkoutAdapter, 'unsupported');
    assert.deepEqual(win.calls, []);
});

test('unsupported checkout and Razorpay Magic Checkout remain untouched', () => {
    const win = makeWindow({}, 'unknown');
    win.document.querySelector = () => null;
    const result = tracking.initialize(win);
    assert.equal(result.diagnostics.status, 'blocked_unsupported_checkout');
    assert.equal(result.diagnostics.razorpayStatus, 'unsupported');
    assert.deepEqual(win.calls, []);
});

test('diagnostics are disabled by default and expose safe status only when enabled', () => {
    const quiet = makeWindow();
    tracking.initialize(quiet);
    assert.equal(quiet.SuperWooMetaTrackingDiagnostics, undefined);
    const diagnostic = makeWindow({ diagnostics: true });
    tracking.initialize(diagnostic);
    select(diagnostic, 'cod');
    assert.equal(diagnostic.SuperWooMetaTrackingDiagnostics.status, 'dispatch_succeeded');
    assert.equal(Object.hasOwn(diagnostic.SuperWooMetaTrackingDiagnostics, 'payload'), false);
    assert.equal(Object.hasOwn(diagnostic.SuperWooMetaTrackingDiagnostics, 'paymentMethod'), false);
});

test('deduplication state is a single bounded in-memory event key', () => {
    const source = fs.readFileSync(path.join(root, 'public/js/meta-checkout-observer.js'), 'utf8');
    assert.match(source, /var lastEventKey = ''/);
    assert.match(source, /var lastEventTime = 0/);
    assert.doesNotMatch(source, /localStorage|sessionStorage|indexedDB|document\.cookie/);
});

test('source has no checkout interception, payment mutation, or server-side tracking', () => {
    const source = fs.readFileSync(path.join(root, 'public/js/meta-checkout-observer.js'), 'utf8');
    assert.doesNotMatch(source, /preventDefault|stopPropagation|\.submit\s*\(|\.click\s*\(/i);
    assert.doesNotMatch(source, /target\.(?:value|checked)\s*(?<![=!<>])=(?!=)/i);
    assert.doesNotMatch(source, /fetch\s*\(|XMLHttpRequest|sendBeacon|process_payment|woocommerce_session/i);
    assert.doesNotMatch(source, /fbq\(['"]init|fbq\(['"]track['"]/i);
});

test('Meta module adds only the frontend enqueue hook and WooCommerce read-only payload', () => {
    const php = fs.readFileSync(path.join(root, 'includes/class-meta-tracking.php'), 'utf8');
    assert.match(php, /add_action\('wp_enqueue_scripts'/);
    assert.doesNotMatch(php, /woocommerce_(?:checkout|payment|order|cart|available_payment_gateways)/i);
    assert.doesNotMatch(php, /calculate_totals|WC\(\)->session|update_meta_data|process_payment/);
});

test('Pixel ID defaults empty, is sanitized, persisted, and exposed by the settings UI', () => {
    const defaults = fs.readFileSync(path.join(root, 'includes/functions.php'), 'utf8');
    const saver = fs.readFileSync(path.join(root, 'includes/class-plugin.php'), 'utf8');
    const view = fs.readFileSync(path.join(root, 'admin/views/settings-page.php'), 'utf8');
    assert.match(defaults, /'meta_primary_pixel_id'\s*=>\s*''/);
    assert.ok(saver.includes("'meta_primary_pixel_id' => $this->sanitize_meta_pixel_id"));
    assert.ok(saver.includes("preg_match('/^[0-9]{1,32}$/D'"));
    assert.match(view, /name="meta_primary_pixel_id"/);
    assert.match(view, /maxlength="32"/);
});

test('settings retain tracking default-off safeguards and V3 shared-save merge', () => {
    const defaults = fs.readFileSync(path.join(root, 'includes/functions.php'), 'utf8');
    const saver = fs.readFileSync(path.join(root, 'includes/class-plugin.php'), 'utf8');
    assert.match(defaults, /'enable_meta_tracking'\s*=>\s*false/);
    assert.match(defaults, /'meta_tracking_kill_switch'\s*=>\s*false/);
    assert.ok(saver.includes('array_merge($existing_settings, $sanitized_settings)'));
    assert.match(saver, /unknown POST keys are never copied/);
});
