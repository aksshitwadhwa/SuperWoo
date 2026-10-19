# SuperWoo Meta Tracking — Phase 3A-V2 Runtime Verification

**Date:** 2026-10-13  
**Scope:** Verification only on the explicitly authorized disposable local WordPress/WooCommerce environment. No Phase 3B work or production changes were made.

## Result

The Phase 3A foundation booted and its frontend gating, consent default, kill switch, and offline checkout invariants were verified locally. **Phase 3B is not ready:** exercising the real settings save handler showed that saving Meta settings resets three existing Cart Recovery settings. That regression needs a separately authorized code fix and a repeat settings round-trip before continuing.

## Test environment

- Disposable site: `~/superwoo-meta-test`; database: `superwoo_meta_test`.
- WordPress **7.1.3**, WooCommerce **11.2.1**, SuperWoo **1.4.2**; SuperWoo activated successfully.
- Host is Apple Silicon (`arm64`). Native Homebrew PHP **8.5.11** was used. The XAMPP x86_64 PHP/curl binaries were not used.
- One synthetic virtual product was created for `$25.00`; taxes were disabled for the product. The only enabled payment method was Cash on Delivery (COD).
- The checkout page rendered WooCommerce Checkout Block markup in the Twenty Twenty-Five theme. No live payment gateway or Razorpay flow was used.

## Runtime and safety checks

- WordPress, WooCommerce, and SuperWoo booted. Home, product, cart, and checkout requests rendered. Empty-cart checkout redirected to cart as WooCommerce normally does.
- Existing-install defaults were exercised: absent Meta settings resolve to disabled. With tracking off, the observer asset was absent from checkout HTML. With tracking enabled, it was present. With the kill switch enabled, it was absent again.
- The consent resolver returned `unknown` by default and `denied` when filtered to denied; dispatch permission was false for both. Checkout localized `consentState: unknown` and `eventDispatchAllowed: false`. Phase 3A has no event dispatcher, Pixel initializer, CAPI sender, or AddPaymentInfo sender.
- Source audit: the Meta module registers only `wp_enqueue_scripts`; it adds **zero payment/cart/order/gateway mutating hooks**. Existing hooks in other SuperWoo modules were not changed.
- PHP lint passed for all **8** PHP files in the Phase 3A change set. Node tests passed **11/11**; observer and test JavaScript syntax checks passed. `git diff --check` passed before this report update.

## Settings round-trip regression

The actual `SuperWoo_Plugin::save_settings()` handler was invoked in the disposable WordPress runtime with representative existing values. It saved the new Meta flags, but rebuilt the settings array without preserving these existing Cart Recovery values:

| Setting | Before save | After save |
|---|---:|---:|
| `enable_cart_recovery` | `false` | `true` |
| `cart_recovery_abandonment_minutes` | `57` | `30` |
| `cart_recovery_token_days` | `13` | `7` |

This is a real settings-save data loss path, even though the static settings test passes. The original disposable-site SuperWoo settings snapshot was restored after the check; the final Meta and kill-switch values are both off. No repository runtime code was changed to fix the issue in this verification phase.

## Offline cart/order comparison

Using a fresh cart session in each run, the same virtual product and COD-only Store API checkout were tested with Meta tracking off and on. Values below are WooCommerce display currency USD; Store API totals are represented in minor units in the raw response.

| Observation | Tracking off | Tracking on |
|---|---:|---:|
| Product quantity | 1 | 1 |
| Subtotal | $25.00 | $25.00 |
| Discount | $0.00 | $0.00 |
| Shipping | $0.00 (virtual product) | $0.00 (virtual product) |
| Tax | $0.00 | $0.00 |
| Fees | $0.00 | $0.00 |
| Total | $25.00 | $25.00 |
| Displayed/available method | COD only | COD only |
| Local COD order status | Processing | Processing |
| Redirect | Same order-received path | Same order-received path |
| Order-received page | HTTP 200; marker present | HTTP 200; marker present |

These were local synthetic COD orders, not card/UPI payments. They establish only that the tested cart totals, displayed method, Store API order result, and redirect behavior matched in this disposable setup. No authorization, capture, successful/failed payment, or Razorpay behavior was tested. The created test orders and product may remain in the disposable database.

## Required status

```text
PHASE_3A_V2_STATUS=FAIL — real settings round-trip regression found; checkout invariance checks passed

HOST_ARCHITECTURE=arm64
TEST_RUNTIME=DISPOSABLE_LOCAL_WORDPRESS — ~/superwoo-meta-test
TEST_DATABASE=superwoo_meta_test
WORDPRESS=7.1.3
WOOCOMMERCE=11.2.1
SUPERWOO=1.4.2; ACTIVATION=PASS
PRODUCTION_DATA_USED=NO

PHP_LINT=PASS — 8 of 8 PHP files; 0 failures
NODE_TESTS=PASS — 11 passed, 0 failed, 0 skipped
JAVASCRIPT_SYNTAX=PASS
GIT_DIFF_CHECK=PASS after report update

EXISTING_INSTALL_DEFAULT_OFF=PASS — runtime default merge; final test setting restored off
TRACKING_ASSET_DISABLED_WHEN_OFF=PASS — checkout HTML
TRACKING_ASSET_PRESENT_WHEN_ON=PASS — checkout HTML
KILL_SWITCH_RUNTIME=PASS — checkout asset absent when enabled
CONSENT_UNKNOWN_FAILS_CLOSED=PASS — runtime resolver and localized checkout config
CONSENT_DENIED_FAILS_CLOSED=PASS — runtime filter check
META_PIXEL_INITIALIZATION=NONE
META_EVENT_DISPATCH=NONE — Phase 3A hard-coded disabled
ADDPAYMENTINFO_DISPATCH=NONE
CAPI_IMPLEMENTED=NO
META_MODULE_PAYMENT_MUTATING_HOOKS=0 source-level

SETTINGS_ROUND_TRIP=FAIL — Cart Recovery enabled/minutes/token-days reset to true/30/7 from false/57/13
SETTINGS_SNAPSHOT_RESTORED=YES — Meta off, kill switch off
CART_TOTAL_INVARIANCE=PASS in tested synthetic virtual-product scenario
GATEWAY_INVARIANCE=PASS — COD only in both runs
CHECKOUT_ORDER_RESULT_INVARIANCE=PASS — local COD status processing in both runs
REDIRECT_INVARIANCE=PASS — same order-received path; page returned HTTP 200

ORDER_CREATION_TESTED=YES — disposable local Store API COD orders only
PAYMENT_AUTHORIZATION_TESTED=NO
PAYMENT_CAPTURE_TESTED=NO
PAYMENT_FAILURE_TESTED=NO
LIVE_PAYMENT_ATTEMPTED=NO
RAZORPAY_INSTALLED_FOR_TEST=NO
RAZORPAY_CODE_MODIFIED=NO
RAZORPAY_OBSERVER=NONE
RAZORPAY_INTERCEPTION=NONE

PRODUCTION_FILES_CHANGED=NO
PRODUCTION_DATABASE_CHANGED=NO
PRODUCTION_SETTINGS_CHANGED=NO
PAYMENT_SETTINGS_CHANGED=NO on production; disposable test site used a COD-only fixture
PHASE_3B_READY=NO — fix the settings preservation regression and repeat the round-trip before implementation
```

**Stop point:** verification is complete. Phase 3B was not implemented or deployed. The report records a settings regression that must be addressed before the tracking feature advances.
