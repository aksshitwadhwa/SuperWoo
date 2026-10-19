# SuperWoo Meta Tracking — Phase 3B Report

**Date:** 2026-10-13  
**Scope:** Browser-only `AddPaymentInfo` implementation and local safety verification. No production deployment, live Meta event, Razorpay flow, or external payment was used.

## Result

Phase 3B is implemented for standard WooCommerce Classic checkout. Dispatch is opt-in and requires the kill switch to be off, the existing consent filter to return `granted`, a numeric Primary Pixel ID, an existing `fbq` function, a valid WooCommerce cart payload, and a genuine trusted Classic payment-radio change. The event targets only that configured Pixel with `trackSingle`. Blocks and Razorpay Magic Checkout remain unsupported.

The local COD invariance runs passed for Meta off and Meta on with consent unknown. Automated unit/settings checks passed. A genuine browser interaction was **not verified**: the disposable site uses WooCommerce Checkout Blocks, and no browser automation driver was available in this session. No attempt was made to turn that Blocks checkout into Classic or to simulate a real browser click. For that reason the next phase gate remains conservative.

## Implementation

The pipeline is a delegated Classic checkout observer → JavaScript payload normalizer → consent/configuration gates → existing `fbq` availability check → bounded in-memory deduplication → targeted browser dispatch. It runs from the checkout page script and adds no WooCommerce payment, checkout-submit, order, cart-calculation, redirect, or gateway hooks.

The observer accepts only a trusted `change` event on a checked `input[name="payment_method"]`. It does not fire on page load, a default gateway, or synthetic/untrusted changes. It reads the selected method identifier only for an in-memory deduplication key. It does not change or click the input, prevent or stop the event, submit the form, or write cart/session/order state.

The dispatch call is exactly `fbq('trackSingle', configuredPixelId, 'AddPaymentInfo', payload)`. SuperWoo does not initialize Pixel, inject Pixel base code, call generic `fbq('track')`, or fall back to a broadcast. Pixel ID input is trimmed and restricted to a numeric string; an empty or malformed ID disables dispatch. No existence check is made against Meta, so the merchant must enter the intended ID.

The payload allowlist is `currency`, `value`, `content_ids`, `content_type`, and `num_items`. Currency and current cart totals come from WooCommerce’s already-calculated cart. Product variation IDs are used when present. On Classic checkout, if WooCommerce exposes a visible order total, the observer reads that displayed value at selection time using WooCommerce’s localized decimal/thousands separators; if it is present but cannot be parsed safely, dispatch is suppressed. No customer, address, session, order key, gateway credential, card, or UPI data is included.

Deduplication keeps one last event key and timestamp in the current page’s memory, based on payment method and normalized cart state, and suppresses an identical repeated notification for 1.5 seconds. A distinct genuine method change, including switching back, can dispatch again. No persistent deduplication state is stored.

Consent continues to use `superwoo_meta_marketing_consent_state`. Missing, invalid, unknown, or denied consent blocks dispatch. No assumption is made from login state, Pixel presence, WooCommerce cookies, or WhatsApp consent. Diagnostics remain off by default. When enabled, the browser property and optional console output expose only fixed status values and safe runtime state, never the payload or payment method.

Any missing configuration, unsupported checkout, missing/malformed Pixel runtime, bad payload, duplicate event, or thrown `fbq` call stops tracking only. Exceptions are caught and do not interrupt checkout. No CAPI client, token, request, queue, retry worker, or server-side event was added.

## Checkout support boundaries

- **Classic:** Implemented at source and unit level. A genuine trusted payment-radio selection is required. No default-gateway event.
- **Blocks:** Unsupported. The existing public active-method selector can report state changes but does not establish genuine user intent, so this phase does not subscribe to it.
- **Razorpay Magic Checkout:** `BLOCKED`. No Razorpay callback, iframe inspection, private message, global patch, button-click inference, or payment-success inference was added.
- **Express checkout:** Unsupported without a verified public pre-payment selection signal.

## Changed files

