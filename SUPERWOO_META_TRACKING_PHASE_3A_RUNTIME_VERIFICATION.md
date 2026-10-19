# SuperWoo Meta Tracking — Phase 3A Runtime Verification

**Date:** 2026-10-13  
**Scope:** Local source and JavaScript safety verification of the Phase 3A foundation. No live checkout, order, payment, or production configuration was touched.

## Environment and execution limits

The requested WordPress/WooCommerce runtime could not be booted. No `wp-config.php`, `wp-load.php`, `wp-tests-config.php`, or PHPUnit configuration was found in this plugin checkout or the inspected XAMPP document root. XAMPP's PHP 8.2 executable is an Intel-only Mach-O binary; this Apple Silicon environment cannot execute it (`Bad CPU type in executable`), and Rosetta is unavailable. XAMPP's MySQL directory contains unrelated site databases, so they were not treated as disposable test data.

As a result, PHP lint, WordPress activation, admin/storefront/cart/checkout loads, settings round-trip, and WooCommerce OFF-vs-ON payment-invariance tests were **skipped**, not passed. The source audit and JavaScript tests below are not substitutes for those runtime checks.

## Source hook audit

The Phase 3A Meta module registers only `wp_enqueue_scripts`; it has no payment, cart, order, gateway, pricing, checkout-submit, redirect, or session hooks. The source audit finds:

```text
META_MODULE_PAYMENT_MUTATING_HOOKS=0
```

Existing registrations elsewhere in SuperWoo are separate from this module: `class-currency.php` attaches to `woocommerce_before_calculate_totals` and `woocommerce_cart_calculate_fees`; `class-bundle-offers.php` attaches to `woocommerce_before_calculate_totals`; `class-cart-drawer.php` has a cart-quantity protector on `woocommerce_before_calculate_totals`; and `class-cart-recovery.php` observes `woocommerce_checkout_order_processed`. `class-plugin.php` already observes checkout/order/payment status hooks for logging. None were added or changed by Phase 3A. They mean a true runtime OFF-vs-ON baseline still needs to run with the existing store configuration in a disposable WooCommerce test site.

The observer source has no `preventDefault`, `stopPropagation`, form submission, gateway click/selection, payment input write, cart/session write, external request, `fbq` call, or Razorpay selector/callback. Its checkout observers expose only the checkout type. A thrown/malformed observer callback is caught. Razorpay is not inspected or intercepted; support remains `unsupported`.

## Automated checks

- **Phase 3A Node tests:** 11 passed, 0 failed, 0 skipped (`node --test tests/meta-tracking.test.js`). Coverage includes master-off and kill-switch no-op, denied/unknown consent, read-only Classic/Blocks observers, malformed Pixel runtime, disabled diagnostics by default, observer exception containment, unsupported checkout/Razorpay no-op, settings wiring, and source boundary checks.
- **Node syntax checks:** passed for the observer and its test file.
- **Existing SuperWoo tests:** none are present in the repository.
- **PHP lint:** not run; 0 PHP files checked. Eight added/modified PHP files remain to be linted in a compatible PHP runtime.
- **WordPress/WooCommerce integration tests:** not run; 0 passed, 0 failed. Runtime unavailable.
- **`git diff --check`:** passed.

## Required status

```text
PHASE_3A_RUNTIME_STATUS=PARTIAL — source/Node checks complete; WordPress runtime unavailable

WORDPRESS_RUNTIME=NOT AVAILABLE — no disposable local WordPress test install
WOOCOMMERCE_RUNTIME=NOT AVAILABLE
SUPERWOO_ACTIVATION=NOT TESTED

PHP_LINT=SKIPPED — XAMPP PHP 8.2 is x86_64 and cannot execute here
PHP_FILES_CHECKED=0 of 8
PHP_LINT_FAILURES=NOT DETERMINED
NODE_TESTS=PASS — 11 passed, 0 failed, 0 skipped
WOOCOMMERCE_INTEGRATION_TESTS=SKIPPED — 0 passed, 0 failed; runtime unavailable
GIT_DIFF_CHECK=PASS

META_TRACKING_DEFAULT_OFF=PASS source-level
EXISTING_INSTALL_DEFAULT_OFF=PASS by default merge for absent key; runtime not tested
SETTINGS_REGRESSION=UNVERIFIED runtime; static defaults/save wiring test passed
TRACKING_ASSET_DISABLED_WHEN_OFF=PASS source-level gate and Node static test; runtime not tested

META_PIXEL_INITIALIZATION=NONE
META_EVENT_DISPATCH=NONE
ADDPAYMENTINFO_DISPATCH=NONE
CAPI_IMPLEMENTED=NO

META_MODULE_PAYMENT_MUTATING_HOOKS=0 source-level; runtime registration not tested

CART_TOTAL_INVARIANCE=UNVERIFIED — integration test not run
GATEWAY_INVARIANCE=UNVERIFIED — integration test not run
CHECKOUT_PAYLOAD_INVARIANCE=UNVERIFIED — integration test not run
ORDER_INVARIANCE=UNVERIFIED — integration test not run
REDIRECT_INVARIANCE=UNVERIFIED — integration test not run

FAULT_ISOLATION=PASS for JavaScript observer exceptions/no-interception tests; checkout runtime unverified
CONSENT_UNKNOWN_FAILS_CLOSED=PASS in Node tests; WordPress filter runtime unverified
KILL_SWITCH=PASS source gate and Node no-op test; runtime not tested

RAZORPAY_CODE_MODIFIED=NO
RAZORPAY_OBSERVER=NONE
RAZORPAY_INTERCEPTION=NONE

PRODUCTION_CHANGES=NONE
REAL_PAYMENT_ATTEMPTED=NO

PHASE_3B_READY=NO — PHP lint, WordPress/WooCommerce runtime, settings round-trip, and payment-invariance checks are safety-critical and remain unverified
```

**Stop point:** verification only. Phase 3B was not implemented, and nothing was deployed.
