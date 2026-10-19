# SuperWoo Meta Tracking — Phase 3A-V3 Verification

**Date:** 2026-10-13
**Scope:** Fix the V2 shared-settings persistence regression and re-run the authorized safety checks on the disposable local site. No Phase 3B implementation or deployment was performed.

## Result

The settings regression is fixed. The real WordPress settings handler now merges sanitized, recognized settings into the existing shared option. Meta toggles can still be turned off, and the full settings form explicitly marks its scope so omitted checkboxes mean unchecked. Partial submissions preserve checkbox values they did not submit. The settings round-trip and synthetic COD off/on invariance checks now pass.

## V2 root cause

`superwoo_get_settings()` defines defaults and merges them at read time. The settings view in `admin/views/settings-page.php` renders one multi-tab form. `SuperWoo_Plugin::save_settings()` in `includes/class-plugin.php` sanitizes known form values into a new array, then previously called `update_option('superwoo_settings', $settings)` with that array alone.

Cart Recovery keys (`enable_cart_recovery`, `cart_recovery_abandonment_minutes`, and `cart_recovery_token_days`) are defined in `includes/functions.php` but are not controls in this settings form and were not added to the newly constructed array. Replacing the option discarded their persisted values. The read-time defaults then made the lost values appear as `true / 30 / 7`, matching the V2 observation after a save.

## Persistence change

Before, the handler replaced the shared option with a freshly reconstructed array. Now `persist_settings_submission()` merges only the handler’s allowlisted, sanitized settings into the existing persisted array. It never copies arbitrary POST fields. Missing non-checkbox fields are omitted from the update, so their existing values survive. The current all-tabs form carries `superwoo_settings_full_form=1`; in that explicitly scoped form, absent checkboxes are saved as `false`, and an empty page-selection checkbox list clears the selection. For a partial submission without that marker, omitted checkboxes are preserved. Clean-install defaults continue to come from `superwoo_get_settings()`.

## Changed files

- `includes/class-plugin.php` — shared merge/persistence behavior and handler integration.
- `admin/views/settings-page.php` — explicit full-form marker.
- `tests/settings-persistence.test.php` — standalone regression suite with 22 assertions.
- `SUPERWOO_META_TRACKING_PHASE_3A_V3_REPORT.md` — this verification report.

The existing Phase 3A source and reports were already present in the working tree; this V3 task did not change the Meta observer, event behavior, payment logic, or other modules. No unrelated files were changed for the V3 fix.

## Settings regression tests and runtime round-trip

The standalone PHP tests pass **22 assertions** covering Cart Recovery preservation (`false / 57 / 13`), Meta enable/disable, kill-switch on/off, unrelated partial saves, currency/cart settings preservation, explicit full-form checkbox clearing, clean-install defaults, and unknown request-key rejection.

The real handler was also invoked repeatedly in WordPress 7.1.3 / WooCommerce 11.2.1. The test POST included an unknown `superwoo_unknown_v3_test` key each time; it never appeared in the stored option.

| Runtime checkpoint | Cart Recovery | Cart drawer | Multi-currency | Meta | Kill switch | Result |
|---|---|---|---|---|---|---|
| Before | `false / 57 / 13` | `false` | `true`, INR/USD, USD default, manual USD rate `0.0127` | off | off | Baseline captured |
| Meta on | `false / 57 / 13` | `false` | unchanged | on | off | Preserved |
| Kill on | `false / 57 / 13` | `false` | unchanged | on | on | Preserved |
| Kill off | `false / 57 / 13` | `false` | unchanged | on | off | Preserved |
| Meta off | `false / 57 / 13` | `false` | unchanged | off | off | Preserved |
| Cart-tab save | `false / 57 / 13` | `false` | unchanged | off | off | Logging changed; unknown key rejected |
| Meta on, then cart-tab save | `false / 57 / 13` | `false` | unchanged | on | off | Meta preserved |

The runtime `superwoo_settings` option was restored to its exact pre-test snapshot after verification. The restored Meta and kill-switch settings resolve to off; the test-created virtual product and local COD orders may remain in the disposable database.

## Runtime and PHP checks

- Host: Apple Silicon `arm64`; native `/opt/homebrew/bin/php` **8.5.11**.
- Disposable site: `~/superwoo-meta-test`, database `superwoo_meta_test`.
- WordPress **7.1.3**, WooCommerce **11.2.1**, SuperWoo **1.4.2**; SuperWoo active.
- Admin settings page rendered and included the new full-form marker.
- Storefront, synthetic product, and cart returned HTTP 200. Empty-cart checkout returned WooCommerce’s expected HTTP 302 to cart; checkout with a populated synthetic cart returned HTTP 200 in both invariance runs.
- PHP lint: **9 files checked, 0 failures** (the eight Phase 3A PHP files plus the new regression test).
- WP-CLI 2.12 emitted a PHP 8.5 deprecation notice from its bundled React dependency. The real save handler also emits redirect/header warnings under WP-CLI because it redirects and exits; the option was persisted and verified after each invocation. No SuperWoo PHP fatal occurred.

## Meta and observer safety