- `includes/class-meta-tracking.php` — localizes a sanitized Pixel ID, WooCommerce commerce snapshot, and currency formatting; remains attached only to `wp_enqueue_scripts`.
- `public/js/meta-checkout-observer.js` — trusted Classic selection observer, payload normalization, fail-open dispatch gates, safe diagnostics, bounded dedupe, and targeted `trackSingle` call.
- `includes/functions.php` — empty Pixel ID default.
- `includes/class-plugin.php` — Pixel ID sanitization and persistence through the existing allowlisted shared-settings merge.
- `admin/views/settings-page.php` — Primary Pixel ID field and updated support/safety description.
- `tests/meta-tracking.test.js` — 28 Node tests for gates, target selection, payload, dedupe, errors, unsupported flows, and source boundaries.
- `tests/settings-persistence.test.php` — expanded shared-settings regression suite.
- `SUPERWOO_META_TRACKING_PHASE_3B_REPORT.md` — this report.

## Verification

The Node suite passed **28/28**. It covers disabled tracking, kill switch, localized WordPress string flags, unknown/denied/granted consent, missing and malformed ID/runtime, a throwing stub `fbq`, trusted vs untrusted changes, no initial event, duplicate suppression, real method changes and switch-back, targeted `trackSingle`, no generic broadcast, commerce-only payload, localized displayed-total parsing, malformed payload, unsupported Blocks/Razorpay, safe diagnostics, bounded in-memory state, and source-level payment boundaries.

The standalone PHP settings suite passed **30 assertions**. It covers V3 preservation, empty default, numeric ID acceptance, malformed/non-scalar ID rejection, ID persistence and intentional clearing, unrelated partial saves, existing Cart Recovery/cart/currency values, explicit full-form checkbox behavior, and unknown POST rejection. The real local WordPress save handler also persisted a synthetic numeric ID, cleared it when submitted blank, and preserved Cart Recovery and currency settings. The exact pre-test `superwoo_settings` option was restored afterward.

PHP lint passed for all five changed PHP files. JavaScript syntax checks and `git diff --check` passed.

The disposable runtime was WordPress **7.1.3**, WooCommerce **11.2.1**, SuperWoo **1.4.2**, PHP **8.5.11**. The checkout page is a WooCommerce Checkout Block and the only available gateway is COD. The Meta-on/unknown-consent page rendered the observer script with `consentState: unknown`, an empty dispatch allowance, a synthetic local-only numeric Pixel ID, and the WooCommerce cart snapshot. No `connect.facebook.net` script or real Pixel runtime was found in the rendered local checkout page. The observer correctly does not support the Blocks checkout.

| Local COD invariant | Meta off | Meta on, consent unknown |
|---|---:|---:|
| Product / quantity | Synthetic product / 1 | Synthetic product / 1 |
| Subtotal | $25.00 | $25.00 |
| Discount | $0.00 | $0.00 |
| Shipping | $0.00 on order (virtual item; Store API reports no shipping line) | $0.00 on order (virtual item; Store API reports no shipping line) |
| Tax / fees | $0.00 / $0.00 | $0.00 / $0.00 |
| Total | $25.00 | $25.00 |
| Available / selected gateway | COD / COD | COD / COD |
| Order status and totals | `processing`, $25.00 | `processing`, $25.00 |
| Redirect / thank-you | Same order-received path and query-key shape; HTTP 200 | Same order-received path and query-key shape; HTTP 200 |

Synthetic local guest COD orders **19–23** remain on the disposable site. The failed out-of-stock add attempt created no order. No authorization or capture occurred. The synthetic product’s stock was replenished for the test and restored to its pre-test out-of-stock state. The disposable site’s settings were restored exactly to their pre-test snapshot.

The granted-consent dispatch path was tested with a controlled in-memory `fbq` stub in Node; it did not contact Meta. A separate granted-consent WordPress checkout/order invariance run was not performed. No real Classic browser selection was exercised, so browser interaction and an end-to-end Meta runtime remain unverified.

## Safety and limitations