- Removing the Meta option keys temporarily in a runtime check produced Meta off, kill switch off, and diagnostics off defaults; the original option was restored within that check.
- With tracking off, the checkout observer script was absent. With tracking on and consent unknown, it was present and localized `consentState: unknown` with event dispatch disabled. With the kill switch on, the script was absent.
- Runtime consent checks returned `unknown → dispatch false` by default and `denied → dispatch false` under an explicit denied filter.
- The Meta module registers only `wp_enqueue_scripts`; payment/cart/order/gateway mutating hooks: **0**.
- The 11 existing Node tests passed, including no event interception, observer exception containment, unknown/denied consent handling, kill-switch no-op, and the no-Pixel/no-dispatch/Razorpay boundary checks. The 22 PHP settings assertions also passed.
- Source and tests confirm no `fbq` initialization/call, no event dispatcher, no AddPaymentInfo event, and no CAPI implementation. No checkout UI input or submission is intercepted by the observer.

## Payment invariance

The same fresh synthetic cart and identical Store API checkout payload were used for Meta off and Meta on with consent unknown. COD was the only available method in both runs. Product price was `$25.00`, quantity `1`, subtotal `$25.00`, discount `$0.00`, shipping `$0.00` (virtual product), tax `$0.00`, fees `$0.00`, and total `$25.00` in both runs. The selected method was COD in each request. Both local COD orders had subtotal `$25.00`, shipping `$0.00`, tax/fees `$0.00`, total `$25.00`, and status `processing`. Both responses returned the same order-received path and query-key shape; both thank-you requests returned HTTP 200 with the order-received marker.

These were synthetic local COD orders only. COD did not authorize or capture a payment. No external payment processor, Razorpay integration, card/UPI payment, or production checkout was used.

## Scope and limitations

The fix affects only settings persistence. The final diff introduces no gateway selection, `process_payment()`, authorization/capture/failure, cart calculation, tax, shipping, fee, order-status, checkout redirect, Razorpay, or Meta dispatch changes. The checkout observer’s non-blocking and fault-isolation behavior was verified through source review and Node tests; no real browser payment-selection flow was exercised. The tested runtime is one modern local WordPress/WooCommerce combination, not a compatibility matrix across all supported versions.

## Required status

```text
PHASE_3A_V3_STATUS=PASS — V2 settings regression fixed and required local re-verification passed

SETTINGS_REGRESSION_ROOT_CAUSE=save_settings rebuilt and replaced the shared option, omitting Cart Recovery keys; read-time defaults then masked the lost persisted values
SETTINGS_FIX_IMPLEMENTED=YES — allowlisted sanitized merge; full-form marker handles intentionally unchecked controls

SETTINGS_ROUND_TRIP=PASS — actual WordPress save handler
CART_RECOVERY_SETTINGS_PRESERVED=PASS — false / 57 / 13 through every save
META_SETTINGS_PRESERVED_ON_UNRELATED_SAVE=PASS — verified with Meta on and off
UNKNOWN_SETTINGS_REJECTED=PASS — unknown POST fixture absent from stored option

PHP_LINT=PASS
PHP_FILES_CHECKED=9
PHP_LINT_FAILURES=0

WORDPRESS_RUNTIME=PASS — 7.1.3
WOOCOMMERCE_RUNTIME=PASS — 11.2.1
SUPERWOO_RUNTIME=PASS — 1.4.2 active; settings page and storefront/cart/checkout exercised

META_TRACKING_DEFAULT_OFF=PASS
EXISTING_INSTALL_DEFAULT_OFF=PASS — absent options resolve off
TRACKING_ASSET_DISABLED_WHEN_OFF=PASS
KILL_SWITCH_RUNTIME=PASS

CONSENT_UNKNOWN_FAILS_CLOSED=PASS
CONSENT_DENIED_FAILS_CLOSED=PASS

META_PIXEL_INITIALIZATION=NONE
META_EVENT_DISPATCH=NONE
ADDPAYMENTINFO_DISPATCH=NONE
CAPI_IMPLEMENTED=NO

META_MODULE_PAYMENT_MUTATING_HOOKS=0

CART_TOTAL_INVARIANCE=PASS
GATEWAY_INVARIANCE=PASS — COD only
CHECKOUT_PAYLOAD_INVARIANCE=PASS — identical synthetic COD Store API payload
ORDER_INVARIANCE=PASS — same order totals and local COD status
REDIRECT_INVARIANCE=PASS — same order-received path and HTTP 200 destination

FRONTEND_OBSERVER_NON_BLOCKING=PASS — source/Node checks; no live method-selection interaction performed
FAULT_ISOLATION=PASS — observer exception containment Node tests

RAZORPAY_CODE_MODIFIED=NO
RAZORPAY_OBSERVER=NONE
RAZORPAY_INTERCEPTION=NONE

REAL_PAYMENT_GATEWAY_USED=NO — synthetic local COD only; no external processor
REAL_PAYMENT_ATTEMPTED=NO

PRODUCTION_CHANGES=NONE
PRODUCTION_DATABASE_TOUCHED=NO
MODU_TOUCHED=NO

NODE_TESTS=PASS — 11 passed, 0 failed, 0 skipped
SETTINGS_REGRESSION_TESTS=PASS — 22 assertions; runtime handler round-trip also passed
GIT_DIFF_CHECK=PASS

PHASE_3B_READY=YES — verification gate passed; Phase 3B remains unimplemented and undeployed
```

**Stop point:** Phase 3A-V3 verification is complete. Phase 3B, AddPaymentInfo, and deployment were not performed.