- Source and tests confirm `META_MODULE_PAYMENT_MUTATING_HOOKS=0`; tracking is not wired into `process_payment`, validation, authorization/capture, failure handling, order creation/status, cart totals, or redirects.
- Meta dispatch is browser-only and requires the merchant’s explicit opt-in, configured ID, and consent filter returning `granted`.
- Existing production Pixel ownership, duplicate `AddPaymentInfo` sources, and the production consent integration remain unverified from the earlier Phase 2 discovery. Keep the feature disabled until the merchant verifies those conditions.
- Classic support has source/unit coverage, but no live browser input event was verified. Current disposable checkout is Blocks and is intentionally unsupported.
- `fbq` availability is determined by the presence of a callable existing runtime. SuperWoo does not check whether the configured ID is registered on that runtime; merchant configuration is responsible for selecting the intended Pixel.
- No production setting, file, database, Pixel, consent setting, Meta Events Manager, GTM, or Razorpay configuration was accessed or changed.

## Required status

```text
PHASE_3B_STATUS=IMPLEMENTED — source/unit tests and local COD invariance passed; real Classic browser interaction unverified

CLASSIC_ADDPAYMENTINFO=IMPLEMENTED; trusted user selection required; browser interaction unverified
BLOCKS_ADDPAYMENTINFO=UNSUPPORTED — public selector does not establish genuine user intent
RAZORPAY_ADDPAYMENTINFO_SUPPORT=BLOCKED

PRIMARY_PIXEL_SETTING=ADDED; empty by default; numeric-format sanitized; persistence and clearing verified
PIXEL_INITIALIZED_BY_SUPERWOO=NO
TARGETED_PIXEL_DISPATCH=YES — fbq('trackSingle', configured Pixel ID, 'AddPaymentInfo', payload)
GENERIC_PIXEL_BROADCAST=NO

CONSENT_UNKNOWN_DISPATCH=NO
CONSENT_DENIED_DISPATCH=NO
CONSENT_GRANTED_REQUIRED=YES

ADDPAYMENTINFO_IMPLEMENTED=YES — Classic source/unit level
ADDPAYMENTINFO_BROWSER_ONLY=YES
CAPI_IMPLEMENTED=NO

INITIAL_DEFAULT_GATEWAY_DISPATCH=NO
GENUINE_PAYMENT_SELECTION_REQUIRED=YES — trusted Classic change event only
DEDUPLICATION=PASS — one bounded page-memory key; 1.5-second duplicate window

PII_IN_EVENT_PAYLOAD=NO

META_MODULE_PAYMENT_MUTATING_HOOKS=0
CHECKOUT_INTERCEPTION=NONE
PAYMENT_EXECUTION_INTERCEPTION=NONE

SETTINGS_REGRESSION_TESTS=PASS — 30 PHP assertions plus local real-handler persist/clear checks
NODE_TESTS=PASS — 28 passed, 0 failed, 0 skipped
PHP_LINT=PASS — 5 PHP files, 0 failures

WORDPRESS_RUNTIME=PASS — 7.1.3 disposable local site
WOOCOMMERCE_RUNTIME=PASS — 11.2.1
SUPERWOO_RUNTIME=PASS — 1.4.2 active

CART_TOTAL_INVARIANCE=PASS — $25.00 in both COD runs
GATEWAY_INVARIANCE=PASS — COD only and selected in both runs
CHECKOUT_PAYLOAD_INVARIANCE=PASS — same COD method, response semantics, and no errors
ORDER_INVARIANCE=PASS — identical item/order totals and processing status
REDIRECT_INVARIANCE=PASS — matching order-received route and HTTP 200 thank-you page

FAULT_ISOLATION=PASS — missing/malformed runtime, invalid payload, and throwing stub tests

REAL_META_EVENT_SENT=NO
REAL_PAYMENT_ATTEMPTED=NO
PRODUCTION_CHANGES=NONE
MODU_TOUCHED=NO

PHASE_3C_READY=NO — genuine Classic browser selection and end-to-end dispatch were not verified; local checkout is Blocks
```

**Stop point:** Phase 3B implementation and the authorized local verification are complete. Phase 3C, production deployment, Meta/CAPI, and Razorpay work were not performed.
